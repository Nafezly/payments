<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class KashierPayment extends BaseController implements PaymentInterface
{
    public  $kashier_url;
    public  $kashier_mode;
    private $kashier_account_key;
    private $kashier_iframe_key;
    private $kashier_token;
    public  $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->kashier_url = config("nafezly-payments.KASHIER_URL");
        $this->kashier_mode = config("nafezly-payments.KASHIER_MODE");
        $this->kashier_account_key = config("nafezly-payments.KASHIER_ACCOUNT_KEY");
        $this->kashier_iframe_key = config("nafezly-payments.KASHIER_IFRAME_KEY");
        $this->kashier_token = config("nafezly-payments.KASHIER_TOKEN");
        $this->currency = config('nafezly-payments.KASHIER_CURRENCY');
        $this->app_name = config('nafezly-payments.APP_NAME');
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
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'KASHIER');

        $payment_id = uniqid();

        $mid = $this->kashier_account_key;
        $order_id = $payment_id;
        $secret = $this->kashier_iframe_key;
        $path = "/?payment={$mid}.{$order_id}.{$this->amount}.{$this->currency}";
        $hash = hash_hmac('sha256', $path, $secret);

        $data = [
            'mid' => $mid,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'order_id' => $order_id,
            'path' => $path,
            'hash' => $hash,
            'source'=>$this->source,
            'redirect_back' => route($this->verify_route_name, ['payment' => "kashier"])
        ];

        return [
            'payment_id' => $payment_id,
            'html' => $this->generate_html($data),
            'redirect_url'=>""
        ];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        if ($request["paymentStatus"] == "SUCCESS" && $request['signature']!=null) {
            $queryString = "";
            foreach ($request->all() as $key => $value) {

                if ($key == "signature" || $key == "mode") {
                    continue;
                }
                $queryString = $queryString . "&" . $key . "=" . $value;
            }
            $queryString = ltrim($queryString, $queryString[0]);
            $signature = hash_hmac('sha256', $queryString, $this->kashier_iframe_key,false);
            if ($signature == $request["signature"]) {
                return [
                    'success' => true,
                    'payment_id'=>$request['merchantOrderId'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id'=>$request['merchantOrderId'],
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all()
                ];
            }
        }else if($request['signature']==null){
            $url_mode = $this->kashier_mode == "live"?'':'test-';
            $response = Http::withHeaders([
                'Authorization' => $this->kashier_token
            ])->get('https://'.$url_mode.'api.kashier.io/payments/orders/'.$request['merchantOrderId'])->json();
            if(isset($response['response']['status']) && $response['response']['status']=="CAPTURED"){
                return [
                    'success' => true,
                    'payment_id'=>$request['merchantOrderId'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            }else{
                return [
                    'success' => false,
                    'payment_id'=>$request['merchantOrderId'],
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all()
                ];
            }
            
        } else {
            return [
                'success' => false,
                'payment_id'=>$request['merchantOrderId'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }

    /**
     * @param $amount
     * @param $data
     * @return string
     */
    private function generate_html($data): string
    {
        return view('nafezly::html.kashier', ['model' => $this, 'data' => $data])->render();
    }

}