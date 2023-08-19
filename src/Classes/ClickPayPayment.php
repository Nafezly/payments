<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class ClickPayPayment extends BaseController implements PaymentInterface 
{
    public $clickpay_server_key;
    public $clickpay_profile_id;
    public $verify_route_name;


    public function __construct()
    {
        $this->clickpay_server_key = config('nafezly-payments.CLICKPAY_SERVER_KEY');
        $this->clickpay_profile_id = config('nafezly-payments.CLICKPAY_PROFILE_ID');
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
     * @return string[]
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount','user_first_name','user_last_name','user_email','user_phone'];
        $this->checkRequiredFields($required_fields, 'CLICKPAY');
 


        $uniqid = uniqid().rand(1000,9999);

        $response = \Http::withHeaders([
            'authorization' => $this->clickpay_server_key
        ])->post('https://secure.clickpay.com.sa/payment/request', [
            "profile_id" => $this->clickpay_profile_id,
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => "cart_" . $uniqid,
            "cart_currency" => $this->currency??"SAR",
            "cart_amount" => $this->amount,
            "cart_description" => "Credit",
            "paypage_lang" => "ar",
            "customer_details" => [
                "name" => $this->user_first_name.' '.$this->user_last_name,
                "email" => $this->user_email,
                "phone" => $this->user_phone,
                "street1" => "address street",
                "city" => "riyadh",
                "state" => "riyadh",
                "country" => "SA",
                "zip" => "12345"
            ],
            "shipping_details" => [
                "name" => $this->user_first_name.' '.$this->user_last_name,
                "email" => $this->user_email,
                "phone" => $this->user_phone,
                "street1" => "street2",
                "city" => "riyadh",
                "state" => "riyadh",
                "country" => "SA",
                "zip" => "54321"
            ],
            "framed"=> false,
            "framed_return_top"=> false,
            "framed_return_parent"=> false,
            "hide_shipping"=> true,                
            "callback"=> route($this->verify_route_name,['payment'=>"clickpay",'payment_id'=>$uniqid]),
            "return" => str_replace('https','http',route($this->verify_route_name,['payment'=>"clickpay",'payment_id'=>$uniqid])),
            "framed" => false,
            "framed_return_top" => false,
            "framed_return_parent" => false,
            "hide_shipping" => true
        ])->json();

        if(isset($response['tran_ref'])){
            cache(['clickpay_ref_code_'.$uniqid => $response['tran_ref'] ]);
            return [
                'payment_id'=>$response['tran_ref'],
                'html'=>$response,
                'redirect_url'=>$response['redirect_url']
            ];
        }
        return [
            'payment_id'=>$uniqid,
            'html'=>$response,
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {

        $response = \Http::withHeaders([
            'authorization' => $this->clickpay_server_key
        ])->post('https://secure.clickpay.com.sa/payment/query', [
            'profile_id' => $this->clickpay_profile_id,
            'tran_ref' => cache('clickpay_ref_code_'.$request['payment_id'])
        ])->json();

        if(isset($response['payment_result']['response_status']) && $response['payment_result']['response_status'] == "A" ){
             return [
                'success' => true,
                'payment_id'=>cache('clickpay_ref_code_'.$request['payment_id']),
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        }else{
            return [
                'success' => false,
                'payment_id'=>"",
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }
}