<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\ValueObjects;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Andriichuk\LaravelBillingBlueSnap\Contracts\BlueSnapPaymentSource;

final readonly class VaultedShopperReference implements BlueSnapPaymentSource
{
    private const string PREFIX = 'bluesnap:vaulted-shopper:';

    public string $id;

    public function __construct(string|int $id)
    {
        $id = trim((string) $id);

        if ($id === '') {
            throw InvalidBillingPayload::because('A BlueSnap vaulted shopper ID must not be empty.');
        }

        $this->id = $id;
    }

    public static function fromPaymentMethodReference(PaymentMethodReference $reference): self
    {
        if (! str_starts_with($reference->id, self::PREFIX)) {
            throw InvalidBillingPayload::because('The payment method is not a BlueSnap vaulted shopper reference.');
        }

        return new self(substr($reference->id, strlen(self::PREFIX)));
    }

    public function toCustomerReference(): CustomerReference
    {
        return new CustomerReference($this->id);
    }

    public function toPaymentMethodReference(): PaymentMethodReference
    {
        return new PaymentMethodReference(self::PREFIX.$this->id);
    }

    public static function supports(PaymentMethodReference $reference): bool
    {
        return str_starts_with($reference->id, self::PREFIX);
    }
}
