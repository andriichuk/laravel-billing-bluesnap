<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Compliance;

use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Testing\DriverComplianceTestCase;
use Andriichuk\LaravelBillingBlueSnap\Tests\CreatesDriver;

final class BlueSnapDriverComplianceTest extends DriverComplianceTestCase
{
    use CreatesDriver;

    protected function driver(): BillingDriver
    {
        return $this->blueSnapDriver();
    }
}
