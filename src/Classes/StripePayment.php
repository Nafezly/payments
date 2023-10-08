<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;
use Illuminate\Support\Str;


class StripePayment extends BaseController implements PaymentInterface 
{
    public $stripe_webhook_secret;
    public $stripe_public_key;
    public $stripe_secret_key;
    public $verify_route_name;


    public function __construct()
    {
        $this->stripe_webhook_secret = config('nafezly-payments.STRIPE_WEBHOOK_SECRET');
        $this->stripe_public_key = config('nafezly-payments.STRIPE_PUBLIC_KEY');
        $this->stripe_secret_key= config('nafezly-payments.STRIPE_SECRET_KEY');
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
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'STRIPE');

     
        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Bearer ' . $this->stripe_secret_key,
            'content-type' =>"application/x-www-form-urlencoded"
        ])->post("https://api.stripe.com/v1/payment_intents",[
            'amount' => 1500,
            'currency' => $this->currency??"usd",
            'description' => 'Credit',
            'payment_method_types'=>["card"],
        ]);

        if ($response->successful()) {
            $paymentIntent = $response->json();
            return [
                'payment_id'=>"",
                'html'=>$paymentIntent,
                'redirect_url'=>"",
            ];
        } else {
            return [
                'payment_id'=>"",
                'html'=>$response->json(),
                'redirect_url'=>""
            ];
        }
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        
    }
}