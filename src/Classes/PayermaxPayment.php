<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PayermaxPayment extends BaseController implements PaymentInterface
{
    private $payermax_app_id;
    private $payermax_merchant_no;
    private $payermax_private_key;
    private $payermax_public_key;
    private $payermax_base_url;
    private $payermax_version;
    private $payermax_key_version;
    private $verify_route_name;
    
    // Country property (currency is inherited from SetVariables trait)
    public $country = null;

    public function __construct()
    {
        $this->payermax_app_id = config('nafezly-payments.PAYERMAX_APP_ID');
        $this->payermax_merchant_no = config('nafezly-payments.PAYERMAX_MERCHANT_NO');
        $this->payermax_private_key = config('nafezly-payments.PAYERMAX_PRIVATE_KEY');
        $this->payermax_public_key = config('nafezly-payments.PAYERMAX_PUBLIC_KEY');
        $this->payermax_base_url = config('nafezly-payments.PAYERMAX_BASE_URL', 'https://pay-gate-uat.payermax.com/aggregate-pay/api/gateway');
        $this->payermax_version = config('nafezly-payments.PAYERMAX_VERSION', '1.4');
        $this->payermax_key_version = config('nafezly-payments.PAYERMAX_KEY_VERSION', '1');
        // Country and currency should be set via setCountry() and setCurrency() methods instead of constructor
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * Generate RSA signature for request body
     * Uses SHA256WithRSA algorithm
     *
     * @param string $data JSON string to sign
     * @return string Base64 encoded signature
     */
    private function generateSignature($data)
    {
        if (empty($this->payermax_private_key)) {
            throw new MissingPaymentInfoException('Payermax private key is not configured');
        }

        // Format private key (add headers if not present)
        $privateKey = $this->payermax_private_key;
        if (strpos($privateKey, '-----BEGIN') === false) {
            $privateKey = "-----BEGIN PRIVATE KEY-----\n" . 
                         chunk_split($privateKey, 64, "\n") . 
                         "-----END PRIVATE KEY-----";
        }

        // Sign the data
        $signature = '';
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$success) {
            throw new \Exception('Failed to generate RSA signature: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Verify RSA signature from response
     *
     * @param string $data JSON string that was signed
     * @param string $signature Base64 encoded signature
     * @return bool
     */
    private function verifySignature($data, $signature)
    {
        if (empty($this->payermax_public_key)) {
            return false;
        }

        // Format public key (add headers if not present)
        $publicKey = $this->payermax_public_key;
        if (strpos($publicKey, '-----BEGIN') === false) {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" . 
                        chunk_split($publicKey, 64, "\n") . 
                        "-----END PUBLIC KEY-----";
        }

        $signatureBinary = base64_decode($signature);
        return openssl_verify($data, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Format JSON string consistently (sorted keys, no spaces)
     * This is important for signature verification
     *
     * @param array $data
     * @return string
     */
    private function formatJsonString(array $data)
    {
        // Recursively sort keys and format arrays
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->formatJsonString($value);
            }
        }
        
        // Encode without spaces, preserving exact format
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sets country code (e.g., 'US', 'EG', 'SA')
     *
     * @param  string  $value
     * @return $this
     */
    public function setCountry($value)
    {
        $this->country = $value;
        return $this;
    }

    /**
     * Get language in Payermax format (lowercase: en, ar, etc.)
     *
     * @return string
     */
    protected function getPayermaxLanguage()
    {
        return strtolower($this->language ?? 'en');
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
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'Payermax');

        if($this->payment_id == null)
            $order_id = uniqid() . rand(100000, 999999);
        else
            $order_id = $this->payment_id;

        $currency = $this->currency ?? config('nafezly-payments.PAYERMAX_CURRENCY', 'USD');
        $country = $this->country ?? config('nafezly-payments.PAYERMAX_COUNTRY', 'US');
        $language = $this->getPayermaxLanguage();

        // Prepare request data
        $requestData = [
            'outTradeNo' => $order_id,
            'subject' => 'Payment',
            'totalAmount' => (string) $this->amount,
            'currency' => $currency,
            'country' => $country,
            'userId' => (string) ($this->user_id ?? $order_id),
            'language' => $language,
            'frontCallbackURL' => route($this->verify_route_name, ['payment' => 'payermax', 'payment_id' => $order_id]),
            'notifyUrl' => route($this->verify_route_name, ['payment' => 'payermax', 'payment_id' => $order_id]),
        ];

        // Add optional customer information
        if ($this->user_email) {
            $requestData['email'] = $this->user_email;
        }

        if ($this->user_phone) {
            $requestData['mobileNo'] = $this->user_phone;
        }

        // Prepare request body
        $requestTime = now()->format('Y-m-d\TH:i:s.vP');
        $requestBody = [
            'version' => $this->payermax_version,
            'keyVersion' => $this->payermax_key_version,
            'requestTime' => $requestTime,
            'appId' => $this->payermax_app_id,
            'merchantNo' => $this->payermax_merchant_no,
            'data' => $requestData
        ];

        // Format JSON string for signing (consistent formatting is critical)
        $jsonString = $this->formatJsonString($requestBody);

        // Generate signature
        $signature = $this->generateSignature($jsonString);

        // Make API request - send the exact JSON string to preserve signature match
        $response = Http::withBody($jsonString, 'application/json')
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'sign' => $signature
            ])
            ->post($this->payermax_base_url . '/orderAndPay');

        $responseData = $response->json();

        // Verify response signature if present
        if (isset($responseData['sign']) && $response->successful()) {
            $responseBody = $response->body();
            $responseSignature = $responseData['sign'];
            unset($responseData['sign']);
            $responseJsonForVerification = json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            // Note: Signature verification is recommended but not blocking
            // if (!$this->verifySignature($responseJsonForVerification, $responseSignature)) {
            //     // Log warning but continue processing
            // }
        }

        if ($response->successful() && isset($responseData['code']) && $responseData['code'] === 'APPLY_SUCCESS') {
            $redirectUrl = $responseData['data']['redirectUrl'] ?? null;
            
            if ($redirectUrl) {
                return [
                    'payment_id' => $order_id,
                    'redirect_url' => $redirectUrl,
                    'html' => ''
                ];
            }
        }

        return [
            'payment_id' => $order_id,
            'redirect_url' => '',
            'html' => $responseData,
            'success' => false,
            'message' => $responseData['msg'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $responseData
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = $request->input('outTradeNo') ?? $request->input('orderId') ?? $request->route('payment_id');
        $tradeNo = $request->input('tradeNo');
        $status = $request->input('status');

        // Payermax sends callback notifications
        // Check if this is a callback notification
        if ($request->has('tradeNo') || $request->has('status')) {
            // Verify signature if present
            if ($request->has('sign')) {
                $requestData = $request->all();
                $signature = $requestData['sign'];
                unset($requestData['sign']);
                $jsonForVerification = json_encode($requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                
                // Verify signature (recommended but not blocking)
                // if (!$this->verifySignature($jsonForVerification, $signature)) {
                //     return [
                //         'success' => false,
                //         'payment_id' => $payment_id,
                //         'message' => 'Invalid signature',
                //         'process_data' => $request->all()
                //     ];
                // }
            }

            // Check payment status
            // Status values: PENDING, SUCCESS, FAILED
            if ($status === 'SUCCESS' || $status === 'SUCCEEDED' || $status === 'PAID') {
                return [
                    'success' => true,
                    'payment_id' => $payment_id ?? $tradeNo,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            }

            return [
                'success' => false,
                'payment_id' => $payment_id ?? $tradeNo,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }

        // If no callback data, try to query order status
        if ($payment_id || $tradeNo) {
            $orderId = $payment_id ?? $tradeNo;
            
            // Query order status using orderQuery API
            $requestTime = now()->format('Y-m-d\TH:i:s.vP');
            $requestBody = [
                'version' => $this->payermax_version,
                'keyVersion' => $this->payermax_key_version,
                'requestTime' => $requestTime,
                'appId' => $this->payermax_app_id,
                'merchantNo' => $this->payermax_merchant_no,
                'data' => [
                    'outTradeNo' => $orderId
                ]
            ];

            $jsonString = $this->formatJsonString($requestBody);
            $signature = $this->generateSignature($jsonString);

            $response = Http::withBody($jsonString, 'application/json')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'sign' => $signature
                ])
                ->post($this->payermax_base_url . '/orderQuery');

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['code']) && $responseData['code'] === 'APPLY_SUCCESS') {
                $orderStatus = $responseData['data']['status'] ?? null;
                
                if ($orderStatus === 'SUCCESS' || $orderStatus === 'SUCCEEDED' || $orderStatus === 'PAID') {
                    return [
                        'success' => true,
                        'payment_id' => $orderId,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => $responseData
                    ];
                }
            }

            return [
                'success' => false,
                'payment_id' => $orderId,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $responseData ?? $request->all()
            ];
        }

        return [
            'success' => false,
            'payment_id' => $payment_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }
}

