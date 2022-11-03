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

        $response = $payment->setUserId(1)
            ->setCurrency("SAR")
            ->setOrderName("Sample order name")
            ->setConfigurationLocal("en")
            ->setAmount(200)
            ->pay();

        $this->assertArrayHasKey("payment_id", $response);
        $this->assertArrayHasKey("redirect_url", $response);
    }

    public function test_mocked_noon_payment_pay_method_returns_right_response()
    {
        $payment = $this->createMock(NoonPayment::class);

        $payment->method("setUserId")->with($this->isType('integer'))->willReturn($payment);
        $payment->method("setCurrency")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setOrderName")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setConfigurationLocal")->with($this->isType('string'))->willReturn($payment);
        $payment->method("setAmount")->with($this->isType('integer'))->willReturn($payment);
        $payment->method("pay")->willReturn($this->mockNoonPaymentPayResponse());

        $response = $payment->setUserId(1)
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
}
