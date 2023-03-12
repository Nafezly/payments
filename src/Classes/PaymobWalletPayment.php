<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PaymobWalletPayment extends BaseController implements PaymentInterface
{
    private $paymob_api_key;
    private $paymob_wallet_integration_id;

    public function __construct()
    {
        $this->paymob_api_key = config('nafezly-payments.PAYMOB_API_KEY');
        $this->currency = config("nafezly-payments.PAYMOB_CURRENCY");
        $this->paymob_wallet_integration_id = config("nafezly-payments.PAYMOB_WALLET_INTEGRATION_ID");
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

        $request_new_token = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymobsolutions.com/api/auth/tokens', [
                "api_key" => $this->paymob_api_key
            ])->json();

        $get_order = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymobsolutions.com/api/ecommerce/orders', [
                "auth_token" => $request_new_token['token'],
                "delivery_needed" => "false",
                "amount_cents" => $this->amount * 100,
                "items" => []
            ])->json();
        $get_url_token = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymobsolutions.com/api/acceptance/payment_keys', [
                "auth_token" => $request_new_token['token'],
                "expiration" => 36000,
                "amount_cents" => $get_order['amount_cents'],
                "order_id" => $get_order['id'],
                "billing_data" => [
                    "apartment" => "NA",
                    "email" => $this->user_email,
                    "floor" => "NA",
                    "first_name" => $this->user_first_name,
                    "street" => "NA",
                    "building" => "NA",
                    "phone_number" => $this->user_phone,
                    "shipping_method" => "NA",
                    "postal_code" => "NA",
                    "city" => "NA",
                    "country" => "NA",
                    "last_name" => $this->user_last_name,
                    "state" => "NA"
                ],
                "currency" => $this->currency,
                "integration_id" => $this->paymob_wallet_integration_id,
                'lock_order_when_paid'=>true
            ])->json();

        $get_pay_link = Http::withHeaders(['content-type' => 'application/json'])
            ->post('https://accept.paymob.com/api/acceptance/payments/pay', [
                'source'=>[
                    "identifier"=>$this->user_phone,
                    'subtype'=>"WALLET"
                ],
                "payment_token"=>$get_url_token['token']
        ])->json(); 
        return [
            'payment_id'=>$get_order['id'],
            'html' => "",
            'redirect_url'=>$get_pay_link['redirect_url']
        ];
        
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $string = $request['amount_cents'] . $request['created_at'] . $request['currency'] . $request['error_occured'] . $request['has_parent_transaction'] . $request['id'] . $request['integration_id'] . $request['is_3d_secure'] . $request['is_auth'] . $request['is_capture'] . $request['is_refunded'] . $request['is_standalone_payment'] . $request['is_voided'] . $request['order'] . $request['owner'] . $request['pending'] . $request['source_data_pan'] . $request['source_data_sub_type'] . $request['source_data_type'] . $request['success'];

        if (hash_hmac('sha512', $string, config('nafezly-payments.PAYMOB_HMAC'))) {
            if ($request['success'] == "true") {
                return [
                    'success' => true,
                    'payment_id'=>$request['order'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            } else {
                return [
                    'success' => false,
                    'payment_id'=>$request['order'],
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
}