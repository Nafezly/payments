<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;

class PayPalCreditPayment extends BaseController implements PaymentInterface
{
    private string $paypal_credit_client_id;

    private string $paypal_credit_secret;

    private string $verify_route_name;

    public string $paypal_credit_mode;

    public $currency;

    public array $custom_values = [];

    public function __construct()
    {
        $this->paypal_credit_client_id = config('nafezly-payments.PAYPAL_CREDIT_CLIENT_ID');
        $this->paypal_credit_secret = config('nafezly-payments.PAYPAL_CREDIT_SECRET');
        $this->verify_route_name = config('nafezly-payments.VERIFY_ROUTE_NAME');
        $this->paypal_credit_mode = config('nafezly-payments.PAYPAL_CREDIT_MODE');
        $this->currency = config('nafezly-payments.PAYPAL_CREDIT_CURRENCY');
    }

    /**
     * @param  null  $user_id
     * @param  null  $user_first_name
     * @param  null  $user_last_name
     * @param  null  $user_email
     * @param  null  $user_phone
     * @param  null  $source
     *
     * @throws MissingPaymentInfoException
     * @throws ConnectionException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array|Redirector|RedirectResponse|Application
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'PayPal');

        $country = [
            'regionName' => '',
            'countryName' => '',
            'cityName' => '',
            'zipCode' => '',
            'countryCode' => '',
        ];
        try {
            $fetch_address = Http::get('https://freeipapi.com/api/json/'.$this->get_ip())->json();
            if (is_array($fetch_address)) {
                $country = array_replace_recursive($country, $fetch_address);
            }
        } catch (\Exception $e) {
        }

        $mode = $this->paypal_credit_mode == 'live' ? '' : '.sandbox';
        $order_id = uniqid().rand(1000, 99999);
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $order_id,
                    'amount' => [
                        'currency_code' => $this->currency ?? 'USD',
                        'value' => sprintf('%0.2f', $this->amount),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'UNRESTRICTED',
                        'locale' => 'ar-SA',
                        'shipping_preference' => $this->source ?? 'NO_SHIPPING',
                        'return_url' => route($this->verify_route_name, ['payment' => 'paypal_credit']),
                        'cancel_url' => route($this->verify_route_name, ['payment' => 'paypal_credit']),
                    ],
                ],
            ],
            'payer' => [
                'email_address' => $this->user_email ?? '',
                'name' => [
                    'given_name' => $this->user_first_name ?? '',
                    'surname' => $this->user_last_name ?? '',
                ],
                'birth_date' => '1996-'.date('m-d'),
                'address' => [
                    'address_line_1' => $this->remove_special_characters($country['countryNameName']).', '.$this->remove_special_characters($country['cityName']).', '.$this->remove_special_characters($country['countryName']),
                    'admin_area_1' => $this->remove_special_characters($country['cityName']),
                    'admin_area_2' => $this->remove_special_characters($country['cityName']),
                    'address_line_2' => $this->remove_special_characters($country['cityName']).', '.$this->remove_special_characters($country['countryName']),
                    'city' => $this->remove_special_characters($country['cityName']) ?? '',
                    'postal_code' => $this->remove_special_characters($country['zipCode']) != '' ? $this->remove_special_characters($country['zipCode']) : '12271',
                    'country_code' => $this->remove_special_characters($country['countryCode']),
                ],
            ],
        ];
        if ($this->user_phone != null) {
            $data['payer']['phone'] = [
                'phone_type' => 'MOBILE',
                'phone_number' => [
                    'national_number' => $this->user_phone,
                ],
            ];
        }
        $data = array_replace_recursive($data, $this->custom_values);
        //dd($data);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en_US',
            'Authorization' => 'Basic '.base64_encode($this->paypal_credit_client_id.':'.$this->paypal_credit_secret),
        ])->post('https://api-m'.$mode.'.paypal.com/v2/checkout/orders', $data);

        if ($response->ok()) {
            $response = $response->json();

            return [
                'payment_id' => $response['id'],
                'html' => /*$response*/ $this->generate_html([
                    'response' => $response,
                    'currency' => $this->currency ?? 'USD',
                    'return_url' => route($this->verify_route_name, ['payment' => 'paypal_credit']),
                    'paypal_client_id' => $this->paypal_credit_client_id,
                ]),
                'redirect_url' => '',
            ];
        }

        return [
            'payment_id' => $order_id,
            'html' => $response->json(),
            'redirect_url' => '',
        ];
    }

    public function verify(Request $request): array
    {
        $mode = $this->paypal_credit_mode == 'live' ? '' : '.sandbox';

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($this->paypal_credit_client_id.':'.$this->paypal_credit_secret),
        ])->post('https://api-m'.$mode.'.paypal.com/v2/checkout/orders/'.$request['order_id'].'/capture', [
            'application_context' => [
                'return_url' => route($this->verify_route_name, ['payment' => 'paypal_credit']),
                'cancel_url' => route($this->verify_route_name, ['payment' => 'paypal_credit']),
            ],
        ]);
        $json_response = $response->json();
        if (
            (isset($json_response['status']) && $json_response['status'] == 'COMPLETED') ||
            (isset($json_response['details'][0]['issue']) && $json_response['details'][0]['issue'] == 'ORDER_ALREADY_CAPTURED')
        ) {
            return [
                'success' => true,
                'payment_id' => $request['order_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $json_response,

            ];
            /*return array_merge($json_response,[
                'success' => true,
                'payment_id'=>$request['order_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $json_response,

            ]);*/
        }

        return [
            'success' => false,
            'payment_id' => $request->order_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $json_response,
        ];
    }

    private function generate_html($data): string
    {
        return view('nafezly::html.paypal-credit', ['data' => $data])->render();
    }

    public function get_ip()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (request()->ip() != null) {
            $ipaddress = request()->ip();
        } else {
            $ipaddress = 'UNKNOWN';
        }
        if ($ipaddress == '127.0.0.1') {
            $ip = Http::get('https://api.ipify.org/?format=json')->json();

            return $ip['ip'];
        }

        return $ipaddress;
    }

    public function remove_special_characters($string): string
    {
        $t = preg_replace('/[^A-Za-z0-9\s]/', '', $string);

        return substr($t, 0, 230);
    }

    public function setCustomValues($values = []): static
    {
        $this->custom_values = $values;

        return $this;
    }
}
