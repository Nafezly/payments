<?php

namespace Tests\Mocks\Responses;

trait NoonResponse
{
  public function mockNoonPaymentPayResponse(): array
  {
    return [
      "payment_id" => "6367df16d51f2",
      "redirect_url" => "https://checkout-stg.noonpayments.com/en/default/index?info=QNSCyKbh4v2QdakCco6odqH8UzkKzIEk9kUuKDS7JzoFNrMh4yANyaxe%2BCxPpg%3D%3D",
      "process_data" => json_decode('{
        "resultCode": 0,
        "message": "Processed successfully",
        "resultClass": 0,
        "classDescription": "",
        "actionHint": "",
        "requestReference": "5e0a39cd-3bd9-4ebe-86f7-0221973d66a6",
        "result": {
          "nextActions": "ADD_PAYMENT_INFO",
          "order": {
            "status": "INITIATED",
            "creationTime": "2022-11-06T16:21:43.5588066Z",
            "errorCode": 0,
            "id": 352034050564,
            "amount": 200,
            "currency": "SAR",
            "name": "Sample order name",
            "reference": "6367df16d51f2",
            "category": "pay",
            "channel": "Web"
          },
          "configuration": {
            "tokenizeCc": true,
            "returnUrl": "http://127.0.0.1:8000/noon_payment_response",
            "locale": "en",
            "paymentAction": "Authorize, Sale"
          },
          "business": {
            "id": "borooa",
            "name": "Borooa"
          },
          "checkoutData": {
            "postUrl": "https://checkout-stg.noonpayments.com/en/default/index?info=QNSCyKbh4v2QdakCco6odqH8UzkKzIEk9kUuKDS7JzoFNrMh4yANyaxe%2BCxPpg%3D%3D",
            "jsUrl": "https://checkout-stg.noonpayments.com/en/scripts/checkout?url=https%3A%2F%2Fcheckout-stg.noonpayments.com%2Fen%2Fdefault%2Findex%3Finfo%3DQNSCyKbh4v2QdakCco6odqH8UzkKzIEk9kUuKDS7JzoFNrMh4yANyaxe%252BCxPpg%253D%253D"
          },
          "deviceFingerPrint": {
            "sessionId": "352034050564"
          },
          "paymentOptions": [
            {
              "method": "CARD_SANDBOX",
              "type": "Card",
              "action": "Card",
              "data": {
                "supportedCardBrands": [
                  "VISA",
                  "MASTERCARD",
                  "MADA"
                ],
                "cvvRequired": "True"
              }
            },
            {
              "method": "Applepay_CheckoutPro",
              "type": "ApplePay",
              "action": "ApplePay",
              "data": {
                "merchantIdentifier": "merchant.com.noonpayments.stg-checkout",
                "paymentRequest": {
                  "countryCode": "AE",
                  "currencyCode": "SAR",
                  "total": {
                    "label": "Borooa",
                    "amount": 200
                  },
                  "supportedNetworks": [
                    "visa",
                    "masterCard",
                    "maestro",
                    "mada"
                  ],
                  "merchantCapabilities": [
                    "supports3DS"
                  ]
                }
              }
            }
          ]
        }
      }')
    ];
  }

