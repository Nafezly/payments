<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class KoraPayPayment extends BaseController implements PaymentInterface
{
    public $korapay_public_key;
    public $korapay_secret_key;
    public $korapay_encryption_key;
    public $korapay_base_url;
    public $korapay_webhook_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.KORAPAY_CURRENCY', 'USD');
        $this->korapay_public_key = config('nafezly-payments.KORAPAY_PUBLIC_KEY');
        $this->korapay_secret_key = config('nafezly-payments.KORAPAY_SECRET_KEY');
        $this->korapay_encryption_key = config('nafezly-payments.KORAPAY_ENCRYPTION_KEY');
        $this->korapay_base_url = rtrim(config('nafezly-payments.KORAPAY_BASE_URL', 'https://api.korapay.com'), '/');
        $this->korapay_webhook_url = config('nafezly-payments.KORAPAY_WEBHOOK_URL');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount', 'user_email'], 'KoraPay');

        if ($this->payment_id == null) {
            $unique_id = 'kpy_' . uniqid() . rand(1000, 9999);
        } else {
            $unique_id = (string) $this->payment_id;
        }

        if (strlen($unique_id) < 8) {
            $unique_id = 'kpy_' . $unique_id . rand(1000, 9999);
        }

        $verifyUrl = route($this->verify_route_name, ['payment' => 'korapay', 'payment_id' => $unique_id]);

        $customerName = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        $payload = [
            'amount' => $this->toMinorUnits($this->amount),
            'currency' => strtoupper($this->currency),
            'reference' => $unique_id,
            'redirect_url' => $verifyUrl,
            'notification_url' => $this->korapay_webhook_url ?: $verifyUrl,
            'channels' => ['card'],
            'default_channel' => 'card',
            'customer' => [
                'email' => $this->user_email,
            ],
        ];

        if ($customerName !== '') {
            $payload['customer']['name'] = $customerName;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->korapay_secret_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->korapay_base_url . '/merchant/api/v1/charges/initialize', $payload)->json();

        if (data_get($response, 'status') === true && data_get($response, 'data.checkout_url')) {
            return [
                'payment_id' => data_get($response, 'data.reference', $unique_id),
                'redirect_url' => data_get($response, 'data.checkout_url'),
                'html' => '',
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => $unique_id,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => data_get($response, 'message', __('nafezly::messages.PAYMENT_FAILED')),
            'process_data' => $response,
        ];
    }

    public function verify(Request $request): array
    {
        $reference = $this->resolveKoraPayReference($request);

        if (!$reference) {
            return [
                'success' => false,
                'payment_id' => $request->route('payment_id') ?? $request->input('payment_id'),
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        if ($request->isMethod('post') && $request->input('event') === 'charge.success') {
            $webhookStatus = data_get($request->all(), 'data.status');
            if ($webhookStatus === 'success') {
                return [
                    'success' => true,
                    'payment_id' => data_get($request->all(), 'data.payment_reference', $reference),
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all(),
                ];
            }
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->korapay_secret_key,
            'Accept' => 'application/json',
        ])->get($this->korapay_base_url . '/merchant/api/v1/charges/' . urlencode($reference))->json();

        $status = data_get($response, 'data.status');

        if (data_get($response, 'status') === true && in_array($status, ['success'], true)) {
            return [
                'success' => true,
                'payment_id' => data_get($response, 'data.reference', $reference),
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }

        return [
            'success' => false,
            'payment_id' => data_get($response, 'data.reference', $reference),
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response ?? $request->all(),
        ];
    }

    protected function resolveKoraPayReference(Request $request): ?string
    {
        foreach ([
            $request->input('reference'),
            $request->route('payment_id'),
            $request->input('payment_id'),
            data_get($request->all(), 'data.reference'),
            data_get($request->all(), 'data.payment_reference'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
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
