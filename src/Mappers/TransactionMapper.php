<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;
use DateTimeImmutable;
use Throwable;

final readonly class TransactionMapper
{
    public function __construct(private BlueSnapPayloadSanitizer $sanitizer) {}

    /** @param array<string, mixed> $payload */
    public function fromProvider(array $payload, ?string $fallbackId = null): TransactionData
    {
        $id = $this->scalarString($payload['transactionId'] ?? $payload['referenceNumber'] ?? $fallbackId);

        if ($id === null) {
            throw InvalidBillingPayload::because('BlueSnap did not return a transaction ID.');
        }
        $type = $this->scalarString($payload['cardTransactionType'] ?? $payload['transactionType'] ?? null);
        $processing = $payload['processingInfo'] ?? null;
        $providerStatus = is_array($processing) ? $processing['processingStatus'] ?? null : $payload['status'] ?? null;
        $status = $this->status($this->scalarString($providerStatus), $type);
        $currency = $payload['currency'] ?? $payload['invoiceChargeCurrency'] ?? null;
        $amount = $payload['amount'] ?? $payload['invoiceChargeAmount'] ?? null;
        $billedAt = $this->date($payload['transactionDate'] ?? null);

        if ($billedAt === null && is_string($payload['transactionApprovalDate'] ?? null)) {
            $billedAt = $this->date($payload['transactionApprovalDate'].' '.(is_string($payload['transactionApprovalTime'] ?? null) ? $payload['transactionApprovalTime'] : '00:00:00'));
        }

        return new TransactionData(
            reference: new TransactionReference($id),
            status: $status,
            subscriptionId: $this->scalarString($payload['subscriptionId'] ?? null),
            type: $type,
            amount: $this->money($amount, $currency),
            billedAt: $billedAt,
            providerData: $this->sanitizer->sanitize($payload),
        );
    }

    public function status(?string $status, ?string $type = null): TransactionStatus
    {
        $type = strtoupper((string) $type);

        if (str_contains($type, 'CHARGEBACK') || str_contains($type, 'DISPUTE')) {
            return TransactionStatus::Disputed;
        }

        if (str_contains($type, 'REFUND')) {
            return TransactionStatus::Refunded;
        }

        return match (strtoupper((string) $status)) {
            'SUCCESS', 'APPROVED', 'COMPLETED' => TransactionStatus::Succeeded,
            'PENDING', 'PENDING_MERCHANT_REVIEW', 'PENDING_REVIEW' => TransactionStatus::Pending,
            'DECLINED', 'FAIL', 'FAILED', 'REJECTED' => TransactionStatus::Failed,
            'REFUNDED' => TransactionStatus::Refunded,
            'CHARGEBACK', 'DISPUTED' => TransactionStatus::Disputed,
            default => TransactionStatus::Unknown,
        };
    }

    private function money(mixed $amount, mixed $currency): ?Money
    {
        if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            return null;
        }

        if (is_int($amount) || is_string($amount)) {
            return new Money($amount, $currency);
        }

        if (is_float($amount) && is_string($encoded = json_encode($amount, JSON_PRESERVE_ZERO_FRACTION))) {
            return new Money($encoded, $currency);
        }

        return null;
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function scalarString(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
