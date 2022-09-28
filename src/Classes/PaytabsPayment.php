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

class PaytabsPayment implements PaymentInterface{

    private $paytabs_profile_id;
    private $paytabs_base_url;
    private $paytabs_server_key;
    private $paytabs_checkout_lang;
    private $paytabs_currency;
    private $verify_route_name;


    public function __construct()
    {
        $this->paytabs_profile_id = config('nafezly-payments.PAYTABS_PROFILE_ID');
        $this->paytabs_base_url = config('nafezly-payments.PAYTABS_BASE_URL');
        $this->paytabs_server_key = config('nafezly-payments.PAYTABS_SERVER_KEY');
        $this->paytabs_checkout_lang = config('nafezly-payments.PAYTABS_CHECKOUT_LANG');
        $this->paytabs_currency = config('nafezly-payments.PAYTABS_CURRENCY');
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
        $amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null
    ){
        $unique_id = uniqid();

        $response = Http::withHeaders([
            'Authorization'=>$this->paytabs_server_key,
            'Content-Type'=>"application/json"
        ])->post($this->paytabs_base_url . "/payment/request",[
            'profile_id'=>$this->paytabs_profile_id,
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => $unique_id,
            "cart_currency" => $this->paytabs_currency,
            "cart_amount" => $amount,
            "hide_shipping"=>true,
            "cart_description" => "items",
            "paypage_lang" => $this->paytabs_checkout_lang,
            "callback" => "http://localhost?customer_ref=".$unique_id, 
            "return" => "http://localhost?customer_ref=".$unique_id,
            "customer_ref"=>$unique_id,
            "customer_details" => [
                "name" => $user_first_name.' '.$user_last_name,
                "email" => $user_email,
                "phone" => $user_phone,
                "street1" => "Not Available Data",
                "city" => "Not Available Data",
                "state" => "Not Available Data",
                "country" => "Not Available Data",
                "zip" => "00000"
            ],
            'valu_down_payment'=>"0",
            "tokenise"=>1
        ])->json();
        Cache::forever('tran_ref', $response['tran_ref']);
        return [
            'payment_id'=>$response['tran_ref'],
            'redirect_url'=>$response['redirect_url'],
            'html' => "",
        ];
    }
    public function verify(Request $request ) : array {
        $response = Http::withHeaders([
            'Authorization'=>$this->paytabs_server_key,
            'Content-Type'=>"application/json"
        ])->post($this->paytabs_base_url . "/payment/query",[
            'profile_id'=>$this->paytabs_profile_id,
            'tran_ref'=>Cache::get('tran_ref')
        ])->json();

        if(isset($response['payment_result']['response_status']) && $response['payment_result']['response_status']=="A"){
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