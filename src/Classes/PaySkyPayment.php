<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class PaySkyPayment extends BaseController implements PaymentInterface
{

    public  $paysky_mid;
    public  $paysky_tid;
    public  $paysky_secret;
    public  $paysky_mode;
    public $verify_route_name;

    public function __construct()
    {

        $this->paysky_mid = config("nafezly-payments.PAYSKY_MID");
        $this->paysky_tid = config("nafezly-payments.PAYSKY_TID");
        $this->paysky_secret = config("nafezly-payments.PAYSKY_SECRET");
        $this->paysky_mode = config("nafezly-payments.PAYSKY_MODE");
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
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {



        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'PAYSKY');

        if($this->payment_id==null)
            $unique_id = uniqid().rand(100000,999999);
        else
            $unique_id = $this->payment_id;



        date_default_timezone_set('UTC');
        $time = date('D, d M Y H:i:s \G\M\T');

        $data = [
            'PAYSKY_MODE'=>$this->paysky_mode,
            'paymentMethodFromLightBox'=> $this->source ?? 2,
            'MID'=>$this->paysky_mid,
            'TID'=>$this->paysky_tid,
            'AmountTrxn'=>$this->amount,
            'MerchantReference'=>$unique_id,
            'TrxDateTime'=>$time,
            'SecureHash'=>$this->generateSecureHash($time,$this->amount,$unique_id),
            'callback_url'=>route($this->verify_route_name, ['payment' => "paysky"]),
        ];

        return [
            'payment_id' => $unique_id,
            'html' => $this->generate_html($data),
            'redirect_url'=>""
        ];

    }


    protected  function generateSecureHash($time , $amount , $merchRef )
    {
        $merchantId =  $this->paysky_mid;
        $terminalId =  $this->paysky_tid;
        $secretKey =  $this->paysky_secret;
        $hashing = "Amount=$amount&DateTimeLocalTrxn=$time&MerchantId=$merchantId&MerchantReference=$merchRef&TerminalId=$terminalId";
        return strtoupper ( hash_hmac('sha256', $hashing , $this->hexToStr($secretKey)));
    }

    protected function hexToStr($hex){
        $string = '';
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }
        return $string;
    }




    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {   

        $callbackParams = [
            'Amount' => $request['Amount'] ?? "" ,
            'Currency' => $request['Currency'] ?? "",
            'MerchantId' => $this->paysky_mid ?? "",
            'MerchantReference' => $request['MerchantReference'] ?? "",
            'PaidThrough' => $request['PaidThrough'] ?? "",
            'TerminalId' => $this->paysky_tid ?? "",
            'TxnDate' => $request['TxnDate']
        ];
        $receivedSecureHash = $request['SecureHash'];
        $merchantSecretKey = $this->paysky_secret;
        ksort($callbackParams);
        $concatenatedString = http_build_query($callbackParams, '', '&');
        $decodedSecretKey = hex2bin($merchantSecretKey);
        $generatedHash = hash_hmac('sha256', $concatenatedString, $decodedSecretKey);
        $generatedHash = strtoupper($generatedHash);

        if ($generatedHash === $receivedSecureHash) {
            return [
                'success' => true,
                'payment_id'=>$request['MerchantReference'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $request->all()
            ];
        } else {
           return [
                'success' => false,
                'payment_id'=>"",
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $request->all()
            ];

        }
        
        $callbackParams = [
            'Amount' => $request['Amount'] ?? "" ,
            'Currency' => $request['Currency'] ?? "",
            'MerchantId' => $this->paysky_mid ?? "",
            'MerchantReference' => $request['MerchantReference'] ?? "",
            'PaidThrough' => $request['PaidThrough'] ?? "",
            'TerminalId' => $this->paysky_tid ?? "",
            'TxnDate' => $request['TxnDate']
        ];
        $receivedSecureHash = $request['SecureHash'] ?? "" ;
        $merchantSecretKey = $this->paysky_secret;

        //ksort($callbackParams);

        

        $concatenatedString = http_build_query($callbackParams, '', '&');

        $generatedHash = hash_hmac('sha256', $merchantSecretKey, $merchantSecretKey);
        $generatedHash = strtoupper($generatedHash);

        dump($generatedHash);
        dump($receivedSecureHash);
        dd("TEST");

        dump(hash('sha256', "Amount=".$request['Amount']."&Currency=".$request['Currency']."&MerchantId=".$this->paysky_mid."&MerchantReference=".$request['MerchantReference']."&PaidThrough=".$request['PaidThrough']."&TerminalId=".$this->paysky_tid."&TxnDate=".$request['TxnDate'] . $this->paysky_secret));

        dump($request['SecureHash']);
        
  //dd($request->all());


        if( isset($request['TxnDate']) && isset($request['SystemReference']) && isset($request['NetworkReference']) && isset($request['Amount']) && isset($request['Currency']) &&  isset($request['PaidThrough']) && isset($request['PayerAccount']) && isset($request['SecureHash'])){

            $data = $request['TxnDate'] . $request['SystemReference'] . $request['NetworkReference'] . $request['Amount'] . $request['Currency'] . $request['PaidThrough'] . $request['PayerAccount'];

            $generatedHash = hash('sha256', $data . $this->paysky_secret);

            dump($generatedHash);
            dump($request['SecureHash']);
            dd("TEST");
            if ($generatedHash == $request['SecureHash']) {
                return [
                    'success' => true,
                    'payment_id'=>$request['MerchantReference'],
                    'message' => __('nafezly::messages.PAYMENT_DONE'),
                    'process_data' => $request->all()
                ];
            }

        }


        return [
            'success' => false,
            'payment_id'=>"",
            'message' => __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request->all()
        ];
        
    }

    /**
     * @param $amount
     * @param $data
     * @return string
     */
    private function generate_html($data): string
    {
        return view('nafezly::html.paysky', ['model' => $this, 'data' => $data])->render();
    }

}