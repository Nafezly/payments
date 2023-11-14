<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class EnotPayment extends BaseController implements PaymentInterface 
{
    public $enot_key;
    public $enot_secret;
    public $enot_shop_id;
    public $verify_route_name;


    public function __construct()
    {
        $this->enot_key = config('nafezly-payments.ENOT_KEY');
        $this->enot_secret = config('nafezly-payments.ENOT_SECRET');
        $this->enot_shop_id = config('nafezly-payments.ENOT_SHOP_ID');
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
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'ENOT');
        $unique_id = uniqid().rand(100000,999999);


        try{
            $response = Http::withHeaders([
                'x-api-key'=>$this->enot_key,
                'accept'=>"application/json",
                'content-type'=>"application/json"
            ])->post('https://api.enot.io/invoice/create',[
              "amount" => $this->amount,
              "order_id" => $unique_id,
              "email" => $this->user_email??"admin@admin.com",
              "currency" => $this->currency??"USD",
              "custom_fields" => ["order" => $unique_id],
              "comment" => "Credit",
              "fail_url" => route($this->verify_route_name,['payment'=>'enot']),
              "success_url" => route($this->verify_route_name,['payment'=>'enot']),
              "hook_url" => route($this->verify_route_name,['payment'=>'enot']),
              "shop_id" => $this->enot_shop_id,
              "expire" => 300,
              "include_service" => ["card"],
              "exclude_service" => ["qiwi"]
            ])->json();

            if(isset($response['data']['url'])){
                return [
                    'payment_id'=>$unique_id,
                    'html'=>"",
                    'redirect_url'=>$response['data']['url']
                ];
            }
        }catch(\Exception $e){
            return [
                'payment_id'=>$unique_id,
                'html'=>$e,
                'redirect_url'=>"",
            ];
        }
        return [
            'payment_id'=>$unique_id,
            'html'=>$response,
            'redirect_url'=>"",
        ];

    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        
        $json_response = Http::withHeaders([
            'x-api-key'=>$this->enot_key,
            'accept'=>"application/json",
            'content-type'=>"application/json"
        ])->get('https://api.enot.io/invoice/info',[
          "invoice_id" => $request['invoice_id'],
          "shop_id" => $this->enot_shop_id
        ])->json();



        $order_id="";
        if(isset($json_response['data']['order_id']))
            $order_id=$json_response['data']['order_id'];
        
        if( (isset($json_response['data']['status']) && $json_response['data']['status']=="success") ){
            return [
                'success' => true,
                'payment_id' => $order_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $json_response
            ];
        }
        return [
            'success' => false,
            'payment_id' => $order_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $json_response
        ];
    }
}