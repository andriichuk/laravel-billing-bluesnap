<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;

final readonly class BlueSnapReconciler implements ReconcilesResources
{
    public function __construct(
        private BlueSnapCustomerGateway $customers,
        private BlueSnapSubscriptionGateway $subscriptions,
        private BlueSnapTransactionGateway $transactions,
    ) {}

    public function reconcile(ReconciliationRequest $request): iterable
    {
        if ($request->model === null || $request->id === null || trim((string) $request->id) === '') {
            throw InvalidBillingPayload::because('BlueSnap reconciliation requires both a resource model and provider ID.');
        }
        $id = (string) $request->id;
        $resource = match ($request->model) {
            'customer' => $this->customers->retrieveCustomer(new CustomerReference($id)),
            'subscription' => $this->subscriptions->retrieveSubscription(new SubscriptionReference($id)),
            'transaction' => $this->transactions->retrieveTransaction(new TransactionReference($id)),
            default => throw InvalidBillingPayload::because('BlueSnap can reconcile customer, subscription, or transaction resources.'),
        };
        [$billableType, $billableId] = $this->billable($resource);

        yield new ReconciliationResult($request->model, $resource, $billableType, $billableId);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function billable(CustomerData|SubscriptionData|TransactionData $resource): array
    {
        $data = $resource->rawProviderData();
        $merchantShopperId = $data['merchantShopperId'] ?? null;
        if (is_string($merchantShopperId) && str_contains($merchantShopperId, ':')) {
            [$type, $id] = explode(':', $merchantShopperId, 2);

            return [$type !== '' ? $type : null, $id !== '' ? $id : null];
        }

        $customerId = $resource instanceof SubscriptionData ? $resource->customerId : ($data['vaultedShopperId'] ?? null);
        if ($resource instanceof TransactionData && $customerId === null && $resource->subscriptionId !== null) {
            $subscription = $this->subscriptions->retrieveSubscription(new SubscriptionReference($resource->subscriptionId));
            $customerId = $subscription->customerId;
        }
        if (is_string($customerId) || is_int($customerId)) {
            $customer = $this->customers->retrieveCustomer(new CustomerReference((string) $customerId));

            return $this->billable($customer);
        }

        return [null, null];
    }
}
