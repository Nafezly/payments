<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Nafezly\Payments\Classes\NoonPayment;
use Tests\Mocks\Responses\NoonResponse;
use Tests\TestCase;

class NoonPaymentTest extends TestCase
{
    use NoonResponse;

    public function test_noon_payment_pay_method_returns_expected_keys()
    {
        $payment = new NoonPayment();

        $response = $payment
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount(200)
            ->pay();

        $this->assertArrayHasKey("payment_id", $response);
        $this->assertArrayHasKey("redirect_url", $response);
        $this->assertArrayHasKey("process_data", $response);
    }

    public function test_mocked_noon_payment_pay_method_returns_right_response()
    {
        $payment = $this->createMock(NoonPayment::class);

        $payment->method("setCurrency")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setOrderName")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setConfigurationLocal")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setAmount")->with($this->isType('integer'))->willReturn($payment);
        $payment->method("pay")->willReturn($this->mockNoonPaymentPayResponse());

        $response = $payment
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount(200)
            ->pay();

        $this->assertEquals($this->mockNoonPaymentPayResponse(), $response);
    }

    public function test_mocked_noon_payment_verify_method_returns_right_response()
    {
        $request = new Request();

        $payment = $this->createMock(NoonPayment::class);

        $payment->method("verify")->with($request)->willReturn($this->mockNoonPaymentVerifyResponse());

        $response = $payment->verify($request);

        $this->assertEquals($this->mockNoonPaymentVerifyResponse(), $response);
    }

    public function test_noon_payment_subscription_pay_method_returns_expected_keys()
    {
        $payment = new NoonPayment();

        $response = $payment
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount(200)
            ->setSubscriptionAmount(200)
            ->setSubscriptionName('Sample order name')
            ->setSubscriptionValidTill('2025-09-25')
            ->subscriptionPay();

        $this->assertArrayHasKey("payment_id", $response);
        $this->assertArrayHasKey("redirect_url", $response);
        $this->assertArrayHasKey("subscription_identifier", $response);
        $this->assertArrayHasKey("process_data", $response);
    }

    public function test_mocked_noon_payment_subscription_pay_method_returns_right_response()
    {
        $payment = $this->createMock(NoonPayment::class);

        $payment->method("setCurrency")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setOrderName")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setConfigurationLocal")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setAmount")->with($this->isType('integer'))->willReturn($payment);
        $payment->method("setSubscriptionAmount")->with($this->isType('integer'))->willReturn($payment);
        $payment->method("setSubscriptionName")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setSubscriptionValidTill")->with($this->isType('string'))->willReturn($payment);
        $payment->method("subscriptionPay")->willReturn($this->mockNoonPaymentSubscriptionPayResponse());

        $response = $payment
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount(200)
            ->setSubscriptionAmount(200)
            ->setSubscriptionName('Sample order name')
            ->setSubscriptionValidTill('2025-09-25')
            ->subscriptionPay();

        $this->assertEquals($this->mockNoonPaymentSubscriptionPayResponse(), $response);
    }

    public function test_mocked_noon_payment_verify_method_returns_right_response_for_subscription()
    {
        $request = new Request();

        $payment = $this->createMock(NoonPayment::class);

        $payment->method("verify")->with($request)->willReturn($this->mockNoonPaymentVerifyResponseForSubscription());

        $response = $payment->verify($request);

        $this->assertEquals($this->mockNoonPaymentVerifyResponseForSubscription(), $response);
    }


    public function test_mocked_noon_payment_subsequent_transaction_pay_method_returns_right_response()
    {
        $payment = $this->createMock(NoonPayment::class);

        $payment->method("setOrderName")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setSubscriptionIdentifier")->with($this->isType('string'))->willReturn($payment);
        $payment->method("subsequentTransactionPay")->willReturn($this->mockNoonPaymentSubsequentTransactionPayResponse());

        $response = $payment
            ->setOrderName("Sample order name")
            ->setSubscriptionIdentifier("055dbdd0-6391-43f5-a6b6-4f10babb0f18")
            ->subsequentTransactionPay();

        $this->assertEquals($this->mockNoonPaymentSubsequentTransactionPayResponse(), $response);
    }
}
