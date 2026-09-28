<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Mappers;

use Andriichuk\BlueSnap\Exception\ApiException;
use Andriichuk\BlueSnap\Exception\ConflictException;
use Andriichuk\BlueSnap\Exception\NotFoundException;
use Andriichuk\BlueSnap\Exception\RateLimitException;
use Andriichuk\BlueSnap\Exception\TransportException;
use Andriichuk\BlueSnap\Exception\ValidationException;
use Andriichuk\LaravelBilling\Exceptions\BillingConflict;
use Andriichuk\LaravelBilling\Exceptions\BillingException;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\Exceptions\PaymentDeclined;
use Andriichuk\LaravelBilling\Exceptions\ProviderRequestFailed;
use Andriichuk\LaravelBilling\Exceptions\RetryableProviderOperation;
use Throwable;

final class ExceptionMapper
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (BillingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->map($exception);
        }
    }

    public function map(Throwable $exception): BillingException
    {
        if ($exception instanceof ApiException && $this->wasDeclined($exception)) {
            return new PaymentDeclined('BlueSnap declined the payment.', previous: $exception);
        }

        if ($exception instanceof NotFoundException) {
            return new BillingResourceNotFound('The requested BlueSnap billing resource was not found.', previous: $exception);
        }

        if ($exception instanceof ConflictException) {
            return new BillingConflict('BlueSnap rejected a conflicting or duplicate operation.', previous: $exception);
        }

        if ($exception instanceof RateLimitException || $exception instanceof TransportException) {
            return new RetryableProviderOperation('The BlueSnap operation can be retried.', previous: $exception);
        }

        if ($exception instanceof ApiException && $exception->statusCode >= 500) {
            return new RetryableProviderOperation('BlueSnap is temporarily unable to process the operation.', previous: $exception);
        }

        if ($exception instanceof ValidationException) {
            return new InvalidBillingPayload('BlueSnap rejected the billing payload.', previous: $exception);
        }

        return new ProviderRequestFailed('The BlueSnap provider request failed.', previous: $exception);
    }

    private function wasDeclined(ApiException $exception): bool
    {
        foreach ($exception->errors as $error) {
            $haystack = strtolower(implode(' ', array_filter([
                $error->name,
                is_int($error->code) || is_string($error->code) ? (string) $error->code : null,
                $error->description,
            ], static fn (?string $value): bool => $value !== null)));

            if (str_contains($haystack, 'declin') || str_contains($haystack, 'insufficient')) {
                return true;
            }
        }

        return false;
    }
}
