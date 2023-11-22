<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class PrimePayment extends BaseController implements PaymentInterface 
{

    
    public $prime_project_id;
    public $prime_secret_word_1;
    public $prime_secret_word_2;
    public $verify_route_name;


    public function __construct()
    {
        $this->prime_project_id = config('nafezly-payments.PRIME_PROJECT_ID');
        $this->prime_secret_word_1 = config('nafezly-payments.PRIME_SECRET_WORD_1');
        $this->prime_secret_word_2 = config('nafezly-payments.PRIME_SECRET_WORD_2');
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
        $required_fields = ['amount','user_email'];
        $this->checkRequiredFields($required_fields, 'PRIME');
        $unique_id = uniqid().rand(100000,999999);



        $data = [
            'action' => 'initPayment',
            'project' => $this->prime_project_id,
            'sum' => $this->amount, 
            'strict_payWay'=>1,
            'currency' => $this->currency??"USD",
            'innerID' => $unique_id,
            'payWay' => $this->source??'1', // например 1 для карт, 5 для qiwi
            'directPay'=>"1",
            'lang'=>"EN",  
            'email' => $this->user_email, // e-mail
            'returnLink' => 1 // returnLink=1 нужно только для API v1
        ];
        $secret1 = $this->prime_secret_word_1;
        $data['sign'] =md5($secret1.$data['action'].$data['project'].$data['sum'].$data['currency'].$data['innerID'].$data['email'].$data['payWay']);

        $response = \Http::asForm()->post('https://pay.primepayments.io/API/v2/',$data)->json();
        dd($response);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, '' );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);

        $answer = json_decode($server_output, true);

        if (isset($answer['status']) && $answer['status'] == 'OK') {
            $link = $answer['result']; 
        } else {
            echo "Произошла ошибка: ".$answer['result'];
        }



        try{
            $push_to_gateway = Http::withHeaders([
                'Authorization'=>"Basic ".base64_encode($this->bigpay_key.':'.$this->bigpay_secret)
            ])->post('https://gateway.big-pay.com/app/transactions/initSession',[
                "orderId"=>$unique_id,
                "description"=>"Credit",
                "store"=>$this->bigpay_key,
                "amount"=>$this->amount,
                "cancelUrl"=>route($this->verify_route_name,['payment'=>'bigpay']),
                "completeUrl"=>route($this->verify_route_name,['payment'=>'bigpay']),
                "timeoutUrl"=>route($this->verify_route_name,['payment'=>'bigpay']),
                "successCallbackUrl"=>route($this->verify_route_name,['payment'=>'bigpay']),
                "failureCallbackUrl"=>route($this->verify_route_name,['payment'=>'bigpay']),
                "session"=>null
            ])->json();
            $get_mastercard_version = Http::get('https://bobsal.gateway.mastercard.com/checkout/api/retrieveWsapiVersion/'.$push_to_gateway['session']['id'])->json();

            $push_to_gateway = Http::asForm()->withHeaders([
                "User-Agent"=>"Mozilla/5.0 (X11; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0",
                "Accept"=>"application/json, text/javascript, */*; q=0.01",
                "Accept-Language"=>"en-US,en;q=0.5",
                "Accept-Encoding"=>"gzip, deflate, br",
                "Content-Length"=>"260",
                "Referer"=>"https://bobsal.gateway.mastercard.com/static/checkout/landing/index.html",
                "Content-Type"=>"application/x-www-form-urlencoded; charset=UTF-8",
                "Content-Length"=>"260",
                "Origin"=>"https://bobsal.gateway.mastercard.com",
                "Sec-Fetch-Dest"=>"empty",
                "Sec-Fetch-Mode"=>"cors",
                "Sec-Fetch-Site"=>"same-origin",
                "Te"=>"trailers",
                "Connection"=>"close",
            ])->post('https://bobsal.gateway.mastercard.com/api/page/version/'.$get_mastercard_version['wsapiVersion'].'/pay',[
                'session.id'=>$push_to_gateway['session']['id'],
                'interaction.cancelUrl'=>urlencode(route($this->verify_route_name,['payment'=>'bigpay'])).'#__hc-action-cancel',
                'interaction.timeoutUrl'=>urlencode(route($this->verify_route_name,['payment'=>'bigpay'])).'#__hc-action-timeout'
            ])->json();
            if(isset($push_to_gateway['redirectURL'])){
                return [
                    'payment_id'=>$unique_id,
                    'html'=>"",
                    'redirect_url'=>'https://bobsal.gateway.mastercard.com'.$push_to_gateway['redirectURL'].'?checkoutVersion=1.0.0'
                ];
            }
        }catch(\Exception $e){
            return [
                'payment_id'=>$unique_id,
                'html'=>$this->generate_html([
                    'bigpay_mode'=>$this->bigpay_mode,
                    'amount'=>$this->amount,
                    'order_number'=>$unique_id,
                    'product_description'=>"Credit",
                    'bigpay_key'=>$this->bigpay_key,
                    'bigpay_secret'=>$this->bigpay_secret,
                    'authorization'=>base64_encode($this->bigpay_key.':'.$this->bigpay_secret),
                    'verify_route_name'=>route($this->verify_route_name,['payment'=>'bigpay']),
                ]),
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
        
        $base_url = "https://app.big-pay.com/integration/transactions/transaction/";
        if($this->bigpay_mode=="live")
            $base_url="https://gateway.big-pay.com/app/transactions/transaction/";

        $response = Http::withHeaders([
            'Authorization'=> "Basic ".base64_encode($this->bigpay_key.':'.$this->bigpay_secret)
        ])->get($base_url.$request->transaction);
        $json_response= $response->json();
        if($response->ok() && isset($json_response['orderNumber']) && isset($json_response['status']) && ($json_response['status']=="SUCCESS" || $json_response['status']=="PAYED")  ){
            return [
                'success' => true,
                'payment_id' => $json_response['orderNumber'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $json_response
            ];
        }
        return [
            'success' => false,
            'payment_id' => $request->transaction,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $json_response
        ];
    }

    /**
     * @param $data
     * @return string
     */
    public function generate_html($data){
        return str_replace("\n",'',view('nafezly::html.bigpay', ['data' => $data])->render());
    }
}