<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class XPayPayment extends BaseController implements PaymentInterface
{
    private $xpay_public_key;
    private $xpay_private_key;
    private $xpay_community_id;
    private $xpay_payment_id;
    private $xpay_base_url;
    private $verify_route_name;
    private $currency;

    public function __construct()
    {
        $this->xpay_public_key = config('nafezly-payments.XPAY_PUBLIC_KEY');
        $this->xpay_private_key = config('nafezly-payments.XPAY_PRIVATE_KEY');
        $this->xpay_community_id = config('nafezly-payments.XPAY_COMMUNITY_ID');
        $this->xpay_payment_id = config('nafezly-payments.XPAY_PAYMENT_ID');
        $this->xpay_base_url = config('nafezly-payments.XPAY_BASE_URL', 'https://staging.xpay.app/api/v1');
        $this->currency = config('nafezly-payments.XPAY_CURRENCY', 'EGP');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * Get Basic Auth header for XPay API
     * XPay uses PublicKey:PrivateKey base64 encoded
     *
     * @return string
     */
    private function getAuthHeader()
    {
        return 'Basic ' . base64_encode($this->xpay_public_key . ':' . $this->xpay_private_key);
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
        $this->checkRequiredFields($required_fields, 'XPay');

        if($this->payment_id == null)
            $unique_id = uniqid() . rand(100000, 999999);
        else
            $unique_id = $this->payment_id;

        $currency = $this->currency ?? 'EGP';

        // XPay API accepts form-encoded request bodies
        $data = [
            'community_id' => $this->xpay_community_id,
            'api_payment_id' => $this->xpay_payment_id,
            'amount' => $this->amount,
            'currency' => $currency,
            'order_id' => $unique_id,
            'redirect_url' => route($this->verify_route_name, ['payment' => 'xpay', 'payment_id' => $unique_id]),
            'callback_url' => route($this->verify_route_name, ['payment' => 'xpay', 'payment_id' => $unique_id]),
        ];

        // Add customer information if provided
        if ($this->user_first_name || $this->user_last_name) {
            $data['customer_name'] = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        }

        if ($this->user_email) {
            $data['customer_email'] = $this->user_email;
        }

        if ($this->user_phone) {
            $data['customer_phone'] = $this->user_phone;
        }

        if ($this->user_id) {
            $data['member_id'] = $this->user_id;
        }

        // XPay uses Basic Authentication with PublicKey:PrivateKey
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Accept' => 'application/json'
            ])
            ->post($this->xpay_base_url . '/pay', $data);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
            $payment_url = $responseData['data']['payment_url'] ?? $responseData['payment_url'] ?? null;
            
            if ($payment_url) {
                return [
                    'payment_id' => $unique_id,
                    'redirect_url' => $payment_url,
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
        $payment_id = $request->input('payment_id') ?? $request->route('payment_id');
        $transaction_id = $request->input('transaction_id') ?? $request->input('transactionId');
        $order_id = $request->input('order_id') ?? $request->input('orderId');

        // Get transaction status from XPay
        // Transaction endpoint: /communities/{community_id}/transactions/{transaction_uuid}/
        if ($transaction_id || $order_id) {
            $key = $transaction_id ?? $order_id;
            
            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Accept' => 'application/json'
            ])
            ->get($this->xpay_base_url . '/communities/' . $this->xpay_community_id . '/transactions/' . $key . '/');

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['success']) && $responseData['success']) {
                $transaction_status = $responseData['data']['status'] ?? $responseData['status'] ?? null;
                
                // Check if payment is successful
                if ($transaction_status === 'success' || $transaction_status === 'completed' || $transaction_status === 'paid') {
                    return [
                        'success' => true,
                        'payment_id' => $payment_id ?? $key,
                        'message' => __('nafezly::messages.PAYMENT_DONE'),
                        'process_data' => $responseData
                    ];
                }
            }

            return [
                'success' => false,
                'payment_id' => $payment_id ?? $key,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $responseData ?? $request->all()
            ];
        }

        // If no transaction_id provided, check request parameters for status
        $status = $request->input('status') ?? $request->input('transaction_status');
        
        if ($status === 'success' || $status === 'completed' || $status === 'paid') {
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        }

        return [
            'success' => false,
            'payment_id' => $payment_id,
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

