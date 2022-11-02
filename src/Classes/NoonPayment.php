<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Nafezly\Payments\Helper\CurlHelper;
use Nafezly\Payments\Traits\SetNoonPaymentVariables;

class NoonPayment extends BaseController implements PaymentInterface
{
    use SetNoonPaymentVariables;

    protected $noon_payment_api;

    protected $noon_payment_channel;

    protected $order_payment_order_category;

    protected $noon_payment_return_url;

    protected $noon_payment_tokenizeCc;

    protected $noon_payment_payment_action;

    public function __construct()
    {
        $this->noon_payment_api = config("nafezly-payments.NOON_PAYMENT_PAYMENT_API");
        $this->noon_payment_channel = config("nafezly-payments.NOON_PAYMENT_CHANNEL");
        $this->order_payment_order_category = config("nafezly-payments.NOON_PAYMENT_ORDER_CATEGORY");
        $this->noon_payment_return_url = config('nafezly-payments.NOON_PAYMENT_RETURN_URL');
        $this->noon_payment_tokenizeCc = "true";
        $this->noon_payment_payment_action = "SALE";
    }

    /**
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return string[]
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $paymentInfo['apiOperation'] = "INITIATE";
        $paymentInfo['order']['reference'] = $unique_id = uniqid();;
        $paymentInfo['order']['amount'] = $this->amount;
        $paymentInfo['order']['currency'] = $this->currency;
        $paymentInfo['order']['name'] = $this->order_name;
        $paymentInfo['order']['channel'] = $this->noon_payment_channel;
        $paymentInfo['order']['category'] = $this->order_payment_order_category;
        // Options for tokenize cc are (true - false)
        $paymentInfo['configuration']['tokenizeCc'] = $this->noon_payment_tokenizeCc;
        $paymentInfo['configuration']['returnUrl'] = $this->noon_payment_return_url;
        // Options for payment action are (AUTHORIZE - SALE)
        $paymentInfo['configuration']['paymentAction'] = $this->noon_payment_payment_action;
        $paymentInfo['configuration']['locale'] = $this->configuration_local;

        $response = json_decode(CurlHelper::post(
            $this->noon_payment_api . "order",
            $paymentInfo,
            $this->getHeaders()
        ));

        return [
            'payment_id' => $unique_id,
            'redirect_url' => $response->result->checkoutData->postUrl
        ];
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        $response = json_decode(CurlHelper::get(config("nafezly-payments.NOON_PAYMENT_PAYMENT_API") . "order/" . $request->orderId, $this->getHeaders()));

        if ($this->isSaleTransactionSuccess($response)) {
            return [
                'success' => true,
                'payment_id' => $response->result->order->reference,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        }

        return [
            'success' => false,
            'payment_id' => $response->result->order->reference,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response
        ];
    }

    private function getHeaders()
    {
        return [
            "Content-type: application/json",
            "Authorization: Key_" . config("nafezly-payments.NOON_PAYMENT_MODE") . " " . config("nafezly-payments.NOON_PAYMENT_AUTH_KEY"),
        ];
    }

    private function isSaleTransactionSuccess($response)
    {
        return isset($response->result->transactions) &&
            is_array($response->result->transactions) &&
            $response->result->transactions[0]->type == "SALE" &&
            $response->result->transactions[0]->status == "SUCCESS";
    }
}
