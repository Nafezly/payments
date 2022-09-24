<?php

namespace Nafezly\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class NafezlyPaymentsPayments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'nafezly';
    }
}