<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Integration;

use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBillingBlueSnap\Testing\BlueSnapDriverFactory;
use Andriichuk\LaravelBillingBlueSnap\Testing\RecordingHttpClient;
use Andriichuk\LaravelBillingBlueSnap\Tests\CreatesDriver;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    use CreatesDriver;

    #[Test]
    public function customer_creation_maps_the_billable_to_a_vaulted_shopper(): void
    {
        $http = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'vaultedShopperId' => 123,
            'merchantShopperId' => 'App\\Models\\User:42',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
        ], JSON_THROW_ON_ERROR)));
        $driver = BlueSnapDriverFactory::create($this->application(), $http);
        $customer = $driver->createCustomer(new CreateCustomerData('App\\Models\\User', '42', 'Ada Lovelace', 'ada@example.com', idempotencyKey: 'customer-42'));
        $sent = json_decode((string) $http->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('123', $customer->reference->id);
        self::assertSame('App\\Models\\User:42', $sent['merchantShopperId']);
        self::assertSame('customer-42', $http->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    #[Test]
    public function hosted_fields_token_is_top_level_on_subscription_requests(): void
    {
        $http = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'subscriptionId' => 456,
            'planId' => 99,
            'status' => 'ACTIVE',
            'quantity' => 2,
            'currency' => 'USD',
            'recurringChargeAmount' => '15.00',
        ], JSON_THROW_ON_ERROR)));
        $driver = BlueSnapDriverFactory::create($this->application(), $http);
        $driver->createSubscription(new CreateSubscriptionData(
            billableType: 'App\\Models\\User',
            billableId: '42',
            type: 'default',
            price: '99',
            quantity: 2,
            paymentMethod: HostedFieldsToken::fromString('pf-opaque')->toPaymentMethodReference(),
        ));
        $sent = json_decode((string) $http->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('pf-opaque', $sent['pfToken']);
        self::assertArrayNotHasKey('paymentSource', $sent);
        self::assertSame('99', $sent['planId']);
    }

    #[Test]
    public function hosted_fields_service_returns_a_typed_expiring_token(): void
    {
        $driver = $this->blueSnapDriver(new Response(201, ['Location' => 'https://sandbox.bluesnap.com/services/2/payment-fields-tokens/token-123']));
        $token = $driver->hostedFields()->createToken();

        self::assertSame('token-123', $token->value);
        self::assertNotNull($token->expiresAt);
    }

    #[Test]
    public function sdk_not_found_errors_are_translated_and_preserve_the_original(): void
    {
        $driver = $this->blueSnapDriver(new Response(404, ['Content-Type' => 'application/json'], '{"message":[{"description":"missing"}]}'));

        try {
            $driver->retrieveCustomer(new CustomerReference('missing'));
            self::fail('Expected the translated exception.');
        } catch (BillingResourceNotFound $exception) {
            self::assertNotNull($exception->getPrevious());
        }
    }
}
