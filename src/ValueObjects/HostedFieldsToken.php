<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\ValueObjects;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Andriichuk\LaravelBillingBlueSnap\Contracts\BlueSnapPaymentSource;
use DateTimeImmutable;

final readonly class HostedFieldsToken implements BlueSnapPaymentSource
{
    private const string PREFIX = 'bluesnap:pf:';

    public string $value;

    public function __construct(string $value, public ?DateTimeImmutable $expiresAt = null)
    {
        $value = trim($value);
        if ($value === '') {
            throw InvalidBillingPayload::because('A BlueSnap Hosted Payment Fields token must not be empty.');
        }

        $this->value = $value;
    }

    public static function fromString(string $token): self
    {
        return new self($token);
    }

    public static function fromPaymentMethodReference(PaymentMethodReference $reference): self
    {
        if (! str_starts_with($reference->id, self::PREFIX)) {
            throw InvalidBillingPayload::because('The payment method is not a BlueSnap Hosted Payment Fields token.');
        }

        return new self(substr($reference->id, strlen(self::PREFIX)));
    }

    public function toPaymentMethodReference(): PaymentMethodReference
    {
        return new PaymentMethodReference(self::PREFIX.$this->value);
    }

    public static function supports(PaymentMethodReference $reference): bool
    {
        return str_starts_with($reference->id, self::PREFIX);
    }
}
