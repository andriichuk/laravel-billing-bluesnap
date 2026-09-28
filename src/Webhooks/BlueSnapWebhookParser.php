<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Webhooks;

use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBillingBlueSnap\Mappers\WebhookMapper;

final readonly class BlueSnapWebhookParser
{
    public function __construct(private WebhookMapper $mapper) {}

    public function parse(WebhookRequest $request): ParsedWebhook
    {
        if (strtoupper($request->method) !== 'POST' || $request->rawBody === '') {
            throw InvalidBillingPayload::because('A BlueSnap webhook must be a non-empty POST request.');
        }
        $payload = [];
        parse_str($request->rawBody, $payload);
        if ($payload === []) {
            throw InvalidBillingPayload::because('The BlueSnap webhook form payload is empty or malformed.');
        }

        /** @var array<string, mixed> $payload */
        return $this->mapper->fromPayload($payload, $request->rawBody);
    }
}
