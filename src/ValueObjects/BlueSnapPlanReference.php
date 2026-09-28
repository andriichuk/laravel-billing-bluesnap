<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\ValueObjects;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;

final readonly class BlueSnapPlanReference
{
    public string $id;

    public function __construct(string|int $id)
    {
        $id = trim((string) $id);
        if ($id === '') {
            throw InvalidBillingPayload::because('A BlueSnap plan ID must not be empty.');
        }

        $this->id = $id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
