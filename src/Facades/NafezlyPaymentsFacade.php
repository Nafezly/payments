<?php

namespace Nafezly\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class NafezlyPaymentsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'nafezly_payments';
    }
}