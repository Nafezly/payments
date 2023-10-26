<?php

namespace Nafezly\Payments\Classes;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Illuminate\Support\Facades\Http;

class PayPalCreditPayment extends BaseController implements PaymentInterface
{
    private $paypal_client_id;
    private $paypal_secret;
    private $verify_route_name;
    public $paypal_mode;
    public $currency;


    public function __construct()
    {
        $this->paypal_client_id = config('nafezly-payments.PAYPAL_CLIENT_ID');
        $this->paypal_secret = config('nafezly-payments.PAYPAL_SECRET');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->paypal_mode = config('nafezly-payments.PAYPAL_MODE');
        $this->currency = config('nafezly-payments.PAYPAL_CURRENCY');
    }

    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return array|Application|RedirectResponse|Redirector
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'PayPal');
 



        $mode = $this->paypal_mode=="live"?'':'.sandbox';
        $order_id = uniqid().rand(1000,99999);
        $response = Http::withHeaders([
            'Content-Type'=> 'application/json',
            'Accept-Language' => 'ar_SA',
            'Authorization'=> 'Basic '.base64_encode($this->paypal_client_id.':'.$this->paypal_secret)
        ])->post('https://api-m'.$mode.'.paypal.com/v2/checkout/orders', [
           "intent" => "CAPTURE", 
           "purchase_units" => [
                 [
                    "reference_id" => $order_id, 
                    "amount" => [
                       "currency_code" => $this->currency??"USD", 
                       "value" => sprintf('%0.2f',$this->amount)
                    ] 
                 ]
              ],
            "payment_source" => [
                "paypal" => [
                    "experience_context" => [
                        "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                        "locale" => "ar-SA",
                        "shipping_preference" => "NO_SHIPPING",
                        /*
                        "return_url" => "https://example.com/returnUrl",
                        "cancel_url" => "https://example.com/cancelUrl",*/
                    ],
                ],
            ],
            "payer"=>[
                'email_address'=>"test@test.com",
                'name'=>[
                    'given_name'=>"test",
                    'surname'=>"test"
                ],
                'phone'=>[
                    'phone_type'=>"MOBILE",
                    'phone_number'=>[
                        "national_number"=>"201234567890"
                    ]
                ],
                'address'=>[
                    'postal_code'=>"00000",
                    'country_code'=>"EG"
                ]
            ]
        ]);

        if($response->ok()){
            $response = $response->json();
            return [
                'payment_id'=>$response['id'],
                'html' => $response /*$this->generate_html([
                    'response'=>$response,
                    'return_url'=>route($this->verify_route_name,['payment'=>'paypal_credit']),
                    'paypal_client_id'=>$this->paypal_client_id
                ])*/,
                'redirect_url'=>""
            ];
        }
        return [
            'payment_id'=>$order_id,
            'html' => $response->json(),
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $mode = $this->paypal_mode=="live"?'':'.sandbox';

        $response = Http::withHeaders([
            'Authorization'=> 'Basic '.base64_encode($this->paypal_client_id.':'.$this->paypal_secret),
        ])->post('https://api-m'.$mode.'.paypal.com/v2/checkout/orders/'.$request['order_id'].'/capture');

        $json_response = $response->json();

        if($response->ok()){
            return [
                'success' => true,
                'payment_id'=>$request->order_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $json_response
            ];
        }
        return [
            'success' => false,
            'payment_id'=>$request->order_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $json_response
        ];
    }



    private function generate_html($data): string
    {
        return view('nafezly::html.paypal-credit', ['data' => $data])->render();
    }

}