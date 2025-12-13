<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class XPayPayment extends BaseController implements PaymentInterface
{
    public $xpay_api_key;
    public $xpay_community_id;
    public $xpay_variable_amount_id;
    public $xpay_base_url;
    public $verify_route_name;
    public $currency;

    public function __construct()
    {
        $this->xpay_api_key = config('nafezly-payments.XPAY_API_KEY');
        $this->xpay_community_id = config('nafezly-payments.XPAY_COMMUNITY_ID');
        $this->xpay_variable_amount_id = config('nafezly-payments.XPAY_VARIABLE_AMOUNT_ID');
        $this->xpay_base_url = config('nafezly-payments.XPAY_BASE_URL', 'https://staging.xpay.app/api/v1');
        $this->currency = config('nafezly-payments.XPAY_CURRENCY', 'EGP');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
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
        // XPay requires: amount, user_email, user_phone, user_first_name, and user_last_name
        $required_fields = ['amount', 'user_email', 'user_phone', 'user_first_name', 'user_last_name'];
        $this->checkRequiredFields($required_fields, 'XPay');

        if($this->payment_id == null)
            $unique_id = uniqid() . rand(100000, 999999);
        else
            $unique_id = $this->payment_id;

        $currency = $this->currency ?? 'EGP';

        // Prepare billing data (required fields)
        // Name must contain first and last name in English letters with space between them
        $billingData = [
            'name' => trim($this->user_first_name . ' ' . $this->user_last_name),
            'email' => $this->user_email,
            'phone_number' => $this->user_phone, // Must contain country code prefixed (e.g., +201234567890)
        ];

        // XPay API request body structure
        $data = [
            'community_id' => $this->xpay_community_id,
            'variable_amount_id' => $this->xpay_variable_amount_id,
            'amount' => (float) $this->amount, // Total amount (with fees if fees are included in bill)
            'original_amount' => (float) $this->amount, // Service cost without fees
            'currency' => $currency,
            'billing_data' => $billingData,
            'pay_using' => 'card', // Default payment method (can be: card, fawry, kiosk, mobile wallets, valU)
        ];

        // Add language if set
        if ($this->language) {
            $data['language'] = $this->getXPayLanguage();
        }

        // Add membership_id if user_id is provided (optional)
        if ($this->user_id) {
            $data['membership_id'] = (string) $this->user_id;
        }

        // XPay uses x-api-key header for authentication
        $response = Http::withHeaders([
            'x-api-key' => $this->xpay_api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])
        ->post($this->xpay_base_url . '/payments/pay/variable-amount', $data);

        $responseData = $response->json();

        // Check response structure: { "status": { "code": ... }, "data": { "iframe_url": ... } }
        if ($response->successful() && isset($responseData['status']['code']) && $responseData['status']['code'] == 200) {
            $iframe_url = $responseData['data']['iframe_url'] ?? null;
            $transaction_uuid = $responseData['data']['transaction_uuid'] ?? null;
            
            if ($iframe_url) {
                // IMPORTANT: XPay redirect URL is configured in the XPay Dashboard, NOT in the API request
                // To set the redirect URL:
                // 1. Log into XPay Dashboard (https://staging.xpay.app/admin/ for test or https://community.xpay.app/admin/ for live)
                // 2. Go to your API Payment (variable_amount_id)
                // 3. Set the "Redirect URL" field (e.g., route('your-verify-route', ['payment' => 'xpay', 'payment_id' => $unique_id]))
                // 4. After payment completion (success or failure), XPay will redirect to this URL
                // 5. The redirect URL will contain query parameters: transaction_uuid, transaction_status, amount, amount_piasters
                return [
                    'payment_id' => $transaction_uuid,
                    'redirect_url' => $iframe_url,
                    'html' => $responseData
                ];
            }
        }

        return [
            'payment_id' => $unique_id,
            'redirect_url' => '',
            'html' => $responseData,
            'process_data' => $responseData
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        //$payment_id = $request->input('payment_id') ?? $request->route('payment_id');
        
        // XPay callback sends data as JSON body (POST) or query parameters (GET redirect)
        // Callback structure: { "transaction_uuid": "...", "transaction_status": "SUCCESSFUL" | "FAILED", ... }
        
        // Check for callback data (POST request from XPay)
        $transaction_uuid = $request->input('transaction_id');
        $transaction_status = $request->input('transaction_status');
        
        // Check for redirect data (GET request - redirect URL)
        if (!$transaction_status) {
            $transaction_status = $request->query('transaction_status');
            $transaction_uuid = $request->query('transaction_id');
        }

        // if ($transaction_status) {
        //     if (strtoupper($transaction_status) === 'SUCCESSFUL') {
        //         return [
        //             'success' => true,
        //             'payment_id' => $payment_id ?? $request->input('payment_id') ?? $transaction_uuid,
        //             'message' => __('nafezly::messages.PAYMENT_DONE'),
        //             'process_data' => $request->all()
        //         ];
        //     } else {
        //         return [
        //             'success' => false,
        //             'payment_id' => $payment_id ?? $request->input('payment_id') ?? $transaction_uuid,
        //             'message' => __('nafezly::messages.PAYMENT_FAILED'),
        //             'process_data' => $request->all()
        //         ];
        //     }
        // }

        // If no status provided, try to query transaction using transaction_uuid
        if ($transaction_uuid) {
            $response = Http::withHeaders([
                'x-api-key' => $this->xpay_api_key,
                'Accept' => 'application/json'
            ])
            ->get($this->xpay_base_url . '/communities/' . $this->xpay_community_id . '/transactions/' . $transaction_uuid . '/');

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']['code']) && $responseData['status']['code'] == 200) {
                $transaction_status = $responseData['data']['transaction_status'] ?? $responseData['data']['status'] ?? null;
                
                if (strtoupper($transaction_status) === 'SUCCESSFUL') {
                    return [
                        'success' => true,
                        'payment_id' => $transaction_uuid,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => $responseData
                    ];
                }
            }

            return [
                'success' => false,
                'payment_id' => $transaction_uuid,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $responseData ?? $request->all()
            ];
        }

        return [
            'success' => false,
            'payment_id' => $transaction_uuid,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

    /**
     * Get language in XPay format (ar, en)
     *
     * @return string
     */
    protected function getXPayLanguage()
    {
        return $this->language;
    }
}

