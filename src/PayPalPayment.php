<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
class PayPalPayment {


	public function pay(Order $order){

        $environment = new SandboxEnvironment(config('nafezly-payments.PAYPAL_CLIENT_ID'), config('nafezly-payments.PAYPAL_SECRET'));
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
                  "cancel_url" => route('payment-verify',['payment'=>"paypal"]),
                  "return_url" => route('payment-verify',['payment'=>"paypal"])
             ] 
        ];
         
        try{
            $response = json_decode(json_encode($client->execute($request)),true);
            $order->update(['payment_id'=>$response['result']['id']]);
            return redirect(collect($response['result']['links'])->where('rel','approve')->firstOrFail()['href']);
        }catch(\Exception $e){
            return [
                'success'=>false,
                'message'=>"حدث خطأ أثناء تنفيذ العملية"
            ];
        } 
	}
	public function verify(Request $request){

        $environment = new SandboxEnvironment(config('nafezly-payments.PAYPAL_CLIENT_ID'), config('nafezly-payments.PAYPAL_SECRET'));
        $client = new PayPalHttpClient($environment);

        try{
            $response = $client->execute(new OrdersGetRequest($request['token']));
            $result = json_decode(json_encode($response),true);
            if($result['result']['intent']=="CAPTURE" && $result['result']['status']=="APPROVED"){
                Order::where('payment_id',$request['token'])->where('status','PENDING')->update([
                    'status'=>"DONE",
                    'process_data'=>json_encode($result)
                ]);
                return [
                    'success'=>true,
                    'message'=>"تمت العملية بنجاح"
                ];

            }else{
                Order::where('payment_id',$request['token'])->where('status','PENDING')->update([
                    'status'=>"FAILED",
                    'process_data'=>json_encode($result)
                ]);
                return [
                    'success'=>false,
                    'message'=>'حدث خطأ أثناء تنفيذ العملية'
                ];
            }
        }catch(\Exception $e){
            Order::where('payment_id',$request['token'])->where('status','PENDING')->update([
                'status'=>"FAILED",
                'process_data'=>json_encode($e)
            ]);
            return [
                'success'=>false,
                'message'=>'حدث خطأ أثناء تنفيذ العملية'
            ];
        }
	}

}