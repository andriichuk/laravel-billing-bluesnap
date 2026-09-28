<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;

final readonly class CustomerMapper
{
    public function __construct(
        private BlueSnapPayloadSanitizer $sanitizer,
        private PaymentSourceMapper $paymentSources
    ) {}

    /** @return array<string, mixed> */
    public function createPayload(CreateCustomerData $data): array
    {
        $options = $data->providerOptions;
        $this->paymentSources->assertSafeProviderPayload($options);
        $payload = $options;
        unset($payload['vaultedShopperId'], $payload['merchantShopperId']);

        if (isset($payload['pfToken'])) {
            if (isset($payload['paymentSources'])) {
                throw InvalidBillingPayload::because('Conflicting BlueSnap customer payment sources were supplied.');
            }
            $token = trim((string) $payload['pfToken']);

            if ($token === '') {
                throw InvalidBillingPayload::because('A BlueSnap Hosted Payment Fields token must not be empty.');
            }
            unset($payload['pfToken']);
            $payload['paymentSources'] = ['creditCardInfo' => [['pfToken' => $token]]];
        }

        $payload['merchantShopperId'] = $data->billableType.':'.$data->billableId;
        $payload = [...$payload, ...$this->identity($data->name, $data->email)];

        return $payload;
    }

    /** @return array<string, mixed> */
    public function updatePayload(UpdateCustomerData $data): array
    {
        $this->paymentSources->assertSafeProviderPayload($data->providerOptions);
        $payload = $data->providerOptions;
        unset($payload['vaultedShopperId'], $payload['merchantShopperId']);

        return [...$payload, ...$this->identity($data->name, $data->email)];
    }

    /** @param array<string, mixed> $payload */
    public function fromProvider(array $payload, ?string $fallbackId = null): CustomerData
    {
        $id = $this->scalarString($payload['vaultedShopperId'] ?? $fallbackId);

        if ($id === null) {
            throw InvalidBillingPayload::because('BlueSnap did not return a vaulted shopper ID.');
        }
        $first = $this->scalarString($payload['firstName'] ?? null);
        $last = $this->scalarString($payload['lastName'] ?? null);
        $name = trim(implode(' ', array_filter([$first, $last], static fn (?string $part): bool => $part !== null && $part !== '')));

        return new CustomerData(
            reference: new CustomerReference($id),
            name: $name !== '' ? $name : null,
            email: $this->scalarString($payload['email'] ?? null),
            providerData: $this->sanitizer->sanitize($payload),
        );
    }

    /** @return array<string, string> */
    private function identity(?string $name, ?string $email): array
    {
        $identity = [];

        if ($name !== null && trim($name) !== '') {
            [$first, $last] = array_pad(preg_split('/\s+/', trim($name), 2) ?: [], 2, null);

            if (is_string($first) && $first !== '') {
                $identity['firstName'] = $first;
            }

            if (is_string($last) && $last !== '') {
                $identity['lastName'] = $last;
            }
        }

        if ($email !== null) {
            $identity['email'] = $email;
        }

        return $identity;
    }

    private function scalarString(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
