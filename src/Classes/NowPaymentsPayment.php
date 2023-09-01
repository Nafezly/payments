<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class NowPaymentsPayment extends BaseController implements PaymentInterface 
{
    public $nowpayments_api_key;
    public $verify_route_name;

    public function __construct()
    {
        $this->nowpayments_api_key = config('nafezly-payments.NOWPAYMENTS_API_KEY');
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
        $required_fields = ['amount','currency'];
        $this->checkRequiredFields($required_fields, 'NOWPAYMENTS');

        if($this->get_minimum_amount($this->currency,'usd')>$this->amount){
            return [
                'payment_id'=>"",
                'html'=>"",
                'redirect_url'=>"",
                'error'=>"AMOUNT_MINIMAL_ERROR"
            ];
        }
        $order_id = uniqid().rand(10000,99999);
        $response = Http::withHeaders([
            'x-api-key' => $this->nowpayments_api_key,
        ])->post('https://api.nowpayments.io/v1/payment',[
                "price_amount"=> $this->amount,
                "price_currency"=> "usd",
                "pay_currency"=> $this->currency,
                "ipn_callback_url"=> route($this->verify_route_name,['payment'=>"nowpayments"]),
                "order_id"=> $order_id,
                "order_description"=> "Credit"
        ])->json();
        if(isset($response['payment_status']) && $response['payment_status'] == "waiting")
            return [
                'payment_id'=>$response['payment_id'],
                'html'=>$response,
                'redirect_url'=>$response['pay_address'],
                'error'=>""
            ];
        return [
            'payment_id'=>"",
            'html'=>"",
            'redirect_url'=>"",
            'error'=>$response['code']
        ];
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        $payment_id = $request->NP_id??$request->payment_id;
        $response = \Http::withHeaders([
            'x-api-key'=>$this->nowpayments_api_key
        ])->get('https://api.nowpayments.io/v1/payment/'.$payment_id)->json();

        if (isset($response['payment_status']) && $response['payment_status'] == "finished") {
            return [
                'success' => true,
                'payment_id'=>$response['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>$payment_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }
    }
    public function get_minimum_amount($from,$to,$fiat_equivalent="usd"){
        try{
            $response = \Http::withHeaders(['x-api-key'=>$this->nowpayments_api_key])->get('https://api.nowpayments.io/v1/min-amount?currency_from='.$from.'&currency_to='.$to.'&fiat_equivalent='.$fiat_equivalent)->json();
            return $response['fiat_equivalent'];
        }catch(\Exception $e){
            return 10000000;
        }

    }

}