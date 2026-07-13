<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class LahzaPayment extends BaseController implements PaymentInterface
{
    private $secret_key;
    private $public_key;
    private $mode;
    private $base_url;
    public $verify_route_name;

    public function __construct()
    {
        $this->secret_key = config('nafezly-payments.LAHZA_SECRET_KEY');
        $this->public_key = config('nafezly-payments.LAHZA_PUBLIC_KEY');
        $this->currency = config('nafezly-payments.LAHZA_CURRENCY', 'ILS');
        $this->mode = config('nafezly-payments.LAHZA_MODE', 'test');
        $this->base_url = rtrim((string) config('nafezly-payments.LAHZA_BASE_URL', 'https://api.lahza.io'), '/');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
    }

    /**
     * Initialize a Lahza transaction and return the hosted checkout URL.
     *
     * Lahza requires the amount in the lowest currency unit (multiply by 100).
     * The unique reference we pass as `ref` is echoed back by Lahza as
     * `data.reference` in both the verify response and the webhook payload,
     * and is the only key CartController::payment_verify uses to locate the
     * local payment row (Payment::where('token_id', ...)).
     *
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
        $required_fields = ['amount', 'user_email'];
        $this->checkRequiredFields($required_fields, 'Lahza');

        $unique_id = $this->payment_id ?? uniqid('lahza_') . rand(100000, 999999);

        $currencyCode = strtoupper(trim((string) ($this->currency ?? 'ILS')));

        // Lahza accepts amounts in the lowest currency unit (agora/cents).
        $lowestUnitAmount = (int) round(((float) $this->amount) * 100);

        // Email is required by Lahza; fall back to a generated address from the phone.
        $email = $this->user_email;
        if (empty($email)) {
            $email = ($this->user_phone ?? 'guest') . '@lahza.guest';
        }

        $payload = [
            'email' => $email,
            'amount' => (string) $lowestUnitAmount,
            'currency' => $currencyCode,
            'ref' => $unique_id,
            'callback_url' => route($this->verify_route_name, ['payment' => 'lahza', 'payment_id' => $unique_id]),
        ];

        if (! empty($this->user_phone)) {
            $payload['mobile'] = (string) $this->user_phone;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
            ])->timeout(15)->connectTimeout(5)->post($this->base_url . '/transaction/initialize', $payload);

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            if ($response->successful() && ($responseData['status'] ?? false) && ! empty($responseData['data']['authorization_url'])) {
                return [
                    'payment_id' => $unique_id,
                    'redirect_url' => $responseData['data']['authorization_url'],
                    'html' => '',
                ];
            }

            Log::warning('Lahza payment response: initialize_failed', [
                'mode' => $this->mode,
                'base_url' => $this->base_url,
                'payment_id' => $unique_id,
                'currency' => $currencyCode,
                'http_status' => $response->status(),
                'lahza_response' => $responseData,
            ]);

            return [
                'payment_id' => null,
                'redirect_url' => '',
                'html' => __('nafezly::messages.LAHZA_PAYMENT_FAILED'),
            ];
        } catch (\Exception $e) {
            Log::error('Lahza checkout exception', [
                'payment_id' => $unique_id,
                'mode' => $this->mode,
                'currency' => $currencyCode,
                'error' => $e->getMessage(),
            ]);

            return [
                'payment_id' => null,
                'redirect_url' => '',
                'html' => __('nafezly::messages.LAHZA_PAYMENT_FAILED'),
            ];
        }
    }

    /**
     * Verify a Lahza payment via webhook (POST) or redirect callback (GET).
     *
     * Both paths return the same array shape used by CartController::payment_verify.
     * The `payment_id` we return MUST be our own `ref` (Lahza echoes it back as
     * `data.reference`), never Lahza's internal transaction id.
     *
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        // POST = Lahza webhook event (charge.success).
        if ($request->isMethod('post')) {
            return $this->verifyWebhook($request);
        }

        // GET = user redirected back from Lahza hosted checkout.
        return $this->verifyRedirectCallback($request);
    }

    /**
     * Handle a Lahza webhook (POST to the verify route).
     *
     * Lahza signs the raw payload with HMAC-SHA256 using the secret key and
     * sends the hex digest in the `x-lahza-signature` header.
     *
     * @param Request $request
     * @return array
     */
    private function verifyWebhook(Request $request): array
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-lahza-signature');

        if (empty($signature) || empty($this->secret_key)) {
            return [
                'success' => false,
                'payment_id' => '',
                'message' => __('nafezly::messages.LAHZA_INVALID_SIGNATURE'),
                'process_data' => [],
            ];
        }

        $computed = hash_hmac('sha256', $rawPayload, $this->secret_key);

        if (! hash_equals($computed, (string) $signature)) {
            Log::warning('Lahza webhook signature mismatch', [
                'mode' => $this->mode,
            ]);

            return [
                'success' => false,
                'payment_id' => '',
                'message' => __('nafezly::messages.LAHZA_INVALID_SIGNATURE'),
                'process_data' => [],
            ];
        }

        $data = json_decode($rawPayload, true);
        $data = is_array($data) ? $data : [];

        $event = $data['event'] ?? '';
        $transaction = is_array($data['data'] ?? null) ? $data['data'] : [];
        $reference = $transaction['reference'] ?? '';
        $status = $transaction['status'] ?? '';

        if ($event === 'charge.success' && $status === 'success' && ! empty($reference)) {
            return [
                'success' => true,
                'payment_id' => $reference,
                'message' => __('nafezly::messages.LAHZA_PAYMENT_SUCCESS'),
                'process_data' => $transaction,
            ];
        }

        Log::warning('Lahza webhook event not a successful charge', [
            'mode' => $this->mode,
            'event' => $event,
            'status' => $status,
            'reference' => $reference,
        ]);

        return [
            'success' => false,
            'payment_id' => $reference,
            'message' => __('nafezly::messages.LAHZA_PAYMENT_FAILED'),
            'process_data' => $data,
        ];
    }

    /**
     * Handle the GET redirect callback from Lahza hosted checkout.
     *
     * Lahza appends `?reference=...` to the callback_url. We call the verify
     * endpoint from the server (never expose the secret key to the frontend)
     * to confirm the final transaction status.
     *
     * @param Request $request
     * @return array
     */
    private function verifyRedirectCallback(Request $request): array
    {
        $reference = $request->query('reference') ?: $request->input('payment_id');

        if (empty($reference)) {
            return [
                'success' => false,
                'payment_id' => '',
                'message' => __('nafezly::messages.LAHZA_PAYMENT_FAILED'),
                'process_data' => $request->all(),
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Cache-Control' => 'no-cache',
            ])->timeout(15)->connectTimeout(5)->get($this->base_url . '/transaction/verify/' . urlencode($reference));

            $responseData = $response->json();
            $responseData = is_array($responseData) ? $responseData : [];

            $apiStatus = $responseData['status'] ?? false;
            $transaction = is_array($responseData['data'] ?? null) ? $responseData['data'] : [];
            $transactionStatus = $transaction['status'] ?? '';
            $returnedReference = $transaction['reference'] ?? $reference;

            // Top-level `status` is the API call status; `data.status` is the transaction status.
            if ($apiStatus === true && $transactionStatus === 'success') {
                return [
                    'success' => true,
                    'payment_id' => $returnedReference,
                    'message' => __('nafezly::messages.LAHZA_PAYMENT_SUCCESS'),
                    'process_data' => $transaction,
                ];
            }

            Log::warning('Lahza verify returned unsuccessful status', [
                'mode' => $this->mode,
                'base_url' => $this->base_url,
                'reference' => $reference,
                'http_status' => $response->status(),
                'api_status' => $apiStatus,
                'transaction_status' => $transactionStatus,
                'lahza_response' => $responseData,
            ]);

            return [
                'success' => false,
                'payment_id' => $returnedReference,
                'message' => __('nafezly::messages.LAHZA_PAYMENT_FAILED'),
                'process_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Lahza verify exception', [
                'reference' => $reference,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'payment_id' => $reference,
                'message' => $e->getMessage(),
                'process_data' => [],
            ];
        }
    }
}
