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


class PayrexxPayment extends BaseController implements PaymentInterface
{
    private $payrexx_api_key;
    private $verify_route_name;
    private $payrexx_instance_name;

    public function __construct()
    { 
        $this->payrexx_instance_name = config('nafezly-payments.PAYREXX_INSTANCE_NAME');
        $this->payrexx_api_key = config('nafezly-payments.PAYREXX_API_KEY');
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


    $instance = $this->payrexx_instance_name;
    $apiSecret = $this->payrexx_api_key;
    $params = [
        'amount' => 100,
        'currency' => 'USD',
        // Add other parameters as needed
    ];

    $apiEndpoint = 'https://api.payrexx.com/v1/Gateway/0';
    $url = "{$apiEndpoint}?instance={$instance}";

    $data = [
        'ApiSignature' => base64_encode(hash_hmac('sha256', '', $apiSecret, true)),
        'amount' => $params['amount'],
        'currency' => $params['currency'] ?? 'USD', // Set the default currency if not provided
        // Add other parameters as needed
    ];

    $response = Http::post($url, $data);
    dd($response->json());
    return $response->json();


        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);

        $required_fields = ['amount'/*, 'user_first_name', 'user_last_name', 'user_email', 'user_phone'*/];
        $this->checkRequiredFields($required_fields, 'PAYREXX');
        $unique_id = uniqid();


        $apiEndpoint = "https://api.payrexx.com/v1.0/Gateway/";
        $instanceName = $this->payrexx_instance_name;
        $apiSecret =$this->payrexx_api_key;



        /*$apiSignature = base64_encode(hash_hmac('sha256',"" , $apiSecret, true));
        $response = Http::get("https://api.payrexx.com/v1.0/SignatureCheck/?instance={$instanceName}&ApiSignature={$apiSignature}")->json();
        dd($response);
*/

        $data = [
            'amount' => $this->amount,
            'currency' => $this->currency??"USD",
            'sku' => $unique_id,
            'pm' => ['visa', 'mastercard', 'twint'],
            'preAuthorization' => 0,
            'reservation' => 0,
            'referenceId' => $unique_id,
            'fields' => [
                'forename' => ['value' => $this->user_first_name??"Test"],
                'surname' => ['value' => $this->user_last_name??"Test"],
                'email' => ['value' => $this->user_email??"test@test.com"]
            ],
            'successRedirectUrl' => route($this->verify_route_name, ['payment' => "payrexx"]),
            'failedRedirectUrl' => route($this->verify_route_name, ['payment' => "payrexx"]),
            'basket' => [
                ['name' => 'Product', 'amount' => 8000, 'quantity' => 1, 'vatRate' => 7.7],
                ['name' => 'Shipping Costs', 'amount' => 925, 'quantity' => 1, 'vatRate' => 0]
            ]
        ];
        $apiSignature =base64_encode(hash_hmac('sha256', http_build_query($data, null, '&') , $apiSecret, true));
        $data['ApiSignature'] = $apiSignature; 
        $response = Http::asForm()->post("{$apiEndpoint}?instance={$instanceName}", $data)->json();
        dd($response);

        try {
            return [
                'payment_id' => $response['id'],
                'redirect_url' => $response['transaction']['url'],
                'process_data' => $response,
                'html' => ""
            ];
        } catch (\Throwable $th) {
            return $response;
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->tap_secret_key,
            "Content-Type" => "application/json",
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





    public function generateSignature($queryString, $apiSecret)
    {
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        return base64_encode($signature);
    }



    public function flattenArray($array, $parentKey = '')
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $parentKey ? "{$parentKey}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

 

}
