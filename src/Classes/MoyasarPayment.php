<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class MoyasarPayment extends BaseController implements PaymentInterface
{
    private $moyasar_secret_key;
    private $moyasar_publishable_key;
    public $verify_route_name;
    public $app_name;

    public function __construct()
    {
        $this->moyasar_secret_key = config('nafezly-payments.MOYASAR_SECRET_KEY');
        $this->moyasar_publishable_key = config('nafezly-payments.MOYASAR_PUBLISHABLE_KEY');
        $this->currency = config('nafezly-payments.MOYASAR_CURRENCY', 'SAR');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->app_name = config('nafezly-payments.APP_NAME');
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
     * @throws \Nafezly\Payments\Exceptions\MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'MOYASAR');

        if ($this->payment_id == null) {
            $unique_id = uniqid() . rand(100000, 999999);
        } else {
            $unique_id = $this->payment_id;
        }

        // تحديد طرق الدفع المتاحة بناءً على المصدر
        $payment_methods = $this->getPaymentMethods($this->source);
        
        // تحديد الشبكات المدعومة (للبطاقات)
        $supported_networks = ['visa', 'mastercard', 'mada'];

        // Amount should be in smallest currency unit (e.g., 100 halalas = 1 SAR)
        $amount_in_smallest_unit = $this->amount * 100;

        $data = [
            'element' => '.mysr-form',
            'amount' => $amount_in_smallest_unit,
            'currency' => $this->currency,
            'description' => $this->app_name . ' Payment #' . $unique_id,
            'publishable_api_key' => $this->moyasar_publishable_key,
            'callback_url' => route($this->verify_route_name, ['payment' => 'moyasar']),
            'methods' => $payment_methods,
            'supported_networks' => $supported_networks,
            'payment_id' => $unique_id,
        ];

        // Apple Pay Configuration (required if Apple Pay is enabled)
        if (in_array('applepay', $payment_methods)) {
            $data['apple_pay'] = [
                'label' => config('nafezly-payments.MOYASAR_APPLE_PAY_LABEL', config('nafezly-payments.APP_NAME')),
                'validate_merchant_url' => config('nafezly-payments.MOYASAR_APPLE_PAY_VALIDATE_URL', url('/')),
                'country' => config('nafezly-payments.MOYASAR_APPLE_PAY_COUNTRY', 'SA'),
            ];
        }

        // إضافة معلومات العميل إذا كانت متوفرة
        if ($this->user_email) {
            $data['metadata'] = [
                'email' => $this->user_email,
                'user_id' => $this->user_id,
                'first_name' => $this->user_first_name,
                'last_name' => $this->user_last_name,
                'phone' => $this->user_phone,
            ];
        }

        return [
            'payment_id' => $unique_id,
            'html' => $this->generate_html($data),
            'redirect_url' => '',
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $payment_id = $request->id ?? $request->payment_id;
        
        if (!$payment_id) {
            return [
                'success' => false,
                'payment_id' => null,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => 'Payment ID not found']
            ];
        }

        try {
            // Fetch payment details from Moyasar API
            $response = Http::withBasicAuth($this->moyasar_secret_key, '')
                ->get('https://api.moyasar.com/v1/payments/' . $payment_id)
                ->json();

            if (isset($response['status']) && $response['status'] === 'paid') {
                return [
                    'success' => true,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $response
                ];
            } elseif (isset($response['status']) && $response['status'] === 'failed') {
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'payment_id' => $payment_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get payment methods based on source
     * 
     * @param string|null $source
     * @return array
     */
    private function getPaymentMethods($source = null): array
    {
        if ($source === 'stcpay') {
            return ['stcpay'];
        } elseif ($source === 'applepay') {
            return ['applepay'];
        } elseif ($source === 'creditcard') {
            return ['creditcard'];
        }
        
        // Default: all methods
        return ['creditcard', 'applepay', 'stcpay'];
    }

    /**
     * Generate HTML for Moyasar payment form
     * 
     * @param array $data
     * @return string
     */
    private function generate_html($data): string
    {
        return view('nafezly::html.moyasar', ['data' => $data])->render();
    }
}
