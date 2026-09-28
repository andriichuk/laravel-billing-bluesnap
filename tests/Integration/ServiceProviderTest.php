<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Integration;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;
use Andriichuk\LaravelBillingBlueSnap\Exceptions\InvalidBlueSnapConfiguration;
use Andriichuk\LaravelBillingBlueSnap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function provider_registers_the_driver_and_the_core_route_handles_the_dashboard_probe(): void
    {
        self::assertContains('bluesnap', app(BillingManager::class)->registeredDrivers());
        self::assertInstanceOf(BlueSnapDriver::class, app(BillingManager::class)->driver('bluesnap'));
        $this->get('/billing/webhooks/bluesnap')->assertOk()->assertContent('');
    }

    #[Test]
    public function credentials_are_validated_only_when_the_driver_is_resolved(): void
    {
        config()->set('billing.drivers.bluesnap.username');
        config()->set('billing.drivers.bluesnap.password');
        app(BillingManager::class)->forgetDrivers();

        self::assertContains('bluesnap', app(BillingManager::class)->registeredDrivers());
        $command = $this->artisan('route:list');

        if (is_int($command)) {
            self::fail('The route:list command did not return a pending command.');
        }
        $command->assertSuccessful();

        $this->expectException(InvalidBlueSnapConfiguration::class);
        app(BillingManager::class)->driver('bluesnap');
    }
}
