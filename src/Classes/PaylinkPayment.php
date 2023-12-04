<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PaylinkPayment extends BaseController implements PaymentInterface
{
    private $paylink_api_key;
    private $paylink_app_id;
    private $paylink_mode;
    private $verify_route_name;

    public function __construct()
    {
        $this->paylink_api_key = config('nafezly-payments.PAYLINK_API_KEY');
        $this->paylink_app_id = config('nafezly-payments.PAYLINK_APP_ID');
        $this->paylink_mode = config('nafezly-payments.PAYLINK_MODE');
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
     * @return void
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'/*, 'user_first_name', 'user_last_name', 'user_email', 'user_phone'*/];
        $this->checkRequiredFields($required_fields, 'PAYLINK');


        if($this->paylink_mode=="live")
            $url = "https://restapi.paylink.sa";
        else
            $url = "https://restpilot.paylink.sa";

        $payment_id = uniqid().rand(10000,99999);
        $get_token_id = \Http::post($url."/api/auth",[
            'apiId'=>$this->paylink_app_id,
            'secretKey'=>$this->paylink_api_key,
            'persistToken'=>false
        ])->json();
        if(isset($get_token_id['id_token'])){
            $response = \Http::withHeaders([
                'Authorization'=>"Bearer ".$get_token_id['id_token'],
                'Content-Type'=>"application/JSON",
            ])->post($url."/api/addInvoice",[
                'amount'=>$this->amount,
                'callBackUrl'=>route($this->verify_route_name,['payment'=>"paylink"]),
                'clientEmail'=>$this->user_email??"",
                'clientMobile'=>$this->user_phone??"96612345678",
                'currency'=>$this->currency??"SAR",
                'clientName'=>$this->user_first_name.' '.$this->user_last_name,
                'orderNumber'=>$payment_id,
                'products'=>[
                    [
                        'title'=>"Credit",
                        'price'=>$this->amount,
                        'qty'=>1
                    ]
                ]
            ])->json();
            if(isset($response['url']))
                return [
                    'payment_id'=>$response['transactionNo'],
                    'html' => "",
                    'redirect_url'=>$response['url']
                ];
        }

        return [
            'payment_id'=>"",
            'html' => "",
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {

        if($this->paylink_mode=="live")
            $url = "https://restapi.paylink.sa";
        else
            $url = "https://restpilot.paylink.sa";

        $payment_id = uniqid().rand(10000,99999);
        $get_token_id = \Http::post($url."/api/auth",[
            'apiId'=>$this->paylink_app_id,
            'secretKey'=>$this->paylink_api_key,
            'persistToken'=>false
        ])->json();
        if(isset($get_token_id['id_token'])){
            $response = \Http::withHeaders([
                'Authorization'=>"Bearer ".$get_token_id['id_token'],
                'Content-Type'=>"application/json",
            ])->get($url."/api/getInvoice/".$request['transactionNo'])->json();
            if(isset($response['orderStatus']) &&  $response['orderStatus'] == "Paid"){
                return [
                    'success' => true,
                    'payment_id'=>$request['transactionNo'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id'=>$request['transactionNo'],
                    'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE'),
                    'process_data' => $response
                ];
            }
        }

        return [
            'success' => false,
            'payment_id'=>$request['transactionNo'],
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

}