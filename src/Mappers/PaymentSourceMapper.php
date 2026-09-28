<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\VaultedShopperReference;

final class PaymentSourceMapper
{
    /**
     * @param  array<string, mixed>  $providerOptions
     * @return array<string, mixed>
     */
    public function forSubscription(?CustomerReference $customer, ?PaymentMethodReference $paymentMethod, array $providerOptions): array
    {
        $explicit = $providerOptions['paymentSource'] ?? null;

        if ($explicit !== null && ! is_array($explicit)) {
            throw InvalidBillingPayload::because('BlueSnap paymentSource must be an object.');
        }

        if ($explicit !== null && $paymentMethod !== null) {
            throw InvalidBillingPayload::because('Conflicting BlueSnap payment sources were supplied.');
        }

        $payload = [];

        if ($customer !== null) {
            $payload['vaultedShopperId'] = $customer->id;
        }

        if ($paymentMethod === null) {
            if (is_array($explicit)) {
                $this->assertSafeProviderPayload($explicit);
                $payload['paymentSource'] = $explicit;
            }

            return $payload;
        }

        if (HostedFieldsToken::supports($paymentMethod)) {
            $payload['pfToken'] = HostedFieldsToken::fromPaymentMethodReference($paymentMethod)->value;

            return $payload;
        }

        if (VaultedShopperReference::supports($paymentMethod)) {
            $shopper = VaultedShopperReference::fromPaymentMethodReference($paymentMethod);

            if ($customer !== null && $customer->id !== $shopper->id) {
                throw InvalidBillingPayload::because('The customer and vaulted shopper payment source do not match.');
            }
            $payload['vaultedShopperId'] = $shopper->id;

            return $payload;
        }

        throw InvalidBillingPayload::because('Unsupported payment method reference for BlueSnap. Use HostedFieldsToken or VaultedShopperReference.');
    }

    /** @param array<string, mixed> $source */
    public function assertSafeProviderPayload(array $source): void
    {
        $forbidden = ['cardnumber', 'cvv', 'cvv2', 'securitycode', 'accountnumber'];
        array_walk_recursive($source, static function (mixed $value, int|string $key) use ($forbidden): void {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key) ?? '');

            if (in_array($normalized, $forbidden, true)) {
                throw InvalidBillingPayload::because('Raw card and bank account data is not accepted by the BlueSnap billing driver.');
            }
        });
    }
}
