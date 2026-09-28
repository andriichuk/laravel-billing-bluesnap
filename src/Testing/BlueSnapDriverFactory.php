<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Testing;

use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapManager;
use Illuminate\Contracts\Foundation\Application;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class BlueSnapDriverFactory
{
    /** @param array<string, mixed> $config */
    public static function create(Application $app, RecordingHttpClient $http, array $config = []): BlueSnapDriver
    {
        $factory = new Psr17Factory;
        $app->instance(ClientInterface::class, $http);
        $app->instance(RequestFactoryInterface::class, $factory);
        $app->instance(StreamFactoryInterface::class, $factory);

        return (new BlueSnapManager($app))->driver(array_replace_recursive([
            'username' => 'test-user',
            'password' => 'test-password',
            'environment' => 'sandbox',
            'api_version' => '3.0',
            'currency' => 'USD',
            'webhook' => [
                'secret' => 'test-webhook-secret',
                'timestamp_tolerance' => 300,
                'verify_signature' => true,
                'verify_ip' => false,
            ],
        ], $config));
    }
}
