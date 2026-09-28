<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Webhooks;

use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidWebhookSignature;
use DateTimeImmutable;
use DateTimeZone;

final readonly class BlueSnapSignatureVerifier
{
    /**
     * @param  list<string>  $allowedIps
     */
    public function __construct(
        #[\SensitiveParameter] private ?string $secret,
        private int $timestampTolerance = 300,
        private bool $verifySignature = true,
        private bool $verifyIp = false,
        private array $allowedIps = [],
    ) {}

    public function verify(WebhookRequest $request): void
    {
        if ($this->verifyIp && ($request->sourceIp === null || ! in_array($request->sourceIp, $this->allowedIps, true))) {
            throw new InvalidWebhookSignature('The webhook source IP is not allowed.');
        }

        if (! $this->verifySignature) {
            return;
        }

        if ($this->secret === null || $this->secret === '') {
            throw new InvalidWebhookSignature('The BlueSnap webhook secret is not configured.');
        }

        $timestamp = $request->firstHeader('Bls-Ipn-Timestamp');
        $signature = $request->firstHeader('Bls-Signature');

        if ($timestamp === null || $signature === null) {
            throw new InvalidWebhookSignature('Required BlueSnap webhook signature headers are missing.');
        }

        if (preg_match('/^[a-fA-F0-9]{64}$/', $signature) !== 1) {
            throw new InvalidWebhookSignature('The BlueSnap webhook signature is malformed.');
        }

        $signedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.v', $timestamp, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($signedAt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidWebhookSignature('The BlueSnap webhook timestamp is malformed.');
        }

        if ($signedAt->format('Y-m-d H:i:s.v') !== $timestamp) {
            throw new InvalidWebhookSignature('The BlueSnap webhook timestamp is malformed.');
        }

        if ($this->timestampTolerance > 0 && abs($request->receivedAt->getTimestamp() - $signedAt->getTimestamp()) > $this->timestampTolerance) {
            throw new InvalidWebhookSignature('The BlueSnap webhook timestamp is outside the allowed replay window.');
        }

        $expected = hash_hmac('sha256', $timestamp.$request->rawBody, $this->secret);

        if (! hash_equals($expected, strtolower($signature))) {
            throw new InvalidWebhookSignature('The BlueSnap webhook signature is invalid.');
        }
    }
}
