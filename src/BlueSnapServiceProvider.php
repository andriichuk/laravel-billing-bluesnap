<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBillingBlueSnap\Console\ConfigureBlueSnapWebhookCommand;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BlueSnapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/billing-bluesnap.php', 'billing-bluesnap');
        $this->app->singleton(BlueSnapManager::class, fn (Application $app): BlueSnapManager => new BlueSnapManager($app));
    }

    public function boot(): void
    {
        $this->app->make(BillingManager::class)->extend('bluesnap', function (Container $app, array $config): BlueSnapDriver {
            $defaults = $app->make('config')->get('billing-bluesnap', []);
            $defaults = is_array($defaults) ? $defaults : [];

            return $app->make(BlueSnapManager::class)->driver(array_replace_recursive($defaults, $config));
        });

        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->commands([ConfigureBlueSnapWebhookCommand::class]);
        $this->publishes([
            __DIR__.'/../config/billing-bluesnap.php' => $this->app->configPath('billing-bluesnap.php'),
        ], 'billing-bluesnap-config');
    }
}
