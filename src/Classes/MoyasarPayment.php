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
    private $apple_pay_label;
    private $apple_pay_country;
    private $payment_methods;

    public function __construct()
    {
        $this->moyasar_secret_key = config('nafezly-payments.MOYASAR_SECRET_KEY');
        $this->moyasar_publishable_key = config('nafezly-payments.MOYASAR_PUBLISHABLE_KEY');
        $this->currency = config('nafezly-payments.MOYASAR_CURRENCY', 'SAR');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->app_name = config('nafezly-payments.APP_NAME');
        $this->apple_pay_label = config('nafezly-payments.MOYASAR_APPLE_PAY_LABEL');
        $this->apple_pay_country = config('nafezly-payments.MOYASAR_APPLE_PAY_COUNTRY', 'SA');
        
        // Parse payment methods from config (comma-separated string to array)
        $methods = config('nafezly-payments.MOYASAR_PAYMENT_METHODS', 'creditcard,applepay,stcpay');
        $this->payment_methods = is_array($methods) ? $methods : array_map('trim', explode(',', $methods));
    }

    /**
     * Set Apple Pay Label
     * 
     * @param string $label
     * @return $this
     */
    public function setApplePayLabel($label)
    {
        $this->apple_pay_label = $label;
        return $this;
    }

    /**
     * Set Apple Pay Country
     * 
     * @param string $country
     * @return $this
     */
    public function setApplePayCountry($country)
    {
        $this->apple_pay_country = $country;
        return $this;
    }

    /**
     * Set payment methods dynamically
     * 
     * @param array|string $methods Array of methods or comma-separated string (e.g., ['creditcard', 'stcpay'])
     * @return $this
     */
    public function setPaymentMethods($methods)
    {
        if (is_string($methods)) {
            $methods = array_map('trim', explode(',', $methods));
        }
        $this->payment_methods = $methods;
        return $this;
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
                'label' => $this->apple_pay_label ?? $this->app_name ?? 'Payment',
                'validate_merchant_url' => 'https://api.moyasar.com/v1/applepay/initiate',
                'country' => $this->apple_pay_country ?? 'SA',
            ];
        }

        // إضافة معلومات العميل والـ payment_id في metadata
        $data['metadata'] = [
            'order_id' => $unique_id, // المعرف الفريد من النظام
            'user_id' => $this->user_id,
        ];
        
        // إضافة معلومات إضافية إذا كانت متوفرة
        if ($this->user_email) {
            $data['metadata']['email'] = $this->user_email;
        }
        if ($this->user_first_name) {
            $data['metadata']['first_name'] = $this->user_first_name;
        }
        if ($this->user_last_name) {
            $data['metadata']['last_name'] = $this->user_last_name;
        }
        if ($this->user_phone) {
            $data['metadata']['phone'] = $this->user_phone;
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
            // Debug: Check if secret key is set
            if (empty($this->moyasar_secret_key)) {
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => ['error' => 'MOYASAR_SECRET_KEY is not configured']
                ];
            }

            // Fetch payment details from Moyasar API
            $httpResponse = Http::withBasicAuth($this->moyasar_secret_key, '')
                ->get('https://api.moyasar.com/v1/payments/' . $payment_id);
            
            $response = $httpResponse->json();

            // Check for HTTP errors
            if ($httpResponse->failed()) {
                return [
                    'success' => false,
                    'payment_id' => $payment_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => [
                        'error' => 'API request failed',
                        'status_code' => $httpResponse->status(),
                        'response' => $response,
                        'secret_key_prefix' => substr($this->moyasar_secret_key, 0, 8) . '...' // For debugging only
                    ]
                ];
            }

            if (isset($response['status']) && $response['status'] === 'paid') {
                // استخراج order_id من metadata إذا كان موجوداً
                $order_id = $response['metadata']['order_id'] ?? $payment_id;
                
                return [
                    'success' => true,
                    'payment_id' => $order_id, // استخدام order_id من metadata
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $response
                ];
            } elseif (isset($response['status']) && $response['status'] === 'failed') {
                $order_id = $response['metadata']['order_id'] ?? $payment_id;
                
                return [
                    'success' => false,
                    'payment_id' => $order_id,
                    'message' => __('nafezly::messages.PAYMENT_FAILED'),
                    'process_data' => $response
                ];
            } else {
                $order_id = $response['metadata']['order_id'] ?? $payment_id;
                
                return [
                    'success' => false,
                    'payment_id' => $order_id,
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
        // If source is specified, return only that method
        if ($source === 'stcpay') {
            return ['stcpay'];
        } elseif ($source === 'applepay') {
            return ['applepay'];
        } elseif ($source === 'creditcard') {
            return ['creditcard'];
        }
        
        // Use configured payment methods (set via constructor or setter)
        return $this->payment_methods;
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
