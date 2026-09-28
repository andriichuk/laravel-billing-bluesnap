<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\HostedPage;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\HostedPage\HostedPageRequest;
use Andriichuk\LaravelBilling\Exceptions\ProviderRequestFailed;
use Andriichuk\LaravelBillingBlueSnap\Exceptions\InvalidBlueSnapConfiguration;
use Andriichuk\LaravelBillingBlueSnap\Exceptions\UnsupportedBlueSnapPayload;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;

final readonly class HostedPageCheckoutService
{
    /** @var list<string> */
    private const array OPTIONS = [
        'plan_id',
        'return_url',
        'merchant_transaction_id',
        'email',
        'quantity',
    ];

    public function __construct(
        private BlueSnapClient $client,
        private ExceptionMapper $exceptions,
    ) {}

    /** @param array<string, mixed> $options */
    public function checkoutUrl(array $options): string
    {
        $this->assertKnownOptions($options);
        $planId = $this->requiredPositiveInteger($options, 'plan_id');
        $quantity = $this->positiveInteger($options, 'quantity');
        $returnUrl = $this->optionalString($options, 'return_url');
        $merchantTransactionId = $this->optionalString($options, 'merchant_transaction_id');
        $email = $this->optionalString($options, 'email');

        if ($returnUrl !== null && ! $this->isHttpUrl($returnUrl)) {
            throw new UnsupportedBlueSnapPayload('BlueSnap Hosted Payment Page option [return_url] must be an HTTP or HTTPS URL.');
        }

        if ($merchantTransactionId !== null && strlen($merchantTransactionId) > 50) {
            throw new UnsupportedBlueSnapPayload('BlueSnap Hosted Payment Page option [merchant_transaction_id] may not exceed 50 characters.');
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnsupportedBlueSnapPayload('BlueSnap Hosted Payment Page option [email] must be a valid email address.');
        }

        $merchantId = $this->client->configuration->merchantId;

        if ($merchantId === null) {
            throw new InvalidBlueSnapConfiguration('BlueSnap merchant ID is required for Hosted Payment Page checkout.');
        }

        $enc = $returnUrl === null ? null : $this->encryptReturnUrl($returnUrl);

        return $this->client->hostedPageUrl()->build(new HostedPageRequest(
            planId: $planId,
            merchantTransactionId: $merchantTransactionId,
            email: $email,
            enc: $enc,
            quantity: $quantity,
        ));
    }

    private function encryptReturnUrl(string $returnUrl): string
    {
        return $this->exceptions->execute(function () use ($returnUrl): string {
            $response = $this->client->paramEncryption()->encrypt([
                'thankyou.backtosellerurl' => $returnUrl,
            ]);

            if (preg_match('/<encrypted-token(?:\s[^>]*)?>(.*?)<\/encrypted-token>/s', $response->body, $matches) !== 1) {
                throw new ProviderRequestFailed('BlueSnap did not return a parameter-encryption token.');
            }

            $token = rawurldecode(html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));

            if ($token === '') {
                throw new ProviderRequestFailed('BlueSnap returned an empty parameter-encryption token.');
            }

            return $token;
        });
    }

    /** @param array<string, mixed> $options */
    private function assertKnownOptions(array $options): void
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ($unknown !== []) {
            throw new UnsupportedBlueSnapPayload(sprintf(
                'Unsupported BlueSnap Hosted Payment Page option [%s].',
                implode(', ', $unknown),
            ));
        }
    }

    /** @param array<string, mixed> $options */
    private function requiredPositiveInteger(array $options, string $key): int
    {
        $integer = $this->positiveInteger($options, $key);

        if ($integer === null) {
            throw new UnsupportedBlueSnapPayload("BlueSnap Hosted Payment Page option [{$key}] must be a positive integer.");
        }

        return $integer;
    }

    /** @param array<string, mixed> $options */
    private function positiveInteger(array $options, string $key): ?int
    {
        $value = $options[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($integer)) {
            throw new UnsupportedBlueSnapPayload("BlueSnap Hosted Payment Page option [{$key}] must be a positive integer.");
        }

        return $integer;
    }

    /** @param array<string, mixed> $options */
    private function optionalString(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new UnsupportedBlueSnapPayload("BlueSnap Hosted Payment Page option [{$key}] must be a non-empty string.");
        }

        return $value;
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true);
    }
}
