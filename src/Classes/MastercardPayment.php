<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Interfaces\PaymentInterface;

class MastercardPayment extends BaseController implements PaymentInterface
{
    public $mastercard_merchant_id;
    public $mastercard_api_username;
    public $mastercard_api_password;
    public $mastercard_base_url;
    public $mastercard_api_version;
    public $mastercard_currency;
    public $mastercard_operation;
    public $verify_route_name;
    public $app_name;
    public $save_token;

    public function __construct()
    {
        $this->mastercard_merchant_id = config('nafezly-payments.MASTERCARD_MERCHANT_ID');
        $this->mastercard_api_username = config('nafezly-payments.MASTERCARD_API_USERNAME');
        $this->mastercard_api_password = config('nafezly-payments.MASTERCARD_API_PASSWORD');
        $configuredBaseUrl = rtrim((string) config('nafezly-payments.MASTERCARD_BASE_URL', 'https://test-gateway.mastercard.com'), '/');
        $this->mastercard_base_url = preg_replace('#/api$#i', '', $configuredBaseUrl);
        $this->mastercard_api_version = config('nafezly-payments.MASTERCARD_API_VERSION', '100');
        $this->mastercard_currency = config('nafezly-payments.MASTERCARD_CURRENCY', 'USD');
        $this->mastercard_operation = strtoupper(config('nafezly-payments.MASTERCARD_OPERATION', 'PAY'));
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->app_name = config('nafezly-payments.APP_NAME', 'Payment');
        $this->save_token = (bool) config('nafezly-payments.MASTERCARD_SAVE_TOKEN', false);
        $this->currency = $this->mastercard_currency;
    }

    public function setOperation($operation)
    {
        $operation = strtoupper((string) $operation);
        if (in_array($operation, ['PAY', 'AUTHORIZE'])) {
            $this->mastercard_operation = $operation;
        }
        return $this;
    }

