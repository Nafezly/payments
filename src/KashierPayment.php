<?php

namespace Nafezly\Payments;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class KashierPayment {


 	//KASHIER_ACCOUNT_KEY
	//KASHIER_IFRAME_KEY
	//KASHIER_URL=https://checkout.kashier.io
	//KASHIER_MODE=test

	public function pay(Order $order){

        $payment_id = uniqid();
        $order->update(['payment_id'=>$payment_id]);

        $mid = config("nafezly-payments.KASHIER_ACCOUNT_KEY");
        $amount = $order->amount;
        $currency = "EGP";
        $order_id = $payment_id;
        $secret = config("nafezly-payments.KASHIER_IFRAME_KEY");
        $path = "/?payment=${mid}.${order_id}.${amount}.${currency}";
        $hash = hash_hmac('sha256', $path, $secret, false);

        $data = [
            'mid' => $mid, 
            'amount' => $order->amount, 
            'currency' => $currency, 
            'order_id' => $order_id, 
            'path' => $path, 
            'hash' => $hash, 
            'redirect_back' => route('payment-verify',['payment'=>"kashier"]) 
        ];

        return ['html'=>$this->generate_html($order,$data)];

	}
	public function verify(Request $request){

        if ($request["paymentStatus"] == "SUCCESS"){

            $queryString = "";
            $secret = config("nafezly-payments.KASHIER_IFRAME_KEY");

            foreach ($request->all() as $key => $value)
            {

                if ($key == "signature" || $key == "mode")
                {
                    continue;
                }
                $queryString = $queryString . "&" . $key . "=" . $value;
            }

            $queryString = ltrim($queryString, $queryString[0]);
            $signature = hash_hmac('sha256', $queryString, $secret, false);
            if ($signature == $request["signature"])
            {
                Order::where('payment_id',$request["merchantOrderId"])->where('status','PENDING')->update([
                    'status'=>"DONE",
                    'process_data'=>json_encode($request->all())
                ]);
                return [
                    'success'=>true,
                    'message'=>"تمت العملية بنجاح"
                ];
            }
            else
            {
                Order::where('payment_id',$request["merchantOrderId"])->where('status','PENDING')->update([
                    'status'=>"FAILED",
                    'process_data'=>json_encode($request->all())
                ]);
                return [
                    'success'=>false,
                    'message'=>"حدث خطأ أثناء تنفيذ العملية"
                ];
            }
        }else{

            return [
                'success'=>false,
                'message'=>"حدث خطأ أثناء تنفيذ العملية"
            ];

        }

	}
    public function generate_html(Order $order,$data){
        return '<body><script id="kashier-iFrame"
         src="'.config("nafezly-payments.KASHIER_URL").'/kashier-checkout.js"
        data-amount="'.$order->amount.'"
        data-description="Credit"
        data-mode="'.config("nafezly-payments.KASHIER_MODE").'"
        data-hash="'.$data["hash"].'"
        data-currency="'.$data["currency"].'"
        data-orderId="'.$data["order_id"].'"
        data-allowedMethods="card"
        data-merchantId="'.$data["mid"].'"
        data-merchantRedirect="'.$data["redirect_back"].'" 
        data-store="'.config("nafezly-payments.APP_NAME").'"
        data-type="external" data-display="ar"></script></body>';
    }

}