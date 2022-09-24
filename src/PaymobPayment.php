<?php

namespace Nafezly\Payments;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class PaymobPayment {

 	//PAYMOB_API_KEY
	//PAYMOB_INTEGRATION_ID
	//PAYMOB_IFRAME_ID
	//PAYMOB_HMAC

	public function pay(Order $order){


		$request_new_token = Http::withHeaders(['content-type' => 'application/json'])
					->post('https://accept.paymobsolutions.com/api/auth/tokens',[
						"api_key" => config('nafezly-payments.PAYMOB_API_KEY')
					])->json();

        $get_order = Http::withHeaders(['content-type' => 'application/json'])
        				->post('https://accept.paymobsolutions.com/api/ecommerce/orders', [
        					"auth_token" => $request_new_token['token'], 
        					"delivery_needed" => "false", 
        					"amount_cents" => $order->amount * 100, 
        					"items" => []
        				])->json();

        $order->update(['payment_id'=>$get_order['id']]);

        $get_url_token = Http::withHeaders(['content-type' => 'application/json'])
        				->post('https://accept.paymobsolutions.com/api/acceptance/payment_keys', [
        					"auth_token" => $request_new_token['token'], 
        					"expiration" => 36000, 
        					"amount_cents" => $get_order['amount_cents'], 
        					"order_id" => $get_order['id'], 
        					"billing_data" => [
        						"apartment" => "NA", 
        						"email" => auth()->user()->email, 
        						"floor" => "NA", 
        						"first_name" => auth()->user()->name, 
        						"street" => "NA", 
        						"building" => "NA", 
        						"phone_number" => isset(auth()->user()->phone)?auth()->user()->phone:"01234567890", 
        						"shipping_method" => "NA", 
        						"postal_code" => "NA", 
        						"city" => "NA", 
        						"country" => "NA", 
        						"last_name" => auth()->user()->name, 
        						"state" => "NA"
        					], 
        					"currency" => "EGP", 
        					"integration_id" => config('nafezly-payments.PAYMOB_INTEGRATION_ID') 
        				])->json();


        header("location: https://accept.paymobsolutions.com/api/acceptance/iframes/" . config("nafezly-payments.PAYMOB_IFRAME_ID") . "?payment_token=" . $get_url_token['token']);
        die();

	}
	public function verify(Request $request){
        $order = Order::where('payment_id',$request['order_id'])->firstOrFail();
		$string = $request['amount_cents'] . $request['created_at'] . $request['currency'] . $request['error_occured'] . $request['has_parent_transaction'] . $request['id'] . $request['integration_id'] . $request['is_3d_secure'] . $request['is_auth'] . $request['is_capture'] . $request['is_refunded'] . $request['is_standalone_payment'] . $request['is_voided'] . $request['order'] . $request['owner'] . $request['pending'] . $request['source_data_pan'] . $request['source_data_sub_type'] . $request['source_data_type'] . $request['success'];

        if (hash_hmac('sha512', $string, config('nafezly-payments.PAYMOB_HMAC')))
        {
            if($request['success']=="true"){
            	Order::where('payment_id',$request['order_id'])->where('status','PENDING')->update([
            		'status'=>"DONE",
            		'process_data'=>json_encode($request->all())
            	]);
            	return [
            		'success'=>true,
            		'message'=>"تمت العملية بنجاح",
                    'order'=>$order
            	];
            }else{
            	Order::where('payment_id',$request['order_id'])->where('status','PENDING')->update([
            		'status'=>"FAILED",
            		'process_data'=>json_encode($request->all())
            	]);
            	return [
            		'success'=>false,
            		'message'=>'حدث خطأ أثناء تنفيذ العملية '.$request['data_message'],
                    'order'=>$order
            	];
            }
            
        }
        else
        {
            return [
        		'success'=>false,
        		'message'=>'حدث خطأ أثناء تنفيذ العملية',
                'order'=>$order
            ];
        }
	}

}