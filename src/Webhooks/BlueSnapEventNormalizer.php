<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Webhooks;

use Andriichuk\LaravelBilling\Data\Events\ChargebackOpened;
use Andriichuk\LaravelBilling\Data\Events\ChargebackUpdated;
use Andriichuk\LaravelBilling\Data\Events\CustomerDeleted;
use Andriichuk\LaravelBilling\Data\Events\CustomerUpdated;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionCanceled;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionPaymentFailed;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionRenewalScheduled;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionUpdated;
use Andriichuk\LaravelBilling\Data\Events\TransactionFailed;
use Andriichuk\LaravelBilling\Data\Events\TransactionPending;
use Andriichuk\LaravelBilling\Data\Events\TransactionRefunded;
use Andriichuk\LaravelBilling\Data\Events\TransactionSucceeded;

final class BlueSnapEventNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<NormalizedEvent>
     */
    public function normalize(string $type, array $payload): array
    {
        $type = strtoupper($type);
        $transactionId = $this->id($payload, ['referenceNumber', 'transactionId', 'originalRefNum']);
        $subscriptionId = $this->id($payload, ['subscriptionId', 'contractId', 'cancelledContractId']);
        $customerId = $this->id($payload, ['vaultedShopperId', 'shopperId', 'accountId']);
        $events = [];

        if (in_array($type, ['CHARGE', 'AUTH_ONLY', 'AUTH_CAPTURE', 'RECURRING'], true) && $transactionId !== null) {
            $events[] = new TransactionSucceeded($transactionId, $payload);
        } elseif (in_array($type, ['DECLINE', 'CC_FAILURE', 'FRAUD_DECLINE'], true) && $transactionId !== null) {
            $events[] = new TransactionFailed($transactionId, $payload);
        } elseif ($type === 'SUBSCRIPTION_CHARGE_FAILURE' && $subscriptionId !== null) {
            $events[] = new SubscriptionPaymentFailed($subscriptionId, $payload);
        } elseif (in_array($type, ['REFUND', 'CANCELLATION_REFUND'], true) && $transactionId !== null) {
            $events[] = new TransactionRefunded($transactionId, $payload);
        } elseif ($type === 'CHARGE_PENDING' && $transactionId !== null) {
            $events[] = new TransactionPending($transactionId, $payload);
        } elseif ($type === 'CHARGEBACK' && $transactionId !== null) {
            $events[] = new ChargebackOpened($transactionId, $payload);
        } elseif ($type === 'CHARGEBACK_STATUS_CHANGED' && $transactionId !== null) {
            $events[] = new ChargebackUpdated($transactionId, $payload);
        } elseif (in_array($type, ['CANCELLATION', 'CANCEL_ON_RENEWAL'], true) && $subscriptionId !== null) {
            $events[] = new SubscriptionCanceled($subscriptionId, $payload);
        } elseif ($type === 'CONTRACT_CHANGE' && $subscriptionId !== null) {
            $events[] = new SubscriptionUpdated($subscriptionId, $payload);
        } elseif ($type === 'SUBSCRIPTION_REMINDER' && $subscriptionId !== null) {
            $events[] = new SubscriptionRenewalScheduled($subscriptionId, $payload);
        } elseif ($type === 'SHOPPER_DELETED' && $customerId !== null) {
            $events[] = new CustomerDeleted($customerId, $payload);
        } elseif (in_array($type, ['PAYMENT_UPDATE', 'ACCOUNT_UPDATER'], true) && $customerId !== null) {
            $events[] = new CustomerUpdated($customerId, $payload);
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function id(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
