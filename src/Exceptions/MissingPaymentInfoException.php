<?php

namespace Nafezly\Payments\Exceptions;

class MissingPaymentInfoException extends \Exception
{
    public function __construct($missing_payment_parameter, $payment_provider)
    {
        parent::__construct($missing_payment_parameter . ' is required to use ' . $payment_provider);
    }
}