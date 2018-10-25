<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdsController extends BaseController
{
    public function unapprove(Request $request)
    {
        $unapprovedAds = $request->input('count');
        $account = $request->input('account');
        $key = 'adwords:unapproved_ads:' . $account;
        $lastUnapprovedAds = Cache::get($key, 0);
        Cache::forever($key, $unapprovedAds);
        if ($lastUnapprovedAds < $unapprovedAds) {
            $this->sendEmail(env('MAIL_TO'), $account . ' has REJECTED ADS', '');
        }
        return $this->success(null);
    }
}
