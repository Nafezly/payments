<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;

class NgeniusDirectPayment extends NgeniusPayment
{
    public $must_3d_secure = false;

    public function __construct()
    {
        parent::__construct();
        $this->must_3d_secure = filter_var(
            config('nafezly-payments.NGENIUS_DIRECT_MUST_3DS', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $this->checkRequiredFields(['amount'], 'N-Genius Direct');

        if (!$this->ngenius_api_key || !$this->ngenius_outlet_id) {
            return $this->failedPayResponse((string) ($this->payment_id ?: ''), 'N-Genius credentials are missing');
        }

        if (!is_array($this->source)) {
            throw new MissingPaymentInfoException('source (card details)', 'N-Genius Direct');
        }

        $card = $this->resolveCardDetails();
        foreach (['pan', 'expiry', 'cvv', 'cardholderName'] as $field) {
            if (empty($card[$field])) {
                throw new MissingPaymentInfoException($field, 'N-Genius Direct');
            }
        }

        $paymentId = (string) ($this->payment_id ?: ('tpd_' . uniqid() . rand(100000, 999999)));
        $token = $this->requestAccessToken();

        if (!$token) {
            return $this->failedPayResponse($paymentId);
        }

        $verifyUrl = route($this->verify_route_name, [
            'payment' => 'ngeniusdirect',
            'payment_id' => $paymentId,
        ]);

        $orderPayload = [
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
            $orderPayload['emailAddress'] = (string) $this->user_email;
        }

        [$firstName, $lastName] = $this->resolveBillingNames($card['cardholderName']);
        $orderPayload['billingAddress'] = [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $orderResponse = Http::withHeaders($this->paymentHeaders($token))
            ->timeout(30)
            ->post($this->outletOrdersUrl(), $orderPayload)
            ->json();

        $orderReference = data_get($orderResponse, 'reference');
        $cardUrl = data_get($orderResponse, '_embedded.payment.0._links.payment:card.href');

        if (!$orderReference || !$cardUrl) {
            return [
                'payment_id' => $paymentId,
                'redirect_url' => '',
                'html' => $orderResponse,
                'success' => false,
                'message' => data_get($orderResponse, 'errors.0.message', __('nafezly::messages.PAYMENT_FAILED')),
                'process_data' => $orderResponse,
            ];
        }

        $this->rememberOrderReference($paymentId, $orderReference);

        $cardResponse = Http::withHeaders($this->paymentHeaders($token))
            ->timeout(30)
            ->withBody(json_encode([
                'pan' => $card['pan'],
                'expiry' => $card['expiry'],
                'cvv' => $card['cvv'],
                'cardholderName' => $card['cardholderName'],
            ]), 'application/vnd.ni-payment.v2+json')
            ->put($cardUrl)
            ->json();

        return $this->buildPayResult($paymentId, $orderReference, $cardResponse, $token, $verifyUrl);
    }

    public function verify(Request $request): array
    {
        $paymentId = $this->resolveOrderNumber($request);
        $orderReference = $paymentId ? $this->resolveStoredOrderReference($paymentId) : null;

        if ($request->filled('PaRes') && $paymentId) {
            return $this->completeThreeDsChallenge($request, $paymentId);
        }

        $lookupReference = $orderReference ?: $paymentId;

        if (!$lookupReference) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $order = $this->fetchOrderByReference($lookupReference);
        $paid = $this->isPaidOrder($order);

        return [
            'success' => $paid,
            'payment_id' => $paymentId ?: $lookupReference,
            'message' => $paid
                ? __('nafezly::messages.PAYMENT_DONE')
                : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $order ?? $request->all(),
        ];
    }

    public function resolveNgeniusLookupIds(Request $request, ?array $verifyResult = null): array
    {
        $paymentId = $verifyResult['payment_id'] ?? null;

        return array_values(array_unique(array_filter([
            $paymentId,
            $request->route('payment_id'),
            $request->input('payment_id'),
            $request->input('ref'),
            $paymentId ? $this->resolveStoredOrderReference($paymentId) : null,
            data_get($verifyResult, 'process_data.reference'),
            data_get($verifyResult, 'process_data.orderReference'),
        ], fn ($value) => is_string($value) && trim($value) !== '')));
    }

    public function getThreeDsSession(string $paymentId): ?array
    {
        $session = Cache::get($this->threeDsCacheKey($paymentId));

        if (!is_array($session)) {
            $session = Cache::get('totalpay_3ds_' . $paymentId);
        }

        return is_array($session) ? $session : null;
    }

    public function completeThreeDsChallenge(Request $request, string $paymentId): array
    {
        $session = $this->getThreeDsSession($paymentId);

        if (!$session || empty($session['cnp3ds_url'])) {
            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $token = $this->requestAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        $response = Http::withHeaders($this->paymentHeaders($token))
            ->timeout(30)
            ->post((string) $session['cnp3ds_url'], [
                'PaRes' => (string) $request->input('PaRes'),
            ])
            ->json();

        Cache::forget($this->threeDsCacheKey($paymentId));

        $state = strtoupper((string) data_get($response, 'state', ''));
        $paid = in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true);

        if ($paid && $this->requires3dSecure() && empty($session['started'])) {
            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response,
            ];
        }

        return [
            'success' => $paid,
            'payment_id' => $paymentId,
            'message' => $paid
                ? __('nafezly::messages.PAYMENT_DONE')
                : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response,
        ];
    }

    protected function buildPayResult(string $paymentId, string $orderReference, ?array $cardResponse, string $token, string $verifyUrl): array
    {
        $state = strtoupper((string) data_get($cardResponse, 'state', ''));

        if ($state === 'AWAIT_3DS') {
            $acsUrl = data_get($cardResponse, '3ds.acsUrl')
                ?? data_get($cardResponse, '3ds.acsurl');
            $paReq = data_get($cardResponse, '3ds.acsPaReq')
                ?? data_get($cardResponse, '3ds.acspareq');
            $md = data_get($cardResponse, '3ds.acsMd')
                ?? data_get($cardResponse, '3ds.acsmd');
            $cnp3dsUrl = data_get($cardResponse, '_links.cnp:3ds.href');

            if (!$acsUrl || !$cnp3dsUrl) {
                return [
                    'payment_id' => $paymentId,
                    'redirect_url' => '',
                    'html' => $cardResponse,
                    'success' => false,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $cardResponse,
                ];
            }

            $threeDsReturnUrl = is_array($this->source) ? ($this->source['threeds_return_url'] ?? null) : null;

            if (!$threeDsReturnUrl) {
                $threeDsReturnUrl = $verifyUrl;
            }

            Cache::put($this->threeDsCacheKey($paymentId), [
                'acsUrl' => $acsUrl,
                'paReq' => $paReq,
                'md' => $md,
                'cnp3ds_url' => $cnp3dsUrl,
                'order_reference' => $orderReference,
                'started' => true,
            ], now()->addHours(2));

            $threeDsLaunchUrl = is_array($this->source) ? ($this->source['threeds_launch_url'] ?? null) : null;

            return [
                'payment_id' => $paymentId,
                'redirect_url' => $threeDsLaunchUrl ?: '',
                'html' => '',
                'process_data' => array_merge(is_array($cardResponse) ? $cardResponse : [], [
                    'await_3ds' => true,
                    'acsUrl' => $acsUrl,
                    'paReq' => $paReq,
                    'md' => $md,
                    'termUrl' => $threeDsReturnUrl,
                ]),
            ];
        }

        if (in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true)) {
            if ($this->requires3dSecure()) {
                return $this->must3dSecureFailureResponse($paymentId, $cardResponse);
            }

            return [
                'payment_id' => $paymentId,
                'redirect_url' => '',
                'html' => '',
                'success' => true,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $cardResponse,
            ];
        }

        return [
            'payment_id' => $paymentId,
            'redirect_url' => '',
            'html' => $cardResponse,
            'success' => false,
            'message' => data_get($cardResponse, 'errors.0.message', __('nafezly::messages.PAYMENT_FAILED')),
            'process_data' => $cardResponse,
        ];
    }

    protected function fetchOrderByReference(string $orderReference): ?array
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

    protected function resolveCardDetails(): array
    {
        $source = $this->source;
        $expiryMonth = str_pad((string) ($source['expiryMonth'] ?? $source['expiry_month'] ?? ''), 2, '0', STR_PAD_LEFT);
        $expiryYear = (string) ($source['expiryYear'] ?? $source['expiry_year'] ?? '');

        $cardHolderName = $source['cardholderName']
            ?? $source['card_holder_name']
            ?? $source['cardHolderName']
            ?? trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));

