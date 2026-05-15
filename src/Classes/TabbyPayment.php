<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class TabbyPayment extends BaseController implements PaymentInterface
{
    private $secret_key;
    private $public_key;
    private $merchant_code;
    private $mode;
    private $base_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->secret_key = config('nafezly-payments.TABBY_SECRET_KEY');
        $this->public_key = config('nafezly-payments.TABBY_PUBLIC_KEY');
        $this->merchant_code = config('nafezly-payments.TABBY_MERCHANT_CODE');
        $this->mode = config('nafezly-payments.TABBY_MODE', 'test');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');

        $this->base_url = $this->normalizeBaseUrl(config('nafezly-payments.TABBY_BASE_URL', 'https://api.tabby.ai/api/v2'));
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
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'Tabby');

        $unique_id = $this->payment_id ?? uniqid('tabby_') . rand(100000, 999999);

        $currencyCode = $this->resolveCurrencyCode($this->currency ?? config('nafezly-payments.TABBY_CURRENCY', 'SAR'));
        if (! in_array($currencyCode, ['AED', 'KWD', 'SAR'], true)) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => __('nafezly::messages.TABBY_UNSUPPORTED_CURRENCY'),
            ];
        }

        $language = $this->resolveLanguage($this->language ?? app()->getLocale());
        $formattedAmount = $this->formatAmount($this->amount, $currencyCode);

        $verifyUrl = route($this->verify_route_name, ['payment' => 'tabby']);

        $payload = [
            'payment' => [
                'amount' => $formattedAmount,
                'currency' => $currencyCode,
                'description' => 'Order #' . $unique_id,
                'buyer' => [
                    'phone' => $this->user_phone,
                    'email' => $this->user_email,
                    'name' => trim($this->user_first_name . ' ' . $this->user_last_name),
                ],
                'shipping_address' => [
                    'city' => 'N/A',
                    'address' => 'N/A',
                    'zip' => '00000',
                ],
                'order' => [
                    'tax_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'updated_at' => now()->toIso8601String(),
                    'reference_id' => $unique_id,
                    'items' => [
                        [
                            'title' => 'Order #' . $unique_id,
                            'quantity' => 1,
                            'unit_price' => $formattedAmount,
                            'tax_amount' => '0.00',
                            'discount_amount' => '0.00',
                            'reference_id' => $unique_id,
                            'category' => 'digital',
                        ],
                    ],
                ],
                'buyer_history' => [
                    'registered_since' => now()->subYear()->toIso8601String(),
                    'loyalty_level' => 0,
                ],
                'order_history' => [
                    [
                        'purchased_at' => now()->subMonth()->toIso8601String(),
                        'amount' => $formattedAmount,
                        'payment_method' => 'card',
                        'status' => 'new',
                    ],
                ],
            ],
            'lang' => $language,
            'merchant_code' => $this->merchant_code,
            'merchant_urls' => [
                'success' => $verifyUrl,
                'cancel' => $verifyUrl,
                'failure' => $verifyUrl,
            ],
        ];

        try {
            $response = Http::withHeaders($this->jsonHeaders())->timeout(15)->connectTimeout(5)->post($this->base_url . '/checkout', $payload);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if ($response->successful() && isset($responseData['configuration']['available_products']['installments'][0]['web_url'])) {
                $webUrl = $responseData['configuration']['available_products']['installments'][0]['web_url'];
                $tabbyPaymentId = $responseData['payment']['id'] ?? $unique_id;

                return [
                    'payment_id' => $tabbyPaymentId,
                    'redirect_url' => $webUrl,
                    'html' => '',
                ];
            }

            $errorMessage = $responseData['error'] ?? $responseData['message'] ?? 'Tabby checkout session creation failed';
            $installments = $responseData['configuration']['available_products']['installments'] ?? [];
            $rejectionReason = $this->extractRejectionReason($responseData);

            if (isset($responseData['configuration']['available_products']) && (empty($installments) || $rejectionReason !== null)) {
                $rejectionReason = $rejectionReason ?? 'not_available';
                $errorMessage = $this->translateTabbyRejectionMessage($rejectionReason);
            }

            $this->logTabbyResponse('checkout_failed', $response, $responseData, [
                'payment_id' => $unique_id,
                'currency' => $currencyCode,
                'rejection_reason' => $rejectionReason,
            ]);

            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby checkout exception', [
                'payment_id' => $unique_id,
                'mode' => $this->mode,
                'currency' => $currencyCode,
                'error' => $e->getMessage(),
            ]);

            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => __('nafezly::messages.PAYMENT_FAILED'),
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $paymentId = $request->input('payment_id');

        if (empty($paymentId)) {
            return [
                'success' => false,
                'payment_id' => '',
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        try {
            $retrieveResponse = $this->retrievePayment($paymentId);

            if (! $retrieveResponse['success']) {
                return [
                    'success' => false,
                    'payment_id' => $paymentId,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $retrieveResponse['data'],
                ];
            }

            $responseData = $retrieveResponse['data'];

            $status = $responseData['status'] ?? '';

            if ($status === 'AUTHORIZED') {
                $captureResponse = $this->captureAuthorizedPayment($paymentId, $responseData['amount'] ?? '0.00');

                if ($captureResponse['success']) {
                    return [
                        'success' => true,
                        'payment_id' => $paymentId,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => array_merge($responseData, ['capture' => $captureResponse['data']]),
                    ];
                }

                Log::warning('Tabby capture failed after authorization', [
                    'mode' => $this->mode,
                    'merchant_code' => $this->merchant_code,
                    'base_url' => $this->base_url,
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'capture_response' => $this->redactTabbyResponseData($captureResponse['data']),
                ]);

                return [
                    'success' => false,
                    'payment_id' => $paymentId,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => array_merge($responseData, ['capture_error' => $captureResponse['data']]),
                ];
            }

            if ($status === 'CLOSED') {
                return [
                    'success' => true,
                    'payment_id' => $paymentId,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $responseData,
                ];
            }

            Log::warning('Tabby verification returned unsuccessful status', [
                'mode' => $this->mode,
                'merchant_code' => $this->merchant_code,
                'base_url' => $this->base_url,
                'payment_id' => $paymentId,
                'status' => $status,
                'tabby_response' => $this->redactTabbyResponseData($responseData),
            ]);

            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby verification exception', [
                'payment_id' => $paymentId,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
                'process_data' => [],
            ];
        }
    }

    /**
     * Retrieve a Tabby payment without performing any side effects.
     *
     * @param string $paymentId
     * @return array
     */
    public function retrievePayment(string $paymentId): array
    {
        if (trim($paymentId) === '') {
            return [
                'success' => false,
                'payment_id' => '',
                'status' => '',
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'data' => [],
            ];
        }

        try {
            $response = Http::withHeaders($this->authorizationHeaders())->timeout(15)->connectTimeout(5)->get($this->base_url . '/payments/' . $paymentId);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if (! $response->successful()) {
                $this->logTabbyResponse('retrieve_payment_failed', $response, $responseData, [
                    'payment_id' => $paymentId,
                ]);

                return [
                    'success' => false,
                    'payment_id' => $paymentId,
                    'status' => $responseData['status'] ?? '',
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'data' => $responseData,
                ];
            }

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => $responseData['status'] ?? '',
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby retrieve payment exception', [
                'payment_id' => $paymentId,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'payment_id' => $paymentId,
                'status' => '',
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Run Tabby's background pre-scoring check through the checkout API.
     *
     * @param mixed $amount
     * @param mixed $user_email
     * @param mixed $user_phone
     * @param mixed $currency
     * @param array<string, mixed> $context
     * @return array
     */
    public function checkEligibility($amount, $user_email, $user_phone, $currency = null, array $context = []): array
    {
        $currencyCode = $this->resolveCurrencyCode($currency ?? config('nafezly-payments.TABBY_CURRENCY', 'SAR'));
        $formattedAmount = $this->formatAmount($amount, $currencyCode);
        $referenceId = $context['payment']['order']['reference_id'] ?? uniqid('tabby_prescore_');

        if (! in_array($currencyCode, ['AED', 'KWD', 'SAR'], true)) {
            return [
                'success' => true,
                'eligible' => false,
                'status' => 'rejected',
                'rejection_reason' => 'unsupported_currency',
                'message' => __('nafezly::messages.TABBY_UNSUPPORTED_CURRENCY'),
                'data' => [],
            ];
        }

        $payload = array_replace_recursive([
            'payment' => [
                'amount' => $formattedAmount,
                'currency' => $currencyCode,
                'description' => 'Order #' . $referenceId,
                'buyer' => [
                    'email' => (string) $user_email,
                    'phone' => (string) $user_phone,
                    'name' => (string) ($context['payment']['buyer']['name'] ?? 'Guest'),
                ],
                'shipping_address' => [
                    'city' => 'N/A',
                    'address' => 'N/A',
                    'zip' => '00000',
                ],
                'order' => [
                    'tax_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'updated_at' => now()->toIso8601String(),
                    'reference_id' => $referenceId,
                    'items' => [
                        [
                            'title' => 'Order #' . $referenceId,
                            'quantity' => 1,
                            'unit_price' => $formattedAmount,
                            'tax_amount' => '0.00',
                            'discount_amount' => '0.00',
                            'reference_id' => $referenceId,
                            'category' => 'digital',
                        ],
                    ],
                ],
                'buyer_history' => [
                    'registered_since' => now()->subYear()->toIso8601String(),
                    'loyalty_level' => 0,
                ],
                'order_history' => [
                    [
                        'purchased_at' => now()->subMonth()->toIso8601String(),
                        'amount' => $formattedAmount,
                        'payment_method' => 'card',
                        'status' => 'new',
                    ],
                ],
            ],
            'lang' => $this->resolveLanguage(app()->getLocale()),
            'merchant_code' => $this->merchant_code,
        ], $context);

        try {
            $response = Http::withHeaders($this->jsonHeaders())->timeout(15)->connectTimeout(5)->post($this->base_url . '/checkout', $payload);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];
            $status = strtolower((string) ($responseData['status'] ?? ''));
            $rejectionReason = $this->extractRejectionReason($responseData);

            if (! $response->successful()) {
                $this->logTabbyResponse('eligibility_failed', $response, $responseData, [
                    'currency' => $currencyCode,
                    'amount' => $formattedAmount,
                    'rejection_reason' => $rejectionReason,
                ]);

                return [
                    'success' => false,
                    'eligible' => true,
                    'status' => $status,
                    'rejection_reason' => $rejectionReason,
                    'message' => $responseData['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
                    'data' => $responseData,
                ];
            }

            return [
                'success' => true,
                'eligible' => $status !== 'rejected',
                'status' => $status,
                'rejection_reason' => $rejectionReason,
                'message' => $rejectionReason ? $this->translateTabbyRejectionMessage($rejectionReason) : '',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby eligibility exception', [
                'mode' => $this->mode,
                'currency' => $currencyCode,
                'amount' => $formattedAmount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'eligible' => true,
                'status' => '',
                'rejection_reason' => null,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Retrieve all configured Tabby webhooks for the current merchant code.
     *
     * @return array
     */
    public function listWebhooks(): array
    {
        try {
            $response = Http::withHeaders($this->webhookHeaders())->timeout(15)->connectTimeout(5)->get($this->webhookBaseUrl() . '/webhooks');

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if (! $response->successful()) {
                $this->logTabbyResponse('list_webhooks_failed', $response, $responseData);

                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
                    'data' => $responseData,
                ];
            }

            return [
                'success' => true,
                'message' => '',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby list webhooks exception', [
                'mode' => $this->mode,
                'merchant_code' => $this->merchant_code,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Register a Tabby webhook for the current merchant code.
     *
     * @param string $url
     * @param string $headerTitle
     * @param string $headerValue
     * @param bool $isTest
     * @return array
     */
    public function registerWebhook(string $url, string $headerTitle, string $headerValue, bool $isTest = false): array
    {
        $payload = [
            'url' => $url,
            'is_test' => $isTest,
            'header' => [
                'title' => $headerTitle,
                'value' => $headerValue,
            ],
        ];

        try {
            $response = Http::withHeaders($this->webhookHeaders())->timeout(15)->connectTimeout(5)->post($this->webhookBaseUrl() . '/webhooks', $payload);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if (! $response->successful()) {
                $this->logTabbyResponse('register_webhook_failed', $response, $responseData, [
                    'webhook_url' => $url,
                    'is_test' => $isTest,
                ]);

                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
                    'data' => $responseData,
                ];
            }

            return [
                'success' => true,
                'message' => '',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Tabby register webhook exception', [
                'mode' => $this->mode,
                'merchant_code' => $this->merchant_code,
                'webhook_url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Capture an authorized Tabby payment.
     *
     * @param string $paymentId
     * @param mixed $amount
     * @return array{success: bool, data: array}
     */
    public function captureAuthorizedPayment(string $paymentId, $amount): array
    {
        return $this->capturePayment($paymentId, (string) $amount);
    }

    /**
     * Capture an authorized Tabby payment
     *
     * @param string $paymentId
     * @param string $amount
     * @return array{success: bool, data: array}
     */
    private function capturePayment(string $paymentId, string $amount): array
    {
        try {
            $response = Http::withHeaders($this->jsonHeaders())->timeout(15)->connectTimeout(5)->post($this->base_url . '/payments/' . $paymentId . '/captures', [
                'amount' => $amount,
                'reference_id' => 'capture_' . $paymentId,
            ]);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            $this->logTabbyResponse('capture_failed', $response, $responseData, [
                'payment_id' => $paymentId,
                'amount' => $amount,
            ]);

            return ['success' => false, 'data' => $responseData];
        } catch (\Exception $e) {
            Log::error('Tabby capture exception', [
                'payment_id' => $paymentId,
                'mode' => $this->mode,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * @param array<string, mixed>|null $responseData
     * @return string|null
     */
    private function extractRejectionReason($responseData)
    {
        if (! is_array($responseData)) {
            return null;
        }

        $installments = $responseData['configuration']['available_products']['installments'] ?? [];

        foreach ($installments as $installment) {
            if (! empty($installment['rejection_reason'])) {
                return $installment['rejection_reason'];
            }
        }

        return $responseData['rejection_reason']
            ?? $responseData['rejection_reason_code']
            ?? $responseData['error']
            ?? $responseData['message']
            ?? null;
    }

    /**
     * @return string
     */
    private function translateTabbyRejectionMessage(string $reason)
    {
        $key = 'nafezly::messages.TABBY_PAYMENT_REJECTED';
        $message = __($key, ['reason' => $reason]);

        if ($message !== $key) {
            return $message;
        }

        if (strpos(app()->getLocale(), 'ar') === 0) {
            return 'تعذر إكمال الدفع عبر تابي. السبب: ' . $reason;
        }

        return 'Unable to complete the payment through Tabby. Reason: ' . $reason;
    }

    /**
     * @param array<string, mixed>|null $responseData
     * @param array<string, mixed> $context
     * @return void
     */
    private function logTabbyResponse(string $event, $response, $responseData, array $context = [])
    {
        Log::warning('Tabby payment response: ' . $event, array_merge([
            'mode' => $this->mode,
            'merchant_code' => $this->merchant_code,
            'base_url' => $this->base_url,
            'http_status' => $response->status(),
            'successful' => $response->successful(),
            'tabby_response' => $this->redactTabbyResponseData($responseData),
        ], $context));
    }

    private function authorizationHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secret_key,
        ];
    }

    private function jsonHeaders(): array
    {
        return array_merge($this->authorizationHeaders(), [
            'Content-Type' => 'application/json',
        ]);
    }

    private function webhookHeaders(): array
    {
        return array_merge($this->jsonHeaders(), [
            'X-Merchant-Code' => (string) $this->merchant_code,
        ]);
    }

    private function webhookBaseUrl(): string
    {
        return str_replace('/api/v2', '/api/v1', $this->base_url);
    }

    private function normalizeBaseUrl($baseUrl): string
    {
        $baseUrl = rtrim((string) $baseUrl, '/');

        $allowedBaseUrls = [
            'https://api.tabby.ai' => 'https://api.tabby.ai/api/v2',
            'https://api.tabby.ai/api/v2' => 'https://api.tabby.ai/api/v2',
            'https://api.tabby.sa' => 'https://api.tabby.sa/api/v2',
            'https://api.tabby.sa/api/v2' => 'https://api.tabby.sa/api/v2',
        ];

        return $allowedBaseUrls[$baseUrl] ?? 'https://api.tabby.ai/api/v2';
    }

    private function resolveCurrencyCode($currency): string
    {
        return strtoupper(trim((string) $currency));
    }

    private function formatAmount($amount, string $currencyCode): string
    {
        $decimals = $currencyCode === 'KWD' ? 3 : 2;

        return number_format((float) $amount, $decimals, '.', '');
    }

    private function resolveLanguage($language): string
    {
        $language = strtolower(substr(trim((string) $language), 0, 2));

        return in_array($language, ['ar', 'en'], true) ? $language : 'ar';
    }

    private function redactTabbyResponseData($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $sensitiveKeys = ['phone', 'email', 'name', 'token'];

        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $value[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->redactTabbyResponseData($item);
            }
        }

        return $value;
    }
}
