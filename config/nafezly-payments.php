<?php
return [
    #PAYMOB
    'PAYMOB_PUBLIC_KEY'=>env('PAYMOB_PUBLIC_KEY'),
    'PAYMOB_SECRET_KEY' => env('PAYMOB_SECRET_KEY'),
    'PAYMOB_INTEGRATION_ID' => env('PAYMOB_INTEGRATION_ID',""), //array of integration ids
    'PAYMOB_CURRENCY'=> env('PAYMOB_CURRENCY',"EGP"),
    'PAYMOB_HMAC' => env('PAYMOB_HMAC'),
    #PAYMOB_WALLET (vodaphone-cash,orange-money,etisalat-cash,we-cash,meza-wallet) - test phone 01010101010 ,PIN & OTP IS 123456
    'PAYMOB_WALLET_INTEGRATION_ID'=>env('PAYMOB_WALLET_INTEGRATION_ID'),

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
    'KASHIER_URL' => env('KASHIER_URL', "https://payments.kashier.io"),
    'KASHIER_MODE' => env('KASHIER_MODE', "test"), //live or test
    'KASHIER_CURRENCY'=>env('KASHIER_CURRENCY',"EGP"),
    'KASHIER_WEBHOOK_URL'=>env('KASHIER_WEBHOOK_URL'),


    #FAWRY
    'FAWRY_URL' => env('FAWRY_URL', "https://atfawry.fawrystaging.com/"),//https://www.atfawry.com/ for production
    'FAWRY_SECRET' => env('FAWRY_SECRET'),
    'FAWRY_MERCHANT' => env('FAWRY_MERCHANT'),
    'FAWRY_DISPLAY_MODE' => env('FAWRY_DISPLAY_MODE',"POPUP"),//required allowed values [POPUP, INSIDE_PAGE, SIDE_PAGE , SEPARATED]
    'FAWRY_PAY_MODE'=>env('FAWRY_PAY_MODE',"CARD"),//allowed values ['CashOnDelivery', 'PayAtFawry', 'MWALLET', 'CARD' , 'VALU']

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

    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME'),



    #TELR
    'TELR_MERCHANT_ID'=>env('TELR_MERCHANT_ID'),
    'TELR_API_KEY'=>env('TELR_API_KEY'),
    'TELR_MODE'=>env('TELR_MODE','test'),//test,live


    #CLICKPAY
    'CLICKPAY_SERVER_KEY'=>env('CLICKPAY_SERVER_KEY'),
    'CLICKPAY_PROFILE_ID'=>env('CLICKPAY_PROFILE_ID'),


    #COINPAYMENTS
    'COINPAYMENTS_PUBLIC_KEY'=>env('COINPAYMENTS_PUBLIC_KEY'),
    'COINPAYMENTS_PRIVATE_KEY'=>env('COINPAYMENTS_PRIVATE_KEY'),




    #BigPay
    'BIGPAY_KEY'=>env('BIGPAY_KEY',"02b0203c-558c-45d4-ba90-954017d40eb6"),
    'BIGPAY_SECRET'=>env('BIGPAY_SECRET',"eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJmb3VhZC5mYXJpc0BhcmVxYWQuY29tIiwiaWF0IjoxNjky MTEwMzQ3LCJleHAiOjIwMDc3Mjk1NDd9.re3qVEjbJ19KWedzWySGsUjChN0DuF2p-SgfBi7mlzM"),
    'BIGPAY_MODE'=>env('BIGPAY_MODE','test'),/*live,test*/


    'ENOT_KEY'=>env('ENOT_KEY'),
    'ENOT_SECRET'=>env('ENOT_SECRET'),
    'ENOT_SHOP_ID'=>env('ENOT_SHOP_ID'),


    'PAYCEC_MERCHANT_USERNAME'=>env('PAYCEC_MERCHANT_USERNAME'),
    'PAYCEC_MERCHANT_SECRET'=>env('PAYCEC_MERCHANT_SECRET'),
    'PAYCEC_MODE'=>env('PAYCEC_MODE','test'),



    'PAYPAL_CREDIT_CLIENT_ID'=>env('PAYPAL_CREDIT_CLIENT_ID'),
    'PAYPAL_CREDIT_SECRET'=>env('PAYPAL_CREDIT_SECRET'),
    'PAYPAL_CREDIT_MODE'=>env('PAYPAL_CREDIT_MODE'),
    'PAYPAL_CREDIT_CURRENCY'=>env('PAYPAL_CREDIT_CURRENCY'),

    'PAYREXX_INSTANCE_NAME'=>env('PAYREXX_INSTANCE_NAME'),
    'PAYREXX_API_KEY'=>env('PAYREXX_API_KEY'),

    
    'CRYPTOMUS_MERCHANT_ID'=>env('CRYPTOMUS_MERCHANT_ID'),
    'CRYPTOMUS_API_KEY'=>env('CRYPTOMUS_API_KEY'),

    #HELEKET — https://doc.heleket.com/ (same API shape as Cryptomus: merchant + sign headers)
    'HELEKET_MERCHANT_ID'=>env('HELEKET_MERCHANT_ID'),
    'HELEKET_API_KEY'=>env('HELEKET_API_KEY'),


    'PRIME_PROJECT_ID'=>env('PRIME_PROJECT_ID'),
    'PRIME_SECRET_WORD_1'=>env('PRIME_SECRET_WORD_1'),
    'PRIME_SECRET_WORD_2'=>env('PRIME_SECRET_WORD_2'),


    'PAYLINK_API_KEY'=>env('PAYLINK_API_KEY','0662abb5-13c7-38ab-cd12-236e58f43766'),
    'PAYLINK_APP_ID'=>env('PAYLINK_APP_ID','APP_ID_1123453311'),
    'PAYLINK_MODE'=>env('PAYLINK_MODE','test'),

    'PAYSKY_MID'=>env('PAYSKY_MID',''),
    'PAYSKY_TID'=>env('PAYSKY_TID',''),
    'PAYSKY_SECRET'=>env('PAYSKY_SECRET',''),
    'PAYSKY_MODE'=>env('PAYSKY_MODE','live'), //test


    'YALLAPAY_PUBLIC_KEY'=>env('YALLAPAY_PUBLIC_KEY',''),
    'YALLAPAY_SECRET_KEY'=>env('YALLAPAY_SECRET_KEY',''),
    

    'ONELAT_KEY'=>env('ONELAT_KEY',''),
    'ONELAT_SECRET'=>env('ONELAT_SECRET',''),
    'ONELAT_API_BASE_URL'=>env('ONELAT_API_BASE_URL','https://api.one.lat'),
    'ONELAT_CHECKOUT_BASE_URL'=>env('ONELAT_CHECKOUT_BASE_URL','https://one.lat/checkout'),


    'PAYOP_PUBLIC_KEY'=>env('PAYOP_PUBLIC_KEY'),
    'PAYOP_SECRET_KEY'=>env('PAYOP_SECRET_KEY'),
    'PAYOP_JWT'=>env('PAYOP_JWT'),//null


    'MAMOPAYMENT_BASE_URL'=>env('MAMOPAYMENT_BASE_URL','https://business.mamopay.com'),
    'MAMOPAYMENT_API_KEY'=>env('MAMOPAYMENT_API_KEY'),


    #ZIINA
    /*
    * Documentation: https://docs.ziina.com/api-reference/payment-intent/create
    * Base URL: https://api-v2.ziina.com/api
    * Authentication: Bearer token (JWT)
    * Supported currencies: AED, USD, EUR, GBP, SAR, QAR, BHD, KWD, OMR, INR
    */
    'ZIINA_API_KEY' => env('ZIINA_API_KEY'),
    'ZIINA_BASE_URL' => env('ZIINA_BASE_URL', 'https://api-v2.ziina.com/api'),
    'ZIINA_CURRENCY' => env('ZIINA_CURRENCY', 'AED'),
    'ZIINA_TEST' => env('ZIINA_TEST', false),

    #MYFATOORAH
    /*
    * API URLs by country:
    * Kuwait, UAE, Bahrain, Jordan, Oman: https://api.myfatoorah.com/
    * Saudi Arabia: https://api-sa.myfatoorah.com/
    * Qatar: https://api-qa.myfatoorah.com/
    * Egypt: https://api-eg.myfatoorah.com/
    * Demo/Test: https://apitest.myfatoorah.com/
    */
    'MYFATOORAH_API_KEY'=>env('MYFATOORAH_API_KEY'),
    'MYFATOORAH_BASE_URL'=>env('MYFATOORAH_BASE_URL','https://apitest.myfatoorah.com'),
    'MYFATOORAH_CURRENCY'=>env('MYFATOORAH_CURRENCY','USD'),


    #XPAY
    /*
    * Documentation: https://xpayeg.github.io/docs/
    * Base URLs:
    * Test: https://staging.xpay.app/api/v1
    * Live: https://community.xpay.app/api/v1
    * Authentication: Uses x-api-key header
    * Endpoint: /payments/pay/variable-amount
    * Required: API Key, Community ID (Merchant ID), Variable Amount ID (API Payment ID)
    */
    'XPAY_API_KEY'=>env('XPAY_API_KEY'),
    'XPAY_COMMUNITY_ID'=>env('XPAY_COMMUNITY_ID'),
    'XPAY_VARIABLE_AMOUNT_ID'=>env('XPAY_VARIABLE_AMOUNT_ID'),
    'XPAY_BASE_URL'=>env('XPAY_BASE_URL','https://staging.xpay.app/api/v1'),
    'XPAY_CURRENCY'=>env('XPAY_CURRENCY','EGP'),


    #PAYERMAX
    /*
    * Base URLs:
    * Test: https://pay-gate-uat.payermax.com/aggregate-pay/api/gateway
    * Live: https://pay-gate.payermax.com/aggregate-pay/api/gateway
    * Authentication: Uses RSA signature (SHA256WithRSA)
    * Private Key: PKCS8 format (without headers/footers, or with -----BEGIN/END PRIVATE KEY-----)
    * Public Key: PayerMax public key for signature verification (without headers/footers, or with -----BEGIN/END PUBLIC KEY-----)
    */
    'PAYERMAX_APP_ID'=>env('PAYERMAX_APP_ID'),
    'PAYERMAX_MERCHANT_NO'=>env('PAYERMAX_MERCHANT_NO'),
    'PAYERMAX_PRIVATE_KEY'=>env('PAYERMAX_PRIVATE_KEY'),
    'PAYERMAX_PUBLIC_KEY'=>env('PAYERMAX_PUBLIC_KEY'),
    'PAYERMAX_BASE_URL'=>env('PAYERMAX_BASE_URL','https://pay-gate-uat.payermax.com/aggregate-pay/api/gateway'),
    'PAYERMAX_VERSION'=>env('PAYERMAX_VERSION','1.4'),
    'PAYERMAX_KEY_VERSION'=>env('PAYERMAX_KEY_VERSION','1'),
    'PAYERMAX_CURRENCY'=>env('PAYERMAX_CURRENCY','USD'),
    'PAYERMAX_COUNTRY'=>env('PAYERMAX_COUNTRY','US'),


    #VOLET
    /*
    * Shopping Cart Interface (SCI) Documentation: https://volet.com/files/documents/volet-sci-v1.0-en.pdf
    * SCI URL: https://account.volet.com/sci/
    * Authentication: Uses SCI password for hash verification (ac_hash)
    * Hash format: SHA256(ac_transfer:ac_start_date:ac_sci_name:ac_src_wallet:ac_dest_wallet:ac_order_id:ac_amount:ac_merchant_currency:SCI's password)
    * Status URL IPs: 50.7.115.5, 51.255.40.139, 13.53.55.89
    */
    'VOLET_ACCOUNT_EMAIL'=>env('VOLET_ACCOUNT_EMAIL'),
    'VOLET_SCI_NAME'=>env('VOLET_SCI_NAME'),
    'VOLET_SCI_PASSWORD'=>env('VOLET_SCI_PASSWORD'),
    'VOLET_SCI_URL'=>env('VOLET_SCI_URL','https://account.volet.com/sci/'),
    'VOLET_CURRENCY'=>env('VOLET_CURRENCY','USD'),

    #PAYZINK
    /*
    * Documentation: https://docs.payzink.com/
    * Sandbox Merchant API: https://merchant-dev.payzink.com
    * Production Merchant API: https://merchant.payzink.com
    * PayzinkPayment: Hosted Payment Page (redirect checkout)
    * PayzinkDirectPayment: Direct API (server-side card, POST /api/v1/payment/card)
    */
    'PAYZINK_PUBLISHABLE_KEY'=>env('PAYZINK_PUBLISHABLE_KEY'),
    'PAYZINK_SECRET_KEY'=>env('PAYZINK_SECRET_KEY'),
    'PAYZINK_BASE_URL'=>env('PAYZINK_BASE_URL','https://merchant-dev.payzink.com'),
    'PAYZINK_CURRENCY'=>env('PAYZINK_CURRENCY','USD'),
    'PAYZINK_ACTION'=>env('PAYZINK_ACTION','PURCHASE'), // PURCHASE or AUTH
    'PAYZINK_WEBHOOK_URL'=>env('PAYZINK_WEBHOOK_URL'),
    'PAYZINK_MUST_3D_SECURE'=>env('PAYZINK_MUST_3D_SECURE', false), // Direct API only

    #KORAPAY
    /*
    * Documentation: https://developers.korapay.com/docs/checkout-redirect
    * Production API: https://api.korapay.com
    * KoraPayPayment: Hosted Checkout Redirect (Visa/Mastercard)
    */
    'KORAPAY_PUBLIC_KEY'=>env('KORAPAY_PUBLIC_KEY'),
    'KORAPAY_SECRET_KEY'=>env('KORAPAY_SECRET_KEY'),
    'KORAPAY_ENCRYPTION_KEY'=>env('KORAPAY_ENCRYPTION_KEY'),
    'KORAPAY_BASE_URL'=>env('KORAPAY_BASE_URL','https://api.korapay.com'),
    'KORAPAY_CURRENCY'=>env('KORAPAY_CURRENCY','USD'),
    'KORAPAY_WEBHOOK_URL'=>env('KORAPAY_WEBHOOK_URL'),

    #FAWATERAK
    /*
    * Documentation: https://fawaterak-api.readme.io/
    * Portal docs: https://app.fawaterk.com/documentation
    * Staging API: https://staging.fawaterk.com
    * Production API: https://app.fawaterk.com
    * FawaterakPayment: Hosted invoice link (createInvoiceLink) or direct gateway (invoiceInitPay)
    * Webhook hash uses FAWATERAK_VENDOR_KEY (HMAC SHA256)
    * For JSON webhooks, use a URL containing "_json" (ex: /payments/verify/fawaterak_json)
    */
    'FAWATERAK_API_KEY'=>env('FAWATERAK_API_KEY'),
    'FAWATERAK_VENDOR_KEY'=>env('FAWATERAK_VENDOR_KEY'),
    'FAWATERAK_BASE_URL'=>env('FAWATERAK_BASE_URL','https://staging.fawaterk.com'),
    'FAWATERAK_CURRENCY'=>env('FAWATERAK_CURRENCY','EGP'),
    'FAWATERAK_WEBHOOK_URL'=>env('FAWATERAK_WEBHOOK_URL'),
    'FAWATERAK_PAYMENT_METHOD_ID'=>env('FAWATERAK_PAYMENT_METHOD_ID'), // optional: 2=Visa/MC, 3=Fawry, 4=Meeza

    #TOTALPAY
    /*
    * Documentation: https://docs.totalpay.global/docs/guides/checkout_integration
    * Checkout session: POST {CHECKOUT_URL}/api/v1/session
    * Status check: POST {CHECKOUT_URL}/api/v1/payment/status
    * Hash default: SHA1(MD5(UPPERCASE(payload + password)))
    * Set TOTALPAY_USE_SHA256_HASH=true if enabled in Protocol Mapping
    * notification_URL is configured in TotalPay admin panel
    */
    'TOTALPAY_CHECKOUT_URL'=>env('TOTALPAY_CHECKOUT_URL'),
    'TOTALPAY_MERCHANT_KEY'=>env('TOTALPAY_MERCHANT_KEY'),
    'TOTALPAY_PASSWORD'=>env('TOTALPAY_PASSWORD'),
    'TOTALPAY_CURRENCY'=>env('TOTALPAY_CURRENCY','USD'),
    'TOTALPAY_OPERATION'=>env('TOTALPAY_OPERATION','purchase'),
    'TOTALPAY_METHODS'=>env('TOTALPAY_METHODS'), // optional JSON/array, e.g. ["card"]
    'TOTALPAY_USE_SHA256_HASH'=>env('TOTALPAY_USE_SHA256_HASH', false),

];