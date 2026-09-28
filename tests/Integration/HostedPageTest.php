<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Integration;

use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\ProviderRequestFailed;
use Andriichuk\LaravelBillingBlueSnap\Exceptions\UnsupportedBlueSnapPayload;
use Andriichuk\LaravelBillingBlueSnap\Testing\BlueSnapDriverFactory;
use Andriichuk\LaravelBillingBlueSnap\Testing\RecordingHttpClient;
use Andriichuk\LaravelBillingBlueSnap\Tests\CreatesDriver;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HostedPageTest extends TestCase
{
    use CreatesDriver;

    #[Test]
    public function the_driver_advertises_hosted_checkout_and_builds_a_url_without_encryption(): void
    {
        $driver = $this->blueSnapDriver();

        self::assertTrue($driver->supports(Capability::HostedCheckout));
        self::assertSame(
            'https://sandbox.bluesnap.com/buynow/checkout?plan3173219=3&merchantid=1469228&merchanttransactionid=subscription%20%2342&email=ada%2Bbilling%40example.com',
            $driver->hostedCheckoutUrl([
                'plan_id' => 3173219,
                'quantity' => 3,
                'merchant_transaction_id' => 'subscription #42',
                'email' => 'ada+billing@example.com',
            ]),
        );
    }

    #[Test]
    public function it_encrypts_the_return_url_and_appends_the_token(): void
    {
        $fixture = file_get_contents(dirname(__DIR__).'/Fixtures/param-encryption.xml');
        self::assertIsString($fixture);
        $http = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/xml'], $fixture));
        $driver = BlueSnapDriverFactory::create($this->application(), $http);

        $url = $driver->hostedPage()->checkoutUrl([
            'plan_id' => 3173219,
            'return_url' => 'https://app.example.com/billing/return?token=abc&source=hpp',
        ]);

        self::assertSame(
            'https://sandbox.bluesnap.com/buynow/checkout?plan3173219&merchantid=1469228&enc=opaque%2Btoken%2F%3D',
            $url,
        );
        self::assertSame('application/xml', $http->lastRequest()->getHeaderLine('Content-Type'));
        self::assertStringContainsString(
            'https://app.example.com/billing/return?token=abc&amp;source=hpp',
            (string) $http->lastRequest()->getBody(),
        );
    }

    #[Test]
    public function an_explicit_checkout_host_overrides_the_environment_default(): void
    {
        $driver = BlueSnapDriverFactory::create($this->application(), new RecordingHttpClient, [
            'checkout_host' => 'https://payments.example.com',
        ]);

        self::assertSame(
            'https://payments.example.com/buynow/checkout?plan3173219&merchantid=1469228',
            $driver->hostedCheckoutUrl(['plan_id' => 3173219]),
        );
    }

    #[Test]
    public function missing_plan_id_is_rejected_before_any_request(): void
    {
        $this->expectException(UnsupportedBlueSnapPayload::class);
        $this->expectExceptionMessage('[plan_id] must be a positive integer');

        $this->blueSnapDriver()->hostedCheckoutUrl([]);
    }

    #[Test]
    public function merchant_transaction_ids_longer_than_fifty_characters_are_rejected(): void
    {
        $this->expectException(UnsupportedBlueSnapPayload::class);
        $this->expectExceptionMessage('may not exceed 50 characters');

        $this->blueSnapDriver()->hostedCheckoutUrl([
            'plan_id' => 3173219,
            'merchant_transaction_id' => str_repeat('x', 51),
        ]);
    }

    #[Test]
    public function credentials_and_ip_allowlist_failures_remain_distinguishable(): void
    {
        $unauthorized = $this->blueSnapDriver(new Response(401));

        try {
            $unauthorized->hostedCheckoutUrl([
                'plan_id' => 3173219,
                'return_url' => 'https://app.example.com/return',
            ]);
            self::fail('Expected a credential error.');
        } catch (ProviderRequestFailed $exception) {
            self::assertStringContainsString('HTTP 401', $exception->getMessage());
            self::assertStringContainsString('credentials', $exception->getMessage());
        }

        $forbidden = $this->blueSnapDriver(new Response(403));

        try {
            $forbidden->hostedCheckoutUrl([
                'plan_id' => 3173219,
                'return_url' => 'https://app.example.com/return',
            ]);
            self::fail('Expected an IP allowlist error.');
        } catch (ProviderRequestFailed $exception) {
            self::assertStringContainsString('HTTP 403', $exception->getMessage());
            self::assertStringContainsString('IP is allowlisted', $exception->getMessage());
        }
    }
}
