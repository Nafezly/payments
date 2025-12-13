<?php

namespace Nafezly\Payments\Classes;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Nafezly\Payments\Interfaces\PaymentInterface;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use Nafezly\Payments\Classes\BaseController;

class PayPalPayment extends BaseController implements PaymentInterface
{
    public $paypal_client_id;
    public $paypal_secret;
    public $verify_route_name;
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
     * Get language in PayPal format (en-US, ar-SA)
     *
     * @return string
     */
    protected function getPayPalLocale()
    {
        $localeMap = [
            'ar' => 'ar-SA',
            'en' => 'en-US'
        ];
        
        // If language is set, use it; otherwise default to en-US
        if ($this->language && isset($localeMap[$this->language])) {
            return $localeMap[$this->language];
        }
        
        return 'en-US';
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

        if($this->paypal_mode=="live")
            $environment = new ProductionEnvironment($this->paypal_client_id, $this->paypal_secret);
        else
            $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);


        if($this->payment_id==null)
            $order_id = uniqid().rand(100000,999999);
        else
            $order_id = $this->payment_id;

        
        $client = new PayPalHttpClient($environment);

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        
        // Get PayPal locale format (en-US, ar-SA)
        $locale = $this->getPayPalLocale();
        
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => $order_id,
                "amount" => [
                    "value" => $this->amount,
                    "currency_code" => $this->currency
                ]
            ]],
            "application_context" => [
                "locale" => $locale,
                "cancel_url" => route($this->verify_route_name, ['payment' => "paypal"]),
                "return_url" => route($this->verify_route_name, ['payment' => "paypal"])
            ]
        ];

        try {
            $response = json_decode(json_encode($client->execute($request)), true);
            return [
                'payment_id'=>$response['result']['id'],
                'html' => "",
                'redirect_url'=>collect($response['result']['links'])->where('rel', 'approve')->firstOrFail()['href']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $e
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {

        if($this->paypal_mode=="live")
            $environment = new ProductionEnvironment($this->paypal_client_id, $this->paypal_secret);
        else
            $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);
            
        $client = new PayPalHttpClient($environment);

        try {
            $response = $client->execute(new OrdersCaptureRequest($request['token']) );
            $result = json_decode(json_encode($response), true);
            if ($result['result']['status'] == "COMPLETED" && $result['statusCode']==201) {
                return [
                    'success' => true,
                    'payment_id'=>$request['token'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $result
                ];

            } else {
                return [
                    'success' => false,
                    'payment_id'=>$request['token'],
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $result
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_id'=>$request['token'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $e
            ];
        }
    }
}