        return [
            'pan' => preg_replace('/\s+/', '', (string) ($source['pan'] ?? $source['card_number'] ?? '')),
            'expiry' => $expiryYear . '-' . $expiryMonth,
            'cvv' => (string) ($source['cvv'] ?? $source['cvc'] ?? ''),
            'cardholderName' => trim($cardHolderName),
        ];
    }

    protected function resolveBillingNames(string $cardholderName): array
    {
        $parts = preg_split('/\s+/', trim($cardholderName)) ?: [];
        $firstName = $parts[0] ?? 'Customer';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';

        return [substr($firstName, 0, 40), substr($lastName, 0, 40)];
    }

    protected function requires3dSecure(): bool
    {
        if (is_array($this->source) && array_key_exists('must_3d_secure', $this->source)) {
            return filter_var($this->source['must_3d_secure'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $this->must_3d_secure;
    }

    protected function must3dSecureFailureResponse(string $paymentId, $processData): array
    {
        return [
            'payment_id' => $paymentId,
            'redirect_url' => '',
            'html' => '',
            'success' => false,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $processData,
        ];
    }

    protected function failedPayResponse(string $paymentId, ?string $message = null): array
    {
        return [
            'payment_id' => $paymentId,
            'redirect_url' => '',
            'html' => '',
            'success' => false,
            'message' => $message ?: __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => [],
        ];
    }

    protected function paymentHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/vnd.ni-payment.v2+json',
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ];
    }

    protected function outletOrdersUrl(): string
    {
        return $this->ngenius_gateway_url . '/transactions/outlets/' . $this->ngenius_outlet_id . '/orders';
    }

    protected function rememberOrderReference(string $paymentId, string $orderReference): void
    {
        Cache::put($this->orderReferenceCacheKey($paymentId), $orderReference, now()->addHours(48));
    }

    protected function resolveStoredOrderReference(string $paymentId): ?string
    {
        $reference = Cache::get($this->orderReferenceCacheKey($paymentId));

        if (!is_string($reference) || $reference === '') {
            $reference = Cache::get('totalpay_order_' . $paymentId);
        }

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    protected function orderReferenceCacheKey(string $paymentId): string
    {
        return 'ngenius_order_' . $paymentId;
    }

    protected function threeDsCacheKey(string $paymentId): string
    {
        return 'ngenius_3ds_' . $paymentId;
    }
}
