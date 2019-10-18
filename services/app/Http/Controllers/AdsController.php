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
            $message = '';
            foreach ($accounts as $account) {
                $key = 'adwords:blocked_account:' . $account->accountName;
                $currentImpressions = $account->impressions;
                $cacheAccount= Cache::get($key, null);
                $account->status = 'active';
                if (!empty($cacheAccount) && is_object($cacheAccount)) {
                    $lastImpressions = $cacheAccount->impressions;
                    $account->status = $cacheAccount->status;
                    \Log::info("Checking accounts were blocked - account:" . $account->accountName . ", lastImpressions: " . $lastImpressions . ", currentImpressions: " . $currentImpressions);
                    if ($lastImpressions >= 0 && $lastImpressions == $currentImpressions) {
                        if ($cacheAccount->status == 'active') {
                            $account->status = 'blocked';
                            array_push($accountsNotIncreaseImpressions, $account);
                        }
                    } else {
                        $account->status = 'active';
                    }
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

    public function getDisplayMessage($accountList)
    {
        $message = "<div>";
        $message .= "<table>";
        $message .= "<tr>";
        $message .= "<th>";
        $message .= "Account";
        $message .= "</th>";
        $message .= "</tr>";
        foreach ($accountList as $account) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $account->accountName;
            $message .= "</td>";
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }
}
