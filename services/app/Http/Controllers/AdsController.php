<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Redis;

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

    public function notIncreaseClick(Request $request)
    {
        try {
            $accounts = $request->input('accounts');
            $accounts = json_decode($accounts);
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $accountsNotIncreaseClick = [];
            $message = '';
            foreach ($accounts as $account) {
                $key = 'adwords:not_increase_click:' . $account->accountName;
                $currentClicks = $account->clicks;
                $cacheAccount= Cache::get($key, null);
                $account->status = 'active';
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $lastClicks = $cacheAccount->clicks;
                    \Log::info("Checking accounts blocked - account:" . $account->accountName . ", lastClicks: " . $lastClicks . ", currentClicks: " . $currentClicks);
                    if ($lastClicks >= 0 && $lastClicks == $currentClicks && $cacheAccount->status == 'active') {
                        $account->status = 'blocked';
                        array_push($accountsNotIncreaseClick, $account);
                    }
                }
                Cache::forever($key, $account);
            }
    
            if (count($accountsNotIncreaseClick) > 0) {
                $message = $this->getDisplayMessage('Clicks', $accountsNotIncreaseClick);
                \Log::info("accounts blocked");
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, 'Accounts Blocked', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor('Accounts Blocked', $message);
            }
    
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage() . ' : ' . $ex->getLine();
        }
    }

    public function getDisplayMessage($type, $accountList)
    {
        $message = "<div>";
        $message .= "<table>";
        $message .= "<tr>";
        $message .= "<th>";
        $message .= "Account";
        $message .= "</th>";
        $message .= "<th>";
        $message .= $type;
        $message .= "</th>";
        $message .= "</tr>";
        foreach ($accountList as $account) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $account->accountName;
            $message .= "</td>";
            $message .= "<td>";
            $message .= $account->clicks;
            $message .= "</td>";
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }
}
