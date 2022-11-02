# Nafezly Payment Gateways

[![Awesome](https://cdn.rawgit.com/sindresorhus/awesome/d7305f38d29fed78fa85652e3a63e154dd8e8829/media/badge.svg)](https://github.com/sindresorhus/awesome)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![Made With Love](https://img.shields.io/badge/Made%20With-Love-orange.svg)](https://github.com/chetanraj/awesome-github-badges)

Payment Helper of Payment Gateways ( PayPal - Paymob - Fawry - Thawani - WeAccept - Kashier - Hyperpay - Tap - Opay - Paytabs - Vodaphone Cash - Orange Money - Meza Wallet - Etisalat Cash)
![payment-gateways.jpg](https://github.com/nafezly/payments/blob/master/payment-gateways.jpg?raw=true&v=2)


## Supported gateways

- [PayPal](https://paypal.com/)
- [PayMob](https://paymob.com/)
- [WeAccept](https://paymob.com/)
- [Kashier](https://kashier.io/)
- [Fawry](https://fawry.com/)
- [HyperPay](https://www.hyperpay.com/)
- [Thawani](https://thawani.om/)
- [Tap](https://www.tap.company/)
- [Opay](https://www.opaycheckout.com/)
- [Paytabs](https://site.paytabs.com/)
- [E Wallets (Vodaphone Cash - Orange Money - Meza Wallet - Etisalat Cash)](https://paymob.com/)

## Installation

```jsx
composer require nafezly/payments
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


    #PAYMOB_WALLET (vodaphone-cash,orange-money,etisalat-cash,we-cash,meza-wallet) - test phone 01010101010 ,PIN & OTP IS 123456
    'PAYMOB_WALLET_INTEGRATION_ID'=>env('PAYMOB_WALLET_INTEGRATION_ID'),

    #Paytabs
    'PAYTABS_PROFILE_ID'  => env('PAYTABS_PROFILE_ID'),
    'PAYTABS_SERVER_KEY' =>  env('PAYTABS_SERVER_KEY'),
    'PAYTABS_BASE_URL' =>   env('PAYTABS_BASE_URL',"https://secure-egypt.paytabs.com"),
    'PAYTABS_CHECKOUT_LANG' => env('PAYTABS_CHECKOUT_LANG',"AR"),
    'PAYTABS_CURRENCY'=>env('PAYTABS_CURRENCY',"EGP"),

    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME'),

    #NOON_PAYMENT
    "NOON_PAYMENT_BUSINESS_ID" => env('NOON_PAYMENT_BUSINESS_ID'),
    "NOON_PAYMENT_APP_NAME" => env('NOON_PAYMENT_APP_NAME'),
    "NOON_PAYMENT_APP_KEY" => env('NOON_PAYMENT_APP_KEY'),
    /**
     *  Base64(BusinessIdentifier.ApplicationIdentifier:ApplicationKey)
     */
    "NOON_PAYMENT_AUTH_KEY" => base64_encode(env('NOON_PAYMENT_BUSINESS_ID').".".env("NOON_PAYMENT_APP_NAME").":".env("NOON_PAYMENT_APP_KEY")),
    "NOON_PAYMENT_RETURN_URL" => env('NOON_PAYMENT_RETURN_URL'),
    /*  Change the mode to Live for the Production */
    "NOON_PAYMENT_MODE" => env('NOON_PAYMENT_MODE'),
    /*  Pre-configured order route categories (the categories will be mentioned also in the initial setup email) */
    "NOON_PAYMENT_ORDER_CATEGORY" => env('NOON_PAYMENT_ORDER_CATEGORY'),
    /*  Channels are pre-defined and limited to Web / Mobile (the channels will be mentioned also in the initial setup email) */
    "NOON_PAYMENT_CHANNEL" => env('NOON_PAYMENT_CHANNEL'),
    /*  Default value - Payment API Endpoint - Chooses 
        https://api.noonpayments.com/payment/v1/
        https://api-test.noonpayments.com/payment/v1/ */
    "NOON_PAYMENT_PAYMENT_API" => env('NOON_PAYMENT_PAYMENT_API'),
];
```

## Web.php MUST Have Route with name “payment-verify”

```php
Route::get('/payments/verify/{payment?}',[FrontController::class,'payment_verify'])->name('payment-verify');
```

## How To Use

```jsx
use Nafezly\Payments\PaymobPayment;

$payment = new PaymobPayment();

//pay function
$payment->pay(
	$amount, 
	$user_id = null, 
	$user_first_name = null, 
	$user_last_name = null, 
	$user_email = null, 
	$user_phone = null, 
	$source = null
);

//or use
$payment->setUserId($id)
        ->setUserFirstName($first_name)
        ->setUserLastName($last_name)
        ->setUserEmail($email)
        ->setUserPhone($phone)
        ->setCurrency($currency)
        ->setAmount($amount)
        ->pay();

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

## How To Use NoonPayment

```jsx
use Nafezly\Payments\NoonPayment;

$payment = new NoonPayment();

// by fluent interface
$response = $payment->setUserId($id)
            ->setCurrency($currency)
            ->setOrderName($order_name)
            ->setConfigurationLocal($local)
            ->setAmount($amount)
            ->pay();
// example
$response = $payment->setUserId(1)
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount($amount)
            ->pay();

//pay function response 
[
	'payment_id'=>"", // refrence code that should stored in your orders table
	'redirect_url'=>"", // redirect url available for some payment gateways
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

## Available Classes

```php

use Nafezly\Payments\Classes\FawryPayment;
use Nafezly\Payments\Classes\HyperPayPayment;
use Nafezly\Payments\Classes\KashierPayment;
use Nafezly\Payments\Classes\PaymobPayment;
use Nafezly\Payments\Classes\PayPalPayment;
use Nafezly\Payments\Classes\ThawaniPayment;
use Nafezly\Payments\Classes\TapPayment;
use Nafezly\Payments\Classes\OpayPayment;
use Nafezly\Payments\Classes\PaytabsPayment;
use Nafezly\Payments\Classes\PaymobWalletPayment;
use Nafezly\Payments\Classes\NoonPayment;
```

## Test Cards

- [Thawani](https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card)
- [Kashier](https://developers.kashier.io/payment/testing)
- [Paymob](https://docs.paymob.com/docs/card-payments)
- [Fawry](https://developer.fawrystaging.com/docs/testing/testing)
- [Tap](https://www.tap.company/eg/en/developers)
- [Opay](https://doc.opaycheckout.com/end-to-end-testing)
- [PayTabs](https://support.paytabs.com/en/support/solutions/articles/60000712315-what-are-the-test-cards-available-to-perform-payments-)
- [NoonPayment](https://docs.noonpayments.com/test/cards)

