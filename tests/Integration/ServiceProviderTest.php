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

    #[Test]
    public function hosted_checkout_uses_merchant_and_checkout_host_from_application_config(): void
    {
        config()->set('billing.drivers.bluesnap.merchant_id', '1469228');
        config()->set('billing.drivers.bluesnap.checkout_host', 'https://payments.example.com');
        app(BillingManager::class)->forgetDrivers();

        $driver = app(BillingManager::class)->driver('bluesnap');

        self::assertInstanceOf(BlueSnapDriver::class, $driver);
        self::assertSame(
            'https://payments.example.com/buynow/checkout?plan3173219&merchantid=1469228',
            $driver->hostedCheckoutUrl(['plan_id' => 3173219]),
        );
    }

    #[Test]
    public function merchant_id_is_required_only_when_hosted_checkout_is_used(): void
    {
        config()->set('billing.drivers.bluesnap.merchant_id');
        app(BillingManager::class)->forgetDrivers();
        $driver = app(BillingManager::class)->driver('bluesnap');

        self::assertInstanceOf(BlueSnapDriver::class, $driver);
        $this->expectException(InvalidBlueSnapConfiguration::class);
        $this->expectExceptionMessage('merchant ID is required for Hosted Payment Page checkout');

        $driver->hostedCheckoutUrl(['plan_id' => 3173219]);
    }

    #[Test]
    public function an_empty_merchant_id_is_treated_as_unconfigured_for_api_only_usage(): void
    {
        config()->set('billing.drivers.bluesnap.merchant_id', '');
        app(BillingManager::class)->forgetDrivers();
        $driver = app(BillingManager::class)->driver('bluesnap');

        self::assertInstanceOf(BlueSnapDriver::class, $driver);
        $this->expectException(InvalidBlueSnapConfiguration::class);
        $this->expectExceptionMessage('merchant ID is required for Hosted Payment Page checkout');

        $driver->hostedCheckoutUrl(['plan_id' => 3173219]);
    }
}
