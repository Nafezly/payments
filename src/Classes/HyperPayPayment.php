<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;


class HyperPayPayment extends BaseController implements PaymentInterface
{
    private $hyperpay_url;
    public $hyperpay_base_url;
    private $hyperpay_token;
    private $hyperpay_credit_id;
    private $hyperpay_mada_id;
    private $hyperpay_apple_id;
    public $app_name;
    public $verify_route_name;
    public $payment_id;

    public function __construct()
    {
        $this->hyperpay_url = config('nafezly-payments.HYPERPAY_URL');
        $this->hyperpay_base_url = config('nafezly-payments.HYPERPAY_BASE_URL');
        $this->hyperpay_token = config('nafezly-payments.HYPERPAY_TOKEN');
        $this->currency = config('nafezly-payments.HYPERPAY_CURRENCY');
        $this->hyperpay_credit_id = config('nafezly-payments.HYPERPAY_CREDIT_ID');
        $this->hyperpay_mada_id = config('nafezly-payments.HYPERPAY_MADA_ID');
        $this->hyperpay_apple_id = config('nafezly-payments.HYPERPAY_APPLE_ID');
        $this->app_name = config('nafezly-payments.APP_NAME');
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
     * @return array|string
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'HYPERPAY');

        $data = http_build_query([
            'entityId' => $this->getEntityId($this->source),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paymentType' => 'DB',
            'merchantTransactionId' => uniqid(),
            'billing.street1' => 'riyadh',
            'billing.city' => 'riyadh',
            'billing.state' => 'riyadh',
            'billing.country' => 'SA',
            'billing.postcode' => '123456',
            'customer.email' => $this->user_email,
            'customer.givenName' => $this->user_first_name,
            'customer.surname' => $this->user_last_name,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->hyperpay_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ' . $this->hyperpay_token
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        $this->payment_id = json_decode($responseData)->id;
        Cache::forever($this->payment_id . '_source', $this->source);
        return [
            'payment_id' => $this->payment_id, 
            'html' => $this->generate_html(),
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array|string
     */
    public function verify(Request $request)
    {
        $source = Cache::get($request['id'] . '_source');
        Cache::forget($request['id'] . '_source');
        $entityId = $this->getEntityId($source);
        $url = $this->hyperpay_url . "/" . $request['id'] . "/payment" . "?entityId=" . $entityId;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->hyperpay_token
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        $final_result = (array)json_decode($responseData, true);
        if (in_array($final_result["result"]["code"], ["000.000.000", "000.100.110", "000.100.111", "000.100.112"])) {
            return [
                'success' => true,
                'payment_id'=>$request['id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $final_result
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>$request['id'],
                'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $final_result["result"]["code"]]),
                'process_data' => $final_result
            ];
        }
    }

    public function generate_html(): string
    {
        return view('nafezly::html.hyper_pay', ['model' => $this, 'brand' => $this->getBrand()])->render();
    }

    private function getEntityId($source)
    {

        switch ($source) {
            case "CREDIT":
                return $this->hyperpay_credit_id;
            case "MADA":
                return $this->hyperpay_mada_id;
            case "APPLE":
                return $this->hyperpay_apple_id;
            default:
                return "";
        }
    }

    private function getBrand()
    {
        $form_brands = "VISA MASTER";
        if ($this->source == "MADA"){
            $form_brands = "MADA";
        }elseif ($this->source == "APPLE"){
            $form_brands = "APPLEPAY";
        }
        return $form_brands;
    }
}


