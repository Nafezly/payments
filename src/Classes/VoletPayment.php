<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class VoletPayment extends BaseController implements PaymentInterface
{
    private $volet_account_email;
    private $volet_sci_name;
    private $volet_sci_password;
    private $volet_sci_url;
    private $verify_route_name;
    private $currency;

    public function __construct()
    {
        $this->volet_account_email = config('nafezly-payments.VOLET_ACCOUNT_EMAIL');
        $this->volet_sci_name = config('nafezly-payments.VOLET_SCI_NAME');
        $this->volet_sci_password = config('nafezly-payments.VOLET_SCI_PASSWORD');
        $this->volet_sci_url = config('nafezly-payments.VOLET_SCI_URL', 'https://account.volet.com/sci/');
        $this->currency = config('nafezly-payments.VOLET_CURRENCY', 'USD');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * Verify ac_hash from Volet SCI callback
     * Hash format: SHA256(ac_transfer:ac_start_date:ac_sci_name:ac_src_wallet:ac_dest_wallet:ac_order_id:ac_amount:ac_merchant_currency:SCI's password)
     *
     * @param array $data Callback data from Volet
     * @return bool
     */
    private function verifyHash($data)
    {
        if (!isset($data['ac_hash'])) {
            return false;
        }

        $hashString = ($data['ac_transfer'] ?? '') . ':' .
                     ($data['ac_start_date'] ?? '') . ':' .
                     ($data['ac_sci_name'] ?? '') . ':' .
                     ($data['ac_src_wallet'] ?? '') . ':' .
                     ($data['ac_dest_wallet'] ?? '') . ':' .
                     ($data['ac_order_id'] ?? '') . ':' .
                     ($data['ac_amount'] ?? '') . ':' .
                     ($data['ac_merchant_currency'] ?? '') . ':' .
                     $this->volet_sci_password;

        $calculatedHash = hash('sha256', $hashString);

        return hash_equals($calculatedHash, $data['ac_hash']);
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
        $this->checkRequiredFields($required_fields, 'Volet');

        if($this->payment_id == null)
            $order_id = uniqid() . rand(100000, 999999);
        else
            $order_id = $this->payment_id;

        $currency = $this->currency ?? 'USD';

        // Prepare form data according to Volet SCI documentation
        $formData = [
            'ac_account_email' => $this->volet_account_email,
            'ac_sci_name' => $this->volet_sci_name,
            'ac_amount' => number_format((float)$this->amount, 2, '.', ''),
            'ac_currency' => $currency,
            'ac_order_id' => $order_id,
            'ac_success_url' => route($this->verify_route_name, ['payment' => 'volet', 'payment_id' => $order_id]),
            'ac_success_url_method' => 'POST',
            'ac_fail_url' => route($this->verify_route_name, ['payment' => 'volet', 'payment_id' => $order_id]),
            'ac_fail_url_method' => 'POST',
            'ac_status_url' => route($this->verify_route_name, ['payment' => 'volet', 'payment_id' => $order_id]),
            'ac_status_url_method' => 'POST',
        ];

        // Add optional comments if provided
        if ($this->user_email || $this->user_phone) {
            $comments = [];
            if ($this->user_email) {
                $comments[] = 'Email: ' . $this->user_email;
            }
            if ($this->user_phone) {
                $comments[] = 'Phone: ' . $this->user_phone;
            }
            if (!empty($comments)) {
                $formData['ac_comments'] = implode(', ', $comments);
            }
        }

        // Add custom fields (up to 10 custom fields allowed)
        if ($this->user_id) {
            $formData['ac_custom_field_1'] = (string) $this->user_id;
        }

        if ($this->user_first_name || $this->user_last_name) {
            $formData['ac_custom_field_2'] = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        }

        return [
            'payment_id' => $order_id,
            'html' => $this->generate_html($formData),
            'redirect_url' => ''
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = $request->input('payment_id') ?? $request->route('payment_id');
        $order_id = $request->input('ac_order_id');
        $transaction_status = $request->input('ac_transaction_status');
        $transfer_id = $request->input('ac_transfer');

        // Use order_id from request if payment_id not found
        if (!$payment_id && $order_id) {
            $payment_id = $order_id;
        }

        // Verify hash for status URL callbacks (status form includes ac_hash)
        if ($request->has('ac_hash')) {
            $isValidHash = $this->verifyHash($request->all());
            
            if (!$isValidHash) {
                return [
                    'success' => false,
                    'payment_id' => $payment_id ?? $order_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED') . ' - Invalid signature',
                    'process_data' => $request->all()
                ];
            }
        }

        // Check transaction status
        // COMPLETED status means payment was successful
        if ($transaction_status === 'COMPLETED') {
            return [
                'success' => true,
                'payment_id' => $payment_id ?? $order_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        }

        // If status URL callback, check if payment was successful
        // Status form is sent in background, so we check ac_transaction_status
        if ($request->has('ac_hash') && $transaction_status) {
            return [
                'success' => $transaction_status === 'COMPLETED',
                'payment_id' => $payment_id ?? $order_id,
                'message' => $transaction_status === 'COMPLETED' 
                    ? __('nafezly::messages.PAYMENT_DONE')
                    : __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }

        // For success/fail URL redirects (user is redirected back)
        // Success form doesn't include ac_hash, but includes transaction details
        if ($request->has('ac_transfer') && $request->has('ac_amount')) {
            // If we have transfer ID and amount, payment was likely successful
            // But we should verify with status URL callback for security
            return [
                'success' => false, // Default to false, wait for status URL callback
                'payment_id' => $payment_id ?? $order_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED') . ' - Waiting for status confirmation',
                'process_data' => $request->all()
            ];
        }

        return [
            'success' => false,
            'payment_id' => $payment_id ?? $order_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
    }

    /**
     * Generate HTML form for Volet SCI payment
     *
     * @param array $data Form data
     * @return string
     */
    private function generate_html($data): string
    {
        return view('nafezly::html.volet', ['data' => $data, 'sci_url' => $this->volet_sci_url])->render();
    }
}
