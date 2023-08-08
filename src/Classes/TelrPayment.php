<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class TelrPayment extends BaseController implements PaymentInterface 
{
    public $telr_merchant_id;
    public $telr_api_key;
    public $telr_mode;
    public $verify_route_name;


    public function __construct()
    {
        $this->telr_merchant_id = config('nafezly-payments.TELR_MERCHANT_ID');
        $this->telr_api_key = config('nafezly-payments.TELR_API_KEY');
        $this->telr_mode= config('nafezly-payments.TELR_MODE');
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
        $required_fields = ['amount','user_first_name','user_last_name','user_email'];
        $this->checkRequiredFields($required_fields, 'TELR');
 
        $uniqid = uniqid().rand(1000,9999);
        $data = [
            'ivp_method' => 'create',
            'ivp_store' => $this->telr_merchant_id,
            'ivp_authkey' => $this->telr_api_key,
            'order_ref' => $uniqid,
            'ivp_cart' => $uniqid,
            'ivp_amount' => $this->amount,
            'ivp_currency' => $this->currency??"SAR",
            'ivp_desc'=> "Credit",
            'ivp_test'=>$this->telr_mode=="live"?false:true,
            'return_auth'=> route($this->verify_route_name,['payment'=>"telr",'payment_id'=>$uniqid]),
            'return_decl'=> route($this->verify_route_name,['payment'=>"telr",'payment_id'=>$uniqid]),
            'return_can'=> route($this->verify_route_name,['payment'=>"telr",'payment_id'=>$uniqid]),
            'bill_fname' => $this->user_first_name,
            'bill_sname' => $this->user_last_name,
            'bill_addr1' => "NA",
            'bill_addr2' => "NA",
            'bill_city' => "NA",
            'bill_region' => "NA",
            'bill_zip' => "NA",
            'bill_country' => "NA",
            'bill_email' => $this->user_email,
            'bill_phone'=>$this->user_phone
        ];
        $response = Http::asForm()->post('https://secure.telr.com/gateway/order.json', $data)->json();
      

        if(isset($response['order']['url'])){
            cache(['telr_ref_code_'.$uniqid => $response['order']['ref']]);
            return [
                'payment_id'=>$uniqid,
                'html'=>$response,
                'redirect_url'=>$response['order']['url']
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
        if($request->ref_code==null)
            $request->merge(['ref_code'=>cache('telr_ref_code_'.$request->payment_id)]);
 
        $data = [
            'ivp_method' => 'check',
            'ivp_store' => $this->telr_merchant_id,
            'ivp_authkey' => $this->telr_api_key,
            'order_ref' => $request['ref_code'],
            'ivp_test'=>$this->telr_mode=="live"?false:true,
        ];
        $response = Http::asForm()->post('https://secure.telr.com/gateway/order.json', $data)->json();

        if (isset($response['order']['status']['text']) &&  $response['order']['status']['text']="Paid") {
            return [
                'success' => true,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>"",
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }
}