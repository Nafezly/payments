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


class YallaPayPayment extends BaseController implements PaymentInterface
{
    private $yallapay_public_key;
    private $yallapay_secret_key;
    private $yallapay_webhook_secret;
    private $verify_route_name;


    public function __construct()
    {
        $this->yallapay_public_key = config('nafezly-payments.YALLAPAY_PUBLIC_KEY');
        $this->yallapay_secret_key = config('nafezly-payments.YALLAPAY_SECRET_KEY');
        $this->yallapay_webhook_secret = config('nafezly-payments.YALLAPAY_WEBHOOK_SECRET');
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
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'OPAY');


        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;



        $response = Http::withHeaders([
            "Accept"=>"application/json",
            "content-type"=>"application/json"  
        ])->post("https://yallapay.net/api/request",[
            "private_key"=>$this->yallapay_secret_key,
            "currency"=>$this->currency??"USD",
            "is_fallback"=>1,
            'fallback_url'=>route($this->verify_route_name,['payment_id'=>$unique_id,'payment'=>"yallapay"]),
            'external_id'=>$unique_id,
            'amount'=>$this->amount,
            'purpose'=>"Transaction"
        ])->json();

        if(isset($response['checkout_url']) ){
            return [
                'payment_id'=>$unique_id,
                'redirect_url'=>$response['checkout_url'],
                'html'=>""
            ];
        }else{
            return [
                'payment_id'=>$unique_id,
                'redirect_url'=>"",
                'html'=>""
            ];
        }

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = $request['external_id'] ;



        if($request['webhook_secret'] == $this->yallapay_webhook_secret){
            if(in_array($request['status'], [1,'Paid','success'])){
                return [
                    'success' => true,
                    'payment_id'=>$payment_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];

            }
        }

        $res = \Http::post('https://yallapay.net/api/transaction/verify',[
            'private_key'=>$this->yallapay_secret_key,
            'trx_id'=>  $payment_id
        ]);
        if($res->ok()){
            if(isset($res[0]['status']) && $res[0]['status'] == "Paid"){
                return [
                    'success' => true,
                    'payment_id'=>$payment_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            }else if(isset($res['error'])){
                return [
                    'success' => false,
                    'payment_id'=>$payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE'),
                    'process_data' => $request->all()
                ];
            }else if(isset($res[0]['status'])){
                return [
                    'success' => false,
                    'payment_id'=>$payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE'),
                    'process_data' => $request->all()
                ];
            }
        }


        return [
            'success' => false,
            'payment_id'=>$payment_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE'),
            'process_data' => $request->all()
        ];


        

        
    }
}