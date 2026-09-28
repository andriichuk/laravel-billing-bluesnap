<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Webhooks;

final class BlueSnapPayloadSanitizer
{
    private const array SENSITIVE_KEYS = [
        'accountnumber', 'authorization', 'authkey', 'cardnumber', 'ccnumber', 'cvv', 'cvv2',
        'password', 'pftoken', 'securitycode', 'secret', 'signature',
    ];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function sanitize(array $payload): array
    {
        $sanitized = [];
        foreach ($payload as $key => $value) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key) ?? '');
            if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
                continue;
            }
            $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }
}
