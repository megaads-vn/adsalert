<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdsController extends BaseController
{
    public function unapprove(Request $request)
    {
        $unapproved = $request->input('count');
        $account = $request->input('account');
        $key = 'adwords:unapproved_ads:' . $account;
        $lastUnapproved = Cache::get($key, -1);
        Cache::forever($key, $unapproved);
        \Log::info("Checking Unapproved ads - Account: " . $account . ", lastUnapproved: " . $lastUnapproved . ", unapproved: " . $unapproved);
        if ($lastUnapproved >= 0 && $lastUnapproved < $unapproved) {
            \Log::info("Notify Unapproved ads");
            $this->sendEmail(env('MAIL_TO'), $account . ' has REJECTED ADS', '');
            $this->callPhone(env('CALL_TO'));
        }
        return $this->success(null);
    }
}
