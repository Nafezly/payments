<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FawryPayment
{
    private $fawry_url;
    private $fawry_secret;
    private $fawry_merchant;

    public function __construct()
    {
        $this->fawry_url = config('nafezly-payments.FAWRY_URL');
        $this->fawry_merchant = config('nafezly-payments.FAWRY_MERCHANT');
        $this->fawry_secret = config('nafezly-payments.FAWRY_SECRET');
    }


    /**
     * @param Order $order
     * @return string[]
     */
    public function pay(Order $order): array
    {
        $unique_id = uniqid();

        $data = [
            'fawry_url' => config('nafezly-payments.FAWRY_URL'),
            'fawry_merchant' => config('nafezly-payments.FAWRY_MERCHANT'),
            'fawry_secret' => config('nafezly-payments.FAWRY_SECRET'),
            'user_id' => auth()->user()->id,
            'user_name' => auth()->user()->name,
            'user_email' => auth()->user()->email,
            'user_phone' => isset(auth()->user()->phone) ? auth()->user()->phone : "01234567890",
            'unique_id' => $unique_id,
            'item_id' => 1,
            'item_quantity' => 1,
            'amount' => $order->amount,
        ];

        $secret = $data['fawry_merchant'] . $data['unique_id'] . $data['user_id'] . $data['item_id'] . $data['item_quantity'] . $data['amount'] . $data['fawry_secret'];
        $data['secret'] = $secret;
        $order->update(['payment_id' => $unique_id]);

        return ['html' => $this->generate_html($order, $data)];

    }

    public function verify(Request $request)
    {
        $response = json_decode($request['chargeResponse'], true);
        $reference_id = $response['merchantRefNumber'];

        $hash = hash('sha256', $this->fawry_merchant . $reference_id . $this->fawry_secret);
        $order = Order::where('payment_id', $reference_id)
            ->where('status', 'PENDING')
            ->firstOrFail();
        $response = Http::get($this->fawry_url . 'ECommerceWeb/Fawry/payments/status/v2?merchantCode=' . $this->fawry_merchant . '&merchantRefNumber=' . $reference_id . '&signature=' . $hash);

        if ($response->offsetGet('statusCode') == 200 && $response->offsetGet('paymentStatus') == "PAID" && (double)$order->amount == (double)$response->offsetGet('paymentAmount')) {

            Order::where('payment_id', $reference_id)->where('status', 'PENDING')->update([
                'status' => "DONE",
                'process_data' => json_encode($request->all())
            ]);
            return [
                'success' => true,
                'message' => "تمت العملية بنجاح",
                'order' => $order
            ];

        } else if ($response->offsetGet('statusCode') != 200) {
            Order::where('payment_id', $reference_id)->where('status', 'PENDING')->update([
                'status' => "FAILED",
                'process_data' => json_encode($request->all())
            ]);
            return [
                'success' => true,
                'message' => "تمت العملية بنجاح",
                'order' => $order
            ];
        }
    }

    public function generate_html(Order $order, $data): string
    {
        return "<link rel='stylesheet' href='https://atfawry.fawrystaging.com/atfawry/plugin/assets/payments/css/fawrypay-payments.css'><script type='text/javascript' src='" . $data['fawry_url'] . "atfawry/plugin/assets/payments/js/fawrypay-payments.js'></script><script>  
            let chargeRequest = {};
            chargeRequest.language= 'ar-eg';
            chargeRequest.merchantCode= '" . $data['fawry_merchant'] . "';
            chargeRequest.merchantRefNumber= '" . $data['payment_id'] . "';
            chargeRequest.customer = {};
            chargeRequest.customer.name = '" . $data['user_name'] . "';
            chargeRequest.customer.mobile = '" . $data['user_phone'] . "';
            chargeRequest.customer.email = '" . $data['user_email'] . "';
            chargeRequest.customer.customerProfileId = '" . $data['user_id'] . "';
            chargeRequest.order = {};
            chargeRequest.order.description = 'Credit';
            chargeRequest.order.expiry = '';
            chargeRequest.order.orderItems = [];
            let item = {};
            item.productSKU =1;
            item.description ='Credit';
            item.price =" . $data['amount'] . ";
            item.quantity =" . $data['item_quantity'] . ";
            chargeRequest.order.orderItems.push(item); 
            chargeRequest.signature = '" . $data['secret'] . "';  
            setTimeout(function(){
                FawryPay.checkout(chargeRequest,'" . route(config('nafezly-payments.verify_route_name'), ['payment' => "fawry"]) . "','" . route(config('nafezly-payments.verify_route_name'), ['payment' => "fawry"]) . "');
            },100); 
        </script>";
    }

}