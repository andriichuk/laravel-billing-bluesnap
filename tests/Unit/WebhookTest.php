<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Unit;

use Andriichuk\LaravelBilling\Data\Events\TransactionSucceeded;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidWebhookSignature;
use Andriichuk\LaravelBillingBlueSnap\Mappers\WebhookMapper;
use Andriichuk\LaravelBillingBlueSnap\Testing\BlueSnapWebhookFactory;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapEventNormalizer;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapSignatureVerifier;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapWebhookParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    #[Test]
    public function signature_verification_uses_the_exact_body_and_case_insensitive_headers(): void
    {
        $request = BlueSnapWebhookFactory::make(['transactionType' => 'CHARGE', 'referenceNumber' => 'tx-1'], headerCase: 'lower');
        $verifier = new BlueSnapSignatureVerifier('test-webhook-secret');
        $verifier->verify($request);

        $this->expectException(InvalidWebhookSignature::class);
        $verifier->verify(new WebhookRequest('POST', $request->rawBody.' ', $request->headers, $request->sourceIp, $request->receivedAt));
    }

    #[Test]
    public function stale_and_malformed_timestamps_fail_closed(): void
    {
        $receivedAt = new DateTimeImmutable('2026-09-28 12:00:00 UTC');
        $request = BlueSnapWebhookFactory::make(['transactionType' => 'CHARGE'], receivedAt: $receivedAt->modify('-301 seconds'));
        $request = new WebhookRequest($request->method, $request->rawBody, $request->headers, $request->sourceIp, $receivedAt);

        $this->expectException(InvalidWebhookSignature::class);
        (new BlueSnapSignatureVerifier('test-webhook-secret'))->verify($request);
    }

    #[Test]
    public function parser_normalizes_form_payloads_and_removes_sensitive_values(): void
    {
        $request = BlueSnapWebhookFactory::make([
            'ipnId' => 'ipn-1',
            'transactionType' => 'CHARGE',
            'referenceNumber' => 'tx-1',
            'amount' => '10.00',
            'authKey' => 'secret-value',
            'cardNumber' => '4111111111111111',
        ]);
        $parser = new BlueSnapWebhookParser(new WebhookMapper(new BlueSnapPayloadSanitizer, new BlueSnapEventNormalizer));
        $parsed = $parser->parse($request);

        self::assertSame('ipn-1', $parsed->eventKey);
        self::assertSame('CHARGE', $parsed->eventType);
        self::assertSame('tx-1', $parsed->providerResourceId);
        self::assertArrayNotHasKey('authKey', $parsed->sanitizedPayload);
        self::assertArrayNotHasKey('cardNumber', $parsed->sanitizedPayload);
        self::assertCount(1, $parsed->events);
        self::assertInstanceOf(TransactionSucceeded::class, $parsed->events[0]);
    }
}
