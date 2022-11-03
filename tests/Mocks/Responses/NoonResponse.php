<?php

namespace Tests\Mocks\Responses;

trait NoonResponse
{
  public function mockNoonPaymentPayResponse(): array
  {
    return [
      "payment_id" => "6363f830e0d85",
      "redirect_url" => "https://checkout-stg.noonpayments.com/en/default/index?info=MquLvnmH5WhRY73jeDUoCf13ARjbtqnEp2QEjFP%2BMANOpR1VTeMlV6kfSVe3UA%3D%3D"
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
}
