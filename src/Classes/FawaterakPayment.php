<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class FawaterakPayment extends BaseController implements PaymentInterface
{
    public $fawaterak_api_key;
    public $fawaterak_vendor_key;
    public $fawaterak_base_url;
    public $fawaterak_webhook_url;
    public $fawaterak_payment_method_id;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.FAWATERAK_CURRENCY', 'EGP');
        $this->fawaterak_api_key = config('nafezly-payments.FAWATERAK_API_KEY');
        $this->fawaterak_vendor_key = config('nafezly-payments.FAWATERAK_VENDOR_KEY');
        $this->fawaterak_base_url = rtrim(config('nafezly-payments.FAWATERAK_BASE_URL', 'https://staging.fawaterk.com'), '/');
        $this->fawaterak_webhook_url = config('nafezly-payments.FAWATERAK_WEBHOOK_URL');
        $this->fawaterak_payment_method_id = config('nafezly-payments.FAWATERAK_PAYMENT_METHOD_ID');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount', 'user_first_name', 'user_last_name'], 'Fawaterak');

        if ($this->payment_id == null) {
            $unique_id = uniqid('fwk_') . rand(100000, 999999);
        } else {
            $unique_id = (string) $this->payment_id;
        }

        $verifyUrl = route($this->verify_route_name, ['payment' => 'fawaterak', 'payment_id' => $unique_id]);
        $webhookUrl = $this->fawaterak_webhook_url ?: $verifyUrl;

        $payload = [
            'cartTotal' => $this->formatAmount($this->amount),
            'currency' => strtoupper($this->currency),
            'invoice_number' => $unique_id,
            'customer' => $this->buildCustomerPayload(),
            'cartItems' => $this->buildCartItems(),
            'payLoad' => $this->buildPayLoad($unique_id),
            'sendEmail' => false,
            'sendSMS' => false,
            'redirectionUrls' => [
                'successUrl' => $verifyUrl,
                'failUrl' => $verifyUrl,
                'pendingUrl' => $verifyUrl,
                'webhookUrl' => $webhookUrl,
            ],
        ];

        $paymentMethodId = $this->resolvePaymentMethodId();
        $endpoint = '/api/v2/createInvoiceLink';

        if ($paymentMethodId !== null) {
            $payload['payment_method_id'] = $paymentMethodId;
            $endpoint = '/api/v2/invoiceInitPay';

            if (is_array($this->source) && array_key_exists('redirectOption', $this->source)) {
                $payload['redirectOption'] = filter_var($this->source['redirectOption'], FILTER_VALIDATE_BOOLEAN);
            }

            $payload['lang'] = $this->resolveLang();
        }

        $response = $this->apiRequest('POST', $endpoint, $payload);

        if (data_get($response, 'status') !== 'success') {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => $response,
                'success' => false,
                'message' => data_get($response, 'message', __('nafezly::messages.PAYMENT_FAILED')),
                'process_data' => $response,
            ];
        }

        $invoiceId = data_get($response, 'data.invoiceId') ?? data_get($response, 'data.invoice_id');
        $invoiceKey = data_get($response, 'data.invoiceKey') ?? data_get($response, 'data.invoice_key');
        $redirectUrl = data_get($response, 'data.url')
            ?? data_get($response, 'data.payment_data.redirectTo');

        if ($invoiceId && $invoiceKey) {
            $this->rememberInvoiceMapping($unique_id, (int) $invoiceId, (string) $invoiceKey);
        }

        if (!$redirectUrl) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => $response,
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => $unique_id,
            'redirect_url' => $redirectUrl,
            'html' => '',
            'process_data' => $response,
        ];
    }

    public function verify(Request $request): array
    {
        $merchantReference = $this->resolveFawaterakMerchantReference($request);

        if ($this->isPaidWebhook($request)) {
            if (!$this->isValidPaidWebhook($request)) {
                return [
                    'success' => false,
                    'payment_id' => $merchantReference,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all(),
                ];
            }

            $merchantReference = $this->resolveFawaterakMerchantReference($request)
                ?: $merchantReference;

            return [
                'success' => true,
                'payment_id' => $merchantReference,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all(),
            ];
        }

        if ($this->isExpiredWebhook($request)) {
            if ($this->fawaterak_vendor_key && !$this->isValidExpiredWebhook($request)) {
                return [
                    'success' => false,
                    'payment_id' => $merchantReference,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $request->all(),
                ];
            }

            return [
                'success' => false,
                'payment_id' => $merchantReference,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $invoiceId = $this->resolveInvoiceId($request, $merchantReference);

        if (!$invoiceId) {
            return [
                'success' => false,
                'payment_id' => $merchantReference,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $response = $this->apiRequest('GET', '/api/v2/getInvoiceData/' . $invoiceId);
        $paid = (int) data_get($response, 'data.paid', 0) === 1;
        $merchantReference = $this->resolveMerchantReferenceFromInvoiceData($response)
            ?: $merchantReference;

        if (data_get($response, 'status') === 'success' && $paid) {
            return [
                'success' => true,
                'payment_id' => $merchantReference,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }

        return [
            'success' => false,
            'payment_id' => $merchantReference,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response ?? $request->all(),
        ];
    }

    /**
     * Collect every merchant-side ID that may have been stored as payment_id.
     */
    public function resolveFawaterakLookupIds(Request $request, ?array $verifyResult = null): array
    {
        $invoiceId = data_get($request->all(), 'invoice_id');
        $invoiceKey = data_get($request->all(), 'invoice_key');

        $candidates = array_values(array_unique(array_filter([
            $verifyResult['payment_id'] ?? null,
            $this->resolveFawaterakMerchantReference($request),
            $request->input('payment_id'),
            $request->route('payment_id'),
            $request->input('invoice_number'),
            is_scalar($invoiceId) ? (string) $invoiceId : null,
            is_string($invoiceKey) ? trim($invoiceKey) : null,
            data_get($request->all(), 'pay_load.merchant_reference'),
            is_array(data_get($request->all(), 'pay_load')) ? data_get($request->all(), 'pay_load.merchant_reference') : null,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        foreach ($candidates as $candidate) {
            $cached = Cache::get('fawaterak_invoice_' . $candidate);
            if (is_array($cached)) {
                if (!empty($cached['invoice_id'])) {
                    $candidates[] = (string) $cached['invoice_id'];
                }
                if (!empty($cached['invoice_key'])) {
                    $candidates[] = (string) $cached['invoice_key'];
                }
            }
        }

        if (is_scalar($invoiceId)) {
            $cachedRef = Cache::get('fawaterak_reference_' . $invoiceId);
            if (is_string($cachedRef) && trim($cachedRef) !== '') {
                $candidates[] = trim($cachedRef);
            }
        }

        if (is_string($invoiceKey) && trim($invoiceKey) !== '') {
            $cachedRef = Cache::get('fawaterak_reference_key_' . trim($invoiceKey));
            if (is_string($cachedRef) && trim($cachedRef) !== '') {
                $candidates[] = trim($cachedRef);
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function buildCustomerPayload(): array
    {
        $customer = [
            'first_name' => $this->sanitizeName($this->user_first_name, 'Customer'),
            'last_name' => $this->sanitizeName($this->user_last_name, 'User'),
        ];

        if ($this->user_email) {
            $customer['email'] = $this->user_email;
        }

        if ($this->user_phone) {
            $customer['phone'] = preg_replace('/\D+/', '', (string) $this->user_phone);
        }

        if (is_array($this->source) && !empty($this->source['address'])) {
            $customer['address'] = (string) $this->source['address'];
        }

        return $customer;
    }

    protected function buildCartItems(): array
    {
        if (is_array($this->source) && !empty($this->source['cartItems']) && is_array($this->source['cartItems'])) {
            return $this->source['cartItems'];
        }

        return [[
            'name' => is_array($this->source) && !empty($this->source['item_name'])
                ? (string) $this->source['item_name']
                : 'Payment',
            'price' => $this->formatAmount($this->amount),
            'quantity' => '1',
        ]];
    }

    protected function buildPayLoad(string $merchantReference): array
    {
        $payload = ['merchant_reference' => $merchantReference];

        if (is_array($this->source) && !empty($this->source['payLoad']) && is_array($this->source['payLoad'])) {
            $payload = array_merge($payload, $this->source['payLoad']);
        }

        return $payload;
    }

    protected function resolveLang(): string
    {
        if (is_array($this->source) && !empty($this->source['lang'])) {
            $lang = strtolower((string) $this->source['lang']);

            return in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
        }

        if (!empty($this->language) && in_array($this->language, ['ar', 'en'], true)) {
            return $this->language;
        }

        return 'ar';
    }

    protected function resolvePaymentMethodId(): ?int
    {
        if (is_array($this->source) && isset($this->source['payment_method_id'])) {
            return (int) $this->source['payment_method_id'];
        }

        if ($this->fawaterak_payment_method_id !== null && $this->fawaterak_payment_method_id !== '') {
            return (int) $this->fawaterak_payment_method_id;
        }

        return null;
    }

    protected function rememberInvoiceMapping(string $merchantReference, int $invoiceId, string $invoiceKey): void
    {
        $ttl = now()->addHours(72);
        $mapping = [
            'invoice_id' => $invoiceId,
            'invoice_key' => $invoiceKey,
        ];

        Cache::put('fawaterak_invoice_' . $merchantReference, $mapping, $ttl);
        Cache::put('fawaterak_reference_' . $invoiceId, $merchantReference, $ttl);
        Cache::put('fawaterak_reference_key_' . $invoiceKey, $merchantReference, $ttl);
    }

    protected function resolveFawaterakMerchantReference(Request $request): ?string
    {
        foreach ([
            $request->route('payment_id'),
            $request->input('payment_id'),
            $request->input('invoice_number'),
            $this->merchantReferenceFromPayload(data_get($request->all(), 'pay_load')),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $invoiceId = data_get($request->all(), 'invoice_id');
        if ($invoiceId !== null) {
            $cached = Cache::get('fawaterak_reference_' . $invoiceId);
            if (is_string($cached) && trim($cached) !== '') {
                return trim($cached);
            }
        }

        $invoiceKey = data_get($request->all(), 'invoice_key');
        if (is_string($invoiceKey) && trim($invoiceKey) !== '') {
            $cached = Cache::get('fawaterak_reference_key_' . trim($invoiceKey));
            if (is_string($cached) && trim($cached) !== '') {
                return trim($cached);
            }
        }

        return null;
    }

    protected function resolveInvoiceId(Request $request, ?string $merchantReference = null): ?int
    {
        $invoiceId = data_get($request->all(), 'invoice_id');
        if (is_numeric($invoiceId)) {
            return (int) $invoiceId;
        }

        if ($merchantReference) {
            $cached = Cache::get('fawaterak_invoice_' . $merchantReference);
            if (is_array($cached) && !empty($cached['invoice_id'])) {
                return (int) $cached['invoice_id'];
            }
        }

        return null;
    }

    protected function resolveMerchantReferenceFromInvoiceData(?array $response): ?string
    {
        $payload = data_get($response, 'data.pay_load');

        return $this->merchantReferenceFromPayload($payload);
    }

    protected function merchantReferenceFromPayload($payload): ?string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $reference = data_get($payload, 'merchant_reference');

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }

    protected function isPaidWebhook(Request $request): bool
    {
        return $request->isMethod('post')
            && strtolower((string) data_get($request->all(), 'invoice_status')) === 'paid';
    }

    protected function isExpiredWebhook(Request $request): bool
    {
        return $request->isMethod('post')
            && strtoupper((string) data_get($request->all(), 'status')) === 'EXPIRED';
    }

    protected function isValidPaidWebhook(Request $request): bool
    {
        if (!$this->fawaterak_vendor_key) {
            return true;
        }

        $providedHash = (string) data_get($request->all(), 'hashKey', '');
        if ($providedHash === '') {
            return false;
        }

        $invoiceId = data_get($request->all(), 'invoice_id');
        $invoiceKey = (string) data_get($request->all(), 'invoice_key', '');
        $paymentMethod = (string) data_get($request->all(), 'payment_method', '');

        $queryParam = 'InvoiceId=' . $invoiceId . '&InvoiceKey=' . $invoiceKey . '&PaymentMethod=' . $paymentMethod;
        $expectedHash = hash_hmac('sha256', $queryParam, $this->fawaterak_vendor_key, false);

        return hash_equals($expectedHash, $providedHash);
    }

    protected function isValidExpiredWebhook(Request $request): bool
    {
        $providedHash = (string) data_get($request->all(), 'hashKey', '');
        if ($providedHash === '') {
            return false;
        }

        $referenceId = (string) data_get($request->all(), 'referenceId', '');
        $paymentMethod = (string) data_get($request->all(), 'paymentMethod', '');
        $queryParam = 'referenceId=' . $referenceId . '&PaymentMethod=' . $paymentMethod;
        $expectedHash = hash_hmac('sha256', $queryParam, $this->fawaterak_vendor_key, false);

        return hash_equals($expectedHash, $providedHash);
    }

    protected function apiRequest(string $method, string $endpoint, ?array $payload = null): ?array
    {
        if (!$this->fawaterak_api_key) {
            return [
                'status' => 'error',
                'message' => 'Fawaterak API key is missing',
            ];
        }

        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fawaterak_api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);

        $response = strtoupper($method) === 'GET'
            ? $request->get($this->fawaterak_base_url . $endpoint)
            : $request->post($this->fawaterak_base_url . $endpoint, $payload ?? []);

        return $response->json();
    }

    protected function formatAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function sanitizeName(?string $value, string $fallback): string
    {
        $name = trim((string) $value);
        $name = preg_replace('/[^a-zA-Z0-9@\-_. ]+/', '', $name) ?: $fallback;

        return substr($name, 0, 50);
    }
}
