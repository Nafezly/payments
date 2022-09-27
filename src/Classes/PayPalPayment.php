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
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;

class PayPalPayment implements PaymentInterface
{

    private $paypal_client_id;
    private $paypal_secret;
    private $verify_route_name;


    public function __construct()
    {
        $this->paypal_client_id = config('nafezly-payments.PAYPAL_CLIENT_ID');
        $this->paypal_secret = config('nafezly-payments.PAYPAL_SECRET');
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
     * @return array|Application|RedirectResponse|Redirector
     */
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);
        $client = new PayPalHttpClient($environment);

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => uniqid(),
                "amount" => [
                    "value" => $amount,
                    "currency_code" => config('nafezly-payments.PAYPAL_CURRENCY')
                ]
            ]],
            "application_context" => [
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
                'message' => __('messages.PAYMENT_FAILED'),
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
        $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);
        $client = new PayPalHttpClient($environment);

        try {
            $response = $client->execute(new OrdersGetRequest($request['token']));
            $result = json_decode(json_encode($response), true);
            if ($result['result']['intent'] == "CAPTURE" && $result['result']['status'] == "APPROVED") {
                return [
                    'success' => true,
                    'message' => __('messages.PAYMENT_DONE'),
                    'process_data' => $result
                ];

            } else {
                return [
                    'success' => false,
                    'message' => __('messages.PAYMENT_FAILED'),
                    'process_data' => $result
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED'),
                'process_data' => $e
            ];
        }
    }
}