<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Unit;

use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBillingBlueSnap\Mappers\PaymentSourceMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\SubscriptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\TransactionMapper;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MapperTest extends TestCase
{
    #[Test]
    #[DataProvider('subscriptionStatuses')]
    public function subscription_statuses_are_centralized(string $provider, SubscriptionStatus $expected): void
    {
        $mapper = new SubscriptionMapper(new BlueSnapPayloadSanitizer, new PaymentSourceMapper);
        self::assertSame($expected, $mapper->status($provider));
    }

    /**
     * @return iterable<string, array{string, SubscriptionStatus}>
     */
    public static function subscriptionStatuses(): iterable
    {
        yield 'active' => ['ACTIVE', SubscriptionStatus::Active];
        yield 'canceled' => ['CANCELED', SubscriptionStatus::Canceled];
        yield 'on hold' => ['ON_HOLD', SubscriptionStatus::Paused];
        yield 'suspended' => ['SUSPENDED', SubscriptionStatus::Paused];
        yield 'finished' => ['FINISHED', SubscriptionStatus::Finished];
        yield 'unknown' => ['FUTURE_STATUS', SubscriptionStatus::Unknown];
    }

    #[Test]
    public function transaction_mapper_uses_decimal_strings_and_preserves_unknown_state(): void
    {
        $mapper = new TransactionMapper(new BlueSnapPayloadSanitizer);
        $transaction = $mapper->fromProvider([
            'transactionId' => 'tx-1',
            'amount' => '12.50',
            'currency' => 'usd',
            'processingInfo' => ['processingStatus' => 'FUTURE_STATUS'],
        ]);

        self::assertSame(TransactionStatus::Unknown, $transaction->status);
        self::assertNotNull($transaction->amount);
        self::assertSame('12.5', $transaction->amount->amount);
        self::assertSame('USD', $transaction->amount->currency);
    }
}
