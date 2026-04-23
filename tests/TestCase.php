<?php

declare(strict_types=1);

namespace Maaz\LaravelZatca\Tests;

use Maaz\LaravelZatca\Facades\Zatca;
use Maaz\LaravelZatca\ZatcaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }

    protected function getPackageProviders($app): array
    {
        return [
            ZatcaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Zatca' => Zatca::class,
        ];
    }
}
