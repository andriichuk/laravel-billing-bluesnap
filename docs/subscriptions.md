# Subscriptions

The core price ID maps to BlueSnap `planId`. The driver supports quantity, trials, payer/provider options, recurring amount overrides supported by BlueSnap, next-charge dates, and POST idempotency keys.

Use the core `CancellationMode::AtPeriodEnd` or `CancellationMode::Immediately`; the driver never simulates cancellation locally. Plan and quantity changes use the corresponding core extension contracts.

BlueSnap status normalization is centralized:

| BlueSnap | Core |
|---|---|
| `ACTIVE` | `active` (or `trialing` with a future trial end) |
| `CANCELED`, `DELETED` | `canceled` |
| `ON_HOLD`, `SUSPENDED` | `paused` |
| `FINISHED` | `finished` |
| unknown | `unknown` |

BlueSnap-specific renewal and activation are explicit extensions:

```php
$driver->subscriptions()->renewSubscription($subscription);
$driver->subscriptions()->resumeSubscription($subscription);
```

These extensions do not cause the driver to advertise generic subscription pausing.
