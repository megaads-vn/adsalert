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
                $logMessage = "Checking Limit Cost - Account: " . $account->accountName . ", Campaign: " . $account->campaignName. ", Cost: " . $account->cost;
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $logMessage .= ", Last Cost: " . $cacheAccount->cost;
                    if ($cacheAccount->cost < config('campaign.limitCost') && $account->cost >= config('campaign.limitCost')) {
                        $accountOverCosts[] = $account;
                    }
                }
                \Log::info($logMessage);
                Cache::forever($key, $account);
            }
    
            if (count($accountOverCosts) > 0) {
                $message = $this->getDisplayCostMessage($accountOverCosts, true);
                \Log::info($username . ' has CAMPAIGNS REACH LIMIT COST');
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, $username . ' has CAMPAIGNS REACH LIMIT COST', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor($username . ' has CAMPAIGNS REACH LIMIT COST', $message);
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
}
