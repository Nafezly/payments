<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;


class HeleketPayment extends BaseController implements PaymentInterface
{
    public const API_BASE = 'https://api.heleket.com';

    public $heleket_merchant_id;
    public $heleket_api_key;
    public $network;
    public $verify_route_name;

    public function __construct()
    {
        $this->heleket_merchant_id = config('nafezly-payments.HELEKET_MERCHANT_ID');
        $this->heleket_api_key = config('nafezly-payments.HELEKET_API_KEY');
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
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'HELEKET');

        if ($this->payment_id == null) {
            $unique_id = uniqid() . rand(100000, 999999);
        } else {
            $unique_id = $this->payment_id;
        }

        try {
            $body = [
                'amount' => "$this->amount",
                'currency' => $this->source ?? 'USD',
                'order_id' => $unique_id,
                'url_return' => route($this->verify_route_name, ['payment' => 'heleket']),
                'url_success' => route($this->verify_route_name, ['payment' => 'heleket']),
                'url_callback' => route($this->verify_route_name, ['payment' => 'heleket']),
                'to_currency' => $this->currency ?? null,
                'network' => $this->network ?? null,
            ];
            $response = Http::withHeaders([
                'merchant' => "$this->heleket_merchant_id",
                'sign' => md5(base64_encode(json_encode($body, JSON_UNESCAPED_UNICODE)) . $this->heleket_api_key),
            ])
                ->post(self::API_BASE . '/v1/payment', $body)->json();
            if (isset($response['result']['url'])) {
                return [
                    'payment_id' => $unique_id,
                    'html' => $response,
                    'redirect_url' => $response['result']['url'],
                ];
            } else {
                return [
                    'payment_id' => $unique_id,
                    'html' => $response,
                    'redirect_url' => '',
                ];
            }
        } catch (\Exception $e) {
            return [
                'payment_id' => $unique_id,
                'html' => $e,
                'redirect_url' => '',
            ];
        }
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        $body = ['order_id' => $request['order_id']];
        $response = Http::withHeaders([
            'merchant' => $this->heleket_merchant_id,
            'sign' => md5(base64_encode(json_encode($body, JSON_UNESCAPED_UNICODE)) . $this->heleket_api_key),
        ])
            ->post(self::API_BASE . '/v1/payment/info', $body)->json();

        if (isset($response['result']['status']) && in_array($response['result']['status'], ['paid', 'paid_over'])) {
            return [
                'success' => true,
                'payment_id' => $request['order_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }
        return [
            'success' => false,
            'payment_id' => $request['order_id'],
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response,
        ];
    }

    public function setNetwork($network)
    {
        $this->network = $network;
        return $this;
    }
}
