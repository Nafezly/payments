<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class WisePayment extends BaseController implements PaymentInterface
{
    private $wise_api_key;
    private $wise_balance_id;
    private $wise_profile_id;
    private $verify_route_name;

    public function __construct()
    {
        $this->wise_api_key = config('nafezly-payments.WISE_API_KEY');
        $this->wise_balance_id = config('nafezly-payments.WISE_BALANCE_ID');
        $this->wise_profile_id = config('nafezly-payments.WISE_PROFILE_ID');
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
     * @return Application|RedirectResponse|Redirector
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setPassedVariablesToGlobal($amount,$user_id,$user_first_name,$user_last_name,$user_email,$user_phone,$source);
        $required_fields = ['amount'];
        $this->checkRequiredFields($required_fields, 'Wise');
      
        try{
            $payer_name = null;
            if($this->user_first_name." ".$this->user_last_name!=" ")
                $payer_name=$this->user_first_name." ".$this->user_last_name;

            $selectedPaymentMethods_init=["WISE_ACCOUNT","ACCOUNT_DETAILS"/*,"CARD"*/];
            if(is_array($this->source))
                $selectedPaymentMethods_init=$this->source;
        
            $init = \Http::withHeaders([
                'Cookie'=>"oauthToken=".$this->wise_api_key,
                'Authorization'=>"Bearer ".$this->wise_api_key
            ])->get('https://wise.com/gateway/v3/profiles/'.$this->wise_profile_id.'/acquiring/payment-methods', [
                'currency' => 'USD',
                'amount' => $this->amount,
            ]);

            $available_payment_methods = [];

            foreach($selectedPaymentMethods_init as $check_available_method)
                if(in_array($check_available_method,collect($init->json()['content'])->where('available',true)->pluck('paymentMethodType')->toArray()))
                    $available_payment_methods[]=$check_available_method;

            $create_payment = \Http::withHeaders([
                'Host' => 'wise.com',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-GB',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Content-Type' => 'application/json',
                'X-Access-Token' => 'Tr4n5f3rw153',
                'Content-Length' => 150,
                'Origin' => 'https://wise.com',
                'Referer' => 'https://wise.com/flows/request/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'Te' => 'trailers',
                'Cookie'=>"oauthToken=".$this->wise_api_key,
                'Authorization'=>"Bearer ".$this->wise_api_key
            ])->post('https://wise.com/gateway/v2/profiles/'.$this->wise_profile_id.'/acquiring/payment-requests', [
                'requestType' => 'SINGLE_USE',
                'amountValue' => $this->amount,
                'balanceId' => $this->wise_balance_id,
                'payer' => [
                    'name' => $payer_name,
                ],
                'selectedPaymentMethods' => $available_payment_methods,
            ]);
            $create_payment_response = $create_payment->json();
            $publish_payment_response = \Http::withHeaders([
                'Host' => 'wise.com',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-GB',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Content-Type' => 'application/json',
                'X-Access-Token' => 'Tr4n5f3rw153',
                'X-Language' => 'en',
                'Content-Length' => 22,
                'Origin' => 'https://wise.com',
                'Referer' => 'https://wise.com/flows/request/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'Te' => 'trailers',
                'Cookie'=>"oauthToken=".$this->wise_api_key,
                'Authorization'=>"Bearer ".$this->wise_api_key
            ])->put('https://wise.com/gateway/v2/profiles/'.$this->wise_profile_id.'/acquiring/payment-requests/'.$create_payment_response['id'].'/status', [
                'status' => 'PUBLISHED',
            ]);
            $responseData = $publish_payment_response->json();
            return [
                'payment_id'=>$responseData['id'],
                'html' => $responseData,
                'redirect_url'=>$responseData['link']
            ];
        }catch(\Exception $e){
            return [
                'payment_id'=>"",
                'html' => $e,
                'redirect_url'=>""
            ];
        }

    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $paid = 0;
        try{
            $response = Http::withHeaders([
                'time-zone' => 'Africa/Cairo',
                'Cookie'=>"oauthToken=".$this->wise_api_key,
                'Authorization'=>"Bearer ".$this->wise_api_key
            ])->get('https://wise.com/gateway/v1/profiles/'.$this->wise_profile_id.'/acquiring/payment-request-details/'.$request['payment_id'])->json();

            if(str_contains($response['subtitle'], "Paid") && !in_array("CANCEL", $response['actions']) && $response['badge']=="POSITIVE"){
                $paid=1;
            }
        }catch(\Exception $e){
            return [
                'success' => false,
                'payment_id'=>"",
                'message' => $e,
                'process_data' => $e
            ];
        }

        if ($paid == 1) {
            return [
                'success' => true,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_DONE'),
                'process_data' => $response
            ];
        } else {
            return [
                'success' => false,
                'payment_id'=>$request['payment_id'],
                'message' => __('nafezly::messages.PAYMENT_FAILED'),
                'process_data' => $response
            ];
        }

    }


    public function payments($type="paid")
    {
      
        try{
            $options = [
                'requestType' => 'SINGLE_USE',
                'status' => 'COMPLETED',
                'sortBy' => 'UPDATED_AT',
                'sortOrder' => 'DESC',
            ];
            if($type=="unpaid")
                $options = [
                    'requestType' => 'SINGLE_USE',
                    'status' => 'PUBLISHED',
                    'sortBy' => 'EXPIRATION_AT',
                    'sortOrder' => 'ASC',
                ];
            $response = Http::withHeaders([
                'time-zone' => 'Africa/Cairo',
                'Cookie'=>"oauthToken=".$this->wise_api_key,
                'Authorization'=>"Bearer ".$this->wise_api_key
            ])->get('https://wise.com/gateway/v2/profiles/'.$this->wise_profile_id.'/acquiring/payment-request-summaries',$options)->json();
            
            $payments = [];
            foreach(collect($response['groups']) as $group){ 
                if(is_array($group['content']))
                    $payments=array_merge($payments,$group['content']);
            }
            return [
                'success' => true,
                'payment_id'=>"",
                'message' => "",
                'process_data' => $payments
            ];
        }catch(\Exception $e){
            return [
                'success' => false,
                'payment_id'=>"",
                'message' => $e,
                'process_data' => $e
            ];
        }

    }


}