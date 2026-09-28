<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Contracts;

use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;

interface BlueSnapPaymentSource
{
    public function toPaymentMethodReference(): PaymentMethodReference;
}
