<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Unit;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\VaultedShopperReference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    #[Test]
    public function payment_sources_round_trip_through_opaque_core_references(): void
    {
        $token = HostedFieldsToken::fromString('pf-token');
        self::assertSame('pf-token', HostedFieldsToken::fromPaymentMethodReference($token->toPaymentMethodReference())->value);

        $shopper = new VaultedShopperReference(12345);
        self::assertSame('12345', VaultedShopperReference::fromPaymentMethodReference($shopper->toPaymentMethodReference())->id);
        self::assertSame('12345', $shopper->toCustomerReference()->id);
    }

    #[Test]
    public function empty_hosted_fields_tokens_are_rejected(): void
    {
        $this->expectException(InvalidBillingPayload::class);
        HostedFieldsToken::fromString('   ');
    }
}
