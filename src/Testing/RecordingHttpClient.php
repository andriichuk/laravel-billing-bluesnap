<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Testing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses;

    private ?ResponseInterface $repeatResponse = null;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public static function repeating(ResponseInterface $response): self
    {
        $client = new self;
        $client->repeatResponse = $response;

        return $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? $this->repeatResponse ?? throw new RuntimeException('No fake BlueSnap response is queued.');
    }

    public function lastRequest(): RequestInterface
    {
        if ($this->requests === []) {
            throw new RuntimeException('No BlueSnap request has been recorded.');
        }

        return $this->requests[count($this->requests) - 1];
    }
}
