<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class MamoPayment extends BaseController implements PaymentInterface 
{
    public $mamopayment_base_url;
    public $mamopayment_api_key;
    public $verify_route_name;


    public function __construct()
    {
        $this->mamopayment_base_url = config('nafezly-payments.MAMOPAYMENT_BASE_URL');
        $this->mamopayment_api_key = config('nafezly-payments.MAMOPAYMENT_API_KEY');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

 
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount','user_id','user_first_name','user_last_name','user_email','user_phone'];
        $this->checkRequiredFields($required_fields, 'mamopayment');

        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;


        $payload = [
            "title"        => "Recharge Amount",
            "amount"       => $this->amount,
            "currency"     => $this->currency??"AED",
            'processing_fee_percentage'=>0,
            "return_url" => route($this->verify_route_name,['payment'=>'mamopayment','payment_id'=>$unique_id]),
            "cancel_url"   => route($this->verify_route_name,['payment'=>'mamopayment','payment_id'=>$unique_id]),
            'failure_return_url'=> route($this->verify_route_name,['payment'=>'mamopayment','payment_id'=>$unique_id]),
            'email'=>$this->user_email,
            'external_id'=>$unique_id,
            'lang'=>$this->language,
            
        ];

        try{
            $response = \Http::withToken($this->mamopayment_api_key)
            ->post("$baseUrl/links",$payload);
            if ($response->failed()) {
                return [
                    'payment_id'=>$unique_id,
                    'html'=>$response,
                    'redirect_url'=>""
                ]; 
            }
            $data = $response->json();
            if(isset($data['payment_url'])){
                cache(['mamo_payment_'.$unique_id => $data['id'] ]);
                return [
                    'payment_id'=>$data['id'],
                    'html'=>$data,
                    'redirect_url'=>$data['payment_url']
                ]; 
            }else{
                return [
                    'payment_id'=>$unique_id,
                    'html'=>$data,
                    'redirect_url'=>""
                ]; 
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
        if(!isset($request['payment_id']))return ['success' => false];
        $payment_id = cache('mamo_payment_'.$request['payment_id']);

        if(isset($payment_id)){
            $response = \Http::withToken($this->mamopayment_api_key)
                ->get("$baseUrl/manage_api/v1/links/".$payment_id);
            if ($response->failed()) {
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all()
                ]; 
            }
            $json_response= $response->json();
            if($response->ok() && 
                (
                    (isset($json_response['charges'][0]['status']) && 
                    $json_response['charges'][0]['status'] == "captured"
                )

            ){
                return [
                    'success' => true,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $json_response
                ];
            }else{
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $json_response
                ];
            }
        }
        return [
            'success' => false,
            'payment_id' => $payment_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

}