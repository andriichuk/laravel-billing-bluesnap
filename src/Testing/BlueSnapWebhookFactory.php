<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Testing;

use Andriichuk\LaravelBilling\Data\WebhookRequest;
use DateTimeImmutable;

final class BlueSnapWebhookFactory
{
    /** @param array<string, scalar> $payload */
    public static function make(array $payload, string $secret = 'test-webhook-secret', ?DateTimeImmutable $receivedAt = null, string $headerCase = 'canonical'): WebhookRequest
    {
        $receivedAt ??= new DateTimeImmutable;
        $timestamp = $receivedAt->format('Y-m-d H:i:s.v');
        $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $timestampHeader = $headerCase === 'lower' ? 'bls-ipn-timestamp' : 'Bls-Ipn-Timestamp';
        $signatureHeader = $headerCase === 'lower' ? 'bls-signature' : 'Bls-Signature';

        return new WebhookRequest('POST', $body, [
            $timestampHeader => [$timestamp],
            $signatureHeader => [hash_hmac('sha256', $timestamp.$body, $secret)],
            'Content-Type' => ['application/x-www-form-urlencoded'],
        ], '141.226.140.200', $receivedAt);
    }
}
