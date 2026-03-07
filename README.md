# Nafezly Payment Gateways

[![Awesome](https://cdn.rawgit.com/sindresorhus/awesome/d7305f38d29fed78fa85652e3a63e154dd8e8829/media/badge.svg)](https://github.com/sindresorhus/awesome)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![Made With Love](https://img.shields.io/badge/Made%20With-Love-orange.svg)](https://github.com/chetanraj/awesome-github-badges)

A unified payment helper and wrapper supporting global and regional payment gateways:

PayPal, Stripe, Paymob, Fawry, HyperPay, Thawani, Tap, Opay, PayTabs, Binance, CoinPayments, PerfectMoney, Cryptomus, Payrexx, Wise, Changelly, OneLat, Kashier — including mobile wallets (Vodafone Cash, Orange Money, Etisalat Cash, Meeza Wallet), and more.

![gateways.jpg](https://github.com/nafezly/payments/blob/master/gateways.jpg?raw=true&v=9)


## Supported gateways

## Supported Gateways

- [PayPal](https://paypal.com/)
- [PayPal Credit Cards](https://developer.paypal.com/docs/checkout/standard/)
- [Stripe](https://stripe.com/)
- [PayMob](https://paymob.com/)
- [WeAccept](https://paymob.com/)
- [Paymob Wallets (Vodafone Cash / Orange Money / Etisalat Cash / Meeza Wallet)](https://paymob.com/)
- [Kashier](https://kashier.io/)
- [Fawry](https://fawry.com/)
- [HyperPay](https://www.hyperpay.com/)
- [Thawani](https://thawani.om/)
- [Tap](https://www.tap.company/)
- [Opay](https://www.opaycheckout.com/)
- [Paytabs](https://site.paytabs.com/)
- [Binance](https://www.binance.com/)
- [PerfectMoney](https://perfectmoney.com/)
- [NowPayments](https://nowpayments.io/)
- [NowPayments Invoice](https://nowpayments.io/)
- [Payeer](https://payeer.com/)
- [Telr](https://telr.com/)
- [ClickPay](https://clickpay.com.sa/)
- [CoinPayments](https://www.coinpayments.net/)
- [BigPay](https://www.big-pay.com/)
- [Enot](https://enot.io/)
- [PAYCEC](https://www.paycec.com/eg-en)
- [PayPal Credit Cards](https://developer.paypal.com/docs/checkout/standard/)
- [Payrexx](https://payrexx.com/en/)
- [Creptomus](https://cryptomus.com/)
- [SkipCash](https://skipcash.app/)
- [Moyasar](https://moyasar.com/)
- [E Wallets (Vodafone Cash - Orange Money - Meza Wallet - Etisalat Cash)](https://paymob.com/)
- [PaySky](https://paysky.io/)
- [Prime Payments](https://primepayments.com/)
- [Wise](https://wise.com/)
- [OneLat](https://one.lat/)
- [Changelly](https://changelly.com/)
- [YallaPay](https://yallapay.io/)
- [Mastercard Gateway](https://test-gateway.mastercard.com/)


## Installation

```jsx
composer require nafezly/payments dev-master
```

## Publish Vendor Files

```jsx
php artisan vendor:publish --tag="nafezly-payments-config"
php artisan vendor:publish --tag="nafezly-payments-lang"
```

### nafezly-payments.php file

```php
<?php
return [

    #PAYMOB
    'PAYMOB_API_KEY' => env('PAYMOB_API_KEY'),
    'PAYMOB_INTEGRATION_ID' => env('PAYMOB_INTEGRATION_ID'),
    'PAYMOB_IFRAME_ID' => env('PAYMOB_IFRAME_ID'),
    'PAYMOB_HMAC' => env('PAYMOB_HMAC'),
    'PAYMOB_CURRENCY'=> env('PAYMOB_CURRENCY',"EGP"),


    #HYPERPAY
    'HYPERPAY_BASE_URL' => env('HYPERPAY_BASE_URL', "https://eu-test.oppwa.com"),
    'HYPERPAY_URL' => env('HYPERPAY_URL', env('HYPERPAY_BASE_URL') . "/v1/checkouts"),
    'HYPERPAY_TOKEN' => env('HYPERPAY_TOKEN'),
    'HYPERPAY_CREDIT_ID' => env('HYPERPAY_CREDIT_ID'),
    'HYPERPAY_MADA_ID' => env('HYPERPAY_MADA_ID'),
    'HYPERPAY_APPLE_ID' => env('HYPERPAY_APPLE_ID'),
    'HYPERPAY_CURRENCY' => env('HYPERPAY_CURRENCY', "SAR"),


    #KASHIER
    'KASHIER_ACCOUNT_KEY' => env('KASHIER_ACCOUNT_KEY'),
    'KASHIER_IFRAME_KEY' => env('KASHIER_IFRAME_KEY'),
    'KASHIER_TOKEN' => env('KASHIER_TOKEN'),
    'KASHIER_URL' => env('KASHIER_URL', "https://checkout.kashier.io"),
    'KASHIER_MODE' => env('KASHIER_MODE', "test"), //live or test
    'KASHIER_CURRENCY'=>env('KASHIER_CURRENCY',"EGP"),
    'KASHIER_WEBHOOK_URL'=>env('KASHIER_WEBHOOK_URL'),


    #FAWRY
    'FAWRY_URL' => env('FAWRY_URL', "https://atfawry.fawrystaging.com/"),//https://www.atfawry.com/ for production
    'FAWRY_SECRET' => env('FAWRY_SECRET'),
    'FAWRY_MERCHANT' => env('FAWRY_MERCHANT'),


    #PayPal
    'PAYPAL_CLIENT_ID' => env('PAYPAL_CLIENT_ID'),
    'PAYPAL_SECRET' => env('PAYPAL_SECRET'),
    'PAYPAL_CURRENCY' => env('PAYPAL_CURRENCY', "USD"),
    'PAYPAL_MODE' => env('PAYPAL_MODE',"sandbox"),//sandbox or live


    #THAWANI
    'THAWANI_API_KEY' => env('THAWANI_API_KEY', ''),
    'THAWANI_URL' => env('THAWANI_URL', "https://uatcheckout.thawani.om/"),
    'THAWANI_PUBLISHABLE_KEY' => env('THAWANI_PUBLISHABLE_KEY', ''),

    #TAP
    'TAP_CURRENCY' => env('TAP_CURRENCY',"USD"),
    'TAP_SECRET_KEY'=>env('TAP_SECRET_KEY','sk_test_XKokBfNWv6FIYuTMg5sLPjhJ'),
    'TAP_PUBLIC_KEY'=>env('TAP_PUBLIC_KEY','pk_test_EtHFV4BuPQokJT6jiROls87Y'),
    'TAP_LANG_KEY'=>env('TAP_LANG_KEY','ar'),


    #OPAY
    'OPAY_CURRENCY'=>env('OPAY_CURRENCY',"EGP"),
    'OPAY_SECRET_KEY'=>env('OPAY_SECRET_KEY'),
    'OPAY_PUBLIC_KEY'=>env('OPAY_PUBLIC_KEY'),
    'OPAY_MERCHANT_ID'=>env('OPAY_MERCHANT_ID'),
    'OPAY_COUNTRY_CODE'=>env('OPAY_COUNTRY_CODE',"EG"),
    'OPAY_BASE_URL'=>env('OPAY_BASE_URL',"https://sandboxapi.opaycheckout.com"),//https://api.opaycheckout.com for production


    #PAYMOB_WALLET (Vodafone-cash,orange-money,etisalat-cash,we-cash,meza-wallet) - test phone 01010101010 ,PIN & OTP IS 123456
    'PAYMOB_WALLET_INTEGRATION_ID'=>env('PAYMOB_WALLET_INTEGRATION_ID'),

    #Paytabs
    'PAYTABS_PROFILE_ID'  => env('PAYTABS_PROFILE_ID'),
    'PAYTABS_SERVER_KEY' =>  env('PAYTABS_SERVER_KEY'),
    'PAYTABS_BASE_URL' =>   env('PAYTABS_BASE_URL',"https://secure-egypt.paytabs.com"),
    'PAYTABS_CHECKOUT_LANG' => env('PAYTABS_CHECKOUT_LANG',"AR"),
    'PAYTABS_CURRENCY'=>env('PAYTABS_CURRENCY',"EGP"),

    
    #Binance
    'BINANCE_API'=>env('BINANCE_API'),
    'BINANCE_SECRET'=>env('BINANCE_SECRET'),


    #NowPayments
    'NOWPAYMENTS_API_KEY'=>env('NOWPAYMENTS_API_KEY'),


    #Payeer
    'PAYEER_MERCHANT_ID'=>env('PAYEER_MERCHANT_ID'),
    'PAYEER_API_KEY'=>env('PAYEER_API_KEY'),
    'PAYEER_ADDITIONAL_API_KEY'=>env('PAYEER_ADDITIONAL_API_KEY'),


    #Perfectmoney
    /*
    *please 
    *1- create POST route /payments/verify/{payment} and put it before your verify route 
    *2- put it into app/Http/Middleware/VerifyCsrfToken.php middleware inside except array
    */
    'PERFECT_MONEY_ID'=>env('PERFECT_MONEY_ID','UXXXXXXX'),
    'PERFECT_MONEY_PASSPHRASE'=>env('PERFECT_MONEY_PASSPHRASE'),

    #TELR
    'TELR_MERCHANT_ID'=>env('TELR_MERCHANT_ID'),
    'TELR_API_KEY'=>env('TELR_API_KEY'),
    'TELR_MODE'=>env('TELR_MODE','test'),//test,live
    #CLICKPAY
    'CLICKPAY_SERVER_KEY'=>env('CLICKPAY_SERVER_KEY'),
    'CLICKPAY_PROFILE_ID'=>env('CLICKPAY_PROFILE_ID'),


    #SKIPCASH
    'SKIPCASH_SECRET_KEY'=>env('SKIPCASH_SECRET_KEY'),
    'SKIPCASH_KEY_ID'=>env('SKIPCASH_KEY_ID'),
    'SKIPCASH_WEBHOOK_KEY'=>env('SKIPCASH_WEBHOOK_KEY'),
    'SKIPCASH_MODE'=>env('SKIPCASH_MODE','test'), //test,live

    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME'),
    //and more config for another payment gateways
];
```

## Web.php MUST Have Route with name “verify-payment”

```php
Route::get('/payments/verify/{payment?}',[FrontController::class,'payment_verify'])->name('verify-payment');
```

## How To Use

```jsx
use Nafezly\Payments\Classes\PaymobPayment;

$payment = new PaymobPayment();


//or use (recommended)
$payment->setUserId($id)
        ->setUserFirstName($first_name)
        ->setUserLastName($last_name)
        ->setUserEmail($email)
        ->setUserPhone($phone)
        ->setCurrency($currency)
        ->setAmount($amount)
        ->pay();

//pay function (deprecated and will be disabled later)
$payment->pay(
	$amount, 
	$user_id = null, 
	$user_first_name = null, 
	$user_last_name = null, 
	$user_email = null, 
	$user_phone = null, 
	$source = null
);



//pay function response 
[
	'payment_id'=>"", // refrence code that should stored in your orders table
	'redirect_url'=>"", // redirect url available for some payment gateways
	'html'=>"" // rendered html available for some payment gateways
]

//verify function
$payment->verify($request);

//outputs
[
	'success'=>true,//or false
    'payment_id'=>"PID",
	'message'=>"Done Successfully",//message for client
	'process_data'=>""//payment response
]

```

### Mastercard Usage Example

```php
use Nafezly\Payments\Classes\MastercardPayment;

$payment = new MastercardPayment();

$response = $payment->setUserId($id)
    ->setUserFirstName($first_name)
    ->setUserLastName($last_name)
    ->setUserEmail($email)
    ->setUserPhone($phone)
    ->setAmount($amount)
    ->setCurrency('USD')
    ->setOperation('PAY') // PAY or AUTHORIZE
    ->pay();

// $response contains:
// payment_id, html (Hosted Checkout script), redirect_url

// verify callback in your verify-payment route:
// $verify = (new MastercardPayment())->verify($request);
```

### Mastercard Token / Recurring Charge Example

```php
use Nafezly\Payments\Classes\MastercardPayment;

$gateway = new MastercardPayment();

// 1) Run first payer-initiated payment via Hosted Checkout
// 2) In verify response, check if token exists in process_data['tokenization']['token']
// 3) Store token in your project DB

$charge = $gateway->chargeByToken(
    100.00,
    $storedToken,
    'order_12345',
    'USD',
    'PAY' // or AUTHORIZE
);
```

### Factory Pattern Use
you can pass only method name without payment key word like (Fawry,Paymob,Opay,SkipCash ...etc) 
and the factory will return the payment instance for you , use it as you want ;)
```php
    $payment = new \Nafezly\Payments\Factories\PaymentFactory();
    $payment=$payment->get(string $paymentName)->pay(
	$amount, 
	$user_id = null, 
	$user_first_name = null, 
	$user_last_name = null, 
	$user_email = null, 
	$user_phone = null, 
	$source = null
);;
```

### SkipCash Usage Example

```php
use Nafezly\Payments\Classes\SkipCashPayment;

$payment = new SkipCashPayment();

// Using pay function
$payment->pay(
    $amount,                    // Required: Payment amount
    $user_id = null,           // Optional: User ID
    $user_first_name,          // Required: User first name
    $user_last_name,           // Required: User last name
    $user_email,               // Required: User email (auto-generated if empty)
    $user_phone,               // Required: User phone number
    $source = null             // Optional: Payment source
);

// Using setter methods
$payment->setUserId($id)
        ->setUserFirstName($first_name)
        ->setUserLastName($last_name)
        ->setUserEmail($email)
        ->setUserPhone($phone)
        ->setAmount($amount)
        ->pay();
```

**SkipCash Configuration Requirements:**
- `SKIPCASH_SECRET_KEY`: Your SkipCash secret key
- `SKIPCASH_KEY_ID`: Your SkipCash key ID (UUID)
- `SKIPCASH_WEBHOOK_KEY`: Your webhook verification key
- `SKIPCASH_MODE`: 'test' for sandbox or 'live' for production

**Important Notes for SkipCash:**
- Phone numbers can be with or without country code for Qatar (+974)
- Non-Qatari numbers must include country code with + prefix
- Email is auto-generated as `{phone}@{domain}.com` if not provided
- Each payment must use unique phone number and email to avoid fraud detection

### Garanti BBVA (Sanal POS) Usage Example

```php
use Nafezly\Payments\Classes\GarantiBbvaPayment;

$payment = new GarantiBbvaPayment();

// Using pay function
$payment->pay(
    $amount,                    // Required: Payment amount
    $user_id = null,            // Optional: User ID
    $user_first_name,           // Required: User first name
    $user_last_name,            // Required: User last name
    $user_email,                // Required: User email
    $user_phone = null          // Optional: User phone
);

// Using setter methods
$payment->setUserId($id)
        ->setUserFirstName($first_name)
        ->setUserLastName($last_name)
        ->setUserEmail($email)
        ->setUserPhone($phone)
        ->setAmount($amount)
        ->pay();
```

**Garanti BBVA Configuration Requirements:**
- `GARANTIBBVA_MERCHANT_ID`
- `GARANTIBBVA_TERMINAL_ID`
- `GARANTIBBVA_PROV_USER_ID`
- `GARANTIBBVA_TERMINAL_USER_ID`
- `GARANTIBBVA_PROVISION_PASSWORD`
- `GARANTIBBVA_STORE_KEY`
- `GARANTIBBVA_MODE` (test/live)
- `GARANTIBBVA_SECURITY_LEVEL` (CUSTOM_PAY, 3D_PAY, 3D_FULL, 3D_HALF)
- `GARANTIBBVA_CURRENCY_CODE` (949=TRY, 840=USD, 978=EUR, 826=GBP, 392=JPY)
- `GARANTIBBVA_TXN_TYPE` (sales)
- `GARANTIBBVA_INSTALLMENT_COUNT` (0 for no installment)

**Important Notes for Garanti BBVA:**
- The gateway returns the result by POST to the verify route.
- Hash verification is already implemented; keep the `STORE_KEY` correct.
- Amount is multiplied by `GARANTIBBVA_AMOUNT_MULTIPLIER` (default 100).

## Some Test Cards

- [SkipCash](https://dev.skipcash.app/doc/api-integration/)
- [Thawani](https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card)
- [Kashier](https://developers.kashier.io/payment/testing)
- [Paymob](https://docs.paymob.com/docs/card-payments)
- [Fawry](https://developer.fawrystaging.com/docs/testing/testing)
- [Tap](https://developers.tap.company/reference/testing-cards)
- [Opay](https://doc.opaycheckout.com/end-to-end-testing)
- [PayTabs](https://support.paytabs.com/en/support/solutions/articles/60000712315-what-are-the-test-cards-available-to-perform-payments-)

