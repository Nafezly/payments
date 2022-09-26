<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

class HyperPayPayment
{
    private $hyperpay_url;
    private $hyperpay_token;
    private $hyperpay_currency;
    private $hyperpay_credit_id;
    private $hyperpay_mada_id;
    private $hyperpay_apple_id;
    private $app_name;
    private $verify_route_name;

    public function __construct()
    {
        $this->hyperpay_url = config('nafezly-payments.HYPERPAY_URL');
        $this->hyperpay_token = config('nafezly-payments.HYPERPAY_TOKEN');
        $this->hyperpay_currency = config('nafezly-payments.HYPERPAY_CURRENCY');
        $this->hyperpay_credit_id = config('nafezly-payments.HYPERPAY_CREDIT_ID');
        $this->hyperpay_mada_id = config('nafezly-payments.HYPERPAY_MADA_ID');
        $this->hyperpay_apple_id = config('nafezly-payments.HYPERPAY_APPLE_ID');
        $this->app_name = config('nafezly-payments.APP_NAME');
        $this->verify_route_name = config('nafezly-payments.verify_route_name');
    }

    /**
     * @param Order $order
     * @return array|string
     */
    public function pay(Order $order)
    {
        $entityId = $this->getEntityId($order);
        $data = "entityId=" . $entityId . "&amount=" . $order->amount . "&currency=" . $this->hyperpay_currency . "&paymentType=DB&merchantTransactionId=" . $order->payment_id . "&billing.street1=riyadh" . "&billing.city=riyadh" . "&billing.state=riyadh" . "&billing.country=SA" . "&billing.postcode=123456" . "&customer.email=" . auth()
                ->user()->email . "&customer.givenName=" . auth()->user()->name
            . "&customer.surname=" . auth()->user()->name;

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
        $payment_id = json_decode($responseData)->id;
        $order->update(['payment_id' => $payment_id]);

        return ['payment_id' => $payment_id, 'html' => $this->generate_html($order, $payment_id)];
    }

    /**
     * @param Request $request
     * @return array|string
     */
    public function verify(Request $request)
    {
        $order = Order::where('payment_id', $request['id'])->where('status', 'PENDING')->firstOrFail();
        $entityId = $this->getEntityId($order);

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
            Order::where('payment_id', $request['id'])->where('status', 'PENDING')->update([
                'status' => "DONE",
                'process_data' => json_encode(json_decode($responseData, true))
            ]);
            return [
                'success' => true,
                'message' => "تمت العملية بنجاح",
                'order' => $order
            ];
        } else {
            Order::where('payment_id', $request['id'])->where('status', 'PENDING')->update([
                'status' => "FAILED",
                'process_data' => json_encode(json_decode($responseData, true))
            ]);
            return [
                'success' => false,
                'message' => "حدث خطأ أثناء تنفيذ العملية ، كود الخطأ : " . $final_result["result"]["code"],
                'order' => $order
            ];
        }
    }

    public function generate_html(Order $order, $payment_id): string
    {

        $form_brands = "VISA MASTER";
        if ($order->source == "MADA")
            $form_brands = "MADA";
        elseif ($order->source == "APPLE")
            $form_brands = "APPLEPAY";

        return "<form action='" . route($this->verify_route_name, ['payment' => 'hyperpay']) . "' class='paymentWidgets' data-brands='" . $form_brands . "'></form>
			<script src=" . $this->verify_route_name . "/v1/paymentWidgets.js?checkoutId=" . $payment_id . "></script>
			<script type='text/javascript'>
			const subTotalAmount = parseFloat(\" . $order->amount . \");
			const shippingAmount = 0;
			const taxAmount = 0;
			const currency = '" . $this->hyperpay_currency . "';
			const applePayTotalLabel = '" . $this->app_name . "';

			function getAmount() {
			    return ((subTotalAmount + shippingAmount + taxAmount)).toFixed(2);
			}
			function getLineItems() {
			    return [{
			        label: 'Subtotal',
			        amount: (subTotalAmount).toFixed(2)
			    }, {
			        label: 'Shipping',
			        amount: (shippingAmount).toFixed(2)
			    }, {
			        label: 'Tax',
			        amount: (taxAmount).toFixed(2)
			    }];
			}

			const wpwlOptions = {
			    applePay: {
			        displayName: '" . $this->app_name . "',
			        total: { 
			            label: '" . $this->app_name . ".'
			        },
			        paymentTarget:'_top', 
			        merchantCapabilities: ['supports3DS'],
			        supportedNetworks: ['mada','masterCard', 'visa' ],
			        supportedCountries: ['SA'],   
			    }
			};
			wpwlOptions.createCheckout = function() {
			    return $.post(" . route($this->verify_route_name, ['payment' => 'hyperpay']) . ")
			    .then(function(response) {
			        return response.checkoutId;
			    });
			};
			</script>";
    }

    private function getEntityId(Order $order)
    {

        switch ($order->source) {
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
}


