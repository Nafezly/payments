<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Nafezly\Payments\Exceptions\MissingPaymentInfoException;
use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes\BaseController;

class GarantiBbvaPayment extends BaseController implements PaymentInterface
{
    private $merchant_id;
    private $terminal_id;
    private $prov_user_id;
    private $terminal_user_id;
    private $provision_password;
    private $store_key;
    private $mode;
    private $api_version;
    private $security_level;
    private $currency_code;
    private $lang;
    private $txn_type;
    private $installment_count;
    private $amount_multiplier;
    private $company_name;
    private $test_url;
    private $live_url;
    private $verify_route_name;

    public function __construct()
    {
        $this->merchant_id = config('nafezly-payments.GARANTIBBVA_MERCHANT_ID');
        $this->terminal_id = config('nafezly-payments.GARANTIBBVA_TERMINAL_ID');
        $this->prov_user_id = config('nafezly-payments.GARANTIBBVA_PROV_USER_ID');
        $this->terminal_user_id = config('nafezly-payments.GARANTIBBVA_TERMINAL_USER_ID','GARANTI');
        $this->provision_password = config('nafezly-payments.GARANTIBBVA_PROVISION_PASSWORD');
        $this->store_key = config('nafezly-payments.GARANTIBBVA_STORE_KEY');
        $this->mode = config('nafezly-payments.GARANTIBBVA_MODE', 'test');
        $this->api_version = config('nafezly-payments.GARANTIBBVA_API_VERSION', '512');
        $this->security_level = config('nafezly-payments.GARANTIBBVA_SECURITY_LEVEL', '3D_PAY');
        $this->currency_code = config('nafezly-payments.GARANTIBBVA_CURRENCY_CODE', '949');
        $this->lang = config('nafezly-payments.GARANTIBBVA_LANG', 'tr');
        $this->txn_type = config('nafezly-payments.GARANTIBBVA_TXN_TYPE', 'sales');
        $this->installment_count = config('nafezly-payments.GARANTIBBVA_INSTALLMENT_COUNT', '0');
        $this->amount_multiplier = (int) config('nafezly-payments.GARANTIBBVA_AMOUNT_MULTIPLIER', 100);
        $this->company_name = config('nafezly-payments.GARANTIBBVA_COMPANY_NAME');
        $this->test_url = config('nafezly-payments.GARANTIBBVA_TEST_URL');
        $this->live_url = config('nafezly-payments.GARANTIBBVA_LIVE_URL');
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
     * @return array
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null): array
    {
        $this->setPassedVariablesToGlobal($amount, $user_id, $user_first_name, $user_last_name, $user_email, $user_phone, $source);
        $required_fields = ['amount', 'user_first_name', 'user_last_name', 'user_email'];
        $this->checkRequiredFields($required_fields, 'GARANTIBBVA');

        $order_id = $this->payment_id == null ? (uniqid() . rand(100000, 999999)) : $this->payment_id;
        $amount_value = $this->formatAmount($this->amount);
        $success_url = route($this->verify_route_name, ['payment' => 'garantibbva']);
        $error_url = route($this->verify_route_name, ['payment' => 'garantibbva']);
        $hash = $this->generateHashData($order_id, $amount_value, $this->currency_code, $success_url, $error_url, $this->txn_type, $this->installment_count);

        return [
            'payment_id' => $order_id,
            'html' => $this->generate_html([
                'action_url' => $this->getGatewayUrl(),
                'mode' => $this->mode === 'live' ? 'PROD' : 'TEST',
                'api_version' => $this->api_version,
                'security_level' => $this->security_level,
                'terminalprovuserid' => $this->prov_user_id,
                'terminaluserid' => $this->terminal_user_id,
                'terminalmerchantid' => $this->merchant_id,
                'terminalid' => $this->terminal_id,
                'orderid' => $order_id,
                'successurl' => $success_url,
                'errorurl' => $error_url,
                'customeremailaddress' => $this->user_email,
                'customeripaddress' => request()->ip(),
                'companyname' => $this->company_name ?? config('app.name'),
                'lang' => $this->lang,
                'txntimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'refreshtime' => '1',
                'secure3dhash' => $hash,
                'txnamount' => $amount_value,
                'display_amount' => number_format((float) $this->amount, 2) . ' TRY',
                'txntype' => $this->txn_type,
                'txncurrencycode' => $this->currency_code,
                'txninstallmentcount' => $this->installment_count,
                'cardholdername' => trim($this->user_first_name . ' ' . $this->user_last_name),
                'user_phone' => $this->user_phone,
            ]),
            'redirect_url' => "",
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function verify(Request $request): array
    {
        $request_data = $request->all();
        $hash_valid = $this->verifyResponseHash($request_data);
        $response = strtoupper($request->get('response', ''));
        $proc_return_code = $request->get('procreturncode');
        $success = $hash_valid && $response === 'APPROVED' && $proc_return_code === '00';

        return [
            'success' => $success,
            'payment_id' => $request->get('orderid') ?? $request->get('oid') ?? $request->get('order_id') ?? $request->get('payment_id'),
            'message' => $success ? __('nafezly::messages.PAYMENT_DONE') : __('nafezly::messages.PAYMENT_FAILED'),
            'process_data' => $request_data,
        ];
    }

    private function getGatewayUrl(): string
    {
        return $this->mode === 'live' ? $this->live_url : $this->test_url;
    }

    private function formatAmount($amount): string
    {
        $multiplier = $this->amount_multiplier > 0 ? $this->amount_multiplier : 100;
        return (string) (int) round(((float) $amount) * $multiplier);
    }

    private function generateSecurityData($terminal_id): string
    {
        // Formula: hashedPassword = SHA1(provisionPassword + "0" + terminalId)
        $sha_data = sha1($this->provision_password . '0' . $terminal_id);
        return strtoupper($sha_data);
    }

    private function generateHashData($order_id, $amount, $currency_code, $success_url, $error_url, $type, $installment_count): string
    {
        $hashed_password = $this->generateSecurityData($this->terminal_id);
        $terminal_id = (string) $this->terminal_id;
        $hash_string = $terminal_id . $order_id . $amount . $currency_code . $success_url . $error_url . $type . $installment_count . $this->store_key . $hashed_password;
        return strtoupper(hash('sha512', $hash_string));
    }

    private function verifyResponseHash(array $data): bool
    {
        $response_hash = $data['hash'] ?? null;
        $hash_params = $data['hashparams'] ?? null;

        if (!$response_hash || !$hash_params) {
            return false;
        }

        $param_list = explode(':', $hash_params);
        $digest_data = '';
        foreach ($param_list as $param) {
            if ($param === '') {
                continue;
            }
            $digest_data .= $data[$param] ?? '';
        }
        $digest_data .= $this->store_key;

        // Use SHA-512 to match the hash format returned by Garanti BBVA
        $calculated_hash = strtoupper(hash('sha512', $digest_data));
        return hash_equals($calculated_hash, strtoupper($response_hash));
    }

    private function generate_html($data): string
    {
        return view('nafezly::html.garantibbva', ['data' => $data])->render();
    }
}
