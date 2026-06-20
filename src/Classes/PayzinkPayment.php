<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class PayzinkPayment extends BaseController implements PaymentInterface
{
    public $payzink_publishable_key;
    public $payzink_secret_key;
    public $payzink_base_url;
    public $payzink_action;
    public $payzink_webhook_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.PAYZINK_CURRENCY', 'USD');
        $this->payzink_publishable_key = config('nafezly-payments.PAYZINK_PUBLISHABLE_KEY');
        $this->payzink_secret_key = config('nafezly-payments.PAYZINK_SECRET_KEY');
        $this->payzink_base_url = rtrim(config('nafezly-payments.PAYZINK_BASE_URL', 'https://merchant-dev.payzink.com'), '/');
        $this->payzink_action = config('nafezly-payments.PAYZINK_ACTION', 'PURCHASE');
        $this->payzink_webhook_url = config('nafezly-payments.PAYZINK_WEBHOOK_URL');
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
     * @return array
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'Payzink');

        if ($this->payment_id == null) {
            $unique_id = uniqid() . rand(100000, 999999);
        } else {
            $unique_id = $this->payment_id;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
            ];
        }

        $verifyUrl = route($this->verify_route_name, ['payment' => 'payzink', 'payment_id' => $unique_id]);

        $payload = [
            'order' => [
                'action' => $this->payzink_action,
                'amount' => [
                    'currencyCode' => strtoupper($this->currency),
                    'value' => $this->toMinorUnits($this->amount),
                ],
            ],
            'extra' => [
                'orderId' => $unique_id,
            ],
            '_links' => [
                'notificationUrl' => $this->payzink_webhook_url ?: $verifyUrl,
            ],
        ];

        if ($this->user_email) {
            $payload['customer'] = ['email' => $this->user_email];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->payzink_base_url . '/api/v1/payment/hosted', $payload)->json();

        if (isset($response['result']['reference'], $response['result']['_links']['payment']['href'])) {
            $reference = $response['result']['reference'];

            Cache::put('payzink_reference_' . $unique_id, $reference, now()->addHours(48));

            return [
                'payment_id' => $reference,
                'redirect_url' => $response['result']['_links']['payment']['href'],
                'html' => '',
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => $unique_id,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => $response['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response,
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $internalId = $request->route('payment_id') ?? $request->input('payment_id');
        $reference = $request->input('ref')
            ?? $request->input('reference')
            ?? $internalId;

        if (!$reference && $internalId) {
            $reference = Cache::get('payzink_reference_' . $internalId);
        }

        if (!$reference) {
            return [
                'success' => false,
                'payment_id' => $internalId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'payment_id' => $reference,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->get($this->payzink_base_url . '/api/v1/payment/transaction/' . $reference . '/info')->json();

        $state = $response['result']['state'] ?? null;

        if (in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true)) {
            return [
                'success' => true,
                'payment_id' => $reference,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }

        return [
            'success' => false,
            'payment_id' => $reference,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response ?? $request->all(),
        ];
    }

    protected function getAccessToken(): ?string
    {
        $cacheKey = 'payzink_access_token_' . md5($this->payzink_publishable_key . $this->payzink_secret_key);

        return Cache::remember($cacheKey, 240, function () {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->payzink_base_url . '/api/v1/auth/access-token', [
                'publishableKey' => $this->payzink_publishable_key,
                'secretKey' => $this->payzink_secret_key,
            ])->json();

            return $response['result']['accessToken'] ?? null;
        });
    }

    protected function toMinorUnits($amount): int
    {
        $currency = strtoupper($this->currency);

        if (in_array($currency, ['BHD', 'KWD', 'OMR', 'JOD'], true)) {
            return (int) round($amount * 1000);
        }

        if (in_array($currency, ['JPY'], true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
