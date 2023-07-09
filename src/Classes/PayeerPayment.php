<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PayeerPayment extends BaseController implements PaymentInterface 
{
    

    public $payeer_api_key;
    public $payeer_additional_api_key;
    public $payeer_merchant_id;
    public $verify_route_name;

    public function __construct()
    {
        $this->payeer_api_key = config('nafezly-payments.PAYEER_API_KEY');
        $this->payeer_additional_api_key = config('nafezly-payments.PAYEER_ADDITIONAL_API_KEY');
        $this->payeer_merchant_id = config('nafezly-payments.PAYEER_MERCHANT_ID');
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
        $this->checkRequiredFields($required_fields, 'PAYEER');

        $m_shop = $this->payeer_merchant_id;
        $m_orderid = uniqid().rand(10000,99999);
        $m_amount = number_format($this->amount, 2, '.', '');
        $m_curr = 'USD';
        $m_desc = base64_encode('credit');

        $m_key = $this->payeer_api_key;
        $arHash = [
            $m_shop,
            $m_orderid,
            $m_amount,
            $m_curr,
            $m_desc
        ];
        $arParams = [
            'success_url' => route($this->verify_route_name,['payment'=>'payeer']),
            'fail_url' => route($this->verify_route_name,['payment'=>'payeer']),
            'status_url' => route($this->verify_route_name,['payment'=>'payeer']),
        ];
        $key = md5($this->payeer_additional_api_key.$m_orderid);
        $m_params = urlencode(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,$key, json_encode($arParams), MCRYPT_MODE_ECB)));
        $arHash[] = $m_params;
        $arHash[] = $m_key;
        $sign = strtoupper(hash('sha256', implode(':', $arHash)));
        $arGetParams = [
            'm_shop' => $m_shop,
            'm_orderid' => $m_orderid,
            'm_amount' => $m_amount,
            'm_curr' => $m_curr,
            'm_desc' => $m_desc,
            'm_sign' => $sign,
            'm_params' => $m_params,
        ];
        $url = 'https://payeer.com/merchant/?'.http_build_query($arGetParams);
        if($url!=null)
            return [
                'payment_id'=>$m_orderid,
                'html'=>"",
                'redirect_url'=>$url
            ];
        return [
            'payment_id'=>$m_orderid,
            'html'=>"",
            'redirect_url'=>""
        ];
    }

    /**
     * @param Request $request
     * @return array|void
     */
    public function verify(Request $request)
    {
        //if (!in_array($_SERVER['REMOTE_ADDR'], array('185.71.65.92', '185.71.65.189','149.202.17.210'))) return;
        if(isset($request['m_operation_id']) && isset($request['m_sign'])){
            $m_key = $this->payeer_api_key;
            $arHash = [
                $request['m_operation_id'],
                $request['m_operation_ps'],
                $request['m_operation_date'],
                $request['m_operation_pay_date'],
                $request['m_shop'],
                $request['m_orderid'],
                $request['m_amount'],
                $request['m_curr'],
                $request['m_desc'],
                $request['m_status']
            ];

            if (isset($request['m_params']))
                $arHash[] = $request['m_params'];

            $arHash[] = $m_key;
            $sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));
            if ($request['m_sign'] == $sign_hash && $request['m_status'] == 'success'){
                return [
                    'success' => true,
                    'payment_id'=>$request['m_orderid'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            }
        }
        return [
            'success' => false,
            'payment_id'=>$request['m_orderid'],
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];


    }

}