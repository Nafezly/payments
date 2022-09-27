<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;

class FawryPayment implements PaymentInterface
{
    private $fawry_url;
    private $fawry_secret;
    private $fawry_merchant;
    private $verify_route_name;

    public function __construct()
    {
        $this->fawry_url = config('nafezly-payments.FAWRY_URL');
        $this->fawry_merchant = config('nafezly-payments.FAWRY_MERCHANT');
        $this->fawry_secret = config('nafezly-payments.FAWRY_SECRET');
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
     * @throws MissingPaymentInfoException
     */
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        if (is_null($user_id)) throw new MissingPaymentInfoException('user_id', 'FAWRY');
        if (is_null($user_first_name)) throw new MissingPaymentInfoException('user_first_name', 'FAWRY');
        if (is_null($user_last_name)) throw new MissingPaymentInfoException('user_last_name', 'FAWRY');
        if (is_null($user_email)) throw new MissingPaymentInfoException('user_email', 'FAWRY');
        if (is_null($user_phone)) throw new MissingPaymentInfoException('user_phone', 'FAWRY');

        $unique_id = uniqid();

        $data = [
            'fawry_url' => $this->fawry_url,
            'fawry_merchant' => $this->fawry_merchant,
            'fawry_secret' => $this->fawry_secret,
            'user_id' => $user_id,
            'user_name' => $user_first_name.' '.$user_last_name,
            'user_email' => $user_email,
            'user_phone' => $user_phone,
            'unique_id' => $unique_id,
            'item_id' => 1,
            'item_quantity' => 1,
            'amount' => $amount,
            'payment_id'=>$unique_id
        ];

        $secret = $data['fawry_merchant'] . $data['unique_id'] . $data['user_id'] . $data['item_id'] . $data['item_quantity'] . $data['amount'] . $data['fawry_secret'];
        $data['secret'] = $secret;

        return [
            'payment_id' => $unique_id, 
            'html' => $this->generate_html($data),
            'redirect_url'=>""
        ];

    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        $res = json_decode($request['chargeResponse'], true);
        $reference_id = $res['merchantRefNumber'];

        $hash = hash('sha256', $this->fawry_merchant . $reference_id . $this->fawry_secret);

        $response = Http::get($this->fawry_url . 'ECommerceWeb/Fawry/payments/status/v2?merchantCode=' . $this->fawry_merchant . '&merchantRefNumber=' . $reference_id . '&signature=' . $hash);

        if ($response->offsetGet('statusCode') == 200 && $response->offsetGet('paymentStatus') == "PAID") {
            return [
                'success' => true,
                'message' => __('messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        } else if ($response->offsetGet('statusCode') != 200) {
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }

    private function generate_html($data): string
    {
        return "<link rel='stylesheet' href='https://atfawry.fawrystaging.com/atfawry/plugin/assets/payments/css/fawrypay-payments.css'><script type='text/javascript' src='" . $data['fawry_url'] . "atfawry/plugin/assets/payments/js/fawrypay-payments.js'></script><script>  
            const chargeRequest = {};
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
            const item = {};
            item.productSKU =1;
            item.description ='Credit';
            item.price =" . $data['amount'] . ";
            item.quantity =" . $data['item_quantity'] . ";
            chargeRequest.order.orderItems.push(item); 
            chargeRequest.signature = '" . $data['secret'] . "';  
            setTimeout(function(){
                FawryPay.checkout(chargeRequest,'" . route($this->verify_route_name, ['payment' => "fawry"]) . "','" . route($this->verify_route_name, ['payment' => "fawry"]) . "');
            },100); 
        </script>";
    }

}