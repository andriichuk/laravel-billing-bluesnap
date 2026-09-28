# Hosted Payment Pages

The BlueSnap driver implements the core `SupportsHostedCheckout` contract. It builds a BlueSnap Hosted Payment Page URL for a subscription plan; the checkout page itself is not created by an API call.

## Prerequisites

Before using this feature:

1. Define a **Data Protection Key** in the BlueSnap merchant console. The key is a merchant-level setting and is never placed in this package's configuration. BlueSnap returns `ENCRYPTION_PASSWORD_REQUIRED` if it is missing.
2. Add the application server's outbound IP address to BlueSnap's API IP allowlist. A `401` response means the API credentials are invalid; a `403` response means the calling IP is not allowlisted. The driver preserves that distinction in its exception messages.

The return URL is protected by BlueSnap's parameter-encryption endpoint. That endpoint accepts **XML only** with `Content-Type: application/xml`; JSON receives HTTP `415`. The driver handles the XML request and response internally.

## Options

Pass these keys to `BlueSnapDriver::hostedCheckoutUrl()` or `BlueSnapDriver::hostedPage()->checkoutUrl()`:

| Key | Required | Type | Description |
| --- | --- | --- | --- |
| `plan_id` | yes | positive integer | Environment-specific BlueSnap subscription plan ID. |
| `return_url` | no | HTTP/HTTPS URL | Encrypted as `thankyou.backtosellerurl` and included through the `enc` parameter. |
| `merchant_transaction_id` | no | non-empty string, max 50 characters | Application reference used to correlate the resulting subscription and webhooks. |
| `email` | no | valid email address | Prefills the shopper email. |
| `quantity` | no | positive integer | Emits `plan{planId}={quantity}` instead of the bare plan key. |

Unknown keys and invalid values throw `UnsupportedBlueSnapPayload`. If `return_url` is omitted, the driver performs no parameter-encryption request and omits `enc`.

Plan IDs belong to a specific BlueSnap environment. Keep sandbox and production values in application configuration rather than hardcoding a single shared ID.

## Example

```php
use Andriichuk\LaravelBilling\Billing;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;

$driver = Billing::driver('bluesnap');
assert($driver instanceof BlueSnapDriver);

$url = $driver->hostedCheckoutUrl([
    'plan_id' => (int) config('billing.plans.pro.bluesnap'),
    'return_url' => route('billing.bluesnap.return'),
    'merchant_transaction_id' => 'subscription-'.$subscription->getKey(),
    'email' => $user->email,
    'quantity' => 1,
]);

return redirect()->away($url);
```

The SDK uses `https://sandbox.bluesnap.com` for sandbox checkout and `https://checkout.bluesnap.com` for production. Override `BLUESNAP_CHECKOUT_HOST` only when BlueSnap assigns a different HTTPS checkout origin.

> The production origin is taken from BlueSnap's published examples and has not been exercised against a live
> production account. Confirm the checkout origin issued to your account and set `BLUESNAP_CHECKOUT_HOST`
> explicitly before going live.

See BlueSnap's [Encrypt Parameters documentation](https://developers.bluesnap.com/v8976-Tools/reference/encrypt-parameters) for the merchant-console prerequisite and wire format.
