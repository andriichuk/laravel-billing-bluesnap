<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
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
        $id = $request->id === null ? null : trim((string) $request->id);

        if ($id !== null && $id !== '') {
            if ($request->model === null) {
                throw InvalidBillingPayload::because('Targeted BlueSnap reconciliation requires a resource model.');
            }

            $resource = match ($request->model) {
                'customer' => $this->customers->retrieveCustomer(new CustomerReference($id)),
                'subscription' => $this->subscriptions->retrieveSubscription(new SubscriptionReference($id)),
                'transaction' => $this->transactions->retrieveTransaction(new TransactionReference($id)),
                default => throw InvalidBillingPayload::because('BlueSnap can reconcile customer, subscription, or transaction resources.'),
            };

            yield new ReconciliationResult($request->model, $resource);

            return;
        }

        if ($request->model !== null && $request->model !== 'subscription') {
            throw InvalidBillingPayload::because('BlueSnap sweep reconciliation is available for subscriptions; customer and transaction reconciliation require an ID.');
        }

        foreach ($this->subscriptions->allSubscriptions($request->pageSize, $request->cursor) as $subscription) {
            yield new ReconciliationResult('subscription', $subscription);
        }
    }
}
