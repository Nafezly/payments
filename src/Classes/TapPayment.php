<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;


class TapPayment extends BaseController implements PaymentInterface
{
    private $tap_secret_key;
    private $tap_public_key;
    private $tap_lang_code;
    private $verify_route_name;

    public function __construct()
    {
        $this->currency = config('nafezly-payments.TAP_CURRENCY');
        $this->tap_secret_key = config('nafezly-payments.TAP_SECRET_KEY');
        $this->tap_public_key = config('nafezly-payments.TAP_PUBLIC_KEY');
        $this->tap_lang_code = config('nafezly-payments.TAP_LANG_CODE');
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
     * @return Application|RedirectResponse|Redirector
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);

        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];

        $this->checkRequiredFields($required_fields, 'Tap');

        $unique_id = uniqid();
        $response = Http::withHeaders([
            "authorization" => "Bearer " . $this->tap_secret_key,
            "content-type" => "application/json",
            'lang_code' => $this->tap_lang_code
        ])->post('https://api.tap.company/v2/charges', [
            "amount" => $this->amount,
            "currency" => $this->currency,
            "threeDSecure" => true,
            "save_card" => false,
            "description" => "Cerdit",
            "statement_descriptor" => "Cerdit",
            "reference" => [
                "transaction" => $unique_id,
                "order" => $unique_id
            ],
            "receipt" => [
                "email" => true,
                "sms" => true
            ], "customer" => [
                "first_name" => $this->user_first_name,
                "middle_name" => "",
                "last_name" => $this->user_last_name,
                "email" => $this->user_email,
                "phone" => [
                    "country_code" => "20",
                    "number" => $this->user_phone
                ]
            ],
            "source" => ["id" => "src_all"],
            "post" => ["url" => route($this->verify_route_name, ['payment' => "tap"])],
            "redirect" => ["url" => route($this->verify_route_name, ['payment' => "tap"])]
        ])->json();

        return [
            'payment_id' => $response['id'],
            'redirect_url' => $response['transaction']['url'],
            'html' => ""
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $response = Http::withHeaders([
            "authorization" => "Bearer " . $this->tap_secret_key,
        ])->get('https://api.tap.company/v2/charges/' . $request->tap_id)->json();
        if (isset($response['status']) && $response['status'] == "CAPTURED") {
            return [
                'success' => true,
                'payment_id' => $request->tap_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        } else {
            return [
                'success' => false,
                'payment_id' => $request->tap_id,
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }
    }
}
