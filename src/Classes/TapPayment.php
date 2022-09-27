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

class TapPayment implements PaymentInterface
{

    private $tap_currency;
    private $tap_secret_key;
    private $tap_public_key;
    private $tap_lang_code;
    private $verify_route_name;

    public function __construct()
    {
        $this->tap_currency = config('nafezly-payments.TAP_CURRENCY');
        $this->tap_secret_key = config('nafezly-payments.TAP_SECRET_KEY');
        $this->tap_public_key = config('nafezly-payments.TAP_PUBLIC_KEY');
        $this->tap_lang_code = config('nafezly-payments.TAP_LANG_CODE');
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
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        if (is_null($user_first_name)) throw new MissingPaymentInfoException('user_first_name', 'Tap');
        if (is_null($user_last_name)) throw new MissingPaymentInfoException('user_last_name', 'Tap');
        if (is_null($user_phone)) throw new MissingPaymentInfoException('user_phone', 'Tap');
        if (is_null($user_email)) throw new MissingPaymentInfoException('user_email', 'Tap');


        $unique_id = uniqid();
        $response = Http::withHeaders([
            "authorization"=>"Bearer ".$this->tap_secret_key,
            "content-type"=>"application/json",
            'lang_code'=>$this->tap_lang_code
        ])->post('https://api.tap.company/v2/charges',[
            "amount" => $amount, 
            "currency" => $this->tap_currency, 
            "threeDSecure" => true, 
            "save_card" => false, 
            "description" => "Cerdit", 
            "statement_descriptor" => "Cerdit", 
            "reference" => [
                "transaction" => $unique_id , 
                "order" => $unique_id 
            ], 
            "receipt" => [
                "email" => true, 
                "sms" => true
            ], "customer" => [
                "first_name" => $user_first_name, 
                "middle_name" => "", 
                "last_name" => $user_last_name, 
                "email" => $user_email, 
                "phone" => [
                    "country_code" => "20", 
                    "number" => $user_phone
                ]
            ], 
            "source" => ["id" => "src_all"], 
            "post" => ["url" => $this->verify_route_name], 
            "redirect" => ["url" => $this->verify_route_name]
        ])->json();
        
        return [
            'payment_id'=>$response['id'],
            'redirect_url'=>$response['transaction']['url'],
            'html'=>""
        ];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $response = Http::withHeaders([
            "authorization"=>"Bearer ".$this->tap_secret_key,
        ])->get('https://api.tap.company/v2/charges/'.$request->tap_id)->json();
        if(isset($response['status']) && $response['status']=="CAPTURED"){
            return [
                'success' => true,
                'message' => __('messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        }else{
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }
        
    }
}