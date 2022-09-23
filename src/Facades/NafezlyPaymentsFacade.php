<?php

namespace Nafezly\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class NafezlyPayments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'nafezly';
    }
}