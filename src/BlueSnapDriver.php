<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap;

use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Contracts\HandlesWebhookProbes;
use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Contracts\ManagesTransactions;
use Andriichuk\LaravelBilling\Contracts\ProcessesWebhooks;
use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Contracts\SupportsHostedCheckout;
use Andriichuk\LaravelBilling\Contracts\SupportsPaymentMethodUpdates;
use Andriichuk\LaravelBilling\Contracts\SupportsPlanChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsQuantityChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsRefunds;
use Andriichuk\LaravelBilling\Contracts\SupportsSubscriptionTrials;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapCustomerGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapReconciler;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapSubscriptionGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapTransactionGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapWebhookGateway;
use Andriichuk\LaravelBillingBlueSnap\HostedFields\PaymentFieldsTokenService;
use Andriichuk\LaravelBillingBlueSnap\HostedPage\HostedPageCheckoutService;

final readonly class BlueSnapDriver implements BillingDriver, HandlesWebhookProbes, ManagesCustomers, ManagesSubscriptions, ManagesTransactions, ProcessesWebhooks, ReconcilesResources, SupportsHostedCheckout, SupportsPaymentMethodUpdates, SupportsPlanChanges, SupportsQuantityChanges, SupportsRefunds, SupportsSubscriptionTrials
{
    /**
     * @var list<Capability>
     */
    private const array CAPABILITIES = [
        Capability::Customers,
        Capability::Subscriptions,
        Capability::Transactions,
        Capability::Webhooks,
        Capability::Reconciliation,
        Capability::SubscriptionTrials,
        Capability::PlanChanges,
        Capability::QuantityChanges,
        Capability::Refunds,
        Capability::PaymentMethodUpdates,
        Capability::HostedCheckout,
    ];

    public function __construct(
        private BlueSnapCustomerGateway $customerGateway,
        private BlueSnapSubscriptionGateway $subscriptionGateway,
        private BlueSnapTransactionGateway $transactionGateway,
        private BlueSnapWebhookGateway $webhookGateway,
        private BlueSnapReconciler $reconciler,
        private PaymentFieldsTokenService $hostedFields,
        private HostedPageCheckoutService $hostedPage,
    ) {}

    public function name(): string
    {
        return 'bluesnap';
    }

    public function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    public function customers(): BlueSnapCustomerGateway
    {
        return $this->customerGateway;
    }

    public function subscriptions(): BlueSnapSubscriptionGateway
    {
        return $this->subscriptionGateway;
    }

    public function transactions(): BlueSnapTransactionGateway
    {
        return $this->transactionGateway;
    }

    public function webhooks(): BlueSnapWebhookGateway
    {
        return $this->webhookGateway;
    }

    public function hostedFields(): PaymentFieldsTokenService
    {
        return $this->hostedFields;
    }

    public function hostedPage(): HostedPageCheckoutService
    {
        return $this->hostedPage;
    }

    public function hostedCheckoutUrl(array $options): string
    {
        return $this->hostedPage->checkoutUrl($options);
    }

    public function createCustomer(CreateCustomerData $data): CustomerData
    {
        return $this->customerGateway->createCustomer($data);
    }

    public function updateCustomer(CustomerReference $customer, UpdateCustomerData $data): CustomerData
    {
        return $this->customerGateway->updateCustomer($customer, $data);
    }

    public function retrieveCustomer(CustomerReference $customer): CustomerData
    {
        return $this->customerGateway->retrieveCustomer($customer);
    }

    public function updatePaymentMethod(CustomerReference $customer, PaymentMethodReference $paymentMethod): void
    {
        $this->customerGateway->updatePaymentMethod($customer, $paymentMethod);
    }

    public function createSubscription(CreateSubscriptionData $data): SubscriptionData
    {
        return $this->subscriptionGateway->createSubscription($data);
    }

    public function updateSubscription(SubscriptionReference $subscription, UpdateSubscriptionData $data): SubscriptionData
    {
        return $this->subscriptionGateway->updateSubscription($subscription, $data);
    }

    public function cancelSubscription(SubscriptionReference $subscription, CancellationMode $mode): SubscriptionData
    {
        return $this->subscriptionGateway->cancelSubscription($subscription, $mode);
    }

    public function retrieveSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->subscriptionGateway->retrieveSubscription($subscription);
    }

    public function changePlan(SubscriptionReference $subscription, string $price, array $providerOptions = []): SubscriptionData
    {
        return $this->subscriptionGateway->changePlan($subscription, $price, $providerOptions);
    }

    public function changeQuantity(SubscriptionReference $subscription, int $quantity, array $providerOptions = []): SubscriptionData
    {
        return $this->subscriptionGateway->changeQuantity($subscription, $quantity, $providerOptions);
    }

    public function retrieveTransaction(TransactionReference $transaction): TransactionData
    {
        return $this->transactionGateway->retrieveTransaction($transaction);
    }

    public function refund(TransactionReference $transaction, ?Money $amount = null, array $providerOptions = []): TransactionData
    {
        return $this->transactionGateway->refund($transaction, $amount, $providerOptions);
    }

    public function verifyWebhook(WebhookRequest $request): void
    {
        $this->webhookGateway->verifyWebhook($request);
    }

    public function parseWebhook(WebhookRequest $request): ParsedWebhook
    {
        return $this->webhookGateway->parseWebhook($request);
    }

    public function reconcile(ReconciliationRequest $request): iterable
    {
        return $this->reconciler->reconcile($request);
    }

    public function handlesWebhookProbe(WebhookRequest $request): bool
    {
        return strtoupper($request->method) === 'GET' && $request->rawBody === '';
    }
}
