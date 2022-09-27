<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Order;

class KashierPayment implements PaymentInterface
{


    private $kashier_url;
    private $kashier_mode;
    private $kashier_account_key;
    private $kashier_iframe_key;
    private $kashier_currency;
    private $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->kashier_url = config("nafezly-payments.KASHIER_URL");
        $this->kashier_mode = config("nafezly-payments.KASHIER_MODE");
        $this->kashier_account_key = config("nafezly-payments.KASHIER_ACCOUNT_KEY");
        $this->kashier_iframe_key = config("nafezly-payments.KASHIER_IFRAME_KEY");
        $this->kashier_currency = config('nafezly-payments.KASHIER_CURRENCY');
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
     * @return string[]
     */
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {

        $payment_id = uniqid();

        $mid = $this->kashier_account_key;
        $currency = $this->kashier_currency;
        $order_id = $payment_id;
        $secret = $this->kashier_iframe_key;
        $path = "/?payment=$mid.$order_id.$amount.$currency";
        $hash = hash_hmac('sha256', $path, $secret);

        $data = [
            'mid' => $mid,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $order_id,
            'path' => $path,
            'hash' => $hash,
            'redirect_back' => route($this->verify_route_name, ['payment' => "kashier"])
        ];

        return [
            'payment_id'=>$payment_id,
            'html' => $this->generate_html($amount, $data),
            'redirect_url'=>""
        ];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        if ($request["paymentStatus"] == "SUCCESS") {
            $queryString = "";
            foreach ($request->all() as $key => $value) {

                if ($key == "signature" || $key == "mode") {
                    continue;
                }
                $queryString = $queryString . "&" . $key . "=" . $value;
            }

            $queryString = ltrim($queryString, $queryString[0]);
            $signature = hash_hmac('sha256', $queryString, $this->kashier_iframe_key);
            if ($signature == $request["signature"]) {
                return [
                    'success' => true,
                    'message' => __('messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('messages.PAYMENT_FAILED'),
                    'process_data' => $request->all()
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }

    /**
     * @param $amount
     * @param $data
     * @return string
     */
    private function generate_html($amount, $data): string
    {
        return '<body><script id="kashier-iFrame"
         src="' . $this->kashier_url . '/kashier-checkout.js"
        data-amount="' . $amount . '"
        data-description="Credit"
        data-mode="' . $this->kashier_mode . '"
        data-hash="' . $data["hash"] . '"
        data-currency="' . $data["currency"] . '"
        data-orderId="' . $data["order_id"] . '"
        data-allowedMethods="card"
        data-merchantId="' . $data["mid"] . '"
        data-merchantRedirect="' . $data["redirect_back"] . '" 
        data-store="' . $this->app_name . '"
        data-type="external" data-display="ar"></script></body>';
    }

}