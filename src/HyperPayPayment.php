<?php

namespace Nafezly\Payments;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class HyperPayPayment {

	//HYPERPAY_BASE_URL=
 	//HYPERPAY_URL="${HYPERPAY_BASE_URL}/v1/checkouts"
	//HYPERPAY_TOKEN
	//HYPERPAY_CREDIT_ID
	//HYPERPAY_MADA_ID
	//HYPERPAY_APPLE_ID
	//HYPERPAY_CURRENCY

	public function pay(Order $order){
		
		$entityId="";
		if ($order->source == "CREDIT") $entityId = config('nafezly-payments.HYPERPAY_CREDIT_ID');
        else if ($order->source == "MADA") $entityId = config('nafezly-payments.HYPERPAY_MADA_ID');
        else if ($order->source == "APPLE") $entityId = config('nafezly-payments.HYPERPAY_APPLE_ID');

        $url = config('nafezly-payments.HYPERPAY_URL');
       
        $data = "entityId=".$entityId. "&amount=" . $order->amount . "&currency=".config('nafezly-payments.HYPERPAY_CURRENCY') . "&paymentType=DB&merchantTransactionId=" . $order->payment_id ."&billing.street1=riyadh" . "&billing.city=riyadh" . "&billing.state=riyadh" . "&billing.country=SA" . "&billing.postcode=123456" . "&customer.email=" . auth()
            ->user()->email . "&customer.givenName=" . auth()->user()->name
            . "&customer.surname=" . auth()->user()->name;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ' . config('nafezly-payments.HYPERPAY_TOKEN')
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch))
        {
            return curl_error($ch);
        }
        curl_close($ch); 
        $payment_id = json_decode($responseData)->id;
        $order->update(['payment_id' => $payment_id]);

        return ['payment_id'=>$payment_id,'html'=>$this->generate_html($order,$payment_id)];
	}
	public function verify(Request $request){
		$order = Order::where('payment_id',$request['id'])->where('status','PENDING')->firstOrFail();
		$entityId="";
	 	if ($order->source == "CREDIT") $entityId = config('nafezly-payments.HYPERPAY_CREDIT_ID');
        else if ($order->source == "MADA") $entityId = config('nafezly-payments.HYPERPAY_MADA_ID');
        else if ($order->source == "APPLE") $entityId = config('nafezly-payments.HYPERPAY_APPLE_ID');

        $url = config('nafezly-payments.HYPERPAY_URL') . "/" . $request['id'] . "/payment"."?entityId=" . $entityId;


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . config('nafezly-payments.HYPERPAY_TOKEN')
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch))
        {
            return curl_error($ch);
        }
        curl_close($ch);
        $final_result = (array)json_decode($responseData, true);
        if(in_array($final_result["result"]["code"], ["000.000.000","000.100.110","000.100.111","000.100.112"])){
        	Order::where('payment_id',$request['id'])->where('status','PENDING')->update([
        		'status'=>"DONE",
        		'process_data'=>json_encode(json_decode($responseData, true))
        	]);
        	return [
        		'success'=>true,
        		'message'=>"تمت العملية بنجاح",
        		'order'=>$order
        	];
        }
        else
        {
           Order::where('payment_id',$request['id'])->where('status','PENDING')->update([
        		'status'=>"FAILED",
        		'process_data'=>json_encode(json_decode($responseData, true))
        	]);
        	return [
        		'success'=>false,
        		'message'=>"حدث خطأ أثناء تنفيذ العملية ، كود الخطأ : ".$final_result["result"]["code"],
        		'order'=>$order
        	];
        }
        
	}
	public function generate_html(Order $order,$payment_id){
		
		$form_brands="VISA MASTER";
		if($order->source=="MADA")
			$form_brands="MADA";
		elseif($order->source=="APPLE")
			$form_brands="APPLEPAY";

		return "<form action='".route(config('nafezly-payments.verify_route_name'),['payment'=>'hyperpay'])."' class='paymentWidgets' data-brands='".$form_brands."'></form>
			<script src=".config('nafezly-payments.HYPERPAY_BASE_URL')."/v1/paymentWidgets.js?checkoutId=".$payment_id."></script>
			<script type='text/javascript'>
			var subTotalAmount = ".$order->amount.";
			var shippingAmount = 0;
			var taxAmount = 0;
			var currency = '".config('nafezly-payments.HYPERPAY_CURRENCY')."';
			var applePayTotalLabel = '".config('nafezly-payments.APP_NAME')."';

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

			var wpwlOptions = {
			    applePay: {
			        displayName: '".config('nafezly-payments.APP_NAME')."',
			        total: { 
			            label: '".config('nafezly-payments.APP_NAME').".'
			        },
			        paymentTarget:'_top', 
			        merchantCapabilities: ['supports3DS'],
			        supportedNetworks: ['mada','masterCard', 'visa' ],
			        supportedCountries: ['SA'],   
			    }
			};
			wpwlOptions.createCheckout = function() {
			    return $.post(".route(config('nafezly-payments.verify_route_name'),['payment'=>'hyperpay']).")
			    .then(function(response) {
			        return response.checkoutId;
			    });
			};
			</script>";
	}
}


