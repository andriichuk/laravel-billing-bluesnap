<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests;

use Andriichuk\LaravelBilling\BillingServiceProvider;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapServiceProvider;
use Andriichuk\LaravelBillingBlueSnap\Testing\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BillingServiceProvider::class, BlueSnapServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('billing.default', 'bluesnap');
        $app['config']->set('billing.drivers.bluesnap.username', 'test-user');
        $app['config']->set('billing.drivers.bluesnap.password', 'test-password');
        $app['config']->set('billing.drivers.bluesnap.merchant_id', '1469228');
        $app['config']->set('billing.drivers.bluesnap.webhook.secret', 'test-secret');
        $factory = new Psr17Factory;
        $app->instance(ClientInterface::class, new RecordingHttpClient);
        $app->instance(RequestFactoryInterface::class, $factory);
        $app->instance(StreamFactoryInterface::class, $factory);
    }
}
