<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PerfectMoneyPayment extends BaseController implements PaymentInterface 
{
    

    public $perfect_money_id;
    public $perfect_money_passphrase;
    public $verify_route_name;

    public function __construct()
    {
        $this->perfect_money_id = config('nafezly-payments.PERFECT_MONEY_ID');
        $this->perfect_money_passphrase = config('nafezly-payments.PERFECT_MONEY_PASSPHRASE');
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
        $this->checkRequiredFields($required_fields, 'PERFECTMONEY');
        $unique_id= uniqid().rand(100000,999999);
        $formData = [
            'PAYEE_ACCOUNT' => $this->perfect_money_id,
            'PAYEE_NAME' => env('APP_NAME'),
            'PAYMENT_AMOUNT' => $this->amount,
            'PAYMENT_ID' => $unique_id,
            'PAYMENT_UNITS' => $this->currency??"USD" ,
            'STATUS_URL' => route($this->verify_route_name,['payment'=>"perfectmoney"]),
            'PAYMENT_URL' => route($this->verify_route_name,['payment'=>"perfectmoney"]),
            'NOPAYMENT_URL' => route($this->verify_route_name,['payment'=>"perfectmoney"]),
            'MEMO'=>"CREDIT"
            // Additional form fields as needed
        ];
        $paymentForm = '<body onload="document.getElementById(\'submit-perfectmoney-form\').submit()" style="margin:0px;overflow:hidden"><form method="post" action="https://perfectmoney.is/api/step1.asp" id="submit-perfectmoney-form" style="width:100%;height:100vh;display:flex;justify-content:center;align-items:center">';
        foreach ($formData as $key => $value) {
            $paymentForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
        }
        $paymentForm .= '<h3>Loading - تحميل</h3></form></body>';

       
        return [
            'payment_id'=>$unique_id,
            'html'=>$paymentForm,
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        $signature = (isset($request['PAYMENT_ID']) ? $request['PAYMENT_ID'] : null) . ':'
            . (isset($request['PAYEE_ACCOUNT']) ? $request['PAYEE_ACCOUNT'] : null) . ':'
            . (isset($request['PAYMENT_AMOUNT']) ? $request['PAYMENT_AMOUNT'] : null) . ':'
            . (isset($request['PAYMENT_UNITS']) ? $request['PAYMENT_UNITS'] : null) . ':'
            . (isset($request['PAYMENT_BATCH_NUM']) ? $request['PAYMENT_BATCH_NUM'] : null) . ':'
            . (isset($request['PAYER_ACCOUNT']) ? $request['PAYER_ACCOUNT'] : null) . ':'
            . strtoupper(md5($this->perfect_money_passphrase)) . ':'
            . (isset($request['TIMESTAMPGMT']) ? $request['TIMESTAMPGMT'] : null);

        $v2_hash = strtoupper(md5($signature));

        if ($v2_hash === $request['V2_HASH'] ) {
            return [
                'success' => true,
                'payment_id'=>$request['PAYMENT_ID'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>$request['PAYMENT_ID'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];
        }

    }

}