<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\LaravelBilling\Contracts\ProcessesWebhooks;
use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapSignatureVerifier;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapWebhookParser;

final readonly class BlueSnapWebhookGateway implements ProcessesWebhooks
{
    public function __construct(
        private BlueSnapClient $client,
        private BlueSnapSignatureVerifier $verifier,
        private BlueSnapWebhookParser $parser,
        private ExceptionMapper $exceptions,
    ) {}

    public function verifyWebhook(WebhookRequest $request): void
    {
        $this->verifier->verify($request);
    }

    public function parseWebhook(WebhookRequest $request): ParsedWebhook
    {
        return $this->parser->parse($request);
    }

    /**
     * @return array<mixed>
     */
    public function configuration(): array
    {
        return $this->exceptions->execute(fn (): array => $this->client->webhookConfigurations()->retrieve()->json());
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<mixed>
     */
    public function configure(array $configuration): array
    {
        return $this->exceptions->execute(fn (): array => $this->client->webhookConfigurations()->update($configuration)->json());
    }

    public function deleteConfiguration(): void
    {
        $this->exceptions->execute(fn () => $this->client->webhookConfigurations()->delete());
    }
}
