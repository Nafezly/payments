<?php

namespace Nafezly\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
class ThawaniPayment {

    
	public function pay(Order $order){
     
        $response = Http::withHeaders([
            'Content-Type'=>"application/json",
            "Thawani-Api-Key"=>config('nafezly-payments.THAWANI_API_KEY')
        ])->post(config('nafezly-payments.THAWANI_URL').'/api/v1/checkout/session',[ 
            "client_reference_id"=> $order->id, 
            "products"=> [
                [
                    "name"=> "credit",
                    "unit_amount"=> $order->amount*1000,
                    "quantity"=> 1
                ], 
            ],
            "success_url"=> route(config('nafezly-payments.verify_route_name'),['payment'=>"thawani",'payment_id'=>$order->id]),
            "cancel_url"=> route(config('nafezly-payments.verify_route_name'),['payment'=>"thawani",'payment_id'=>$order->id]),
            "metadata"=> [
                "customer"=> auth()->user()->name,
                "order_id"=> $order->id,
                "phone"=>isset(auth()->user()->phone)?auth()->user()->phone:"+96879153777"
            ] 
        ])->json();
        $order->update(['payment_id'=>$response['data']['session_id']]);
        return redirect(config('nafezly-payments.THAWANI_URL').'/pay/'.$response['data']['session_id']."?key=".config('nafezly-payments.THAWANI_PUBLISHABLE_KEY'));
         
	}
	public function verify(Request $request){

        $order = Order::where('id',$request['payment_id'])->where('status','PENDING')->firstOrFail();
        $response = Http::withHeaders([
                'content-type' => 'application/json',
                'Thawani-Api-Key'=>config('nafezly-payments.THAWANI_API_KEY')
        ])->get(config('nafezly-payments.THAWANI_URL').'/api/v1/checkout/session/'.$order->payment_id)->json();
        
        if($response['data']['total_amount'] == $order->amount*1000 && $response['data']['payment_status']=="paid"){
            $order->update([
                'status'=>"DONE",
                'process_data'=>json_encode($response)
            ]);
            return [
                'success'=>true,
                'message'=>"تمت العملية بنجاح",
                'order'=>$order
            ];

        }else{
            $order->update([
                'status'=>"FAILED",
                'process_data'=>json_encode($response)
            ]);
            return [
                'success'=>false,
                'message'=>'حدث خطأ أثناء تنفيذ العملية',
                'order'=>$order
            ];
        }

	}

}