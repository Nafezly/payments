<?php 

class PayTabPayment{

    private $paytab_profile_id;
    private $paytab_base_url;
    private $paytab_server_key;
    public function __construct()
    {
        $this->paytab_profile_id = config('nafezly-payments.PAYTAB_PROFILE_ID');
        $this->paytab_base_url = config('nafezly-payments.PAYTAB_BASE_URL');
        $this->paytab_server_key = config('nafezly-payments.PAYTAB_SERVER_KEY');
 
    }


    public function sendRequest($request_url, $data, $request_method = null){

        $data['profile_id'] = $this->paytab_profile_id;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->paytab_base_url . $request_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => isset($request_method) ? $request_method : 'POST',
            CURLOPT_POSTFIELDS => json_encode($data, true),
            CURLOPT_HTTPHEADER => array(
                'authorization:' . $this->paytab_server_key,
                'Content-Type:application/json'
            ),
        ));

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        return $response;
    }

     /**
     * @param $amount
     * @param null $user_first_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $currency
     * @param null $paypage_lang
     * @param null $callback
     * @param null $return
     * @return string[]
     */

    public function pay($amount , $user_first_name = null , $user_email , $user_phone ,  $currency , $paypage_lang = "en", $callback , $return){

        $order_id = uniqid();

        $plugin = new PayTabPayment();
        $request_url = 'payment/request';
        $data = [
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => $order_id,
            "cart_currency" => $currency,
            "cart_amount" => $amount,
            "cart_description" => "items",
            "paypage_lang" => $paypage_lang,
            "callback" => $callback, 
            "return" => $return,
            "customer_details" => [
                "name" => $user_first_name,
                "email" => $user_email,
                "phone" => $user_phone,
                "street1" => "delivery_street",
                "city" => "not given",
                "state" => "not given",
                "country" => "not given",
                "zip" => "00000"
            ],
            "shipping_details" => [
                "name" => "not given",
                "email" => "not given",
                "phone" => "not given",
                "street1" => "not given",
                "city" => "not given",
                "state" => "not given",
                "country" => "not given",
                "zip" => "0000"
            ],
            "user_defined" => [
                "udf9" => "UDF9",
                "udf3" => "UDF3"
            ]
        ];
        $page = $this->sendRequest($request_url, $data);
        if(!isset($page['redirect_url'])) {
            return [
                'status' => false,
                'message' => 'mis configuration or data missing'
            ] ;
        }
      
        return [
            'payment_id'=>$order_id,
            'html' => "",
            'redirect_url'=>$page['redirect_url']
        ];
    }

}