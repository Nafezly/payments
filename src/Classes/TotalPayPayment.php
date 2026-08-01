<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class TotalPayPayment extends BaseController implements PaymentInterface
{
    public $ngenius_api_key;
    public $ngenius_outlet_id;
    public $ngenius_realm;
    public $ngenius_gateway_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->currency = strtoupper(config('nafezly-payments.NGENIUS_CURRENCY', config('nafezly-payments.TOTALPAY_CURRENCY', 'AED')));
        $this->ngenius_api_key = config('nafezly-payments.NGENIUS_API_KEY');
        $this->ngenius_outlet_id = config('nafezly-payments.NGENIUS_OUTLET_ID');
        $this->ngenius_realm = config('nafezly-payments.NGENIUS_REALM', 'NetworkInternational');
        $this->ngenius_gateway_url = rtrim((string) config('nafezly-payments.NGENIUS_GATEWAY_URL', 'https://api-gateway.ngenius-payments.com'), '/');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'TotalPay');

        if (!$this->ngenius_api_key || !$this->ngenius_outlet_id) {
            return [
                'payment_id' => $this->payment_id,
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => 'TotalPay (N-Genius) credentials are missing',
                'process_data' => [],
            ];
        }

        $orderNumber = (string) ($this->payment_id ?: ('tpy_' . uniqid() . rand(100000, 999999)));
        $token = $this->requestAccessToken();

        if (!$token) {
            return [
                'payment_id' => $orderNumber,
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => [],
            ];
        }

        $verifyUrl = route($this->verify_route_name, [
            'payment' => 'totalpay',
            'payment_id' => $orderNumber,
        ]);

        $payload = [
            'action' => $this->resolveAction(),
            'amount' => [
                'currencyCode' => $this->currency,
                'value' => $this->toMinorUnits($this->amount),
            ],
            'merchantAttributes' => [
                'redirectUrl' => $verifyUrl,
            ],
        ];

        if ($this->user_email) {
            $payload['emailAddress'] = (string) $this->user_email;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/vnd.ni-payment.v2+json',
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ])->timeout(30)->post(
            $this->ngenius_gateway_url . '/transactions/outlets/' . $this->ngenius_outlet_id . '/orders',
            $payload
        )->json();

        $redirectUrl = data_get($response, '_links.payment.href');
        $orderReference = data_get($response, 'reference', $orderNumber);

        if ($redirectUrl) {
            return [
                'payment_id' => (string) $orderNumber,
                'redirect_url' => (string) $redirectUrl,
                'html' => '',
                'process_data' => array_merge(is_array($response) ? $response : [], [
                    'ngenius_order_reference' => $orderReference,
                ]),
            ];
        }

        return [
            'payment_id' => (string) $orderNumber,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => data_get($response, 'errors.0.message', __('nafezly::messages.PAYMENT_FAILED')),
            'process_data' => $response,
        ];
    }

    public function verify(Request $request): array
    {
        $orderNumber = $this->resolveOrderNumber($request);

        if (!$orderNumber) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $order = $this->fetchOrder($orderNumber);
        $paid = $this->isPaidOrder($order);

        return [
            'success' => $paid,
            'payment_id' => $orderNumber,
            'message' => $paid
                ? __('nafezly::messages.PAYMENT_DONE')
                : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $order ?? $request->all(),
        ];
    }

    public function resolveTotalPayLookupIds(Request $request, ?array $verifyResult = null): array
    {
        return array_values(array_unique(array_filter([
            $verifyResult['payment_id'] ?? null,
            $request->route('payment_id'),
            $request->input('payment_id'),
            $request->input('ref'),
            $request->input('orderReference'),
            $request->input('order_id'),
            $request->input('order_number'),
            data_get($verifyResult, 'process_data.reference'),
            data_get($verifyResult, 'process_data.ngenius_order_reference'),
        ], fn ($value) => is_string($value) && trim($value) !== '')));
    }

    protected function resolveAction(): string
    {
        $operation = config('nafezly-payments.TOTALPAY_OPERATION', 'purchase');

        if (is_array($this->source) && !empty($this->source['operation'])) {
            $operation = (string) $this->source['operation'];
        }

        return strtoupper($operation) === 'AUTH' ? 'AUTH' : 'PURCHASE';
    }

    protected function requestAccessToken(): ?string
    {
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.ni-identity.v1+json',
            'Authorization' => 'Basic ' . $this->ngenius_api_key,
            'Content-Type' => 'application/vnd.ni-identity.v1+json',
        ])->timeout(30)->post(
            $this->ngenius_gateway_url . '/identity/auth/access-token',
            ['realmName' => $this->ngenius_realm]
        )->json();

        return data_get($response, 'access_token');
    }

    protected function fetchOrder(string $orderReference): ?array
    {
        $token = $this->requestAccessToken();

        if (!$token || !$this->ngenius_outlet_id) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ])->timeout(30)->get(
            $this->ngenius_gateway_url . '/transactions/outlets/' . $this->ngenius_outlet_id . '/orders/' . $orderReference
        )->json();

        return is_array($response) ? $response : null;
    }

    protected function isPaidOrder(?array $order): bool
    {
        if (!$order) {
            return false;
        }

        $payments = data_get($order, '_embedded.payment', []);

        if (!is_array($payments)) {
            return false;
        }

        foreach ($payments as $payment) {
            $state = strtoupper((string) data_get($payment, 'state', ''));

            if (in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function resolveOrderNumber(Request $request): ?string
    {
        foreach ([
            $request->route('payment_id'),
            $request->input('ref'),
            $request->input('orderReference'),
            $request->input('order_id'),
            $request->input('order_number'),
            $request->input('payment_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function toMinorUnits($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Complete a Hosted Session SDK payment (card or digital wallet).
     *
     * @throws MissingPaymentInfoException
     */
    public function payHostedSession(?string $sessionId = null): array
    {
        $this->checkRequiredFields(['amount'], 'TotalPay Hosted Session');
        $sessionId = trim((string) ($sessionId ?: (is_array($this->source) ? ($this->source['session_id'] ?? '') : '')));

        if ($sessionId === '') {
            throw new MissingPaymentInfoException('session_id', 'TotalPay Hosted Session');
        }

        if (!$this->ngenius_api_key || !$this->ngenius_outlet_id) {
            return [
                'payment_id' => (string) ($this->payment_id ?: ''),
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => 'TotalPay (N-Genius) credentials are missing',
                'payment_response' => null,
                'process_data' => [],
            ];
        }

        $paymentId = (string) ($this->payment_id ?: ('tphs_' . uniqid() . rand(100000, 999999)));
        $token = $this->requestAccessToken();

        if (!$token) {
            return [
                'payment_id' => $paymentId,
                'redirect_url' => '',
                'html' => '',
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'payment_response' => null,
                'process_data' => [],
            ];
        }

        $payload = [
            'action' => $this->resolveAction(),
            'amount' => [
                'currencyCode' => $this->currency,
                'value' => $this->toMinorUnits($this->amount),
            ],
        ];

        $response = Http::withHeaders($this->paymentHeaders($token))
            ->timeout(30)
            ->post(
                $this->ngenius_gateway_url . '/transactions/outlets/' . $this->ngenius_outlet_id . '/payment/hosted-session/' . $sessionId,
                $payload
            )->json();

        $orderReference = data_get($response, 'reference')
            ?? data_get($response, 'orderReference')
            ?? data_get($response, '_embedded.payment.0.orderReference');

        Cache::put($this->hostedSessionCacheKey($paymentId), [
            'order_reference' => $orderReference,
            'session_id' => $sessionId,
            'response' => $response,
        ], now()->addHours(2));

        $state = strtoupper((string) data_get($response, 'state', ''));
        $paid = in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true);

        return [
            'payment_id' => $paymentId,
            'redirect_url' => '',
            'html' => '',
            'success' => $paid,
            'message' => $paid
                ? __('nafezly::messages.PAYMENT_DONE')
                : data_get($response, 'errors.0.message', __('nafezly::messages.PAYMENT_FAILED')),
            'payment_response' => is_array($response) ? $response : null,
            'process_data' => is_array($response) ? $response : [],
        ];
    }

    public function verifyHostedSessionPayment(string $paymentId): array
    {
        $cached = Cache::get($this->hostedSessionCacheKey($paymentId), []);
        $orderReference = data_get($cached, 'order_reference');

        if (!$orderReference) {
            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $cached,
            ];
        }

        $order = $this->fetchOrder((string) $orderReference);
        $paid = $this->isPaidOrder($order);

        return [
            'success' => $paid,
            'payment_id' => $paymentId,
            'message' => $paid
                ? __('nafezly::messages.PAYMENT_DONE')
                : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $order ?? $cached,
        ];
    }

    protected function hostedSessionCacheKey(string $paymentId): string
    {
        return 'totalpay_hosted_session_' . $paymentId;
    }

    protected function paymentHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/vnd.ni-payment.v2+json',
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ];
    }
}
