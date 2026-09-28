<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapEventNormalizer;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;

final readonly class WebhookMapper
{
    public function __construct(
        private BlueSnapPayloadSanitizer $sanitizer,
        private BlueSnapEventNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromPayload(array $payload, string $rawBody): ParsedWebhook
    {
        $type = $this->string($payload['transactionType'] ?? $payload['eventType'] ?? null) ?? 'UNKNOWN';
        $resourceId = $this->resourceId($payload);
        $eventKey = $this->string($payload['ipnId'] ?? null) ?? hash('sha256', $rawBody);
        $sanitized = $this->sanitizer->sanitize($payload);

        return new ParsedWebhook(
            eventKey: $eventKey,
            eventType: strtoupper($type),
            providerResourceId: $resourceId,
            sanitizedPayload: $sanitized,
            events: $this->normalizer->normalize($type, $sanitized),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resourceId(array $payload): ?string
    {
        foreach (['referenceNumber', 'transactionId', 'subscriptionId', 'vaultedShopperId', 'accountId', 'contractId'] as $key) {
            $value = $this->string($payload[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
