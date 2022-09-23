# Nafezly Payment Gateways
Payment Helper of Payment Gateways ( PayPal - Paymob - Fawry - Thawani - WeAccept - Kashier - Hyperpay )
![payment-gateways.png](https://github.com/nafezly/payments/blob/master/payment-gateways.png?raw=true)


## Installation

```jsx
composer require nafezly/payments
```

## Publish Vendor Files

```jsx
php artisan vendor:publish --tag="nafezly-payments-config"
php artisan vendor:publish --tag="nafezly-payments-migrations"
```

## Migrate The Migration File

```jsx
php artisan migrate
//it will add status,payment_id,amount,process_data columns if not exists in orders table
```

### nafezly-payments.php file

```php
<?php
return [

	#PAYMOB
	'PAYMOB_API_KEY'=>env('PAYMOB_API_KEY'),
	'PAYMOB_INTEGRATION_ID'=>env('PAYMOB_INTEGRATION_ID'),
	'PAYMOB_IFRAME_ID'=>env('PAYMOB_IFRAME_ID'),
	'PAYMOB_HMAC'=>env('PAYMOB_HMAC'),

	#HYPERPAY
	'HYPERPAY_BASE_URL'=>env('HYPERPAY_BASE_URL',"https://eu-test.oppwa.com"),
	'HYPERPAY_URL'=>env('HYPERPAY_URL',env('HYPERPAY_BASE_URL')."/v1/checkouts"),
	'HYPERPAY_TOKEN'=>env('HYPERPAY_TOKEN'),
	'HYPERPAY_CREDIT_ID'=>env('HYPERPAY_CREDIT_ID'),
	'HYPERPAY_MADA_ID'=>env('HYPERPAY_MADA_ID'),
	'HYPERPAY_APPLE_ID'=>env('HYPERPAY_APPLE_ID'),
	'HYPERPAY_CURRENCY'=>env('HYPERPAY_CURRENCY',"SAR"),

	#KASHIER
	'KASHIER_ACCOUNT_KEY'=>env('KASHIER_ACCOUNT_KEY'),
	'KASHIER_IFRAME_KEY'=>env('KASHIER_IFRAME_KEY'),
	'KASHIER_URL'=>env('KASHIER_URL',"https://checkout.kashier.io"),
	'KASHIER_MODE'=>env('KASHIER_MODE',"test"), //live or test

	#FAWRY
	'FAWRY_URL'=>env('FAWRY_URL',"https://atfawry.fawrystaging.com/"),//or https://www.atfawry.com/ for production
	'FAWRY_SECRET'=>env('FAWRY_SECRET'),
	'FAWRY_MERCHANT'=>env('FAWRY_MERCHANT'),

	#PayPal
	'PAYPAL_CLIENT_ID'=>env('PAYPAL_CLIENT_ID'),
	'PAYPAL_SECRET'=>env('PAYPAL_SECRET'),
	'PAYPAL_CURRENCY'=>env('PAYPAL_CURRENCY',"USD"),

	#THAWANI
	'THAWANI_API_KEY'=>env('THAWANI_API_KEY','rRQ26GcsZzoEhbrP2HZvLYDbn9C9et'),
	'THAWANI_URL'=>env('THAWANI_URL',"https://uatcheckout.thawani.om/"),
	'THAWANI_PUBLISHABLE_KEY'=>env('THAWANI_PUBLISHABLE_KEY','HGvTMLDssJghr9tlN9gr4DVYt0qyBy')

];
```
## Put keys in .env File
```php

	PAYMOB_API_KEY=
	PAYMOB_INTEGRATION_ID=
	PAYMOB_IFRAME_ID=
	PAYMOB_HMAC=

	HYPERPAY_BASE_URL=
	HYPERPAY_URL=
	HYPERPAY_TOKEN=
	HYPERPAY_CREDIT_ID=
	HYPERPAY_MADA_ID=
	HYPERPAY_APPLE_ID=
	HYPERPAY_CURRENCY=

	KASHIER_ACCOUNT_KEY=
	KASHIER_IFRAME_KEY=
	KASHIER_URL=
	KASHIER_MODE=

	FAWRY_URL=
	FAWRY_SECRET=
	FAWRY_MERCHANT=

	PAYPAL_CLIENT_ID=
	PAYPAL_SECRET=
	PAYPAL_CURRENCY=

	THAWANI_API_KEY=
	THAWANI_URL=
	THAWANI_PUBLISHABLE_KEY=

```
## How To Use

```jsx
use Nafezly\Payments\ThawaniPayment;

$payment = new PaymobPayment();
//pay
$payment->pay($order);
//verify
$payment->verify($order);

```

## Available Classes

```php

$payment = new \Nafezly\Payments\FawryPayment();
$payment = new \Nafezly\Payments\HyperPayPayment();
$payment = new \Nafezly\Payments\KashierPayment();
$payment = new \Nafezly\Payments\PaymobPayment();
$payment = new \Nafezly\Payments\PayPalPayment();
$payment = new \Nafezly\Payments\ThawaniPayment();
```

## Test Cards

```php
# Thawani 
[https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card](https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card)

# Kashier 
[https://developers.kashier.io/payment/testing](https://developers.kashier.io/payment/testing)

# Paymob
[https://docs.paymob.com/docs/card-payments](https://docs.paymob.com/docs/card-payments)

# Fawry
[https://developer.fawrystaging.com/docs/testing/testing](https://developer.fawrystaging.com/docs/testing/testing)
```
