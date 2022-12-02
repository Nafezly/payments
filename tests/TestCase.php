<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Nafezly\Payments\NafezlyPaymentsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            NafezlyPaymentsServiceProvider::class
        ];
    }
}
