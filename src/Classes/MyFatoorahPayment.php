<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class MyFatoorahPayment extends BaseController implements PaymentInterface
{
    public $myfatoorah_api_key;
    public $myfatoorah_base_url;
    public $verify_route_name;
    public $currency;

    public function __construct()
    {
        $this->myfatoorah_api_key = config('nafezly-payments.MYFATOORAH_API_KEY');
        $this->myfatoorah_base_url = config('nafezly-payments.MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com');
        $this->currency = config('nafezly-payments.MYFATOORAH_CURRENCY', 'USD');
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
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'MyFatoorah');

        if($this->payment_id == null)
            $invoice_id = uniqid() . rand(100000, 999999);
        else
            $invoice_id = $this->payment_id;

        $currency = $this->currency ?? 'USD';

        $data = [
            'InvoiceValue' => $this->amount,
            'NotificationOption'=>"LNK",
            'CurrencyIso' => $currency,
            'CallBackUrl' => route($this->verify_route_name, ['payment' => 'myfatoorah', 'payment_id' => $invoice_id]),
            'ErrorUrl' => route($this->verify_route_name, ['payment' => 'myfatoorah', 'payment_id' => $invoice_id]),
            'CustomerName' => $this->user_first_name . ' ' . $this->user_last_name,
            'CustomerEmail' => $this->user_email,
            'CustomerMobile' => !empty($this->user_phone) ? substr($this->user_phone, -11) : null,
            'InvoiceItems' => [
                [
                    'ItemName' => 'Payment',
                    'Quantity' => 1,
                    'UnitPrice' => $this->amount
                ]
            ],
            'Language' => $this->getMyFatoorahLanguage(),
            'DisplayCurrencyIso' => $currency
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
            'Content-Type' => 'application/json'
        ])->post($this->myfatoorah_base_url . '/v2/SendPayment', $data);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['IsSuccess']) && $responseData['IsSuccess']) {
            $payment_url = $responseData['Data']['InvoiceURL'] ?? null;
            $myfatoorah_invoice_id = $responseData['Data']['InvoiceId'] ?? null;
            
            if ($payment_url && $myfatoorah_invoice_id) {
                // Store the MyFatoorah InvoiceId to use it later in verification
                Cache::put('myfatoorah_invoice_' . $invoice_id, $myfatoorah_invoice_id, now()->addHours(72));
                
                return [
                    'payment_id' => $invoice_id,
                    'redirect_url' => $payment_url,
                    'html' => ''
                ];
            }
        }

        return [
            'payment_id' => $invoice_id,
            'redirect_url' => '',
            'html' => $responseData,
            'success' => false,
            'message' => $responseData['Message'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $responseData
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        // MyFatoorah callback sends paymentId in the callback URL
        // We also store our internal invoice_id in the route
        $payment_id_internal = $request->input('paymentId');
        $invoice_id = $request->route('payment_id') ?? $request->input('payment_id');
        
        // Try to get MyFatoorah InvoiceId from cache (stored during pay())
        $myfatoorah_invoice_id = null;
        if ($invoice_id) {
            $myfatoorah_invoice_id = Cache::get('myfatoorah_invoice_' . $invoice_id);
        }

        // Determine which key to use for GetPaymentStatus
        // Priority: paymentId (from callback) > cached InvoiceId > invoice_id (our internal ID)
        $key = $payment_id_internal ?? $myfatoorah_invoice_id ?? $invoice_id;
        $keyType = $payment_id_internal ? 'PaymentId' : 'InvoiceId';

        if (!$key) {
            return [
                'success' => false,
                'payment_id' => $invoice_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }

        // Get payment status from MyFatoorah
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->myfatoorah_api_key,
            'Content-Type' => 'application/json'
        ])->post($this->myfatoorah_base_url . '/v2/GetPaymentStatus', [
            'Key' => $key,
            'KeyType' => $keyType
        ]);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['IsSuccess']) && $responseData['IsSuccess']) {
            $invoice_status = $responseData['Data']['InvoiceStatus'] ?? null;
            $invoice_transactions = $responseData['Data']['InvoiceTransactions'] ?? [];
            
            // Check if payment is successful
            // MyFatoorah returns 'Paid' status for successful payments
            // TransactionStatus can be 'Success' or 'Succeeded' (case may vary)
            $is_success = false;
            
            if ($invoice_status === 'Paid') {
                $is_success = true;
            } elseif (!empty($invoice_transactions) && isset($invoice_transactions[0]['TransactionStatus'])) {
                $transaction_status = $invoice_transactions[0]['TransactionStatus'];
                if (in_array(strtolower($transaction_status), ['success', 'succeeded', 'paid'])) {
                    $is_success = true;
                }
            }
            
            if ($is_success) {
                // Clear cache after successful verification
                // if ($invoice_id) {
                //     Cache::forget('myfatoorah_invoice_' . $invoice_id);
                // }
                
                return [
                    'success' => true,
                    'payment_id' => $invoice_id ?? $payment_id_internal,
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $responseData
                ];
            }
        }

        return [
            'success' => false,
            'payment_id' => $invoice_id ?? $payment_id_internal,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $responseData ?? $request->all()
        ];
    }

    /**
     * Get language in MyFatoorah format (AR, EN)
     *
     * @return string
     */
    protected function getMyFatoorahLanguage()
    {
        return strtoupper($this->language);
    }
}

