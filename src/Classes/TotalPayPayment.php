<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class TotalPayPayment extends BaseController implements PaymentInterface
{
    public $totalpay_merchant_key;
    public $totalpay_password;
    public $totalpay_checkout_url;
    public $totalpay_operation;
    public $totalpay_webhook_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.TOTALPAY_CURRENCY', 'AED');
        $this->totalpay_merchant_key = config('nafezly-payments.TOTALPAY_MERCHANT_KEY');
        $this->totalpay_password = config('nafezly-payments.TOTALPAY_PASSWORD');
        $this->totalpay_checkout_url = rtrim(
            config('nafezly-payments.TOTALPAY_CHECKOUT_URL', 'https://checkout.totalpay.global'),
            '/'
        );
        $this->totalpay_operation = config('nafezly-payments.TOTALPAY_OPERATION', 'purchase');
        $this->totalpay_webhook_url = config('nafezly-payments.TOTALPAY_WEBHOOK_URL');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount', 'user_email'], 'TotalPay');

        if (!$this->totalpay_merchant_key || !$this->totalpay_password) {
            return [
                'payment_id' => (string) ($this->payment_id ?: ''),
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => 'TotalPay credentials are missing',
                'process_data' => [],
            ];
        }

        if ($this->payment_id == null) {
            $unique_id = 'tpy_' . uniqid() . rand(1000, 9999);
        } else {
            $unique_id = (string) $this->payment_id;
        }

        $currency = strtoupper($this->currency ?? 'AED');
        $amountFormatted = $this->formatAmount($this->amount);
        $description = data_get($this->source, 'description', 'Payment');
        $verifyUrl = route($this->verify_route_name, ['payment' => 'totalpay', 'payment_id' => $unique_id]);
        $notificationUrl = $this->totalpay_webhook_url ?: $verifyUrl;

        $customerName = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $billingAddress = data_get($this->source, 'billing_address', []);
        if (!is_array($billingAddress)) {
            $billingAddress = [];
        }

        $billingAddress = array_merge([
            'country' => 'AE',
            'city' => 'Dubai',
            'address' => 'N/A',
            'zip' => '00000',
            'phone' => $this->normalizePhone($this->user_phone),
        ], $billingAddress);

        $payload = [
            'merchant_key' => $this->totalpay_merchant_key,
            'operation' => data_get($this->source, 'operation', $this->totalpay_operation),
            'methods' => ['card'],
            'order' => [
                'number' => $unique_id,
                'amount' => $amountFormatted,
                'currency' => $currency,
                'description' => (string) $description,
            ],
            'success_url' => $verifyUrl,
            'cancel_url' => $verifyUrl,
            'url_target' => '_self',
            'customer' => [
                'name' => $customerName,
                'email' => $this->user_email,
            ],
            'billing_address' => $billingAddress,
            'hash' => $this->sessionHash($unique_id, $amountFormatted, $currency, (string) $description),
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->totalpay_checkout_url . '/api/v1/session', $payload)->json();

        if (data_get($response, 'redirect_url')) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => data_get($response, 'redirect_url'),
                'html' => '',
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => $unique_id,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => data_get($response, 'message', data_get($response, 'error', __('nafezly::messages.PAYMENT_FAILED'))),
            'process_data' => $response,
        ];
    }

    public function verify(Request $request): array
    {
        $orderNumber = $this->resolveOrderNumber($request);

        if ($request->isMethod('post') && $this->isWebhookPayload($request)) {
            return $this->verifyWebhook($request, $orderNumber);
        }

        if (!$orderNumber) {
            return [
                'success' => false,
                'payment_id' => $request->route('payment_id') ?? $request->input('payment_id'),
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        return $this->verifyByStatusApi($request, $orderNumber);
    }

    protected function verifyWebhook(Request $request, ?string $orderNumber): array
    {
        $paymentId = (string) ($request->input('id') ?? $request->input('payment_id') ?? '');
        $orderNumber = $orderNumber ?: (string) ($request->input('order_number') ?? '');
        $orderAmount = (string) ($request->input('order_amount') ?? '');
        $orderCurrency = (string) ($request->input('order_currency') ?? '');
        $orderDescription = (string) ($request->input('order_description') ?? '');
        $receivedHash = (string) ($request->input('hash') ?? '');

        if (
            $paymentId !== ''
            && $orderNumber !== ''
            && $receivedHash !== ''
            && !$this->hashEquals(
                $receivedHash,
                $this->callbackHash($paymentId, $orderNumber, $orderAmount, $orderCurrency, $orderDescription)
            )
        ) {
            return [
                'success' => false,
                'payment_id' => $orderNumber,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $isSuccessful = $request->input('order_status') === 'settled'
            && $request->input('status') === 'success'
            && $request->input('type') === 'sale';

        return [
            'success' => $isSuccessful,
            'payment_id' => $orderNumber ?: $paymentId,
            'message' => $isSuccessful
                ? __('nafezly::messages.PAYMENT_DONE')
                : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all(),
        ];
    }

    protected function verifyByStatusApi(Request $request, string $orderNumber): array
    {
        $gatewayPaymentId = $this->resolveGatewayPaymentId($request);

        if ($gatewayPaymentId !== null) {
            $response = $this->fetchStatusByPaymentId($gatewayPaymentId);
        } else {
            $response = $this->fetchStatusByOrderId($orderNumber);
        }

        $status = data_get($response, 'status');
        $resolvedOrderNumber = data_get($response, 'order.number', $orderNumber);

        if ($status === 'settled') {
            return [
                'success' => true,
                'payment_id' => $resolvedOrderNumber,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }

        return [
            'success' => false,
            'payment_id' => $resolvedOrderNumber,
            'message' => data_get($response, 'reason', __('nafezly::messages.PAYMENT_FAILED')),
            'process_data' => $response ?: $request->all(),
        ];
    }

    protected function fetchStatusByPaymentId(string $paymentId): array
    {
        $payload = [
            'merchant_key' => $this->totalpay_merchant_key,
            'payment_id' => $paymentId,
            'hash' => $this->statusHashByPaymentId($paymentId),
        ];

        return $this->postStatus($payload);
    }

    protected function fetchStatusByOrderId(string $orderId): array
    {
        $payload = [
            'merchant_key' => $this->totalpay_merchant_key,
            'order_id' => $orderId,
            'hash' => $this->statusHashByOrderId($orderId),
        ];

        return $this->postStatus($payload);
    }

    protected function postStatus(array $payload): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->totalpay_checkout_url . '/api/v1/payment/status', $payload);

        return $response->json() ?? [];
    }

    protected function resolveOrderNumber(Request $request): ?string
    {
        foreach ([
            $request->input('order_number'),
            $request->input('order_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $paymentId = $request->input('payment_id');
        if (is_string($paymentId) && trim($paymentId) !== '' && !$this->looksLikeGatewayPaymentId($paymentId)) {
            return trim($paymentId);
        }

        return null;
    }

    protected function resolveGatewayPaymentId(Request $request): ?string
    {
        foreach ([
            $request->input('trans_id'),
            $request->input('id'),
            $request->input('payment_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && $this->looksLikeGatewayPaymentId($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function looksLikeGatewayPaymentId(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    protected function isWebhookPayload(Request $request): bool
    {
        return $request->filled('order_status')
            || $request->filled('type')
            || $request->filled('id');
    }

    protected function sessionHash(string $orderNumber, string $amount, string $currency, string $description): string
    {
        return $this->makeHash($orderNumber . $amount . $currency . $description . $this->totalpay_password);
    }

    protected function statusHashByPaymentId(string $paymentId): string
    {
        return $this->makeHash($paymentId . $this->totalpay_password);
    }

    protected function statusHashByOrderId(string $orderId): string
    {
        return $this->makeHash($orderId . $this->totalpay_password);
    }

    protected function callbackHash(
        string $paymentId,
        string $orderNumber,
        string $amount,
        string $currency,
        string $description
    ): string {
        return $this->makeHash($paymentId . $orderNumber . $amount . $currency . $description . $this->totalpay_password);
    }

    protected function makeHash(string $payload): string
    {
        return sha1(md5(strtoupper($payload)));
    }

    protected function hashEquals(string $expected, string $actual): bool
    {
        return hash_equals(strtolower($expected), strtolower($actual));
    }

    protected function formatAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return '500000000';
        }

        return $digits;
    }
}
