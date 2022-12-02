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

    protected $unique_id;

    public function __construct()
    {
        $this->noon_payment_api = config("nafezly-payments.NOON_PAYMENT_PAYMENT_API");
        $this->noon_payment_channel = config("nafezly-payments.NOON_PAYMENT_CHANNEL");
        $this->order_payment_order_category = config("nafezly-payments.NOON_PAYMENT_ORDER_CATEGORY");
        $this->noon_payment_return_url = config('nafezly-payments.NOON_PAYMENT_RETURN_URL');
        $this->noon_payment_tokenizeCc = "true";
        $this->noon_payment_payment_action = "AUTHORIZE,SALE";
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
        $paymentInfo = $this->setPaymentBasicInfo();

        $response = json_decode(CurlHelper::post(
            $this->noon_payment_api . "order",
            $paymentInfo,
            $this->getHeaders()
        ));

        return [
            'payment_id' => $this->unique_id,
            'redirect_url' => $response->result->checkoutData->postUrl,
            'process_data' => $response
        ];
    }

    public function subscriptionPay(): array
    {
        $paymentInfo = $this->setPaymentSubscriptionInfo();

        $response = json_decode(CurlHelper::post(
            $this->noon_payment_api . "order",
            $paymentInfo,
            $this->getHeaders()
        ));

        return [
            'payment_id' => $this->unique_id,
            'subscription_identifier' => $response->result->subscription->identifier,
            'redirect_url' => $response->result->checkoutData->postUrl,
            'process_data' => $response
        ];
    }


    public function subsequentTransactionPay(): array
    {
        $paymentInfo = $this->setSubsequentTransactionInfo();

        $response = json_decode(CurlHelper::post(
            $this->noon_payment_api . "order",
            $paymentInfo,
            $this->getHeaders()
        ));

        if ($this->isSubsequentTransactionSuccess($response)) {
            return [
                'success' => true,
                'payment_id' => $this->unique_id,
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        }

        return [
            'success' => false,
            'payment_id' => $this->unique_id,
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $response
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

    private function setPaymentBasicInfo() {
        $paymentInfo['apiOperation'] = "INITIATE";
        $paymentInfo['order']['reference'] = $this->unique_id = uniqid();
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
        return $paymentInfo;
    }

    private function setPaymentSubscriptionInfo() {
        $paymentInfo['subscription']['type'] = 'Recurring';
        $paymentInfo['subscription']['amount'] = $this->subscription_amount;
        $paymentInfo['subscription']['name'] = $this->subscription_name;
        if($this->subscription_valid_till) {
            $paymentInfo['subscription']['validTill'] = $this->subscription_valid_till;
        }
        return array_merge($this->setPaymentBasicInfo(), $paymentInfo);
    }


    private function setSubsequentTransactionInfo() {
        $paymentInfo['apiOperation'] = "INITIATE";
        $paymentInfo['order']['reference'] = $this->unique_id = uniqid();
        $paymentInfo['order']['name'] = $this->order_name;
        $paymentInfo['order']['channel'] = $this->noon_payment_channel;
        // Options for payment action are (AUTHORIZE - SALE)
        $paymentInfo['configuration']['paymentAction'] = $this->noon_payment_payment_action;

        $paymentInfo['paymentData']['type'] = 'Subscription';
        $paymentInfo['paymentData']['data']['subscriptionIdentifier'] = $this->subscription_identifier;
        return $paymentInfo;
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

    private function isSubsequentTransactionSuccess($response)
    {
        return $response->resultClass == 0;
    }
}
