<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;

class ThawaniPayment
{

    private $thawani_url;
    private $thawani_api_key;
    private $thawani_publishable_key;
    private $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->thawani_url = config('nafezly-payments.THAWANI_URL');
        $this->thawani_api_key = config('nafezly-payments.THAWANI_API_KEY');
        $this->thawani_publishable_key = config('nafezly-payments.THAWANI_PUBLISHABLE_KEY');
        $this->app_name = config('nafezly-payments.APP_NAME');
        $this->verify_route_name = config('nafezly-payments.verify_route_name');
    }

    /**
     * @param Order $order
     * @return Application|RedirectResponse|Redirector
     */
    public function pay(Order $order)
    {

        $response = Http::withHeaders([
            'Content-Type' => "application/json",
            "Thawani-Api-Key" => $this->thawani_api_key
        ])->post($this->thawani_url . '/api/v1/checkout/session', [
            "client_reference_id" => $order->id,
            "products" => [
                [
                    "name" => "credit",
                    "unit_amount" => $order->amount * 1000,
                    "quantity" => 1
                ],
            ],
            "success_url" => route($this->verify_route_name, ['payment' => "thawani", 'payment_id' => $order->id]),
            "cancel_url" => route($this->verify_route_name, ['payment' => "thawani", 'payment_id' => $order->id]),
            "metadata" => [
                "customer" => auth()->user()->name,
                "order_id" => $order->id,
                "phone" => isset(auth()->user()->phone) ? auth()->user()->phone : "+96879153777"
            ]
        ])->json();
        $order->update(['payment_id' => $response['data']['session_id']]);
        return redirect($this->thawani_url . '/pay/' . $response['data']['session_id'] . "?key=" . $this->thawani_publishable_key);

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $order = Order::where('id', $request['payment_id'])->where('status', 'PENDING')->firstOrFail();
        $response = Http::withHeaders([
            'content-type' => 'application/json',
            'Thawani-Api-Key' => $this->thawani_api_key
        ])->get($this->thawani_url . '/api/v1/checkout/session/' . $order->payment_id)->json();

        if ($response['data']['total_amount'] == $order->amount * 1000 && $response['data']['payment_status'] == "paid") {
            $order->update([
                'status' => "DONE",
                'process_data' => json_encode($response)
            ]);
            return [
                'success' => true,
                'message' => "تمت العملية بنجاح",
                'order' => $order
            ];

        } else {
            $order->update([
                'status' => "FAILED",
                'process_data' => json_encode($response)
            ]);
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تنفيذ العملية',
                'order' => $order
            ];
        }
    }
}