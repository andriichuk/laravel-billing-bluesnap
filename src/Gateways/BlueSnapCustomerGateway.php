<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Http\Response;
use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Contracts\SupportsPaymentMethodUpdates;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Andriichuk\LaravelBillingBlueSnap\Mappers\CustomerMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;

final readonly class BlueSnapCustomerGateway implements ManagesCustomers, SupportsPaymentMethodUpdates
{
    public function __construct(
        private BlueSnapClient $client,
        private CustomerMapper $mapper,
        private ExceptionMapper $exceptions
    ) {}

    public function createCustomer(CreateCustomerData $data): CustomerData
    {
        return $this->exceptions->execute(function () use ($data): CustomerData {
            $response = $this->client->vaultedShoppers()->create($this->mapper->createPayload($data), $data->idempotencyKey);

            return $this->mapper->fromProvider($response->json(), $this->idFromLocation($response));
        });
    }

    public function updateCustomer(CustomerReference $customer, UpdateCustomerData $data): CustomerData
    {
        return $this->exceptions->execute(function () use ($customer, $data): CustomerData {
            $response = $this->client->vaultedShoppers()->update($customer->id, $this->mapper->updatePayload($data));
            $payload = $response->json();

            if ($payload === []) {
                $payload = $this->client->vaultedShoppers()->retrieve($customer->id)->json();
            }

            return $this->mapper->fromProvider($payload, $customer->id);
        });
    }

    public function retrieveCustomer(CustomerReference $customer): CustomerData
    {
        return $this->exceptions->execute(fn (): CustomerData => $this->mapper->fromProvider(
            $this->client->vaultedShoppers()->retrieve($customer->id)->json(),
            $customer->id,
        ));
    }

    public function deleteCustomer(CustomerReference $customer): void
    {
        $this->exceptions->execute(fn (): Response => $this->client->vaultedShoppers()->delete($customer->id));
    }

    public function updatePaymentMethod(CustomerReference $customer, PaymentMethodReference $paymentMethod): void
    {
        if (! HostedFieldsToken::supports($paymentMethod)) {
            throw InvalidBillingPayload::because('BlueSnap payment-method updates require a HostedFieldsToken.');
        }

        $token = HostedFieldsToken::fromPaymentMethodReference($paymentMethod);
        $this->exceptions->execute(fn (): Response => $this->client->vaultedShoppers()->update($customer->id, [
            'paymentSources' => ['creditCardInfo' => [['pfToken' => $token->value]]],
        ]));
    }

    private function idFromLocation(Response $response): ?string
    {
        $location = $response->location();

        if ($location === null) {
            return null;
        }

        $path = parse_url($location, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $id = basename($path);

        return $id !== '' ? rawurldecode($id) : null;
    }
}
