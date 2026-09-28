<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Enums\BillingInterval;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;
use DateTimeImmutable;
use Throwable;

final readonly class SubscriptionMapper
{
    public function __construct(private BlueSnapPayloadSanitizer $sanitizer, private PaymentSourceMapper $paymentSources, private string $defaultCurrency = 'USD') {}

    /** @return array<string, mixed> */
    public function createPayload(CreateSubscriptionData $data): array
    {
        if ($data->quantity < 1) {
            throw InvalidBillingPayload::because('Subscription quantity must be at least one.');
        }
        $options = $data->providerOptions;
        $this->paymentSources->assertSafeProviderPayload($options);
        $source = $this->paymentSources->forSubscription($data->customer, $data->paymentMethod, $options);
        unset($options['paymentSource'], $options['planId'], $options['subscriptionId'], $options['pfToken'], $options['vaultedShopperId']);
        $payload = [...$options, ...$source, 'planId' => $data->price, 'quantity' => $data->quantity];
        if ($data->trialDays !== null) {
            if ($data->trialDays < 0) {
                throw InvalidBillingPayload::because('Trial days must not be negative.');
            }
            $payload['trialPeriodDays'] = $data->trialDays;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public function updatePayload(UpdateSubscriptionData $data): array
    {
        $this->paymentSources->assertSafeProviderPayload($data->providerOptions);
        $payload = $data->providerOptions;
        unset($payload['subscriptionId'], $payload['status']);
        if ($data->price !== null) {
            $payload['planId'] = $data->price;
        }
        if ($data->quantity !== null) {
            if ($data->quantity < 1) {
                throw InvalidBillingPayload::because('Subscription quantity must be at least one.');
            }
            $payload['quantity'] = $data->quantity;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public function fromProvider(array $payload, string $fallbackType = 'default', ?string $fallbackId = null): SubscriptionData
    {
        $id = $this->scalarString($payload['subscriptionId'] ?? $fallbackId);
        if ($id === null) {
            throw InvalidBillingPayload::because('BlueSnap did not return a subscription ID.');
        }
        $status = $this->status($this->scalarString($payload['status'] ?? null));
        $trialEnd = $this->date($payload['trialEndDate'] ?? $payload['trialEndsAt'] ?? null);
        if ($status === SubscriptionStatus::Active && $trialEnd !== null && $trialEnd > new DateTimeImmutable) {
            $status = SubscriptionStatus::Trialing;
        }
        $amount = $this->money($payload['recurringChargeAmount'] ?? null, $payload['currency'] ?? $this->defaultCurrency);

        return new SubscriptionData(
            reference: new SubscriptionReference($id),
            type: $fallbackType,
            status: $status,
            customerId: $this->scalarString($payload['vaultedShopperId'] ?? null),
            productId: $this->scalarString($payload['productId'] ?? null),
            priceId: $this->scalarString($payload['planId'] ?? null),
            quantity: is_int($payload['quantity'] ?? null) ? max(1, $payload['quantity']) : 1,
            recurringAmount: $amount,
            billingInterval: $this->interval($this->scalarString($payload['chargeFrequency'] ?? null)),
            billingIntervalCount: $amount !== null ? 1 : null,
            autoRenew: ! isset($payload['autoRenew']) || (bool) $payload['autoRenew'],
            trialEndsAt: $trialEnd,
            nextChargeAt: $this->date($payload['nextChargeDate'] ?? null),
            endsAt: $this->date($payload['cancellationDate'] ?? $payload['endDate'] ?? null),
            pausedAt: in_array(strtoupper((string) ($payload['status'] ?? '')), ['ON_HOLD', 'SUSPENDED'], true) ? new DateTimeImmutable : null,
            providerData: $this->sanitizer->sanitize($payload),
        );
    }

    public function status(?string $status): SubscriptionStatus
    {
        return match (strtoupper((string) $status)) {
            'ACTIVE' => SubscriptionStatus::Active,
            'CANCELED', 'CANCELLED', 'DELETED' => SubscriptionStatus::Canceled,
            'ON_HOLD', 'SUSPENDED' => SubscriptionStatus::Paused,
            'FINISHED' => SubscriptionStatus::Finished,
            default => SubscriptionStatus::Unknown,
        };
    }

    private function interval(?string $frequency): ?BillingInterval
    {
        return match (strtoupper((string) $frequency)) {
            'DAILY' => BillingInterval::Day,
            'WEEKLY' => BillingInterval::Week,
            'MONTHLY' => BillingInterval::Month,
            'ANNUALLY', 'YEARLY' => BillingInterval::Year,
            default => null,
        };
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            return null;
        }
        if (is_int($amount) || is_string($amount)) {
            return new Money($amount, $currency);
        }
        if (is_float($amount) && is_string($encoded = json_encode($amount, JSON_PRESERVE_ZERO_FRACTION))) {
            return new Money($encoded, $currency);
        }

        return null;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function scalarString(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
