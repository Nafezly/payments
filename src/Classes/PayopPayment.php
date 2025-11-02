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


class PayopPayment extends BaseController implements PaymentInterface
{
    public $payop_public_key;
    public $payop_secret_key;
    public $verify_route_name;


    public function __construct()
    {
        $this->payop_public_key = config('nafezly-payments.PAYOP_PUBLIC_KEY');
        $this->payop_secret_key = config('nafezly-payments.PAYOP_SECRET_KEY');
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
        $required_fields = ['amount','user_id','user_first_name','user_last_name','user_email'];
        $this->checkRequiredFields($required_fields, 'PAYOP');


        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;


        $amount = $this->amount;
        $currency = $this->currency??"USD";
        $orderId = $unique_id;
        $secret = $this->payop_secret_key;
        $public = $this->payop_public_key;

        $signature = $this->payopSignature($amount, $currency, $orderId, $secret);

        $payload = [
            "publicKey" => $public,
            "order" => [
                "id" => $orderId,
                "amount" => $amount,
                "currency" => $currency,
                "items" => [
                ],
            ],
            "signature" => $signature,
            "payer" => [
                "email" => $this->user_email,
                'name'=>$this->user_first_name." ".$this->user_last_name,
            ],
            "paymentMethod" => $this->source??"700001",
            "language" => "en",
            "resultUrl" => route($this->verify_route_name,['payment'=>"payop",'payment_id'=>$unique_id]),
            "failPath" => route($this->verify_route_name,['payment'=>"payop",'payment_id'=>$unique_id]),
        ];
 
        $response = \Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://api.payop.com/v1/invoices/create', $payload);
        $json_response = $response->json();

        
        if(isset($json_response['data']) && $json_response['status'] == 1){
            cache(['PAYOP_'.$unique_id => $json_response['data']]);
            return [
                'payment_id'=>$json_response['data'],
                'redirect_url'=>'https://checkout.payop.com/en/payment/'.$json_response['data'],
                'html'=>""
            ];
        }else{
            return [
                'payment_id'=>$unique_id,
                'redirect_url'=>"",
                'html'=>$response->body()
            ];
        }

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = "" ;
        $invoice_id = "" ;
        if(isset($request['payment_id'])){
            $payment_id =  $request['payment_id'];
            $invoice_id = cache('PAYOP_'.$payment_id);
        }elseif(isset($request['invoice']['id'])){
            $invoice_id = $request['invoice']['id'];
        }elseif(isset($request['invoiceId'])){
            $invoice_id = $request['invoiceId'];
        }

        if($invoice_id != ""){
            $res = \Http::get('https://api.payop.com/v1/invoices/'.$invoice_id)->json();
            if(isset($res['data']['status']) && in_array($res['data']['status'], ['success','paid'])){
                return [
                    'success' => true,
                    'payment_id'=>$invoice_id,
                    'message' => "",
                    'process_data' => $request->all()
                ];
            }
        }
        return [
            'success' => false,
            'payment_id'=>$invoice_id,
            'message' => "",
            'process_data' => $request->all()
        ]; 
    }

    public function payopSignature($amount, $currency, $orderId, $secretKey)
    {
        // All must be strings — don't break hash with float crap
        $data = [
            (string)$amount,
            strtoupper($currency),
            (string)$orderId,
            (string)$secretKey,
        ];

        return hash('sha256', implode(':', $data));
    }


}