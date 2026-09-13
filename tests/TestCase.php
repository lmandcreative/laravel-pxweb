<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb\Tests;

use Illuminate\Foundation\Application;
use LmSomeco\PxWeb\PxWebServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PxWebServiceProvider::class];
    }
}
