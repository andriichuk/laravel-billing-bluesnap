<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\HostedFields;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\LaravelBilling\Exceptions\ProviderRequestFailed;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;
use DateTimeImmutable;

final readonly class PaymentFieldsTokenService
{
    public function __construct(
        private BlueSnapClient $client,
        private ExceptionMapper $exceptions
    ) {}

    /** @param array<string, scalar|null> $options */
    public function createToken(array $options = []): HostedFieldsToken
    {
        return $this->exceptions->execute(function () use ($options): HostedFieldsToken {
            $response = $this->client->paymentFieldsTokens()->create($options);
            $location = $response->location();
            $path = $location !== null ? parse_url($location, PHP_URL_PATH) : null;
            $token = is_string($path) ? rawurldecode(basename($path)) : '';

            if ($token === '') {
                throw new ProviderRequestFailed('BlueSnap did not return a Hosted Payment Fields token.');
            }

            return new HostedFieldsToken($token, new DateTimeImmutable('+60 minutes'));
        });
    }
}
