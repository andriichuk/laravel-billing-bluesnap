<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Tests\Integration;

use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBillingBlueSnap\Testing\BlueSnapDriverFactory;
use Andriichuk\LaravelBillingBlueSnap\Testing\RecordingHttpClient;
use Andriichuk\LaravelBillingBlueSnap\Tests\CreatesDriver;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Small]
final class ReconciliationTest extends TestCase
{
    use CreatesDriver;

    #[Test]
    public function subscription_sweeps_stream_every_page_and_stop_at_the_last_page(): void
    {
        $http = new RecordingHttpClient(
            $this->jsonResponse([
                'totalResults' => 3,
                'lastPage' => false,
                'subscriptions' => [
                    $this->subscriptionPayload(300, 10),
                    $this->subscriptionPayload(299, 11),
                ],
            ]),
            $this->jsonResponse([
                'totalResults' => 3,
                'lastPage' => true,
                'subscriptions' => [$this->subscriptionPayload(298, 12)],
            ]),
        );
        $driver = BlueSnapDriverFactory::create($this->application(), $http);

        $results = iterator_to_array($driver->reconcile(new ReconciliationRequest(cursor: '301', pageSize: 2)));

        self::assertSame(['300', '299', '298'], array_map(
            static fn ($result): string => $result->resource->reference->id,
            $results,
        ));
        self::assertCount(2, $http->requests);
        parse_str($http->requests[0]->getUri()->getQuery(), $firstQuery);
        parse_str($http->requests[1]->getUri()->getQuery(), $secondQuery);
        self::assertSame(['pagesize' => '2', 'gettotal' => 'true', 'fulldescription' => 'true', 'after' => '301'], $firstQuery);
        self::assertSame('299', $secondQuery['after']);
    }

    #[Test]
    public function a_targeted_subscription_without_a_merchant_shopper_id_is_returned_without_customer_lookup(): void
    {
        $http = new RecordingHttpClient($this->jsonResponse($this->subscriptionPayload(300, 10)));
        $driver = BlueSnapDriverFactory::create($this->application(), $http);

        $results = iterator_to_array($driver->reconcile(new ReconciliationRequest('subscription', '300')));

        self::assertCount(1, $results);
        self::assertInstanceOf(SubscriptionData::class, $results[0]->resource);
        self::assertSame('10', $results[0]->resource->customerId);
        self::assertCount(1, $http->requests);
        self::assertStringEndsWith('/recurring/subscriptions/300', $http->requests[0]->getUri()->getPath());
    }

    #[Test]
    public function a_self_referencing_vaulted_shopper_terminates_without_recursive_refetches(): void
    {
        $http = RecordingHttpClient::repeating($this->jsonResponse([
            'vaultedShopperId' => 42,
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
        ]));
        $driver = BlueSnapDriverFactory::create($this->application(), $http);

        $results = iterator_to_array($driver->reconcile(new ReconciliationRequest('customer', '42')));

        self::assertCount(1, $results);
        self::assertSame('42', $results[0]->resource->reference->id);
        self::assertCount(1, $http->requests);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonResponse(array $payload): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(int $subscriptionId, int $shopperId): array
    {
        return [
            'subscriptionId' => $subscriptionId,
            'vaultedShopperId' => $shopperId,
            'planId' => 99,
            'status' => 'ACTIVE',
            'quantity' => 1,
            'currency' => 'USD',
            'recurringChargeAmount' => '15.00',
        ];
    }
}
