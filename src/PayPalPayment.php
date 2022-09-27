<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;

class PayPalPayment
{

    private $paypal_client_id;
    private $paypal_secret;
    private $app_name;
    private $verify_route_name;


    public function __construct()
    {
        $this->paypal_client_id = config('nafezly-payments.PAYPAL_CLIENT_ID');
        $this->paypal_secret = config('nafezly-payments.PAYPAL_SECRET');
        $this->app_name = config('nafezly-payments.APP_NAME');
        $this->verify_route_name = config('nafezly-payments.verify_route_name');

    }

    /**
     * @param Order $order
     * @return array|Application|RedirectResponse|Redirector
     */
    public function pay(Order $order)
    {
        $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);
        $client = new PayPalHttpClient($environment);

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => uniqid(),
                "amount" => [
                    "value" => $order->amount,
                    "currency_code" => config('nafezly-payments.PAYPAL_CURRENCY')
                ]
            ]],
            "application_context" => [
                "cancel_url" => route($this->verify_route_name, ['payment' => "paypal"]),
                "return_url" => route($this->verify_route_name, ['payment' => "paypal"])
            ]
        ];

        try {
            $response = json_decode(json_encode($client->execute($request)), true);
            $order->update(['payment_id' => $response['result']['id']]);
            return redirect(collect($response['result']['links'])->where('rel', 'approve')->firstOrFail()['href']);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "حدث خطأ أثناء تنفيذ العملية"
            ];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $order = Order::where('payment_id', $request['token'])->firstOrFail();
        $environment = new SandboxEnvironment($this->paypal_client_id, $this->paypal_secret);
        $client = new PayPalHttpClient($environment);

        try {
            $response = $client->execute(new OrdersGetRequest($request['token']));
            $result = json_decode(json_encode($response), true);
            if ($result['result']['intent'] == "CAPTURE" && $result['result']['status'] == "APPROVED") {
                Order::where('payment_id', $request['token'])->where('status', 'PENDING')->update([
                    'status' => "DONE",
                    'process_data' => json_encode($result)
                ]);
                return [
                    'success' => true,
                    'message' => "تمت العملية بنجاح",
                    'order' => $order
                ];

            } else {
                Order::where('payment_id', $request['token'])->where('status', 'PENDING')->update([
                    'status' => "FAILED",
                    'process_data' => json_encode($result)
                ]);
                return [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تنفيذ العملية',
                    'order' => $order
                ];
            }
        } catch (\Exception $e) {
            Order::where('payment_id', $request['token'])->where('status', 'PENDING')->update([
                'status' => "FAILED",
                'process_data' => json_encode($e)
            ]);
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تنفيذ العملية',
                'order' => $order
            ];
        }
    }
}