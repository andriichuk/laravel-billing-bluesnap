<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests;

use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;
use Andriichuk\LaravelBillingBlueSnap\Testing\BlueSnapDriverFactory;
use Andriichuk\LaravelBillingBlueSnap\Testing\RecordingHttpClient;
use Illuminate\Foundation\Application;
use Psr\Http\Message\ResponseInterface;

trait CreatesDriver
{
    protected function blueSnapDriver(ResponseInterface ...$responses): BlueSnapDriver
    {
        return BlueSnapDriverFactory::create($this->application(), new RecordingHttpClient(...$responses));
    }

    protected function application(): Application
    {
        $app = new Application(dirname(__DIR__));
        $app->detectEnvironment(static fn (): string => 'testing');

        return $app;
    }
}
