<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class CoinPaymentsPayment extends BaseController implements PaymentInterface
{ 
    private $coinpayments_public_key;
    private $coinpayments_private_key;
    private $verify_route_name;

    public function __construct()
    {
        $this->coinpayments_public_key = config('nafezly-payments.COINPAYMENTS_PUBLIC_KEY');
        $this->coinpayments_private_key = config('nafezly-payments.COINPAYMENTS_PRIVATE_KEY');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return Application|RedirectResponse|Redirector
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
 

        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount','user_email'];

        $fields = [
            'version'=>1,
            'key'=>$this->coinpayments_public_key,
            'format'=>"json",
            'cmd' => 'create_transaction',
            'amount' => $this->amount,
            'currency1' => 'USD',
            'currency2' => $this->currency??"USDT",
            'buyer_email' => $this->user_email??null,
            'ipn_url'=> route($this->verify_route_name,['payment'=>"coinpayments"]),
            'success_url'=> route($this->verify_route_name,['payment'=>"coinpayments"]),
            'cancel_url'=> route($this->verify_route_name,['payment'=>"coinpayments"]),
            
        ];
        $response = Http::asForm()->withHeaders([
            'content-type'=>"application/x-www-form-urlencoded",
            'HMAC' => hash_hmac('sha512', http_build_query($fields, '', '&'), $this->coinpayments_private_key),
        ])->post("https://www.coinpayments.net/api.php", $fields)->json();
        if($response['error']=="ok")
            return [
                'payment_id'=>$response['result']['txn_id'],
                'html' => $response,
                'redirect_url'=>$response['result']['checkout_url']
            ];
        else
            return [
                'payment_id'=>"",
                'html' => $response,
                'redirect_url'=>""
            ];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $fields = [
            'version'=>1,
            'key'=>$this->coinpayments_public_key,
            'format'=>"json",
            'cmd' => 'get_tx_info',
            'txid' => $request['payment_id'],
        ];
        $response = Http::withHeaders([
            'HMAC' => hash_hmac('sha512', http_build_query($fields, '', '&'), $this->coinpayments_private_key),
        ])->post("https://www.coinpayments.net/api.php", $fields)->json();
 

        if ($response['error'] == 'ok' && $response['result']['status'] == 100) {
            return [
                'success' => true,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ]; 
        } else {

            return [
                'success' => false,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ]; 
        } 
    }
}