  public function mockNoonPaymentVerifyResponse(): array
  {
    return [
      "success" => true,
      "payment_id" => "6363f830e0d85",
      "message" => "operation completed successfully",
      "process_data" => json_decode('{"resultCode":0,
        "message":"Processed successfully",
        "resultClass":0,
        "classDescription":"",
        "actionHint":"",
        "requestReference":"1c29f044-7656-4da6-81c5-2e8adb95ff6c",
        "result":{
          "nextActions":"REFUND",
          "transactions":[
            {
              "type":"SALE",
              "authorizationCode":"831000",
              "creationTime":"2022-11-03T17:20:01.02Z",
              "status":"SUCCESS",
              "amountRefunded":0.00,
              "stan":"131120",
              "id":"6674960013376947804953",
              "amount":200.00,"currency":"SAR"
            }
          ],
          "order":{
            "status":"CAPTURED",
            "creationTime":"2022-11-03T17:19:46.993Z",
            "totalAuthorizedAmount":200.00,
            "totalCapturedAmount":200.00,
            "totalRefundedAmount":0.00,
            "totalRemainingAmount":0.00,
            "totalReversedAmount":0.00,
            "totalSalesAmount":200.00,
            "errorCode":0,
            "id":609456999323,
            "amount":200.00,
            "currency":"SAR",
            "name":"Sample order name",
            "reference":"6363f830e0d85",
            "category":"pay",
            "channel":"Web"
          },
          "paymentDetails":{
            "instrument":"CARD",
            "tokenIdentifier":"d21d998c-1b16-44c3-b011-ed7c5e9f4790",
            "cardAlias":"f217d7c3-4b20-4623-a5ad-fb57900b5f83",
            "mode":"Card",
            "integratorAccount":"CARD_SANDBOX",
            "paymentInfo":"445653xxxxxxxxx1007",
            "brand":"VISA",
            "scheme":"VISA",
            "expiryMonth":"11",
            "expiryYear":"2023",
            "isNetworkToken":"FALSE",
            "cardType":"CREDIT",
            "cardCountry":"US",
            "cardCountryName":"United States of America"}
          }
        }
      }')
    ];
  }
  public function mockNoonPaymentSubscriptionPayResponse(): array
  {
    return [
      "payment_id" => "6367e7f8a2b31",
      "subscription_identifier" => "0f453f66-4669-462e-ab56-6fe349cb7a23",
      "redirect_url" => "https://checkout-stg.noonpayments.com/en/default/index?info=cz6zIDl2QrhRIAWeG9pWW8oQrUJnBWUqkxMvMREo7e6U7SyCmtX5XdQ3t7N%2Ffw%3D%3D",
      "proccess_data" => json_decode('{
        "resultCode": 0,
        "message": "Processed successfully",
        "resultClass": 0,
        "classDescription": "",
        "actionHint": "",
        "requestReference": "ae16c291-db1a-43ec-ba50-5f1e9113c3ac",
        "result": {
          "nextActions": "ADD_PAYMENT_INFO",
          "order": {
            "status": "INITIATED",
            "creationTime": "2022-11-06T16:59:37.3005809Z",
            "errorCode": 0,
            "id": 109130394511,
            "amount": 200,
            "currency": "SAR",
            "name": "Sample order name",
            "reference": "6367e7f8a2b31",
            "category": "pay",
            "channel": "Web"
          },
          "configuration": {
            "tokenizeCc": true,
            "returnUrl": "http://127.0.0.1:8000/noon_payment_response",
            "locale": "en",
            "paymentAction": "Authorize, Sale"
          },
          "business": {
            "id": "borooa",
            "name": "Borooa"
          },
          "checkoutData": {
            "postUrl": "https://checkout-stg.noonpayments.com/en/default/index?info=cz6zIDl2QrhRIAWeG9pWW8oQrUJnBWUqkxMvMREo7e6U7SyCmtX5XdQ3t7N%2Ffw%3D%3D",
            "jsUrl": "https://checkout-stg.noonpayments.com/en/scripts/checkout?url=https%3A%2F%2Fcheckout-stg.noonpayments.com%2Fen%2Fdefault%2Findex%3Finfo%3Dcz6zIDl2QrhRIAWeG9pWW8oQrUJnBWUqkxMvMREo7e6U7SyCmtX5XdQ3t7N%252Ffw%253D%253D"
          },
          "deviceFingerPrint": {
            "sessionId": "109130394511"
          },
          "paymentOptions": [
            {
              "method": "CARD_SANDBOX",
              "type": "Card",
              "action": "Card",
              "data": {
                "supportedCardBrands": [
                  "VISA",
                  "MASTERCARD",
                  "MADA"
                ],
                "cvvRequired": "True"
              }
            },
            {
              "method": "Applepay_CheckoutPro",
              "type": "ApplePay",
              "action": "ApplePay",
              "data": {
                "merchantIdentifier": "merchant.com.noonpayments.stg-checkout",
                "paymentRequest": {
                  "countryCode": "AE",
                  "currencyCode": "SAR",
                  "total": {
                    "label": "Borooa",
                    "amount": 200
                  },
                  "supportedNetworks": [
                    "visa",
                    "masterCard",
                    "maestro",
                    "mada"
                  ],
                  "merchantCapabilities": [
                    "supports3DS"
                  ]
                }
              }
            }
          ],
          "subscription": {
            "status": "Initiated",
            "createdOn": "2022-11-06T16:59:37.360135Z",
            "amount": 200,
            "name": "Sample order name",
            "validTill": "2025-09-25T00:00:00Z",
            "type": "Recurring",
            "identifier": "1be13dcc-c60c-43f6-b3d3-26db5b942b56"
          }
        }
      }')
    ];
  }
  public function mockNoonPaymentVerifyResponseForSubscription(): array
  {
    return [
      "success" => true,
      "payment_id" => "6367e9e4b858b",
      "message" => "operation completed successfully",
      "process_data" => json_decode('{
        "resultCode": 0,
        "message": "Processed successfully",
        "resultClass": 0,
        "classDescription": "",
        "actionHint": "",
        "requestReference": "7a60d8e1-91e1-438f-83e1-da245a43ad7a",
        "result": {
          "nextActions": "REFUND",
          "transactions": [
            {
              "type": "SALE",
              "authorizationCode": "831000",
              "creationTime": "2022-11-06T17:09:05.06Z",
              "status": "SUCCESS",
              "amountRefunded": 0,
              "stan": "187683",
              "id": "6677545453746383504953",
              "amount": 200,
              "currency": "SAR"
            }
          ],
          "order": {
            "status": "CAPTURED",
            "creationTime": "2022-11-06T17:07:49.383Z",
            "totalAuthorizedAmount": 200,
            "totalCapturedAmount": 200,
            "totalRefundedAmount": 0,
            "totalRemainingAmount": 0,
            "totalReversedAmount": 0,
            "totalSalesAmount": 200,
            "errorCode": 0,
            "id": 648536329895,
            "amount": 200,
            "currency": "SAR",
            "name": "Sample order name",
            "reference": "6367e9e4b858b",
            "category": "pay",
            "channel": "Web"
          },
          "paymentDetails": {
            "instrument": "CARD",
            "tokenIdentifier": "03ccf4b5-2e65-4639-b575-0efd33811909",
            "cardAlias": "7f4448c9-bc38-4c4f-b6f3-a96475f0f0c6",
            "mode": "Card",
            "integratorAccount": "CARD_SANDBOX",
            "paymentInfo": "445653xxxxxx1096",
            "brand": "VISA",
            "scheme": "VISA",
            "expiryMonth": "11",
            "expiryYear": "2033",
            "isNetworkToken": "FALSE",
            "cardType": "CREDIT",
            "cardCountry": "US",
            "cardCountryName": "United States of America"
          },
          "subscription": {
            "status": "Active",
            "createdOn": "2022-11-06T17:07:49.423Z",
            "amount": 200,
            "name": "Sample order name",
            "validTill": "2025-09-25T00:00:00Z",
            "type": "Recurring",
            "identifier": "2f7348e3-5250-4d5b-aad0-a005ef5964af"
          }
        }
      }')
    ];
  }

  public function mockNoonPaymentSubsequentTransactionPayResponse(): array
  {
    return [
      "success" => true,
      "payment_id" => "6367ebfb694aa",
      "message" => "operation completed successfully",
      'process_data' => json_decode('{
        "resultCode": 0,
        "message": "Processed successfully",
        "resultClass": 0,
        "classDescription": "",
        "actionHint": "",
        "requestReference": "dba157e3-90d8-47cf-ab15-6e21b6fe1b58",
        "result": {
          "nextActions": "REFUND",
          "transaction": {
            "type": "SALE",
            "authorizationCode": "831000",
            "creationTime": "2022-11-06T17:16:44.2202023Z",
            "status": "SUCCESS",
            "amountRefunded": 0,
            "stan": "187706",
            "id": "6677550052926439904953",
            "amount": 200,
            "currency": "SAR"
          },
          "order": {
            "status": "CAPTURED",
            "creationTime": "2022-11-06T17:16:44.1261936Z",
            "totalAuthorizedAmount": 200,
            "totalCapturedAmount": 200,
            "totalRefundedAmount": 0,
            "totalRemainingAmount": 0,
            "totalReversedAmount": 0,
            "totalSalesAmount": 200,
            "errorCode": 0,
            "id": 143182758954,
            "amount": 200,
            "currency": "SAR",
            "name": "Sample order name",
            "reference": "6367ebfb694aa",
            "category": "pay",
            "channel": "Web"
          },
          "configuration": {
            "tokenizeCc": false,
            "locale": "en",
            "paymentAction": "Authorize, Sale"
          },
          "avs": {
            "status": "MATCH"
          },
          "merchant": {
            "id": "noonpayments",
            "name": "Borooa (Sandbox)"
          },
          "business": {
            "id": "borooa",
            "name": "Borooa"
          },
          "deviceFingerPrint": {
            "sessionId": "143182758954"
          },
          "paymentDetails": {
            "instrument": "CARD",
            "tokenIdentifier": "7a4ccf85-d8c2-4110-8724-15f3e35572b9",
            "cardAlias": "7f4448c9-bc38-4c4f-b6f3-a96475f0f0c6",
            "mode": "Card",
            "integratorAccount": "CARD_SANDBOX",
            "paymentInfo": "445653xxxxxx1096",
            "brand": "VISA",
            "scheme": "VISA",
            "expiryMonth": "11",
            "expiryYear": "2033",
            "isNetworkToken": "FALSE",
            "cardType": "CREDIT",
            "cardCountry": "US",
            "cardCountryName": "United States of America"
          },
          "subscription": {
            "status": "Active",
            "createdOn": "2022-11-06T09:48:51.83Z",
            "amount": 200,
            "name": "Sample order name",
            "validTill": "2025-09-25T00:00:00Z",
            "type": "Recurring",
            "identifier": "055dbdd0-6391-43f5-a6b6-4f10babb0f18"
          }
        }
      }')
    ];
  }
}
