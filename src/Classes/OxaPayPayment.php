<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class OxaPayPayment extends BaseController implements PaymentInterface
{
    public $oxapay_base_url;
    public $oxapay_merchant_api_key;
    public $verify_route_name;
    public $app_name;
    public $payment_type;
    public $pay_currency;
    public $network;
    public $lifetime;
    public $fee_paid_by_payer;
    public $under_paid_coverage;
    public $to_currency;
    public $auto_withdrawal;
    public $mixed_payment;
    public $callback_url;
    public $return_url;
    public $thanks_message;
    public $description;
    public $sandbox;
    public $render_payment_details;

    public function __construct()
    {
        $this->oxapay_base_url = rtrim((string) config('nafezly-payments.OXAPAY_BASE_URL', 'https://api.oxapay.com/v1'), '/');
        $this->oxapay_merchant_api_key = config('nafezly-payments.OXAPAY_MERCHANT_API_KEY');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->app_name = config('nafezly-payments.APP_NAME', 'Payment');
        $this->currency = strtoupper((string) config('nafezly-payments.OXAPAY_CURRENCY', 'USD'));
        $this->payment_type = $this->normalizePaymentType(config('nafezly-payments.OXAPAY_PAYMENT_TYPE', 'invoice'));
        $this->pay_currency = $this->normalizeSymbol(config('nafezly-payments.OXAPAY_PAY_CURRENCY'));
        $this->network = config('nafezly-payments.OXAPAY_NETWORK');
        $this->lifetime = $this->castInteger(config('nafezly-payments.OXAPAY_LIFETIME', 60));
        $this->fee_paid_by_payer = $this->castNullableInteger(config('nafezly-payments.OXAPAY_FEE_PAID_BY_PAYER'));
        $this->under_paid_coverage = $this->castNullableNumber(config('nafezly-payments.OXAPAY_UNDER_PAID_COVERAGE'));
        $this->to_currency = $this->normalizeSymbol(config('nafezly-payments.OXAPAY_TO_CURRENCY'));
        $this->auto_withdrawal = $this->castNullableBoolean(config('nafezly-payments.OXAPAY_AUTO_WITHDRAWAL'));
        $this->mixed_payment = $this->castNullableBoolean(config('nafezly-payments.OXAPAY_MIXED_PAYMENT'));
        $this->callback_url = config('nafezly-payments.OXAPAY_CALLBACK_URL');
        $this->return_url = config('nafezly-payments.OXAPAY_RETURN_URL');
        $this->thanks_message = config('nafezly-payments.OXAPAY_THANKS_MESSAGE');
        $this->description = config('nafezly-payments.OXAPAY_DESCRIPTION');
        $this->sandbox = $this->castNullableBoolean(config('nafezly-payments.OXAPAY_SANDBOX'));
        $this->render_payment_details = $this->castNullableBoolean(config('nafezly-payments.OXAPAY_RENDER_PAYMENT_DETAILS', true));
    }

    public function setPaymentType($value)
    {
        $this->payment_type = $this->normalizePaymentType($value);
        return $this;
    }

    public function setPayCurrency($value)
    {
        $this->pay_currency = $this->normalizeSymbol($value);
        return $this;
    }

    public function setNetwork($value)
    {
        $this->network = $value;
        return $this;
    }

    public function setLifetime($value)
    {
        $this->lifetime = $this->castInteger($value);
        return $this;
    }

    public function setFeePaidByPayer($value = 1)
    {
        $this->fee_paid_by_payer = $this->castNullableInteger($value);
        return $this;
    }

    public function setUnderPaidCoverage($value)
    {
        $this->under_paid_coverage = $this->castNullableNumber($value);
        return $this;
    }

    public function setToCurrency($value)
    {
        $this->to_currency = $this->normalizeSymbol($value);
        return $this;
    }

    public function setAutoWithdrawal($value = true)
    {
        $this->auto_withdrawal = $this->castNullableBoolean($value);
        return $this;
    }

    public function setMixedPayment($value = true)
    {
        $this->mixed_payment = $this->castNullableBoolean($value);
        return $this;
    }

    public function setCallbackUrl($value)
    {
        $this->callback_url = $value;
        return $this;
    }

    public function setReturnUrl($value)
    {
        $this->return_url = $value;
        return $this;
    }

    public function setThanksMessage($value)
    {
        $this->thanks_message = $value;
        return $this;
    }

    public function setDescription($value)
    {
        $this->description = $value;
        return $this;
    }

    public function setSandbox($value = true)
    {
        $this->sandbox = $this->castNullableBoolean($value);
        return $this;
    }

    public function setRenderPaymentDetails($value = true)
    {
        $this->render_payment_details = $this->castNullableBoolean($value);
        return $this;
    }

    public function payInvoice($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null): array
    {
        $this->setPaymentType('invoice');
        return $this->pay($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone);
    }

    public function payWhiteLabel($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null): array
    {
        $this->setPaymentType('white_label');
        return $this->pay($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone);
    }

    public function payStaticAddress($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null): array
    {
        $this->setPaymentType('static_address');
        return $this->pay($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone);
    }

    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);

        if (!empty($source) && in_array($this->normalizePaymentType($source), ['invoice', 'white_label', 'static_address'], true)) {
            $this->setPaymentType($source);
        }

        $payment_type = $this->normalizePaymentType($this->payment_type);
        $reference_id = $this->payment_id ?: uniqid() . rand(100000, 999999);

        $response = $this->createPayment($payment_type, $reference_id);
        $body = $response['body'];
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        $track_id = $data['track_id'] ?? null;

        if (!empty($track_id)) {
            $this->storeTrackMapping($reference_id, $track_id);
        }

        return [
            'success' => $response['ok'] && !empty($track_id),
            'payment_id' => $reference_id,
            'track_id' => $track_id,
            'html' => $this->buildPaymentHtml($payment_type, $data),
            'redirect_url' => $payment_type === 'invoice' ? ($data['payment_url'] ?? '') : '',
            'message' => $body['message'] ?? '',
            'process_data' => !empty($data) ? $data : $body,
        ];
    }

    public function verify(Request $request)
    {
        $raw_body = $request->getContent();
        $payload = $this->parseWebhookPayload($raw_body);

        if ($this->hasWebhookSignature($request) && !$this->verifyWebhookSignature($request)) {
            return [
                'success' => false,
                'payment_id' => $request->get('payment_id'),
                'track_id' => $payload['track_id'] ?? null,
                'message' => 'Invalid HMAC signature',
                'process_data' => !empty($payload) ? $payload : $request->all(),
            ];
        }

        $track_id = $this->resolveTrackId($request, $payload);
        if (!empty($track_id)) {
            $payment_information = $this->paymentInformation($track_id);
            $payment_data = isset($payment_information['data']) && is_array($payment_information['data']) ? $payment_information['data'] : [];

            if (!empty($payment_data)) {
                return $this->buildVerifyResponse(
                    $payment_data,
                    $this->isSuccessfulStatus($payment_data['status'] ?? null),
                    $payment_information
                );
            }
        }

        if (!empty($payload)) {
            return $this->buildVerifyResponse(
                $payload,
                $this->isSuccessfulStatus($payload['status'] ?? null),
                $payload
            );
        }

        return [
            'success' => false,
            'payment_id' => $request->get('payment_id'),
            'track_id' => null,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all(),
        ];
    }

    public function paymentInformation($track_id): array
    {
        $this->assertRequiredValue($track_id, 'track_id', 'OXAPAY');
        $response = $this->request('get', '/payment/' . $track_id);
        return $response['body'];
    }

    public function paymentHistory(array $filters = []): array
    {
        $response = $this->request('get', '/payment', [], $this->cleanQueryFilters($filters));
        return $response['body'];
    }

    public function acceptedCurrencies(): array
    {
        $response = $this->request('get', '/payment/accepted-currencies');
        return $response['body'];
    }

    public function supportedCurrencies(): array
    {
        $response = $this->request('get', '/common/currencies');
        return $response['body'];
    }

    public function supportedNetworks(): array
    {
        $response = $this->request('get', '/common/networks');
        return $response['body'];
    }

    public function staticAddressList(array $filters = []): array
    {
        $response = $this->request('get', '/payment/static-address', [], $this->cleanQueryFilters($filters));
        return $response['body'];
    }

    public function revokeStaticAddress($address): array
    {
        $this->assertRequiredValue($address, 'address', 'OXAPAY');
        $response = $this->request('post', '/payment/static-address/revoke', [
            'address' => $address,
        ]);
        return $response['body'];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $received_hmac = (string) $request->header('HMAC', '');
        $raw_body = $request->getContent();

        if ($received_hmac === '' || $raw_body === '') {
            return false;
        }

        $calculated_hmac = hash_hmac('sha512', $raw_body, (string) $this->oxapay_merchant_api_key);

        if (function_exists('hash_equals')) {
            return hash_equals($calculated_hmac, $received_hmac);
        }

        return $calculated_hmac === $received_hmac;
    }

    private function createPayment($payment_type, $reference_id): array
    {
        $this->assertRequiredValue($this->oxapay_merchant_api_key, 'merchant_api_key', 'OXAPAY');

        if ($payment_type === 'white_label') {
            $this->assertRequiredValue($this->amount, 'amount', 'OXAPAY');
            $this->assertRequiredValue($this->pay_currency, 'pay_currency', 'OXAPAY');
            return $this->request('post', '/payment/white-label', $this->buildWhiteLabelPayload($reference_id));
        }

        if ($payment_type === 'static_address') {
            $this->assertRequiredValue($this->network, 'network', 'OXAPAY');
            return $this->request('post', '/payment/static-address', $this->buildStaticAddressPayload($reference_id));
        }

        $this->assertRequiredValue($this->amount, 'amount', 'OXAPAY');
        return $this->request('post', '/payment/invoice', $this->buildInvoicePayload($reference_id));
    }

    private function buildInvoicePayload($reference_id): array
    {
        $payload = [
            'amount' => $this->castNullableNumber($this->amount),
            'currency' => $this->normalizeSymbol($this->currency),
            'lifetime' => $this->castInteger($this->lifetime),
            'fee_paid_by_payer' => $this->castNullableInteger($this->fee_paid_by_payer),
            'under_paid_coverage' => $this->castNullableNumber($this->under_paid_coverage),
            'to_currency' => $this->normalizeSymbol($this->to_currency),
            'auto_withdrawal' => $this->castNullableBoolean($this->auto_withdrawal),
            'mixed_payment' => $this->castNullableBoolean($this->mixed_payment),
            'callback_url' => $this->resolveCallbackUrl($reference_id),
            'return_url' => $this->resolveReturnUrl($reference_id),
            'email' => $this->user_email,
            'order_id' => $reference_id,
            'thanks_message' => $this->thanks_message,
            'description' => $this->resolveDescription($reference_id),
            'sandbox' => $this->castNullableBoolean($this->sandbox),
        ];

        return $this->removeNullValues($payload);
    }

    private function buildWhiteLabelPayload($reference_id): array
    {
        $payload = [
            'pay_currency' => $this->normalizeSymbol($this->pay_currency),
            'amount' => $this->castNullableNumber($this->amount),
            'currency' => $this->normalizeSymbol($this->currency),
            'network' => $this->network,
            'lifetime' => $this->castInteger($this->lifetime),
            'fee_paid_by_payer' => $this->castNullableInteger($this->fee_paid_by_payer),
            'under_paid_coverage' => $this->castNullableNumber($this->under_paid_coverage),
            'to_currency' => $this->normalizeSymbol($this->to_currency),
            'auto_withdrawal' => $this->castNullableBoolean($this->auto_withdrawal),
            'callback_url' => $this->resolveCallbackUrl($reference_id),
            'email' => $this->user_email,
            'order_id' => $reference_id,
            'description' => $this->resolveDescription($reference_id),
        ];

        return $this->removeNullValues($payload);
    }

    private function buildStaticAddressPayload($reference_id): array
    {
        $payload = [
            'network' => $this->network,
            'to_currency' => $this->normalizeSymbol($this->to_currency),
            'auto_withdrawal' => $this->castNullableBoolean($this->auto_withdrawal),
            'callback_url' => $this->resolveCallbackUrl($reference_id),
            'email' => $this->user_email,
            'order_id' => $reference_id,
            'description' => $this->resolveDescription($reference_id),
        ];

        return $this->removeNullValues($payload);
    }

    private function buildPaymentHtml($payment_type, array $data)
    {
        if ($payment_type === 'invoice' || !$this->render_payment_details || empty($data)) {
            return '';
        }

        return view('nafezly::html.oxapay', [
            'type' => $payment_type,
            'language' => $this->language,
            'payment_data' => $data,
        ])->render();
    }

    private function buildVerifyResponse(array $payment_data, $is_success, $process_data): array
    {
        $track_id = $payment_data['track_id'] ?? null;
        $payment_id = $payment_data['order_id'] ?? null;

        if (empty($payment_id) && !empty($track_id)) {
            $payment_id = Cache::get($this->referenceCacheKey($track_id));
        }

        return [
            'success' => $is_success,
            'payment_id' => $payment_id ?: $track_id,
            'track_id' => $track_id,
            'message' => $is_success ? __('nafezly::messages.PAYMENT_DONE') : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $process_data,
        ];
    }

    private function request($method, $path, array $payload = [], array $query = []): array
    {
        $request = Http::withHeaders([
            'merchant_api_key' => (string) $this->oxapay_merchant_api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $url = $this->oxapay_base_url . $path;

        if (strtolower((string) $method) === 'get') {
            $http_response = $request->get($url, $query);
        } else {
            $http_response = $request->post($url, $payload);
        }

        $body = $http_response->json();
        if (!is_array($body)) {
            $body = [
                'data' => [],
                'message' => $http_response->body(),
                'error' => [],
                'status' => $http_response->status(),
            ];
        }

        if (!isset($body['status'])) {
            $body['status'] = $http_response->status();
        }

        return [
            'ok' => $http_response->successful()
                && (int) ($body['status'] ?? 0) === 200
                && empty($body['error']),
            'status' => $http_response->status(),
            'body' => $body,
        ];
    }

    private function resolveCallbackUrl($reference_id)
    {
        if (!empty($this->callback_url)) {
            return $this->callback_url;
        }

        return route($this->verify_route_name, [
            'payment' => 'oxapay',
            'payment_id' => $reference_id,
        ]);
    }

    private function resolveReturnUrl($reference_id)
    {
        if (!empty($this->return_url)) {
            return $this->return_url;
        }

        return route($this->verify_route_name, [
            'payment' => 'oxapay',
            'payment_id' => $reference_id,
        ]);
    }

    private function resolveDescription($reference_id)
    {
        if (!empty($this->description)) {
            return $this->description;
        }

        return ($this->app_name ?: 'Payment') . ' #' . $reference_id;
    }

    private function resolveTrackId(Request $request, array $payload = []): ?string
    {
        $track_id = $payload['track_id'] ?? $request->get('track_id') ?? $request->get('payment_track_id');
        if (!empty($track_id)) {
            return (string) $track_id;
        }

        $payment_id = $request->get('payment_id');
        if (!empty($payment_id)) {
            $cached_track_id = Cache::get($this->trackCacheKey($payment_id));
            if (!empty($cached_track_id)) {
                return (string) $cached_track_id;
            }
        }

        return null;
    }

    private function hasWebhookSignature(Request $request): bool
    {
        return (string) $request->header('HMAC', '') !== '';
    }

    private function parseWebhookPayload($raw_body): array
    {
        if (empty($raw_body)) {
            return [];
        }

        $decoded = json_decode($raw_body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function storeTrackMapping($reference_id, $track_id): void
    {
        Cache::put($this->trackCacheKey($reference_id), $track_id, now()->addDays(7));
        Cache::put($this->referenceCacheKey($track_id), $reference_id, now()->addDays(7));
    }

    private function trackCacheKey($reference_id): string
    {
        return 'OXAPAY_TRACK_' . $reference_id;
    }

    private function referenceCacheKey($track_id): string
    {
        return 'OXAPAY_REFERENCE_' . $track_id;
    }

    private function normalizePaymentType($value): string
    {
        $value = strtolower((string) $value);
        $value = str_replace(['-', ' '], '_', $value);

        if (in_array($value, ['invoice', 'white_label', 'static_address'], true)) {
            return $value;
        }

        return 'invoice';
    }

    private function normalizeSymbol($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtoupper((string) $value);
    }

    private function cleanQueryFilters(array $filters): array
    {
        foreach ($filters as $key => $value) {
            if (is_bool($value)) {
                $filters[$key] = $value ? 1 : 0;
            }
        }

        return $this->removeNullValues($filters);
    }

    private function removeNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function assertRequiredValue($value, $field, $gateway): void
    {
        if ($value === null || $value === '') {
            throw new MissingPaymentInfoException($field, $gateway);
        }
    }

    private function castInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function castNullableInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function castNullableNumber($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : $value;
    }

    private function castNullableBoolean($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        $value = strtolower(trim((string) $value));

        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

    private function isSuccessfulStatus($status): bool
    {
        $normalized_status = strtolower((string) $status);
        return in_array($normalized_status, ['paid', 'manual_accept'], true);
    }
}
