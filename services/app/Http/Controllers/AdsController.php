<?php

namespace App\Http\Controllers;

use Redis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BaseController;

class AdsController extends BaseController
{
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
                \Log::info($username . ' has BLOCKED ACCOUNTS');
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
                $key = 'adwords:campaign_cost:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId;
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost 30 Days - Account: " . $account->accountName . ", Campaign: " . $account->campaignName. ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $logMessage .= ", Last Cost: " . $cacheAccount->cost;
                    if ($cacheAccount->cost < config('campaign.limitCost') && $account->cost >= config('campaign.limitCost')) {
                        $accountOverCosts[] = $account;
                    }
                } elseif ($account->cost >= config('campaign.limitCost')) {
                    $accountOverCosts[] = $account;
                }
                \Log::info($logMessage);
                Cache::forever($key, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS');
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS', $message);
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
                $key = 'adwords:campaign_cost:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId;
                $keyAllTime = 'adwords:campaign_cost_all_time:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId;
                $cacheAccountAllTime = Cache::get($keyAllTime, null);
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost All Time - Account: " . $account->accountName . ", Campaign: " . $account->campaignName. ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount) && !empty($cacheAccountAllTime) && is_object($cacheAccountAllTime)) {
                    $logMessage .= ", Last Cost: " . $cacheAccountAllTime->cost;
                    if (
                        $cacheAccountAllTime->cost < config('campaign.limitCost') && 
                        $account->cost >= config('campaign.limitCost') &&
                        $cacheAccount->cost < config('campaign.limitCost')
                    ) {
                        $accountOverCosts[] = $account;
                    }
                }
                \Log::info($logMessage);
                Cache::forever($keyAllTime, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME');
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST ALL TIME', $message);
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
                $key = 'adwords:campaign_cost_usd:' . $account->accountName . ':' . $account->campaignName . ':' . $account->campaignId;
                $cacheAccount= Cache::get($key, null);
                $logMessage = "Checking Limit Cost 30 Days USD - Account: " . $account->accountName . ", Campaign: " . $account->campaignName. ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $logMessage .= ", Last Cost: " . $cacheAccount->cost;
                    if ($cacheAccount->cost < config('campaign.limitCostUsd') && $account->cost >= config('campaign.limitCostUsd') && $account->cost <= config('campgaign.upperLimitCostUsd')) {
                        $accountOverCosts[] = $account;
                    }
                } elseif ($account->cost >= config('campaign.limitCostUsd') && $account->cost <= config('campgaign.upperLimitCostUsd')) {
                    $accountOverCosts[] = $account;
                }
                \Log::info($logMessage);
                Cache::forever($key, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD');
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST IN 30 DAYS USD', $message);
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
                \Log::info($username . ' has LIMITED BUGDGET CAMPAIGN');
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

        $message = $this->getAlertPausedCampaginMessage($accounts, true);
        \Log::info($username . ' has Paused Campaign');
        \Log::info('PAUSED CAMPAIGN', [$accounts]);
        if ($mailTo != '') {
            $this->sendEmail($mailTo, $username . ' has PAUSED CAMPAIGN', $message);
        }
        if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
            $this->callPhone($callTo);
        }
        $this->requestMonitor($username . ' has PAUSED CAMPAIGN', $message);

        return $message;
    }
}
