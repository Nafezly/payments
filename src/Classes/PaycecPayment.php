<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class PaycecPayment extends BaseController implements PaymentInterface 
{
    public $paycec_merchant_username;
    public $paycec_merchant_secret;
    public $paycec_mode;
    public $verify_route_name;


    public function __construct()
    {
        $this->paycec_merchant_username = config('nafezly-payments.PAYCEC_MERCHANT_USERNAME');
        $this->paycec_merchant_secret = config('nafezly-payments.PAYCEC_MERCHANT_SECRET');
        $this->paycec_mode = config('nafezly-payments.PAYCEC_MODE');
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
        $this->checkRequiredFields($required_fields, 'PAYCEC');
        $unique_id = uniqid().rand(100000,999999);


        $base_url = "secure";
        if($this->paycec_mode=="test")
            $base_url = "securetest";

        $endpoint = 'https://'.$base_url.'.paycec.com/redirect-service/request-token';
        $redirect_url = 'https://'.$base_url.'.paycec.com/redirect-service/webscreen?token=';


        try{

            $country = \Http::get('http://ip-api.com/json/'.$this->get_ip())->json();
            $params =  [
                'merchantName' => $this->paycec_merchant_username,
                'merchantSecretKey' => $this->paycec_merchant_secret,
                'merchantToken' => $unique_id,                                  //You can change it by your unique token builder
                'merchantReferenceCode' => 'Credit',  //Replace it by your description
                'userEmail' => $this->user_email,                           //Replace it by your buyer email
                'amount' => $this->amount,                                           //Replace it by your total amount
                'currencyCode' => $this->currency??"USD",                                    //Replace it by your currency code
                'returnUrl' => route($this->verify_route_name,['payment'=>'paycec']),   //Replace it by your return call back url
                'cancelUrl' => route($this->verify_route_name,['payment'=>'paycec']),
                'billing' => serialize(array(
                    'first_name'    => $this->user_first_name??"",
                    'last_name'     => $this->user_last_name??"",
                    'address'       => $country['country'].', '.$country['regionName'].', '.$country['city'],
                    'country'       => $country['countryCode'],
                    'city'          => $country['city'],
                    'state'         => $country['city'],
                    'postal'        => $country['zip']!=""?$country['zip']:'00000',
                    'phone'         => $this->user_phone??'',
                    'email'         => $this->user_email??"",
                )),
            ];

            ksort($params);
            $sig = $endpoint.'?'.http_build_query($params);
            $params['sig']= hash_hmac('sha512', $sig, $this->paycec_merchant_secret, false);
 
            $response = Http::withoutVerifying()->withOptions([
                'debug' => false,
                'verify' => false,
            ])->asForm()->post($sig,$params)->json();

            if(isset($response['token'])){
                return [
                    'payment_id'=>$response['token'],
                    'html'=>"",
                    'redirect_url'=>$redirect_url.$response['token']
                ];
            }else{
                return [
                    'payment_id'=>$response['token'],
                    'html'=>$response,
                    'redirect_url'=>""
                ];
            }

        }catch(\Exception $e){
            return [
                'payment_id'=>$unique_id,
                'html'=>$e,
                'redirect_url'=>"",
            ];
        }

 

    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        
        $base_url = "secure";
        if($this->paycec_mode=="test")
            $base_url = "securetest";
        $endpoint = 'https://'.$base_url.'.paycec.com/redirect-service/purchase-details';

        $params=[
            'merchantName' => $this->paycec_merchant_username,
            'merchantSecretKey' => $this->paycec_merchant_secret,
            'token'=>$request['token']
        ];
        ksort($params);
        $sigString =  $endpoint.'?'.http_build_query($params);
        $params['sig']  = hash_hmac('sha512', $sigString, $this->paycec_merchant_secret , false);
        $response = Http::asForm()->post($endpoint,$params)->json();

        if($response['isSuccessful']==true){
            return [
                'success' => true,
                'payment_id' => $request['token'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        }
        return [
            'success' => false,
            'payment_id' => $request['token'],
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response
        ];
    }

    public function get_ip(){
        $ipaddress = '';
        if(isset($_SERVER["HTTP_CF_CONNECTING_IP"]))
            $ipaddress=$_SERVER["HTTP_CF_CONNECTING_IP"];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress=$_SERVER['REMOTE_ADDR'];
        else if(isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else if(request()->ip()!=null)
            $ipaddress = request()->ip();
        else
            $ipaddress = 'UNKNOWN';
        if($ipaddress=="127.0.0.1"){
            $ip = \Http::get('https://api.ipify.org/?format=json')->json();
            return $ip['ip'];
        }
        return $ipaddress;
    }
}