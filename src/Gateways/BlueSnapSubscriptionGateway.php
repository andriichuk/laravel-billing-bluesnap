<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Contracts\SupportsPlanChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsQuantityChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsSubscriptionTrials;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\SubscriptionMapper;

final readonly class BlueSnapSubscriptionGateway implements ManagesSubscriptions, SupportsPlanChanges, SupportsQuantityChanges, SupportsSubscriptionTrials
{
    public function __construct(
        private BlueSnapClient $client,
        private SubscriptionMapper $mapper,
        private ExceptionMapper $exceptions
    ) {}

    public function createSubscription(CreateSubscriptionData $data): SubscriptionData
    {
        return $this->exceptions->execute(function () use ($data): SubscriptionData {
            $response = $this->client->subscriptions()->create($this->mapper->createPayload($data), $data->idempotencyKey);

            return $this->mapper->fromProvider($response->json(), $data->type);
        });
    }

    public function updateSubscription(SubscriptionReference $subscription, UpdateSubscriptionData $data): SubscriptionData
    {
        $payload = $this->mapper->updatePayload($data);

        if ($payload === []) {
            throw InvalidBillingPayload::because('A BlueSnap subscription update must contain at least one change.');
        }

        return $this->updateAndMap($subscription, $payload);
    }

    public function cancelSubscription(SubscriptionReference $subscription, CancellationMode $mode): SubscriptionData
    {
        return $this->exceptions->execute(function () use ($subscription, $mode): SubscriptionData {
            $response = match ($mode) {
                CancellationMode::AtPeriodEnd => $this->client->subscriptions()->cancelAtPeriodEnd($subscription->id),
                CancellationMode::Immediately => $this->client->subscriptions()->cancel($subscription->id),
            };
            $payload = $response->json();

            if ($payload === []) {
                $payload = $this->client->subscriptions()->retrieve($subscription->id)->json();
            }

            return $this->mapper->fromProvider($payload, fallbackId: $subscription->id);
        });
    }

    public function retrieveSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->exceptions->execute(fn (): SubscriptionData => $this->mapper->fromProvider(
            $this->client->subscriptions()->retrieve($subscription->id)->json(),
            fallbackId: $subscription->id,
        ));
    }

    /**
     * @return \Generator<int, SubscriptionData>
     */
    public function allSubscriptions(int $pageSize, ?string $cursor = null): \Generator
    {
        if ($pageSize < 1 || $pageSize > 500) {
            throw InvalidBillingPayload::because('BlueSnap reconciliation page size must be between 1 and 500.');
        }

        $after = $cursor;

        do {
            $query = [
                'pagesize' => $pageSize,
                'gettotal' => true,
                'fulldescription' => true,
                'after' => $after,
            ];
            $payload = $this->exceptions->execute(fn (): array => $this->client->subscriptions()->all($query)->json());
            $subscriptions = $payload['subscriptions'] ?? [];

            if (! is_array($subscriptions)) {
                throw InvalidBillingPayload::because('BlueSnap returned an invalid subscriptions page.');
            }

            $next = null;

            foreach ($subscriptions as $subscription) {
                if (! is_array($subscription)) {
                    throw InvalidBillingPayload::because('BlueSnap returned an invalid subscription in a reconciliation page.');
                }

                $mapped = $this->mapper->fromProvider($subscription);
                $next = $mapped->reference->id;

                yield $mapped;
            }

            $lastPage = ($payload['lastPage'] ?? false) === true;

            if ($lastPage || $subscriptions === []) {
                return;
            }

            if ($next === null || $next === $after) {
                throw InvalidBillingPayload::because('BlueSnap reconciliation pagination did not advance.');
            }

            $after = $next;
        } while (true);
    }

    public function changePlan(SubscriptionReference $subscription, string $price, array $providerOptions = []): SubscriptionData
    {
        if (trim($price) === '') {
            throw InvalidBillingPayload::because('A BlueSnap plan ID must not be empty.');
        }

        return $this->updateAndMap($subscription, [...$providerOptions, 'planId' => $price]);
    }

    public function changeQuantity(SubscriptionReference $subscription, int $quantity, array $providerOptions = []): SubscriptionData
    {
        if ($quantity < 1) {
            throw InvalidBillingPayload::because('Subscription quantity must be at least one.');
        }

        return $this->updateAndMap($subscription, [...$providerOptions, 'quantity' => $quantity]);
    }

    /**
     * @param  array<string, scalar|null>  $changes
     */
    public function previewSwitchChargeAmount(SubscriptionReference $subscription, array $changes): ?string
    {
        return $this->exceptions->execute(function () use ($subscription, $changes): ?string {
            $payload = $this->client->subscriptions()->switchChargeAmount($subscription->id, $changes)->json();
            $amount = $payload['amount'] ?? $payload['switchChargeAmount'] ?? null;

            return is_string($amount) || is_int($amount) ? (string) $amount : null;
        });
    }

    public function resumeSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->exceptions->execute(function () use ($subscription): SubscriptionData {
            $response = $this->client->subscriptions()->activate($subscription->id);
            $payload = $response->json();

            if ($payload === []) {
                $payload = $this->client->subscriptions()->retrieve($subscription->id)->json();
            }

            return $this->mapper->fromProvider($payload, fallbackId: $subscription->id);
        });
    }

    public function renewSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->exceptions->execute(function () use ($subscription): SubscriptionData {
            $response = $this->client->subscriptions()->renew($subscription->id);
            $payload = $response->json();

            if ($payload === []) {
                $payload = $this->client->subscriptions()->retrieve($subscription->id)->json();
            }

            return $this->mapper->fromProvider($payload, fallbackId: $subscription->id);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateAndMap(SubscriptionReference $subscription, array $payload): SubscriptionData
    {
        return $this->exceptions->execute(function () use ($subscription, $payload): SubscriptionData {
            $response = $this->client->subscriptions()->update($subscription->id, $payload);
            $result = $response->json();

            if ($result === []) {
                $result = $this->client->subscriptions()->retrieve($subscription->id)->json();
            }

            return $this->mapper->fromProvider($result, fallbackId: $subscription->id);
        });
    }
}
