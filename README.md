# Nafezly Payment Gateways

[![Awesome](https://cdn.rawgit.com/sindresorhus/awesome/d7305f38d29fed78fa85652e3a63e154dd8e8829/media/badge.svg)](https://github.com/sindresorhus/awesome)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![Made With Love](https://img.shields.io/badge/Made%20With-Love-orange.svg)](https://github.com/chetanraj/awesome-github-badges)

Payment Helper of Payment Gateways ( PayPal - Paymob - Fawry - Thawani - WeAccept - Kashier - Hyperpay )
![payment-gateways.png](https://github.com/nafezly/payments/blob/master/payment-gateways.png?raw=true)

## Supported gateways

- [PayPal](https://paypal.com/)
- [PayMob](https://paymob.com/)
- [WeAccept](https://paymob.com/)
- [Kashier](https://kashier.io/)
- [Fawry](https://fawry.com/)
- [HyperPay](https://www.hyperpay.com/)
- [Thawani](https://https://thawani.om/)

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
    'KASHIER_URL' => env('KASHIER_URL', "https://checkout.kashier.io"),
    'KASHIER_MODE' => env('KASHIER_MODE', "test"), //live or test
    'KASHIER_CURRENCY'=>env('KASHIER_CURRENCY',"EGP"),

    #FAWRY
    'FAWRY_URL' => env('FAWRY_URL', "https://atfawry.fawrystaging.com/"),//or https://www.atfawry.com/ for production
    'FAWRY_SECRET' => env('FAWRY_SECRET'),
    'FAWRY_MERCHANT' => env('FAWRY_MERCHANT'),

    #PayPal
    'PAYPAL_CLIENT_ID' => env('PAYPAL_CLIENT_ID'),
    'PAYPAL_SECRET' => env('PAYPAL_SECRET'),
    'PAYPAL_CURRENCY' => env('PAYPAL_CURRENCY', "USD"),

    #THAWANI
    'THAWANI_API_KEY' => env('THAWANI_API_KEY', ''),
    'THAWANI_URL' => env('THAWANI_URL', "https://uatcheckout.thawani.om/"),
    'THAWANI_PUBLISHABLE_KEY' => env('THAWANI_PUBLISHABLE_KEY', ''),

    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME')
];
```

## Web.php MUST Have Route with name “payment-verify”

```php
Route::get('/payments/verify/{payment?}',[FrontController::class,'payment_verify'])->name('payment-verify');
```

## How To Use

```jsx
use Nafezly\Payments\ThawaniPayment;

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

//pay function response 
[
		'payment_id'=>"", // refrence code that should stored in your orders table
		'redirect_url'=>"", // redirect url available for some payment gateways
		'html'=>"" // rendered html available for some payment gateways
]

//verify function
$payment->verify($request);
[
		'success'=>true,//or false
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
```

## Test Cards

- [Thawani](https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card)
- [Kashier](https://developers.kashier.io/payment/testing)
- [Paymob](https://docs.paymob.com/docs/card-payments)
- [Fawry](https://developer.fawrystaging.com/docs/testing/testing)