<?php
namespace App\Http\Controllers;

use Illuminate\Http\Response;

class BaseController extends \Laravel\Lumen\Routing\Controller
{
    protected function success($data)
    {
        return response()->json([
            'result' => $data,
            'status' => 'successful',
        ]);
    }
    protected function error($data)
    {
        return response()->json([
            'result' => $data,
            'status' => 'fail',
        ]);
    }

    protected function stringToDateOrNull($dateString)
    {
        if ($dateString == null || strlen($dateString) == 0) {
            return null;
        }
        $retVal = DateTime::createFromFormat("d/m/Y", $dateString);
        $retVal->setTime(0, 0, 0);
        return $retVal;
    }
    protected function sendRequest($url, $method = "GET", $data = [], $async = false)
    {
        $channel = curl_init();
        curl_setopt($channel, CURLOPT_URL, $url);
        curl_setopt($channel, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($channel, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($channel, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        if ($async) {
            curl_setopt($channel, CURLOPT_NOSIGNAL, 1);
            curl_setopt($channel, CURLOPT_TIMEOUT_MS, 200);
            curl_setopt($channel, CURLOPT_RETURNTRANSFER, 0);
        }
        $response = curl_exec($channel);
        curl_close($channel);
        $responseInJson = json_decode($response);
        return isset($responseInJson->result) ? $responseInJson->result : $responseInJson;
    }
    public function sendEmail($to, $subject, $content)
    {
        $retval = false;
        $emailService = env('MAILER_URL');
        $token = $this->getMailerToken($emailService);
        if (!empty($token)) {
            $emailData = [];
            $emailData['to'] = $to;
            $emailData['subject'] = $subject;
            $emailData['name'] = 'Megaas AdsAlert';
            $emailData['content'] = $content;
            $emailData['token'] = $token;
            $this->sendRequest($emailService . '/api/send-mail', "POST", $emailData, true);
            $retval = true;
        }
        return $retval;
    }

    private function getMailerToken($emailService)
    {
        $payload = array(
            'email' => env('MAILER_USERNAME'),
            'password' => env('MAILER_PASSWORD'),
        );
        $result = $this->sendRequest($emailService . '/auth/login', "POST", $payload);
        if (isset($result->token)) {
            return $result->token;
        }
        return null;
    }
}
