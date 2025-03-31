<?php

namespace App\Http\Controllers;

use Redis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BaseController;

class AdsController extends BaseController
{
    const DEFAULT_MAIL_TO = "phult.contact@gmail.com,khoan.mega@gmail.com";

    function getKey($key, $mailTo) {
        if ($mailTo == self::DEFAULT_MAIL_TO) {
            return $key;
        }

        return $mailTo . "::" . $key;
    }

    public function unapprove(Request $request)
    {
        $unapproved = $request->input('count');
        $account = $request->input('account');
        $message = $request->input('message');
        $mailTo = $request->input('mailTo', '');
        $callTo = $request->input('callTo', '');
        $key = 'adwords:unapproved_ads:' . $account;
        $lastUnapproved = Cache::get($key, -1);
        Cache::forever($key, $unapproved);
        \Log::info("Checking Unapproved ads - Account: " . $account . ", lastUnapproved: " . $lastUnapproved . ", unapproved: " . $unapproved);
        if ($lastUnapproved >= 0 && $lastUnapproved < $unapproved) {
            \Log::info("Notify Unapproved ads");
            if ($mailTo != '') {
                $this->sendEmail($mailTo, $account . ' has DISAPPROVED ADS', $message);
            }
            if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                $this->callPhone($callTo);
            }
            $this->requestMonitor($account . ' has DISAPPROVED ADS', $message);
        }
        return $this->success(null);
    }

    public function blocked(Request $request)
    {
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountsNotIncreaseImpressions = [];
            $message = 'Don\'t have any blocked account';
            foreach ($accounts as $account) {
                $key = 'adwords:blocked_account:' . $account->accountName;
                $currentImpressions = $account->impressions;
                $cacheAccount= Cache::get($key, null);
                $account->status = 'active';
                $account->percentChange = 0;
                $account->impressionChange = 0;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $lastImpressions = $cacheAccount->impressions;
                    $account->status = $cacheAccount->status;
                    $account->percentChange = $cacheAccount->percentChange;
                    $account->impressionChange = $cacheAccount->impressionChange;
                    \Log::info("Checking accounts were blocked - account:" . $account->accountName . ", lastImpressions: " . $lastImpressions . ", currentImpressions: " . $currentImpressions);
                    $impressionChange = abs($currentImpressions - $lastImpressions);
                    $percentChange = $impressionChange / $lastImpressions * 100;
                    if ($lastImpressions >= 0 && $lastImpressions == $currentImpressions) {
                        $condition = ($account->percentChange >= 5 || $account->impressionChange >= 50) || $account->percentChange == 0;
                        if ($cacheAccount->status == 'active' && $condition) {
                            $account->status = 'blocked';
                            array_push($accountsNotIncreaseImpressions, $account);
                        }
                    } else {
                        $account->status = 'active';
                    }
                    $account->percentChange = $percentChange;
                    $account->impressionChange = $impressionChange;
                }
                Cache::forever($key, $account);
            }
    
            if (count($accountsNotIncreaseImpressions) > 0) {
                $message = $this->getDisplayMessage($accountsNotIncreaseImpressions);
                \Log::info($username . ' has BLOCKED ACCOUNTS', [$accountsNotIncreaseImpressions]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has BLOCKED ACCOUNTS', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has BLOCKED ACCOUNTS', $message);
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function getMailToByAccountId($accountId) {
        $mails = [];
        $config = [
            'thaont.megaads@gmail.com' => [
                "495-513-6985",
                "406-953-6438",
                "180-648-9991",
                "727-118-6921",
                "875-262-8652",
                "177-380-5303",
                "689-779-5991",
                "857-324-8182",
                "997-056-5123",
                "447-986-8039",
            ],
            // 'khanhlinhvi.mega@gmail.com' => [
            //     "495-513-6985",
            //     "689-779-5991"
            // ],
            // 'hanhtran111196@gmail.com' => [
            //     "447-986-8039",
            //     "997-056-5123"
            // ],
            // 'phuonganhcouponde@gmail.com' => [
            //     "461-761-6275",
            //     "727-118-6921",
            //     "351-292-4640",
            //     "875-262-8652",
            //     '553-267-3226',
            //     '177-380-5303'
            // ],
            'thuongnt.coupon@gmail.com' => [
                '326-362-2447',
                "398-022-7660"
            ],
        ];
        $mails = [];
        foreach ($config as $mail => $accountIds) {
            foreach ($accountIds as $id) {
                if ($id == $accountId) {
                    $mails[] = $mail;
                }
            }
        }

        return implode(",", $mails);
    }

    public function getMailTo($campName) {
        $campName = strtoupper($campName);
        $ignoreWhenUS = ['DE', 'ES', 'IT', 'JP', 'BR', 'PT', 'FR', 'UK', 'IR', 'BE'];
        $config = [
            'thaont.megaads@gmail.com' => [
                'US',
                'UK'
            ],
            'khanhlinhvi.mega@gmail.com' => [
                'UK', 'IR'
            ],
            'hanhtran111196@gmail.com' => [
                'FR'
            ],
            'phuonganhcouponde@gmail.com' => [
                'DE'
            ],
            'phuongtunguyen36.mega@gmail.com' => [
                'ES', 
                'IT'
            ],
            'thuongnt.coupon@gmail.com' => [
                'JP'
            ]
        ];
        $mails = [];
        foreach ($config as $mail => $locales) {
            foreach ($locales as $locale) {
                if ($locale == "US") {
                    $check = false;
                    foreach ($ignoreWhenUS as $lc) {
                        preg_match("/\b$lc\b/", $campName, $matches);
                        if ($matches && count($matches)) {
                            $check = true;
                            break;
                        }
                    }
                    if (!$check) {
                        $mails[] = $mail;
                    }
                } else {
                    preg_match("/\b$locale\b/", $campName, $matches);
                    if ($matches && count($matches)) {
                        $mails[] = $mail;
                    }
                }
            }
        }

        return implode(",", $mails);
    }

    public function testMailTo(Request $request) {
        $accounts = $request->get('accounts');
        $result = [];
        foreach ($accounts as $account) {
            $mailTo = $this->getMailTo($account['Campaign']);
            if (!isset($result[$mailTo])) {
                $result[$mailTo] = [];
            }
            $result[$mailTo][] = $account['Campaign'];
        }

        return $result;
    }

    public function getStaffAlerts($accounts) {
        $retVal = [];
        foreach ($accounts as $account) {
            if (isset($account->accountId)) {
                $mailTo = $this->getMailToByAccountId($account->accountId);
            } else {
                $mailTo = $this->getMailTo($account->campaignName);
            }
            if ($mailTo) {
                if (!isset($retVal[$mailTo])) {
                    $retVal[$mailTo] = [];
                }
                $retVal[$mailTo][] = $account;
            }
        }

        return $retVal;
    }

    public function cost(Request $request)
    {
        if ($request->has('clearCache')) {
            Cache::flush();
        }
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountOverCosts = [];
            $message = 'Don\'t have any campaigns reach limit';
            foreach ($accounts as $account) {
                $key = $this->getKey('adwords:campaign_cost:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost 30 Days - Account: " . $account->accountName . (isset($account->accountId) ? ", AccountId: " . $account->accountId : "") . ", Campaign: " . $account->campaignName . " CampaignId: " . $account->campaignId . ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $logMessage .= ", Last Cost: " . $cacheAccount->cost;
                    $account->is_send = isset($cacheAccount->is_send) ? $cacheAccount->is_send : 0;
                    if ($account->cost < $cacheAccount->cost) {
                        $account->is_send = 0;
                    }
                    if (!isset($cacheAccount->is_send)) {
                        $cacheAccount->is_send = 1;
                    }
                    if ($cacheAccount->cost < config('campaign.limitCost') && $account->cost >= config('campaign.limitCost')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    } else if ((!$cacheAccount->is_send || strpos(strtolower($account->campaignName), 'chua ok') !== false) && $account->cost >= config('campaign.limitCost')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    }
                } elseif ($account->cost >= config('campaign.limitCost')) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                }
                if (!empty($account->is_send)) {
                    $logMessage .= ' Is Send To: ' . $mailTo;
                }
                \Log::info($logMessage);
                Cache::forever($key, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($mailTo . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', [$accountOverCosts]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', $message);
                $staffs = $this->getStaffAlerts($accountOverCosts);
                foreach ($staffs as $mail => $accounts) {
                    $messageItem = $this->getDisplayCostMessage($accounts, true);
                    \Log::info($mail . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', [$accounts]);
                    if ($mail != '') {
                        $this->sendEmail($mail, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', $messageItem);
                    }
                }
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function costAllTime(Request $request)
    {
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountOverCosts = [];
            $message = 'Don\'t have any campaigns reach limit';
            foreach ($accounts as $account) {
                $key = $this->getKey('adwords:campaign_cost:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $keyAllTime = $this->getKey('adwords:campaign_cost_all_time:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $cacheAccountAllTime = Cache::get($keyAllTime, null);
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost All Time - Account: " . $account->accountName . (isset($account->accountId) ? ", AccountId: " . $account->accountId : "") . " , Campaign: " . $account->campaignName . " CampaignId: " . $account->campaignId . ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount) && !empty($cacheAccountAllTime) && is_object($cacheAccountAllTime)) {
                    $logMessage .= ", Last Cost: " . $cacheAccountAllTime->cost;
                    $account->is_send = isset($cacheAccount->is_send) ? $cacheAccount->is_send : 0;
                    if (!isset($cacheAccount->is_send)) {
                        $cacheAccount->is_send = 1;
                    }
                    if ($account->cost < $cacheAccount->cost) {
                        $account->is_send = 0;
                    }
                    if (
                        $cacheAccountAllTime->cost < config('campaign.limitCost') && 
                        $account->cost >= config('campaign.limitCost') &&
                        $cacheAccount->cost < config('campaign.limitCost')
                    ) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    } else if ((!$cacheAccount->is_send || strpos(strtolower($account->campaignName), 'chua ok') !== false) && $account->cost >= config('campaign.limitCost')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    }
                } elseif (
                    !empty($cacheAccount) 
                    && is_object($cacheAccount) 
                    && $cacheAccount->cost < config('campaign.limitCost') 
                    && $account->cost >= config('campaign.limitCost')
                ) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                } elseif (
                    !empty($cacheAccountAllTime) 
                    && is_object($cacheAccountAllTime) 
                    && $cacheAccountAllTime->cost < config('campaign.limitCost') 
                    && $account->cost >= config('campaign.limitCost')
                ) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                } else if ($account->cost >= config('campaign.limitCost')) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                }
                if (!empty($account->is_send)) {
                    $logMessage .= ' Is Send To: ' . $mailTo;
                }
                \Log::info($logMessage);
                Cache::forever($keyAllTime, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($mailTo . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', [$accountOverCosts]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', $message);

                $staffs = $this->getStaffAlerts($accountOverCosts);
                foreach ($staffs as $mail => $accounts) {
                    $messageItem = $this->getDisplayCostMessage($accounts, true);
                    \Log::info($mail . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', [$accounts]);
                    if ($mail != '') {
                        $this->sendEmail($mail, $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', $messageItem);
                    }
                }
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function costAllTimeUSD(Request $request)
    {
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountOverCosts = [];
            $message = 'Don\'t have any campaigns reach limit';
            foreach ($accounts as $account) {
                $key = $this->getKey('adwords:campaign_cost:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $keyAllTime = $this->getKey('adwords:campaign_cost_all_time:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $cacheAccountAllTime = Cache::get($keyAllTime, null);
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost All Time USD - Account: " . $account->accountName . (isset($account->accountId) ? ", AccountId: " . $account->accountId : "") . " , Campaign: " . $account->campaignName . " CampaignId: " . $account->campaignId . ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount) && !empty($cacheAccountAllTime) && is_object($cacheAccountAllTime)) {
                    $logMessage .= ", Last Cost: " . $cacheAccountAllTime->cost;
                    $account->is_send = isset($cacheAccount->is_send) ? $cacheAccount->is_send : 0;
                    if (!isset($cacheAccount->is_send)) {
                        $cacheAccount->is_send = 1;
                    }
                    if ($account->cost < $cacheAccount->cost) {
                        $account->is_send = 0;
                    }
                    if (
                        $cacheAccountAllTime->cost < config('campaign.limitCostUsd') && 
                        $account->cost >= config('campaign.limitCostUsd') &&
                        $cacheAccount->cost < config('campaign.limitCostUsd')
                    ) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    } else if ((!$cacheAccount->is_send || strpos(strtolower($account->campaignName), 'chua ok') !== false) && $account->cost >= config('campaign.limitCostUsd')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    }
                } elseif (
                    !empty($cacheAccount) 
                    && is_object($cacheAccount) 
                    && $cacheAccount->cost < config('campaign.limitCostUsd') 
                    && $account->cost >= config('campaign.limitCostUsd')
                ) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                } elseif (
                    !empty($cacheAccountAllTime) 
                    && is_object($cacheAccountAllTime) 
                    && $cacheAccountAllTime->cost < config('campaign.limitCostUsd') 
                    && $account->cost >= config('campaign.limitCostUsd')
                ) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                } else if ($account->cost >= config('campaign.limitCostUsd')) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                }
                if (!empty($account->is_send)) {
                    $logMessage .= ' Is Send To: ' . $mailTo;
                }
                \Log::info($logMessage);
                Cache::forever($keyAllTime, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($mailTo . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME USD', [$accountOverCosts]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME USD', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME USD', $message);

                $staffs = $this->getStaffAlerts($accountOverCosts);
                foreach ($staffs as $mail => $accounts) {
                    $messageItem = $this->getDisplayCostMessage($accounts, true);
                    \Log::info($mail . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME USD', [$accounts]);
                    if ($mail != '') {
                        $this->sendEmail($mail, $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME USD', $messageItem);
                    }
                }
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function costUsd(Request $request)
    {
        if ($request->has('clearCache')) {
            Cache::flush();
        }
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountOverCosts = [];
            $message = 'Don\'t have any campaigns reach limit';
            foreach ($accounts as $account) {
                $key = $this->getKey('adwords:campaign_cost_usd:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId, $mailTo);
                $cacheAccount= Cache::get($key, null);
                $account->cost = floatval($account->cost);
                $logMessage = "Checking Limit Cost 30 Days USD - Account: " . $account->accountName . (isset($account->accountId) ? ", AccountId: " . $account->accountId : "") . ", Campaign: " . $account->campaignName . " CampaignId: " . $account->campaignId . ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $account->is_send = isset($cacheAccount->is_send) ? $cacheAccount->is_send : 0;
                    if (!isset($cacheAccount->is_send)) {
                        $cacheAccount->is_send = 1;
                    }
                    if ($account->cost < $cacheAccount->cost) {
                        $account->is_send = 0;
                    }
                    $cacheAccount->cost = floatval($cacheAccount->cost);
                    $logMessage .= ", Last Cost: " . $cacheAccount->cost;
                    if ($cacheAccount->cost < config('campaign.limitCostUsd') && $account->cost >= config('campaign.limitCostUsd') && $account->cost <= config('campaign.upperLimitCostUsd')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    } else if ((!$cacheAccount->is_send || strpos(strtolower($account->campaignName), 'chua ok') !== false) && $account->cost >= config('campaign.limitCostUsd') && $account->cost <= config('campaign.upperLimitCostUsd')) {
                        $account->is_send = 1;
                        $accountOverCosts[] = $account;
                    }
                } elseif ($account->cost >= config('campaign.limitCostUsd') && $account->cost <= config('campaign.upperLimitCostUsd')) {
                    $account->is_send = 1;
                    $accountOverCosts[] = $account;
                }
                if (!empty($account->is_send)) {
                    $logMessage .= ' Is Send To: ' . $mailTo;
                }
                \Log::info($logMessage);
                Cache::forever($key, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', [$accountOverCosts]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', $message);

                $staffs = $this->getStaffAlerts($accountOverCosts);
                foreach ($staffs as $mail => $accounts) {
                    $messageItem = $this->getDisplayCostMessage($accounts, true);
                    \Log::info($mail . ' | ' . $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', [$accounts]);
                    if ($mail != '') {
                        $this->sendEmail($mail, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', $messageItem);
                    }
                }
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function limitedBudget(Request $request)
    {
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $username = $request->input('username', '');
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $limitedBudgetCampaigns = [];
            $message = '';
            foreach ($accounts as $account) {
                $key = 'adwords:limited_campagin:' . $account->campaignId;
                $currentImpressions = $account->impressions;
                $cacheAccount= Cache::get($key, null);
                $account->status = 'active';
                $account->percentChange = 0;
                $account->impressionChange = 0;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $lastImpressions = $cacheAccount->impressions;
                    $account->status = $cacheAccount->status;
                    $account->percentChange = $cacheAccount->percentChange;
                    $account->impressionChange = $cacheAccount->impressionChange;
                    \Log::info("Checking limited budget campaign - account:" . $account->accountName . ", lastImpressions: " . $lastImpressions . ", currentImpressions: " . $currentImpressions);
                    $impressionChange = abs($currentImpressions - $lastImpressions);
                    $percentChange = $impressionChange / $lastImpressions * 100;
                    if ($lastImpressions >= 0 && $lastImpressions == $currentImpressions) {
                        $condition = ($account->percentChange >= 5 || $account->impressionChange >= 30) || $account->percentChange == 0;
                        if ($cacheAccount->status == 'active' && $condition) {
                            $account->status = 'blocked';
                            array_push($limitedBudgetCampaigns, $account);
                        }
                    } else {
                        $account->status = 'active';
                    }
                    $account->percentChange = $percentChange;
                    $account->impressionChange = $impressionChange;
                }
                Cache::forever($key, $account);
            }
    
            if (count($limitedBudgetCampaigns) > 0) {
                $message = $this->getDisplayMessage($limitedBudgetCampaigns, true);
                \Log::info($username . ' has LIMITED BUGDGET CAMPAIGN', [$limitedBudgetCampaigns]);
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has LIMITED BUGDGET CAMPAIGN', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has LIMITED BUGDGET CAMPAIGN', $message);
            }
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function getDisplayMessage($accountList, $isDisplayCampaign = false)
    {
        $message = "<div>";
        $message .= "<table>";
        $message .= "<tr>";
        $message .= "<th>";
        $message .= "Account";
        $message .= "</th>";
        if ($isDisplayCampaign) {
            $message .= "<th>";
            $message .= "Campaign";
            $message .= "</th>";
        }
        $message .= "</tr>";
        foreach ($accountList as $account) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $account->accountName;
            $message .= "</td>";
            if ($isDisplayCampaign) {
                $message .= "<td>";
                $message .= $account->campaignName;
                $message .= "</td>";
            }
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }

    public function getDisplayCostMessage($accountList, $isDisplayCampaign = false)
    {
        $message = "<div>";
        $message .= "<table>";
        $message .= "<tr>";
        $message .= "<th>";
        $message .= "Account";
        $message .= "</th>";
        if ($isDisplayCampaign) {
            $message .= "<th>";
            $message .= "Campaign";
            $message .= "</th>";
        }
        $message .= "<th>";
        $message .= "Cost";
        $message .= "</th>";
        $message .= "</tr>";
        foreach ($accountList as $account) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $account->accountName;
            $message .= "</td>";
            if ($isDisplayCampaign) {
                $message .= "<td>";
                $message .= $account->campaignName;
                $message .= "</td>";
            }
            $message .= "<td>";
            $message .= $account->cost;
            $message .= "</td>";
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }

    public function getAlertPausedCampaginMessage($accountList)
    {
        $message = "<div>";
        $message .= "<table>";
        $message .= "<tr>";
        $message .= "<th>";
        $message .= "Account";
        $message .= "</th>";
        $message .= "<th>";
        $message .= "Campaign";
        $message .= "</th>";
        $message .= "</tr>";
        foreach ($accountList as $account) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $account->accountName;
            $message .= "</td>";
            $message .= "<td>";
            $message .= $account->campaignName;
            $message .= "</td>";
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }

    public function alertPausedCampagin(Request $request)
    {
        $accounts = $request->input('accounts');
        $accounts = json_decode($accounts);
        $username = $request->input('username', '');
        $mailTo = $request->input('mailTo', '');
        $callTo = $request->input('callTo', '');
        $file = $request->get('file');

        $message = $this->getAlertPausedCampaginMessage($accounts, true);
        \Log::info($username . ' has Paused Campaign');
        \Log::info('PAUSED CAMPAIGN' . ($file ? (' ' . $file) : ''), [$accounts]);
        if ($mailTo != '') {
            $this->sendEmail($mailTo, $username . ' has PAUSED CAMPAIGN', $message);
        }
        if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
            $this->callPhone($callTo);
        }
        $this->requestMonitor($username . ' has PAUSED CAMPAIGN', $message);

        $staffs = $this->getStaffAlerts($accounts);
        foreach ($staffs as $mail => $accs) {
            $messageItem = $this->getAlertPausedCampaginMessage($accs, true);
            if ($mail != '') {
                $this->sendEmail($mail, $username . ' has PAUSED CAMPAIGN', $messageItem);
            }
        }

        return $message;
    }

    public function sendFailEmail(Request $request) {
        set_time_limit(5 * 3600);
        $failEmailMessages = Cache::get('adwords::fail-email-messages', []);
        Cache::forever('adwords::fail-email-messages', []);
        $count = 0;
        foreach ($failEmailMessages as $message) {
            if (isset($message['to']) && isset($message['subject']) && isset($message['content'])) {
                $this->sendEmail($message['to'], $message['subject'], $message['content']);
                $count++;
            }
        }

        return $count;
    }

    public function parseLog() {
        $logFile = file(storage_path().'/logs/lumen2.log');
        $logCollection = [];
        // Loop through an array, show HTML source as HTML source; and line numbers too.
        foreach ($logFile as $line_num => $line) {
            //$logCollection[] = array('line'=> $line_num, 'content'=> htmlspecialchars($line));
            $content = $line;
            preg_match_all("/\,\sCost\:\s(\\d+)/m", $content, $cost, 1);
            preg_match_all("/\,\sLast\sCost\:\s(\\d+)/m", $content, $lastCost, 1);
            if ($cost && count($cost) > 1 && count($cost[1]) && $lastCost && count($lastCost) > 1 && count($lastCost[1])) {
                $cost = $cost[1][0];
                $lastCost = $lastCost[1][0];
                if ($cost > $lastCost) {
                    if (strpos($content, 'USD') !== false && $cost >= 9 && $lastCost < 9 && $cost < 50) {
                        $logCollection[] = $content;
                    } else if ($cost >= 200000 && $lastCost < 200000) {
                        $logCollection[] = $content;
                    }
                }
            }
            
        }
        return $logCollection;
    }
}
