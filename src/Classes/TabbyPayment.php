<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        $this->base_url = 'https://api.tabby.ai/api/v2';
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

        $currencyCode = $this->currency ?? config('nafezly-payments.TABBY_CURRENCY', 'SAR');

        $verifyUrl = route($this->verify_route_name, ['payment' => 'tabby']);

        $payload = [
            'payment' => [
                'amount' => number_format($this->amount, 2, '.', ''),
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
                            'unit_price' => number_format($this->amount, 2, '.', ''),
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
                        'amount' => number_format($this->amount, 2, '.', ''),
                        'payment_method' => 'card',
                        'status' => 'new',
                    ],
                ],
            ],
            'lang' => $this->language ?? app()->getLocale(),
            'merchant_code' => $this->merchant_code,
            'merchant_urls' => [
                'success' => $verifyUrl,
                'cancel' => $verifyUrl,
                'failure' => $verifyUrl,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
            ])->post($this->base_url . '/checkout', $payload);

            $responseData = $response->json();

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

            if (isset($responseData['configuration']['available_products']) && empty($responseData['configuration']['available_products']['installments'])) {
                $rejectionReason = $responseData['configuration']['products']['installments'][0]['rejection_reason'] ?? 'not_available';
                $errorMessage = __('nafezly::messages.TABBY_PAYMENT_REJECTED', ['reason' => $rejectionReason]);
            }

            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => $errorMessage,
            ];
        } catch (\Exception $e) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => 'Error: ' . $e->getMessage(),
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret_key,
            ])->get($this->base_url . '/payments/' . $paymentId);

            $responseData = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'payment_id' => $paymentId,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $responseData,
                ];
            }

            $status = $responseData['status'] ?? '';

            if ($status === 'AUTHORIZED') {
                $captureResponse = $this->capturePayment($paymentId, $responseData['amount'] ?? '0.00');

                if ($captureResponse['success']) {
                    return [
                        'success' => true,
                        'payment_id' => $paymentId,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => array_merge($responseData, ['capture' => $captureResponse['data']]),
                    ];
                }

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

            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $responseData,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
                'process_data' => [],
            ];
        }
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
            ])->post($this->base_url . '/payments/' . $paymentId . '/captures', [
                'amount' => $amount,
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            return ['success' => false, 'data' => $responseData];
        } catch (\Exception $e) {
            return ['success' => false, 'data' => ['error' => $e->getMessage()]];
        }
    }
}
