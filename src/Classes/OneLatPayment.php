<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class OneLatPayment extends BaseController implements PaymentInterface 
{
    public $onelat_key;
    public $onelat_secret;
    public $onelat_api_base_url;
    public $onelat_checkout_base_url;
    public $verify_route_name;


    public function __construct()
    {
        $this->onelat_key = config('nafezly-payments.ONELAT_KEY');
        $this->onelat_secret = config('nafezly-payments.ONELAT_SECRET');
        $this->onelat_api_base_url = config('nafezly-payments.ONELAT_API_BASE_URL');
        $this->onelat_checkout_base_url = config('nafezly-payments.ONELAT_CHECKOUT_BASE_URL');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

 
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'ONELAT');

        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;

        
        try{
            $payment_methods = \Http::withHeaders([
                'x-api-key' => $this->onelat_key,
                'x-api-secret' => $this->onelat_secret,
                'Content-Type' => 'application/json',
            ])->get($this->onelat_api_base_url.'/v1/payment_methods')->json();
            if(isset($payment_methods['payment_methods'])){
                if(null !== collect($payment_methods['payment_methods'])->where('type','CARD')->where('currency','USD')->first() ){
                    $method = collect($payment_methods['payment_methods'])->where('type','CARD')->where('currency','USD')->first();

                    $response = \Http::withHeaders([
                        'x-api-key' => $this->onelat_key,
                        'x-api-secret' => $this->onelat_secret,
                        'Content-Type' => 'application/json',
                    ])->post($this->onelat_api_base_url.'/v1/checkout_preferences', [
                        'amount' => $this->amount,
                        'currency' => $this->currency??"USD",
                        'origin' => 'API',
                        'external_id' => $unique_id,
                        'title' => 'Recharging Amount '.$this->amount,
                        'selected_payment_method_id'=>$method['id'],
                        'type' => 'PAYMENT',
                        'custom_urls' => [
                            //'status_changes_webhook' => route($this->verify_route_name,['payment'=>'onelat','payment_id'=>$unique_id,'step'=>'change']),
                            'success_payment_redirect' => route($this->verify_route_name,['payment'=>'onelat','payment_id'=>$unique_id]),
                            'error_payment_redirect' => route($this->verify_route_name,['payment'=>'onelat','payment_id'=>$unique_id]),
                        ],
                        'payer' => [
                            'email' => $this->user_email??"",
                            'phone_number' => $this->user_phone??"",
                            'first_name' => $this->user_first_name??"",
                            'last_name' => $this->user_last_name??"",
                        ],
                    ])->json();
                    if(isset($response['checkout_url'])){
                        cache(['one_lat_'.$unique_id => $response['id'] ]);
                        return [
                            'payment_id'=>$unique_id,
                            'html'=>"",
                            'redirect_url'=>$response['checkout_url']
                        ];
                    }
                    else
                        return [
                            'payment_id'=>$unique_id,
                            'html'=>$response,
                            'redirect_url'=>""
                        ];
                }   
            }
        }catch(\Exception $e){
            return [
                'payment_id'=>$unique_id,
                'html'=>$e,
                'redirect_url'=>""
            ];
        }
        return [
            'payment_id'=>$unique_id,
            'html'=>"",
            'redirect_url'=>""
        ];

    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
     
        if(isset($request['entity_id'])){
            $response = Http::withHeaders([
                'x-api-key' => $this->onelat_key,
                'x-api-secret' => $this->onelat_secret,
                'Content-Type' => 'application/json',
            ])->get($this->onelat_api_base_url."/v1/payment_orders/".$request['entity_id']);

            $json_response= $response->json();
            if($response->ok() && isset($json_response['status']) && in_array($json_response['status'], ["CLOSED"]) && $json_response['external_id'] == $request['payment_id'] ){
                return [
                    'success' => true,
                    'payment_id' => $request['payment_id'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $json_response
                ];
            }
        }

        return [
            'success' => false,
            'payment_id' => $request['payment_id'],
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

}