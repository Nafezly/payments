<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class PayzinkDirectPayment extends PayzinkPayment implements PaymentInterface
{
    /**
     * Process a card payment via Payzink Direct API (POST /api/v1/payment/card).
     *
     * Pass card details through setSource():
     * [
     *   'pan' => '4111111111111111',
     *   'expiryYear' => '2038',
     *   'expiryMonth' => '05',
     *   'cvv' => '123',
     *   'cardHolderName' => 'John Doe',
     *   'zipCode' => '34517',        // optional
     *   'ip' => '81.214.125.134',    // optional, recommended
     * ]
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
        $this->checkRequiredFields(['amount'], 'Payzink Direct');

        if (!is_array($this->source)) {
            throw new MissingPaymentInfoException('source (card details)', 'Payzink Direct');
        }

        $card = $this->resolveCardDetails();
        foreach (['pan', 'expiryYear', 'expiryMonth', 'cvv', 'cardHolderName'] as $field) {
            if (empty($card[$field])) {
                throw new MissingPaymentInfoException($field, 'Payzink Direct');
            }
        }

        if ($this->payment_id == null) {
            $unique_id = uniqid() . rand(100000, 999999);
        } else {
            $unique_id = $this->payment_id;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return $this->failedPayResponse($unique_id);
        }

        $verifyUrl = route($this->verify_route_name, ['payment' => 'payzinkdirect', 'payment_id' => $unique_id]);

        $payload = [
            'order' => [
                'action' => $this->payzink_action,
                'amount' => [
                    'currencyCode' => strtoupper($this->currency),
                    'value' => $this->toMinorUnits($this->amount),
                ],
            ],
            'payment' => $card,
            'extra' => [
                'orderId' => $unique_id,
            ],
            '_links' => [
                'callbackUrl' => $verifyUrl,
                'notificationUrl' => $this->payzink_webhook_url ?: $verifyUrl,
            ],
        ];

        if (!empty($this->source['reference'])) {
            $payload['reference'] = $this->source['reference'];
        }

        $customer = $this->buildDirectCustomerPayload();
        if (!empty($customer)) {
            $payload['customer'] = $customer;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->payzink_base_url . '/api/v1/payment/card', $payload)->json();

        $result = $response['result'] ?? null;
        if (!$result || empty($result['reference'])) {
            return [
                'payment_id' => $unique_id,
                'redirect_url' => '',
                'html' => $response,
                'success' => false,
                'message' => $response['message'] ?? ($result['statusMessage'] ?? __('nafezly::messages.PAYMENT_FAILED')),
                'process_data' => $response,
            ];
        }

        $reference = $result['reference'];
        Cache::put('payzink_reference_' . $unique_id, $reference, now()->addHours(48));

        $state = $result['state'] ?? null;

        if ($state === 'AWAIT_3DS') {
            $threeDsUrl = $result['_links']['payment:3ds']['href'] ?? '';

            return [
                'payment_id' => $reference,
                'redirect_url' => $threeDsUrl,
                'html' => '',
                'process_data' => $response,
            ];
        }

        if (in_array($state, ['PURCHASED', 'AUTHORISED'], true)) {
            return [
                'payment_id' => $reference,
                'redirect_url' => '',
                'html' => '',
                'success' => true,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response,
            ];
        }

        return [
            'payment_id' => $reference,
            'redirect_url' => '',
            'html' => $response,
            'success' => false,
            'message' => $result['statusMessage'] ?? __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response,
        ];
    }

    protected function resolveCardDetails(): array
    {
        $source = $this->source;

        $cardHolderName = $source['cardHolderName']
            ?? $source['card_holder_name']
            ?? trim(($this->user_first_name ?? '') . ' ' . ($this->user_last_name ?? ''));

        return [
            'pan' => preg_replace('/\s+/', '', (string) ($source['pan'] ?? $source['card_number'] ?? '')),
            'expiryYear' => (string) ($source['expiryYear'] ?? $source['expiry_year'] ?? ''),
            'expiryMonth' => str_pad((string) ($source['expiryMonth'] ?? $source['expiry_month'] ?? ''), 2, '0', STR_PAD_LEFT),
            'cvv' => (string) ($source['cvv'] ?? $source['cvc'] ?? ''),
            'cardHolderName' => trim($cardHolderName),
        ];
    }

    protected function buildDirectCustomerPayload(): array
    {
        $customer = [];

        if ($this->user_email) {
            $customer['email'] = $this->user_email;
        }

        if ($this->user_phone) {
            $customer['phoneNumber'] = $this->user_phone;
        }

        if (!empty($this->source['zipCode'])) {
            $customer['zipCode'] = $this->source['zipCode'];
        } elseif (!empty($this->source['zip_code'])) {
            $customer['zipCode'] = $this->source['zip_code'];
        }

        if (!empty($this->source['ip'])) {
            $customer['ip'] = $this->source['ip'];
        }

        return $customer;
    }

    protected function failedPayResponse(string $unique_id): array
    {
        return [
            'payment_id' => $unique_id,
            'redirect_url' => '',
            'html' => '',
            'success' => false,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
        ];
    }
}
