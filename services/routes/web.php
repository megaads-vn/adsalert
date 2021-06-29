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
$app->post('/ads/cost-usd', 'AdsController@costUsd');
$app->post('/ads/cost-all-time', 'AdsController@costAllTime');
$app->post('/ads/paused', 'AdsController@alertPausedCampagin');
$app->post('/ads/limited-budget', 'AdsController@limitedBudget');
$app->get('/ads/fail-email', 'AdsController@sendFailEmail');
$app->get('/ads/parse-log', 'AdsController@parseLog');
