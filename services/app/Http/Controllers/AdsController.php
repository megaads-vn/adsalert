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
            $campaigns = $request->input('campaigns');
            $campaigns = json_decode($campaigns);
            $mailTo = $request->input('mailTo', '');
            $callTo = $request->input('callTo', '');
            $campaignsNotIncreaseClick = [];
            $message = '';
            foreach ($campaigns as $campaign) {
                $key = 'adwords:not_increase_click:' . $campaign->accountName . ':' . $campaign->campaignName;
                $currentClicks = $campaign->clicks;
                $lastClicks= Cache::get($key, -1);
                Cache::forever($key, $currentClicks);
                \Log::info("Checking Campaigns doesn't increase click - Campaign:" . $campaign->campaignName . ", lastClicks: " . $lastClicks . ", currentClicks: " . $currentClicks);
                if ($lastClicks >= 0 && $lastClicks == $currentClicks) {
                    array_push($campaignsNotIncreaseClick, $campaign);
                }
            }
    
            if (count($campaignsNotIncreaseClick) > 0) {
                $message = $this->getDisplayMessage('Clicks', $campaignsNotIncreaseClick);
                \Log::info("Campaigns doesn\'t increase click");
                if ($mailTo != '') {
                    $this->sendEmail($mailTo, 'CAMPAIGNS DOESN\'T INCREASE CLICK', $message);
                }
                if ($callTo != '' && (date('H') >= 23 || date('H') <= 6)) {
                    $this->callPhone($callTo);
                }
                $this->requestMonitor('CAMPAIGNS DOESN\'T INCREASE CLICK', $message);
            }
    
            return $message;
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function getDisplayMessage($type, $campaignList)
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
        $message .= "<th>";
        $message .= $type;
        $message .= "</th>";
        $message .= "</tr>";
        foreach ($campaignList as $campaign) {
            $message .= "<tr>";
            $message .= "<td>";
            $message .= $campaign->accountName;
            $message .= "</td>";
            $message .= "<td>";
            $message .= $campaign->campaignName;
            $message .= "</td>";
            $message .= "<td>";
            $message .= $campaign->clicks;
            $message .= "</td>";
            $message .= "</tr>";
        }
        $message .= "</table>";
        $message .= "</div>";

        return $message;
    }
}
