<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class SkipCashPayment extends BaseController implements PaymentInterface
{
    private $secret_key;
    private $key_id;
    private $base_url;
    private $webhook_key;
    private $mode;
    public $verify_route_name;

    public function __construct()
    {
        $this->secret_key = config('nafezly-payments.SKIPCASH_SECRET_KEY');
        $this->key_id = config('nafezly-payments.SKIPCASH_KEY_ID');
        $this->mode = config('nafezly-payments.SKIPCASH_MODE', 'test');
        $this->webhook_key = config('nafezly-payments.SKIPCASH_WEBHOOK_KEY');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        
        // Set the base URL based on mode
        if ($this->mode === 'live') {
            $this->base_url = 'https://api.skipcash.app/api/v1/payments';
        } else {
            $this->base_url = 'https://skipcashtest.azurewebsites.net/api/v1/payments';
        }
    }

    /**
     * Hide sensitive data when dumping/debugging the object
     * This method is called by var_dump(), print_r(), and similar functions
     * 
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'base_url' => $this->base_url,
            'mode' => $this->mode,
            'verify_route_name' => $this->verify_route_name,
            'secret_key' => '[HIDDEN]',
            'key_id' => '[HIDDEN]',
            'webhook_key' => '[HIDDEN]',
            // Include parent class properties if needed
            'payment_id' => $this->payment_id ?? null,
            'user_id' => $this->user_id ?? null,
            'amount' => $this->amount ?? null,
        ];
    }

    /**
     * Hide sensitive data when object is converted to string
     * 
     * @return string
     */
    public function __toString()
    {
        return 'SkipCashPayment[mode=' . $this->mode . ', base_url=' . $this->base_url . ']';
    }

    /**
     * Generate UUID v4
     * 
     * @param string|null $data
     * @return string
     */
    private function generateUuid($data = null)
    {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Output the 36 character UUID
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Calculate authorization header hash
     * 
     * @param array $data
     * @return string
     */
    private function calculateAuthorizationHeader($data)
    {
        $headerString = "Uid=" . $data['Uid'] . 
                       ',KeyId=' . $data['KeyId'] . 
                       ',Amount=' . $data['Amount'] . 
                       ',FirstName=' . $data['FirstName'] . 
                       ',LastName=' . $data['LastName'] . 
                       ',Phone=' . $data['Phone'] . 
                       ',Email=' . $data['Email'] . 
                       ',TransactionId=' . $data['TransactionId'];
        
        $hash = hash_hmac('sha256', $headerString, $this->secret_key, true);
        return base64_encode($hash);
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
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'SkipCash');

        if ($this->payment_id == null) {
            $unique_id = uniqid() . rand(100000, 999999);
        } else {
            $unique_id = $this->payment_id;
        }

        // If email is not provided or invalid, generate one based on phone
        if (empty($this->user_email) || !filter_var($this->user_email, FILTER_VALIDATE_EMAIL)) {
            $this->user_email = $this->user_phone . '@' . (config('app.name', 'merchant') . '.com');
        }

        $uuid = $this->generateUuid();
        
        $paymentData = [
            'Uid' => $uuid,
            'KeyId' => $this->key_id,
            'Amount' => number_format($this->amount, 2, '.', ''),
            'FirstName' => $this->user_first_name,
            'LastName' => $this->user_last_name,
            'Phone' => $this->user_phone,
            'Email' => $this->user_email,
            'TransactionId' => $unique_id,
            'Subject' => 'Payment for Order #' . $unique_id,
            'Description' => 'Payment processing via SkipCash',
            'ReturnUrl' => route($this->verify_route_name, ['payment' => 'skipcash']),
            'WebhookUrl' => route($this->verify_route_name, ['payment' => 'skipcash']),
            'Custom1' => $unique_id,
        ];

        // Calculate authorization header
        $authorizationHeader = $this->calculateAuthorizationHeader($paymentData);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $authorizationHeader
            ])->post($this->base_url, $paymentData);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['resultObj']['payUrl'])) {
                return [
                    'payment_id' => $unique_id,
                    'redirect_url' => $responseData['resultObj']['payUrl'],
                    'html' => ''
                ];
            } else {
                return [
                    'payment_id' => $unique_id,
                    'redirect_url' => '',
                    'html' => 'Error: ' . ($responseData['errorMessage'] ?? 'Payment creation failed')
                ];
            }
        } catch (\Exception $e) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook authorization header
     * 
     * @param array $webhookData
     * @param string $authorizationHeader
     * @return bool
     */
    private function verifyWebhookSignature($webhookData, $authorizationHeader)
    {
        // Build the string for verification based on SkipCash documentation
        $verificationString = "PaymentId=" . $webhookData['PaymentId'] .
                             ",Amount=" . $webhookData['Amount'] .
                             ",StatusId=" . $webhookData['StatusId'];
        
        // Add optional fields if they exist
        if (!empty($webhookData['TransactionId'])) {
            $verificationString .= ",TransactionId=" . $webhookData['TransactionId'];
        }
        
        if (!empty($webhookData['Custom1'])) {
            $verificationString .= ",Custom1=" . $webhookData['Custom1'];
        }
        
        $verificationString .= ",VisaId=" . $webhookData['VisaId'];

        $calculatedHash = base64_encode(hash_hmac('sha256', $verificationString, $this->webhook_key, true));
        
        return hash_equals($calculatedHash, $authorizationHeader);
    }    /**
     * Get status message based on StatusId
     * 
     * @param int $statusId
     * @return string
     */
    private function getStatusMessage($statusId)
    {
        $statuses = [
            0 => __('nafezly::messages.SKIPCASH_PAYMENT_NEW'),
            1 => __('nafezly::messages.SKIPCASH_PAYMENT_PENDING'),
            2 => __('nafezly::messages.SKIPCASH_PAYMENT_PAID'),
            3 => __('nafezly::messages.SKIPCASH_PAYMENT_CANCELED'),
            4 => __('nafezly::messages.SKIPCASH_PAYMENT_FAILED'),
            5 => __('nafezly::messages.SKIPCASH_PAYMENT_REJECTED'),
            6 => __('nafezly::messages.SKIPCASH_PAYMENT_REFUNDED'),
            7 => __('nafezly::messages.SKIPCASH_PAYMENT_PENDING_REFUND'),
            8 => __('nafezly::messages.SKIPCASH_PAYMENT_REFUND_FAILED')
        ];

        return $statuses[$statusId] ?? __('nafezly::messages.An_error_occurred_while_executing_the_operation');
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        // Check if this is a GET request with ID (return from payment page)
        if ($request->isMethod('get') && $request->has('id')) {
            return $this->verifyPaymentStatus($request->get('id'));
        }

        // Handle webhook POST request
        if ($request->isMethod('post')) {
            $webhookData = $request->all();
            $authorizationHeader = $request->header('Authorization');

            // Verify webhook signature
            if (!$this->verifyWebhookSignature($webhookData, $authorizationHeader)) {
                return [
                    'success' => false,
                    'payment_id' => $webhookData['TransactionId'] ?? null,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $webhookData
                ];
            }

            $statusId = $webhookData['StatusId'];
            $transactionId = $webhookData['TransactionId'] ?? $webhookData['Custom1'];

            if ($statusId == 2) { // paid
                return [
                    'success' => true,
                    'payment_id' => $transactionId,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $webhookData
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id' => $transactionId,
                    'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $this->getStatusMessage($statusId)]),
                    'process_data' => $webhookData
                ];
            }
        }

        // If no valid request type
        return [
            'success' => false,
            'payment_id' => null,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

    /**
     * Verify payment status by calling SkipCash API
     * 
     * @param string $paymentId
     * @return array
     */
    private function verifyPaymentStatus($paymentId)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $this->key_id // Use key_id as client ID for verification
            ])->get($this->base_url . '/' . $paymentId);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['resultObj'])) {
                $payment = $responseData['resultObj'];
                $statusId = $payment['statusId'];
                $transactionId = $payment['transactionId'] ?? $payment['custom1'];

                if ($statusId == 2) { // paid
                    return [
                        'success' => true,
                        'payment_id' => $transactionId,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => $payment
                    ];
                } else {
                    return [
                        'success' => false,
                        'payment_id' => $transactionId,
                        'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $this->getStatusMessage($statusId)]),
                        'process_data' => $payment
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'payment_id' => null,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $responseData
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => $e->getMessage()]
            ];
        }
    }
}
