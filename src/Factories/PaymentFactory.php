<?php

namespace Nafezly\Payments\Factories;

use Nafezly\Payments\Interfaces\PaymentInterface;
use Nafezly\Payments\Classes;


class PaymentFactory
{


    /**
     *
     * get the payment class that the user want
     * if not exist return ex
     * @param string $name
     * @return PaymentInterface|Exception
     * @throws Exception
     */

    public function get(string $name): PaymentInterface|Exception
    {

        $className = 'Nafezly\Payments\Classes\\' . $name . 'Payment';

        if (class_exists($className))
            return new $className();

        throw new \Exception("Invalid gateway");
    }


}
