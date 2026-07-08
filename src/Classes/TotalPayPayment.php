<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class TotalPayPayment extends BaseController implements PaymentInterface
{
    public $totalpay_checkout_url;
    public $totalpay_merchant_key;
    public $totalpay_password;
    public $totalpay_operation;
    public $totalpay_use_sha256_hash;
    public $totalpay_methods;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.TOTALPAY_CURRENCY', 'USD');
        $this->totalpay_checkout_url = rtrim((string) config('nafezly-payments.TOTALPAY_CHECKOUT_URL', ''), '/');
        $this->totalpay_merchant_key = config('nafezly-payments.TOTALPAY_MERCHANT_KEY');
        $this->totalpay_password = config('nafezly-payments.TOTALPAY_PASSWORD');
        $this->totalpay_operation = config('nafezly-payments.TOTALPAY_OPERATION', 'purchase');
        $this->totalpay_use_sha256_hash = filter_var(
            config('nafezly-payments.TOTALPAY_USE_SHA256_HASH', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->totalpay_methods = config('nafezly-payments.TOTALPAY_METHODS');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'TotalPay');

        if (!$this->totalpay_checkout_url || !$this->totalpay_merchant_key || !$this->totalpay_password) {
            return [
                'payment_id' => $this->payment_id,
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => 'TotalPay credentials are missing',
                'process_data' => [],
            ];
        }

        $orderNumber = $this->payment_id ?: ('tpy_' . uniqid() . rand(100000, 999999));
        $amount = $this->formatOrderAmount($this->amount);
        $currency = strtoupper($this->currency);
        $description = $this->resolveOrderDescription();

        $verifyUrl = route($this->verify_route_name, [
            'payment' => 'totalpay',
            'payment_id' => $orderNumber,
        ]);

        $payload = [
            'merchant_key' => $this->totalpay_merchant_key,
            'operation' => $this->resolveOperation(),
            'order' => [
                'number' => (string) $orderNumber,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
            ],
            'success_url' => $verifyUrl,
            'cancel_url' => $verifyUrl,
            'hash' => $this->buildAuthHash($orderNumber, $amount, $currency, $description),
        ];

        $methods = $this->resolveMethods();
        if (!empty($methods)) {
            $payload['methods'] = $methods;
        }

        $customer = $this->buildCustomerPayload();
        if (!empty($customer)) {
            $payload['customer'] = $customer;
        }

        if (is_array($this->source) && !empty($this->source['custom_data']) && is_array($this->source['custom_data'])) {
            $payload['custom_data'] = $this->source['custom_data'];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->totalpay_checkout_url . '/api/v1/session', $payload)->json();

        if (!empty($response['redirect_url'])) {
            return [
                'payment_id' => (string) $orderNumber,
                'redirect_url' => (string) $response['redirect_url'],
                'html' => '',
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => (string) $orderNumber,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => data_get($response, 'error_message', __('nafezly::messages.PAYMENT_FAILED')),
            'process_data' => $response,
        ];
    }

    public function verify(Request $request): array
    {
        $orderNumber = $this->resolveOrderNumber($request);

        if ($this->isCallbackRequest($request)) {
            if (!$this->isValidCallbackHash($request)) {
                return [
                    'success' => false,
                    'payment_id' => $orderNumber,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all(),
                ];
            }

            return [
                'success' => $this->isSuccessfulCallback($request),
                'payment_id' => $orderNumber ?: $request->input('order_number'),
                'message' => $this->isSuccessfulCallback($request)
                    ? __('nafezly::messages.PAYMENT_DONE')
                    : __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        if ($this->isReturnRequest($request)
            && $request->filled('order_amount')
            && $request->filled('order_currency')
            && $request->filled('order_description')
            && !$this->isValidReturnHash($request)) {
            return [
                'success' => false,
                'payment_id' => $orderNumber,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $statusResponse = $this->fetchStatusByOrderId($orderNumber);
        if (!$statusResponse && $request->filled('payment_id')) {
            $statusResponse = $this->fetchStatusByPaymentId((string) $request->input('payment_id'));
        }

        if (is_array($statusResponse)) {
            $orderNumber = data_get($statusResponse, 'order.number', $orderNumber);
            $paid = strtolower((string) data_get($statusResponse, 'status')) === 'settled';

            return [
                'success' => $paid,
                'payment_id' => $orderNumber ?: $request->route('payment_id'),
                'message' => $paid
                    ? __('nafezly::messages.PAYMENT_DONE')
                    : __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $statusResponse,
            ];
        }

        return [
            'success' => false,
            'payment_id' => $orderNumber ?: $request->route('payment_id'),
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all(),
        ];
    }

    public function resolveTotalPayLookupIds(Request $request, ?array $verifyResult = null): array
    {
        return array_values(array_unique(array_filter([
            $verifyResult['payment_id'] ?? null,
            $request->route('payment_id'),
            $request->input('payment_id'),
            $request->input('order_id'),
            $request->input('order_number'),
            $request->input('id'),
        ], fn ($value) => is_string($value) && trim($value) !== '')));
    }

    protected function resolveOperation(): string
    {
        if (is_array($this->source) && !empty($this->source['operation'])) {
            return (string) $this->source['operation'];
        }

        return (string) $this->totalpay_operation;
    }

    protected function resolveMethods(): array
    {
        if (is_array($this->source) && !empty($this->source['methods']) && is_array($this->source['methods'])) {
            return $this->source['methods'];
        }

        if (is_array($this->totalpay_methods)) {
            return $this->totalpay_methods;
        }

        if (is_string($this->totalpay_methods) && trim($this->totalpay_methods) !== '') {
            $decoded = json_decode($this->totalpay_methods, true);

            return is_array($decoded) ? $decoded : array_map('trim', explode(',', $this->totalpay_methods));
        }

        return [];
    }

    protected function resolveOrderDescription(): string
    {
        if (is_array($this->source) && !empty($this->source['description'])) {
            return substr((string) $this->source['description'], 0, 1024);
        }

        return 'Payment';
    }

    protected function buildCustomerPayload(): array
    {
        $name = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        $customer = [];

        if ($name !== '') {
            $customer['name'] = substr($name, 0, 255);
        }

        if ($this->user_email) {
            $customer['email'] = (string) $this->user_email;
        }

        return $customer;
    }

    protected function formatOrderAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function buildAuthHash(string $orderNumber, string $amount, string $currency, string $description): string
    {
        return $this->buildSignature(
            $orderNumber . $amount . $currency . $description . $this->totalpay_password
        );
    }

    protected function buildCallbackHash(string $paymentPublicId, string $orderNumber, string $amount, string $currency, string $description): string
    {
        return $this->buildSignature(
            $paymentPublicId . $orderNumber . $amount . $currency . $description . $this->totalpay_password
        );
    }

    protected function buildStatusHashByPaymentId(string $paymentId): string
    {
        return $this->buildSignature($paymentId . $this->totalpay_password);
    }

    protected function buildStatusHashByOrderId(string $orderId): string
    {
        return $this->buildSignature($orderId . $this->totalpay_password);
    }

    protected function buildSignature(string $payload): string
    {
        $normalized = strtoupper($payload);

        if ($this->totalpay_use_sha256_hash) {
            return sha1(hash('sha256', $normalized));
        }

        return sha1(md5($normalized));
    }

    protected function isCallbackRequest(Request $request): bool
    {
        return $request->filled('order_status')
            && $request->filled('type')
            && $request->filled('status')
            && $request->filled('hash');
    }

    protected function isReturnRequest(Request $request): bool
    {
        return $request->filled('hash')
            && ($request->filled('payment_id') || $request->filled('order_id'));
    }

    protected function isSuccessfulCallback(Request $request): bool
    {
        return strtolower((string) $request->input('order_status')) === 'settled'
            && strtolower((string) $request->input('type')) === 'sale'
            && strtolower((string) $request->input('status')) === 'success';
    }

    protected function isValidCallbackHash(Request $request): bool
    {
        $provided = strtolower((string) $request->input('hash', ''));
        if ($provided === '') {
            return false;
        }

        $expected = strtolower($this->buildCallbackHash(
            (string) $request->input('id', ''),
            (string) $request->input('order_number', ''),
            $this->normalizeCallbackAmount($request->input('order_amount')),
            (string) $request->input('order_currency', ''),
            (string) $request->input('order_description', '')
        ));

        return hash_equals($expected, $provided);
    }

    protected function isValidReturnHash(Request $request): bool
    {
        $provided = strtolower((string) $request->input('hash', ''));
        if ($provided === '') {
            return false;
        }

        $orderNumber = (string) ($request->input('order_id') ?: $request->route('payment_id') ?: $request->input('order_number'));
        $amount = $this->normalizeCallbackAmount(
            $request->input('order_amount') ?? data_get($request->all(), 'amount')
        );
        $currency = strtoupper((string) ($request->input('order_currency') ?? $this->currency));
        $description = (string) ($request->input('order_description') ?? $this->resolveOrderDescription());

        $expected = strtolower($this->buildCallbackHash(
            (string) $request->input('payment_id', ''),
            $orderNumber,
            $amount,
            $currency,
            $description
        ));

        return hash_equals($expected, $provided);
    }

    protected function normalizeCallbackAmount($amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return $this->formatOrderAmount($amount);
    }

    protected function resolveOrderNumber(Request $request): ?string
    {
        foreach ([
            $request->route('payment_id'),
            $request->input('order_id'),
            $request->input('order_number'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function fetchStatusByOrderId(?string $orderId): ?array
    {
        if (!$orderId || !$this->totalpay_checkout_url || !$this->totalpay_merchant_key || !$this->totalpay_password) {
            return null;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->totalpay_checkout_url . '/api/v1/payment/status', [
            'merchant_key' => $this->totalpay_merchant_key,
            'order_id' => $orderId,
            'hash' => $this->buildStatusHashByOrderId($orderId),
        ])->json();

        return is_array($response) && !empty($response['status']) ? $response : null;
    }

    protected function fetchStatusByPaymentId(string $paymentId): ?array
    {
        if (!$this->totalpay_checkout_url || !$this->totalpay_merchant_key || !$this->totalpay_password) {
            return null;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post($this->totalpay_checkout_url . '/api/v1/payment/status', [
            'merchant_key' => $this->totalpay_merchant_key,
            'payment_id' => $paymentId,
            'hash' => $this->buildStatusHashByPaymentId($paymentId),
        ])->json();

        return is_array($response) && !empty($response['status']) ? $response : null;
    }
}
