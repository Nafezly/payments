<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

class KashierPayment
{


    private $kashier_url;
    private $kashier_mode;
    private $kashier_account_key;
    private $kashier_iframe_key;
    private $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->kashier_url = config("nafezly-payments.KASHIER_URL");
        $this->kashier_mode = config("nafezly-payments.KASHIER_MODE");
        $this->kashier_account_key = config("nafezly-payments.KASHIER_ACCOUNT_KEY");
        $this->kashier_iframe_key = config("nafezly-payments.KASHIER_IFRAME_KEY");
        $this->app_name = config('nafezly-payments.APP_NAME');
        $this->verify_route_name = config('nafezly-payments.verify_route_name');
    }

    /**
     * @param Order $order
     * @return string[]
     */
    public function pay(Order $order): array
    {

        $payment_id = uniqid();
        $order->update(['payment_id' => $payment_id]);

        $mid = $this->kashier_account_key;
        $amount = $order->amount;
        $currency = "EGP";
        $order_id = $payment_id;
        $secret = $this->kashier_iframe_key;
        $path = "/?payment=${mid}.${order_id}.${amount}.${currency}";
        $hash = hash_hmac('sha256', $path, $secret);

        $data = [
            'mid' => $mid,
            'amount' => $order->amount,
            'currency' => $currency,
            'order_id' => $order_id,
            'path' => $path,
            'hash' => $hash,
            'redirect_back' => route($this->verify_route_name, ['payment' => "kashier"])
        ];

        return ['html' => $this->generate_html($order, $data)];

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $order = Order::where('payment_id', $request["merchantOrderId"])->firstOrFail();
        if ($request["paymentStatus"] == "SUCCESS") {

            $queryString = "";
            $secret = config("nafezly-payments.KASHIER_IFRAME_KEY");

            foreach ($request->all() as $key => $value) {

                if ($key == "signature" || $key == "mode") {
                    continue;
                }
                $queryString = $queryString . "&" . $key . "=" . $value;
            }

            $queryString = ltrim($queryString, $queryString[0]);
            $signature = hash_hmac('sha256', $queryString, $secret, false);
            if ($signature == $request["signature"]) {
                Order::where('payment_id', $request["merchantOrderId"])->where('status', 'PENDING')->update([
                    'status' => "DONE",
                    'process_data' => json_encode($request->all())
                ]);
                return [
                    'success' => true,
                    'message' => "تمت العملية بنجاح",
                    'order' => $order
                ];
            } else {
                Order::where('payment_id', $request["merchantOrderId"])->where('status', 'PENDING')->update([
                    'status' => "FAILED",
                    'process_data' => json_encode($request->all())
                ]);
                return [
                    'success' => false,
                    'message' => "حدث خطأ أثناء تنفيذ العملية",
                    'order' => $order
                ];
            }
        } else {

            return [
                'success' => false,
                'message' => "حدث خطأ أثناء تنفيذ العملية",
                'order' => $order
            ];

        }

    }

    /**
     * @param Order $order
     * @param $data
     * @return string
     */
    public function generate_html(Order $order, $data): string
    {
        return '<body><script id="kashier-iFrame"
         src="' . $this->kashier_url . '/kashier-checkout.js"
        data-amount="' . $order->amount . '"
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