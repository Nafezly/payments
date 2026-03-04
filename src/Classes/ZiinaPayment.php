<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class ZiinaPayment extends BaseController implements PaymentInterface
{
    public $ziina_api_key;
    public $ziina_base_url;
    public $verify_route_name;
    public $currency;

    public function __construct()
    {
        $this->ziina_api_key = config('nafezly-payments.ZIINA_API_KEY');
        $this->ziina_base_url = config('nafezly-payments.ZIINA_BASE_URL', 'https://api-v2.ziina.com/api');
        $this->currency = config('nafezly-payments.ZIINA_CURRENCY', 'AED');
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
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'Ziina');

        if ($this->payment_id == null) {
            $invoice_id = uniqid() . rand(100000, 999999);
        } else {
            $invoice_id = $this->payment_id;
        }

        $currency = $this->currency ?? 'AED';

        // Ziina expects amount in base units (e.g., $10.50 = 1050 cents, 10.50 AED = 1050 fils)
        $amount_in_base_units = (int) round($this->amount * 100);

        $verify_url = route($this->verify_route_name, ['payment' => 'ziina', 'payment_id' => $invoice_id]);

        $data = [
            'amount' => $amount_in_base_units,
            'currency_code' => $currency,
            'success_url' => $verify_url,
            'cancel_url' => $verify_url,
            'failure_url' => $verify_url,
            'test' => config('nafezly-payments.ZIINA_TEST', false),
        ];

        if (!empty($this->user_first_name) || !empty($this->user_last_name)) {
            $data['message'] = trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->ziina_api_key,
            'Content-Type' => 'application/json',
        ])->post($this->ziina_base_url . '/payment_intent', $data);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['id']) && isset($responseData['redirect_url'])) {
            $payment_intent_id = $responseData['id'];
            $redirect_url = $responseData['redirect_url'];

            // Store Ziina payment intent id for verification (callback uses our invoice_id)
            Cache::put('ziina_intent_' . $invoice_id, $payment_intent_id, now()->addHours(24));

            return [
                'payment_id' => $invoice_id,
                'redirect_url' => $redirect_url,
                'html' => '',
            ];
        }

        return [
            'payment_id' => $invoice_id,
            'redirect_url' => '',
            'html' => '',
            'success' => false,
            'message' => $responseData['latest_error']['message'] ?? $responseData['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $responseData,
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $invoice_id = $request->route('payment_id') ?? $request->input('payment_id') ?? $request->input('id');

        if (!$invoice_id) {
            return [
                'success' => false,
                'payment_id' => '',
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        // Get Ziina payment intent id from cache (stored during pay())
        $payment_intent_id = Cache::get('ziina_intent_' . $invoice_id) ?? $invoice_id;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->ziina_api_key,
            'Content-Type' => 'application/json',
        ])->get($this->ziina_base_url . '/payment_intent/' . $payment_intent_id);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['status'])) {
            if ($responseData['status'] === 'completed') {
                return [
                    'success' => true,
                    'payment_id' => $invoice_id,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $responseData,
                ];
            }
        }

        return [
            'success' => false,
            'payment_id' => $invoice_id,
            'message' => $responseData['latest_error']['message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $responseData ?? $request->all(),
        ];
    }
}
