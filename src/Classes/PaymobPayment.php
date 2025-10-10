<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PaymobPayment extends BaseController implements PaymentInterface
{
    private $paymob_public_key;
    private $paymob_secret_key;
    private $paymob_integration_id;
    private $paymob_currency;
    private $paymob_hmac;
    public $verify_route_name;

    public function __construct()
    {
        $this->paymob_public_key = config('nafezly-payments.PAYMOB_PUBLIC_KEY');
        $this->paymob_secret_key = config('nafezly-payments.PAYMOB_SECRET_KEY');
        $this->paymob_integration_id = config('nafezly-payments.PAYMOB_INTEGRATION_ID');
        $this->paymob_currency = config("nafezly-payments.PAYMOB_CURRENCY");
        $this->paymob_hmac = config("nafezly-payments.PAYMOB_HMAC");
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
     * @return void
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields, 'PayMob');

        $integrations = [];
        foreach(explode(',', $this->paymob_integration_id) as $integration){
            if((int)$integration == $integration)
                $integrations[] = (int)$integration;
            else
                $integrations[] = $integration;
        }


        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;

        $res = \Http::withHeaders([
            'Authorization'=>"Token ".$this->paymob_secret_key,
        ])->post('https://accept.paymob.com/v1/intention/',[
            'amount'=>$this->amount*100,
            'currency'=>$this->paymob_currency ?? "EGP",
            'payment_methods'=>$integrations,
            'billing_data'=>[
                'first_name'=>$this->user_first_name,
                'last_name'=>$this->user_last_name,
                'phone_number'=>$this->user_phone,
                'email'=>$this->user_email,
            ],
            'special_reference'=>$unique_id,
            'expiration'=>3600,
            'notification_url'=>route($this->verify_route_name,['payment'=>'paymob','payment_id'=>$unique_id]),
            'redirection_url'=>route($this->verify_route_name,['payment'=>'paymob','payment_id'=>$unique_id])
        ]); 
        $json_res = $res->json();
        if(isset($json_res['client_secret'])){
            return [
                'payment_id'=>$unique_id,
                'html'=>"",
                'redirect_url'=>"https://accept.paymob.com/unifiedcheckout/?publicKey=".$this->paymob_public_key."&clientSecret=".$json_res['client_secret']
            ];
        }
        return [
            'payment_id'=>$unique_id,
            'html'=>$res->body(),
            'redirect_url'=>""

        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $string = $request['amount_cents'] . $request['created_at'] . $request['currency'] . $request['error_occured'] . $request['has_parent_transaction'] . $request['id'] . $request['integration_id'] . $request['is_3d_secure'] . $request['is_auth'] . $request['is_capture'] . $request['is_refunded'] . $request['is_standalone_payment'] . $request['is_voided'] . $request['order'] . $request['owner'] . $request['pending'] . $request['source_data_pan'] . $request['source_data_sub_type'] . $request['source_data_type'] . $request['success'];

        if ( isset($request['hmac']) && $request['hmac'] !=null &&  hash_equals(hash_hmac('sha512', $string, $this->paymob_hmac),$request['hmac'])  ){
            if ($request['success'] == "true") {
                return [
                    'success' => true,
                    'payment_id'=>$request['merchant_order_id'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id'=>$request['merchant_order_id'],
                    'message' => __('nafezly::messages.PAYMENT_FAILED_WITH_CODE',['CODE'=>$this->getErrorMessage($request['txn_response_code'])]),
                    'process_data' => $request->all()
                ];
            }

        } else {
            return [
                'success' => false,
                'payment_id'=>$request['order'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }
    }
    public function getErrorMessage($code){
        $errors=[
            'BLOCKED'=>__('nafezly::messages.Process_Has_Been_Blocked_From_System'),
            'B'=>__('nafezly::messages.Process_Has_Been_Blocked_From_System'),
            '5'=>__('nafezly::messages.Balance_is_not_enough'),
            'F'=>__('nafezly::messages.Your_card_is_not_authorized_with_3D_secure'),
            '7'=>__('nafezly::messages.Incorrect_card_expiration_date'),
            '2'=>__('nafezly::messages.Declined'),
            '6051'=>__('nafezly::messages.Balance_is_not_enough'),
            '637'=>__('nafezly::messages.The_OTP_number_was_entered_incorrectly'),
            '11'=>__('nafezly::messages.Security_checks_are_not_passed_by_the_system'),
        ];
        if(isset($errors[$code]))
            return $errors[$code];
        else
            return __('nafezly::messages.An_error_occurred_while_executing_the_operation');
    }

    public function refund($transaction_id,$amount): array
    {
        $request_new_token = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymobsolutions.com/api/auth/tokens', [
                "api_key" => $this->paymob_api_key
            ])->json();
        $refund_process = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymob.com/api/acceptance/void_refund/refund',['auth_token'=>$request_new_token['token'],'transaction_id'=>$transaction_id,'amount_cents'=>$amount])->json();

        dd($refund_process);
        return [
            'transaction_id'=>$transaction_id,
            'amount'=>$amount,
        ];

    }

}