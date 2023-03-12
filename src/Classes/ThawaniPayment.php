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

class ThawaniPayment extends BaseController implements PaymentInterface
{
    private $thawani_url;
    private $thawani_api_key;
    private $thawani_publishable_key;
    private $verify_route_name;

    public function __construct()
    {
        $this->thawani_url = config('nafezly-payments.THAWANI_URL');
        $this->thawani_api_key = config('nafezly-payments.THAWANI_API_KEY');
        $this->thawani_publishable_key = config('nafezly-payments.THAWANI_PUBLISHABLE_KEY');
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
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'Thawani');
        $unique_id = uniqid();
        $response = Http::withHeaders([
            'Content-Type' => "application/json",
            "Thawani-Api-Key" => $this->thawani_api_key
        ])->post($this->thawani_url . '/api/v1/checkout/session', [
            "client_reference_id" => $unique_id,
            "products" => [
                [
                    "name" => "credit",
                    "unit_amount" => $this->amount * 1000,
                    "quantity" => 1
                ],
            ],
            "success_url" => route($this->verify_route_name, ['payment' => "thawani", 'payment_id' => $unique_id]),
            "cancel_url" => route($this->verify_route_name, ['payment' => "thawani", 'payment_id' => $unique_id]),
            "metadata" => [
                "customer" => $this->user_first_name.' '.$this->user_last_name,
                "order_id" => $unique_id,
                "phone" => $this->user_phone
            ]
        ])->json();
        Cache::forever($unique_id, $response['data']['session_id']);

        return [
            'payment_id'=>$response['data']['session_id'],
            'html' => "",
            'redirect_url'=>$this->thawani_url . '/pay/' . $response['data']['session_id'] . "?key=" . $this->thawani_publishable_key
        ];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = $request->payment_id!=null?$request->payment_id:Cache::get($request['payment_id']);
        Cache::forget($request['payment_id']);
        $response = Http::withHeaders([
            'content-type' => 'application/json',
            'Thawani-Api-Key' => $this->thawani_api_key
        ])->get($this->thawani_url . '/api/v1/checkout/session/' . $payment_id)->json();

        if ($response['data']['payment_status'] == "paid") {
            return [
                'success' => true,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }
    }
}