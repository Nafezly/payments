<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Traits\SetVariables;
use Nafezly\Payments\Traits\SetRequiredFields;

class HyperPayPayment
{
    use SetVariables, SetRequiredFields;
    private $hyperpay_url;
    private $hyperpay_base_url;
    private $hyperpay_token;
    private $hyperpay_credit_id;
    private $hyperpay_mada_id;
    private $hyperpay_apple_id;
    private $app_name;
    private $verify_route_name;

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
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'HYPERPAY', func_get_args());

        $data = http_build_query([
            'entityId' => $this->getEntityId($source),
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
        $payment_id = json_decode($responseData)->id;
        Cache::forever($payment_id . '_source', $source);
        return [
            'payment_id' => $payment_id, 
            'html' => $this->generate_html($source, $amount, $payment_id),
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
                'message' => __('messages.PAYMENT_DONE'),
                'process_data' => $final_result
            ];
        } else {
            return [
                'success' => false,
                'message' => __('messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $final_result["result"]["code"]]),
                'process_data' => $final_result
            ];
        }
    }

    private function generate_html($source, $amount, $payment_id): string
    {

        $form_brands = "VISA MASTER";
        if ($source == "MADA")
            $form_brands = "MADA";
        elseif ($source == "APPLE")
            $form_brands = "APPLEPAY";

        return "<form action='" . route($this->verify_route_name, ['payment' => 'hyperpay']) . "' class='paymentWidgets' data-brands='" . $form_brands . "'></form>
			<script src=" . $this->hyperpay_base_url . "/v1/paymentWidgets.js?checkoutId=" . $payment_id . "></script>
			<script type='text/javascript'>
			const subTotalAmount = parseFloat(\" . $amount . \");
			const shippingAmount = 0;
			const taxAmount = 0;
			const currency = '" . $this->currency . "';
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
			    return $.post('" . route($this->verify_route_name, ['payment' => 'hyperpay']) . "')
			    .then(function(response) {
			        return response.checkoutId;
			    });
			};
			</script>";
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
}


