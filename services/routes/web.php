<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return "Megaads Adsalert service";
});
$app->post('/ads/unapprove', 'AdsController@unapprove');
$app->post('/ads/blocked', 'AdsController@blocked');
$app->post('/ads/cost', 'AdsController@cost');
$app->post('/ads/paused', 'AdsController@alertPausedCampagin');
