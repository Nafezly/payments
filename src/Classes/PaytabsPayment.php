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


class PaytabsPayment extends BaseController implements PaymentInterface
{

    private $paytabs_profile_id;
    private $paytabs_base_url;
    private $paytabs_server_key;
    private $paytabs_checkout_lang;
    private $verify_route_name;


    public function __construct()
    {
        $this->paytabs_profile_id = config('nafezly-payments.PAYTABS_PROFILE_ID');
        $this->paytabs_base_url = config('nafezly-payments.PAYTABS_BASE_URL');
        $this->paytabs_server_key = config('nafezly-payments.PAYTABS_SERVER_KEY');
        $this->paytabs_checkout_lang = config('nafezly-payments.PAYTABS_CHECKOUT_LANG');
        $this->currency = config('nafezly-payments.PAYTABS_CURRENCY');
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
     * @return array|Application|RedirectResponse|Redirector
     */

    public function pay(
        $amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null
    )
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'PayTabs');
        $unique_id = uniqid();

        $response = Http::withHeaders([
            'Authorization' => $this->paytabs_server_key,
            'Content-Type' => "application/json"
        ])->post($this->paytabs_base_url . "/payment/request", [
            'profile_id' => $this->paytabs_profile_id,
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => $unique_id,
            "cart_currency" => $this->currency,
            "cart_amount" => $this->amount,
            "hide_shipping" => true,
            "cart_description" => "items",
            "paypage_lang" => $this->paytabs_checkout_lang,
            "callback" => route($this->verify_route_name,['payment_id'=>$unique_id]), //Post end point  -the payment status will be sent to server
            "return" => route($this->verify_route_name,['payment_id'=>$unique_id]), //Get end point - The link to which the user will be redirected
            "customer_ref" => $unique_id,
            "customer_details" => [
                "name" => $this->user_first_name . ' ' . $this->user_last_name,
                "email" => $this->user_email,
                "phone" => $this->user_phone,
                "street1" => "Not Available Data",
                "city" => "Not Available Data",
                "state" => "Not Available Data",
                "country" => "Not Available Data",
                "zip" => "00000"
            ],
            'valu_down_payment' => "0",
            "tokenise" => 1
        ])->json();

        if (!isset($response['code'])) {
            Cache::forever($unique_id, $response['tran_ref']);
            return [
                'payment_id' => $response['tran_ref'],
                'redirect_url' => $response['redirect_url'],
                'html' => "",
            ];
        }
        return [
            'success' => false,
            'message' => $response['message'],
        ];
    }

    public function verify(Request $request): array
    {
        $payment_id = $request->tranRef!=null?$request->tranRef:Cache::get($request['tranRef']);
        Cache::forget($request['tranRef']);

        $response = Http::withHeaders([
            'Authorization' => $this->paytabs_server_key,
            'Content-Type' => "application/json"
        ])->post($this->paytabs_base_url . "/payment/query", [
            'profile_id' => $this->paytabs_profile_id,
            'tran_ref' => $payment_id
        ])->json();

        if (isset($response['payment_result']['response_status']) && $response['payment_result']['response_status'] == "A") {
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];

        } else {
            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }
    }
}