    public function setSaveToken($value = true)
    {
        $this->save_token = (bool) $value;
        return $this;
    }

    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'MASTERCARD');

        $payment_id = $this->payment_id ?: uniqid() . rand(100000, 999999);
        $operation = $this->mastercard_operation === 'AUTHORIZE' ? 'AUTHORIZE' : 'PURCHASE';
        $return_url = route($this->verify_route_name, ['payment' => 'mastercard', 'payment_id' => $payment_id]);

        $payload = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'interaction' => [
                'operation' => $operation,
                'returnUrl' => $return_url,
                'merchant' => [
                    'name' => $this->app_name,
                ],
            ],
            'order' => [
                'id' => (string) $payment_id,
                'amount' => $this->formatAmount($this->amount),
                'currency' => strtoupper($this->currency ?: $this->mastercard_currency),
                'description' => $this->app_name . ' #' . $payment_id,
            ],
        ];

        if (!empty($this->user_email)) {
            $payload['customer']['email'] = $this->user_email;
        }

        if (!empty($this->user_first_name)) {
            $payload['customer']['firstName'] = $this->user_first_name;
        }

        if (!empty($this->user_last_name)) {
            $payload['customer']['lastName'] = $this->user_last_name;
        }

        $response = $this->gatewayRequest('post', '/merchant/' . $this->mastercard_merchant_id . '/session', $payload);

        if (!$response['ok'] || empty($response['body']['session']['id'])) {
            return [
                'payment_id' => $payment_id,
                'html' => '',
                'redirect_url' => '',
                'success' => false,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response['body'],
            ];
        }

        $session_id = $response['body']['session']['id'];

        return [
            'success' => true,
            'payment_id' => $payment_id,
            'html' => $this->generate_html([
                'session_id' => $session_id,
                'checkout_js_url' => $this->getCheckoutJsUrl(),
                'return_url' => $return_url,
            ]),
            'redirect_url' => '',
        ];
    }

    public function verify(Request $request): array
    {
        $payment_id = $request->get('payment_id') ?: $request->get('order_id') ?: $request->get('orderId');

        if (empty($payment_id)) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => 'payment_id_not_found'],
            ];
        }

        $response = $this->gatewayRequest('get', '/merchant/' . $this->mastercard_merchant_id . '/order/' . $payment_id);

        if (!$response['ok']) {
            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response['body'],
            ];
        }

        $body = $response['body'];
        ['is_success' => $is_success, 'gateway_code' => $gateway_code] = $this->resolveSuccessState($body);

        if (! empty($gateway_code) && empty(data_get($body, 'response.gatewayCode'))) {
            data_set($body, 'response.gatewayCode', $gateway_code);
        }

        $token = $this->extractTokenFromResponse($body);
        if ($token) {
            $body['tokenization'] = [
                'token' => $token,
                'save_on_project' => true,
                'can_charge_recurring' => true,
            ];
        }

        return [
            'success' => $is_success,
            'payment_id' => $payment_id,
            'message' => $is_success ? __('nafezly::messages.PAYMENT_DONE') : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $body,
        ];
    }

    public function chargeByToken($amount, $token, $payment_id = null, $currency = null, $operation = 'PAY'): array
    {
        if (empty($token)) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => 'token_is_required'],
            ];
        }

        $payment_id = $payment_id ?: uniqid() . rand(100000, 999999);
        $operation = strtoupper($operation) === 'AUTHORIZE' ? 'AUTHORIZE' : 'PAY';
        $transaction_id = uniqid('txn_');

        $payload = [
            'apiOperation' => $operation,
            'order' => [
                'amount' => $this->formatAmount($amount),
                'currency' => strtoupper($currency ?: $this->mastercard_currency),
                'description' => $this->app_name . ' #' . $payment_id,
            ],
            'sourceOfFunds' => [
                'type' => 'CARD',
                'token' => $token,
            ],
            'transaction' => [
                'reference' => (string) $payment_id,
            ],
        ];

        $response = $this->gatewayRequest(
            'put',
            '/merchant/' . $this->mastercard_merchant_id . '/order/' . $payment_id . '/transaction/' . $transaction_id,
            $payload
        );

        if (!$response['ok']) {
            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response['body'],
            ];
        }

        $body = $response['body'];
        ['is_success' => $is_success, 'gateway_code' => $gateway_code] = $this->resolveSuccessState($body);

        if (! empty($gateway_code) && empty(data_get($body, 'response.gatewayCode'))) {
            data_set($body, 'response.gatewayCode', $gateway_code);
        }

        return [
            'success' => $is_success,
            'payment_id' => $payment_id,
            'message' => $is_success ? __('nafezly::messages.PAYMENT_DONE') : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $body,
        ];
    }

    public function createTokenFromOrder(string $referenceOrderId, ?string $currency = null): array
    {
        if (empty($referenceOrderId)) {
            return [
                'success' => false,
                'token' => null,
                'process_data' => ['error' => 'reference_order_id_is_required'],
            ];
        }

        $payload = [
            'referenceOrderId' => (string) $referenceOrderId,
            'transaction' => [
                'currency' => strtoupper((string) ($currency ?: $this->mastercard_currency)),
            ],
        ];

        $response = $this->gatewayRequest('post', '/merchant/' . $this->mastercard_merchant_id . '/token', $payload);

        if (! $response['ok']) {
            return [
                'success' => false,
                'token' => null,
                'process_data' => $response['body'],
            ];
        }

        $body = $response['body'];
        $result = strtoupper((string) data_get($body, 'result', ''));
        $status = strtoupper((string) data_get($body, 'status', ''));
        $token = data_get($body, 'token');

        return [
            'success' => $result === 'SUCCESS' && ! empty($token) && ($status === '' || $status === 'VALID'),
            'token' => is_string($token) ? $token : null,
            'process_data' => $body,
        ];
    }

    public function generate_html($data)
    {
        return str_replace("\n", '', view('nafezly::html.mastercard', ['data' => $data])->render());
    }

    private function gatewayRequest($method, $path, $payload = []): array
    {
        $url = $this->mastercard_base_url . '/api/rest/version/' . $this->mastercard_api_version . $path;
        $apiUsername = !empty($this->mastercard_api_username)
            ? (string) $this->mastercard_api_username
            : 'merchant.' . $this->mastercard_merchant_id;

        $request = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($apiUsername . ':' . $this->mastercard_api_password),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        if ($method === 'get') {
            $http_response = $request->get($url);
        } elseif ($method === 'put') {
            $http_response = $request->put($url, $payload);
        } else {
            $http_response = $request->post($url, $payload);
        }

        return [
            'ok' => $http_response->successful(),
            'status' => $http_response->status(),
            'body' => $http_response->json() ?: ['raw' => $http_response->body()],
        ];
    }

    private function extractTokenFromResponse(array $response): ?string
    {
        $paths = [
            ['tokenization', 'token'],
            ['sourceOfFunds', 'token'],
            ['sourceOfFunds', 'provided', 'card', 'token'],
            ['transaction', 'sourceOfFunds', 'token'],
            ['transaction', 'sourceOfFunds', 'provided', 'card', 'token'],
            ['order', 'sourceOfFunds', 'token'],
        ];

        foreach ($paths as $path) {
            $value = data_get($response, implode('.', $path));
            if (!empty($value) && is_string($value)) {
                return $value;
            }
        }

        $transactions = data_get($response, 'transaction');
        if (is_array($transactions)) {
            foreach ($transactions as $transaction) {
                if (! is_array($transaction)) {
                    continue;
                }

                $transactionToken = data_get($transaction, 'sourceOfFunds.token')
                    ?? data_get($transaction, 'sourceOfFunds.provided.card.token')
                    ?? data_get($transaction, 'tokenization.token');

                if (! empty($transactionToken) && is_string($transactionToken)) {
                    return $transactionToken;
                }
            }
        }

        return null;
    }

    private function formatAmount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function getCheckoutJsUrl(): string
    {
        $parts = parse_url($this->mastercard_base_url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'test-gateway.mastercard.com';

        return $scheme . '://' . $host . '/static/checkout/checkout.min.js';
    }

    private function resolveSuccessState(array $body): array
    {
        $approvedCodes = ['APPROVED', 'APPROVED_AUTO', 'APPROVED_PENDING_SETTLEMENT'];

        $result = strtoupper((string) data_get($body, 'result', ''));
        $gatewayCodes = $this->extractGatewayCodes($body);
        $primaryGatewayCode = $gatewayCodes[0] ?? '';

        if (! empty($gatewayCodes)) {
            foreach ($gatewayCodes as $code) {
                if (in_array($code, $approvedCodes, true)) {
                    return ['is_success' => $result === 'SUCCESS', 'gateway_code' => $code];
                }
            }

            return ['is_success' => false, 'gateway_code' => $primaryGatewayCode];
        }

        $orderStatus = strtoupper((string) data_get($body, 'order.status', ''));
        $isSuccessByOrderStatus = in_array($orderStatus, ['CAPTURED', 'AUTHORIZED', 'PARTIALLY_CAPTURED', 'PARTIALLY_AUTHORIZED'], true);

        return [
            'is_success' => $result === 'SUCCESS' && $isSuccessByOrderStatus,
            'gateway_code' => $primaryGatewayCode,
        ];
    }

    private function extractGatewayCodes(array $body): array
    {
        $codes = [];

        $directCode = strtoupper((string) data_get($body, 'response.gatewayCode', ''));
        if (! empty($directCode)) {
            $codes[] = $directCode;
        }

        $transactions = data_get($body, 'transaction');

        if (is_array($transactions)) {
            foreach ($transactions as $transaction) {
                $code = strtoupper((string) data_get($transaction, 'response.gatewayCode', ''));
                if (! empty($code)) {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }
}
