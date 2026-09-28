<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Gateways;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\LaravelBilling\Contracts\ManagesTransactions;
use Andriichuk\LaravelBilling\Contracts\SupportsRefunds;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\TransactionMapper;

final readonly class BlueSnapTransactionGateway implements ManagesTransactions, SupportsRefunds
{
    public function __construct(private BlueSnapClient $client, private TransactionMapper $mapper, private ExceptionMapper $exceptions) {}

    public function retrieveTransaction(TransactionReference $transaction): TransactionData
    {
        return $this->exceptions->execute(fn (): TransactionData => $this->mapper->fromProvider(
            $this->client->transactions()->retrieve($transaction->id)->json(),
            $transaction->id,
        ));
    }

    public function refund(TransactionReference $transaction, ?Money $amount = null, array $providerOptions = []): TransactionData
    {
        return $this->exceptions->execute(function () use ($transaction, $amount, $providerOptions): TransactionData {
            $idempotencyKey = is_string($providerOptions['idempotencyKey'] ?? null) ? $providerOptions['idempotencyKey'] : null;
            unset($providerOptions['idempotencyKey'], $providerOptions['transactionId']);
            if ($amount !== null) {
                $providerOptions['amount'] = $amount->amount;
                $providerOptions['currency'] = $amount->currency;
            }
            $response = $this->client->transactions()->refund($transaction->id, $providerOptions, $idempotencyKey);

            return $this->mapper->fromProvider($response->json(), $transaction->id);
        });
    }
}
