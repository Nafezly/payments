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

class OpayPayment implements PaymentInterface
{

    private $opay_currency;
    private $opay_secret_key;
    private $opay_public_key;
    private $opay_merchant_id;
    private $opay_country_code;
    private $opay_base_url
    private $verify_route_name;


    public function __construct()
    {
        $this->opay_currency = config('nafezly-payments.OPAY_CURRENCY');
        $this->opay_secret_key = config('nafezly-payments.OPAY_SECRET_KEY');
        $this->opay_public_key = config('nafezly-payments.OPAY_PUBLIC_KEY');
        $this->opay_merchant_id = config('nafezly-payments.OPAY_MERCHANT_ID');
        $this->opay_country_code = config('nafezly-payments.OPAY_COUNTRY_CODE');
        $this->opay_base_url = config('nafezly-payments.OPAY_BASE_URL');
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
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        if (is_null($user_first_name)) throw new MissingPaymentInfoException('user_first_name', 'Opay');
        if (is_null($user_last_name)) throw new MissingPaymentInfoException('user_last_name', 'Opay');
        if (is_null($user_phone)) throw new MissingPaymentInfoException('user_phone', 'Opay');
        if (is_null($user_email)) throw new MissingPaymentInfoException('user_email', 'Opay');

        $unique_id=uniqid();
        $response = Http::withHeaders([
            "MerchantId"=>$this->opay_merchant_id,
            "authorization"=>"Bearer ".$this->opay_public_key,
            "content-type"=>"application/json"  
        ])->post($this->opay_base_url.'/api/v1/international/cashier/create',[
           "amount" => [
                 "currency" => $this->opay_currency, 
                 "total" => $amount*100 
            ], 
           "callbackUrl" => $this->verify_route_name."?reference_id=".$unique_id, 
           "cancelUrl" => $this->verify_route_name."?reference_id=".$unique_id, 
           "country" => "EG", 
           "expireAt" => 780, 
           "payMethod" => "BankCard", 
           "productList" => [
                [
                   "description"=>"credit",
                   "name" => "credit", 
                   "price" => $amount, 
                   "productId" => $unique_id, 
                   "quantity" => 1 
                ] 
            ], 
           "reference" => $unique_id, 
           "returnUrl" => $this->verify_route_name."?reference_id=".$unique_id, 
           "userInfo" => [
              "userEmail" => $user_email, 
              "userId" => $user_id, 
              "userMobile" => $user_phone, 
              "userName" => $user_first_name.' '.$user_last_name 
           ] 
        ])->json();
        if($response['code']=="00000"){
            return [
                'payment_id'=>$unique_id,
                'redirect_url'=>$response['data']['cashierUrl'],
                'html'=>""
            ];
        }else{
            return [
                'payment_id'=>$unique_id,
                'redirect_url'=>"",
                'html'=>$response['message']
            ];
        }

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $data = (string)json_encode(['country' => "EG",'reference' => $request->reference_id],JSON_UNESCAPED_SLASHES);
        $auth = hash_hmac('sha512', $data, $this->opay_secret_key); 
        $response = Http::withHeaders([
            "MerchantId"=>$this->opay_merchant_id,
            "authorization"=>"Bearer ".$auth
        ])->post($this->opay_base_url.'/api/v1/international/cashier/status',[
            'reference'=>$request->reference_id,
            'country'=>"EG"
        ])->json();
        if($response['code']=="00000" && isset($response['data']['status']) && $response['data']['status']){
            return [
                'success' => true,
                'message' => __('messages.PAYMENT_DONE'),
                'process_data' => $response
            ];

        }else{
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED_WITH_CODE',['CODE'=>$response['message']]),
                'process_data' => $response
            ];
        }
    }
}