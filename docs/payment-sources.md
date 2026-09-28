# Payment sources

Core payment methods remain opaque. The package provides two typed BlueSnap references:

```php
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\HostedFieldsToken;
use Andriichuk\LaravelBillingBlueSnap\ValueObjects\VaultedShopperReference;

$hosted = HostedFieldsToken::fromString($pfToken);
$paymentMethod = $hosted->toPaymentMethodReference();

$shopper = new VaultedShopperReference($vaultedShopperId);
$customer = $shopper->toCustomerReference();
```

Hosted Payment Fields flow:

1. Laravel calls `$driver->hostedFields()->createToken()`.
2. The browser initializes BlueSnap Hosted Payment Fields with that token.
3. BlueSnap associates the submitted payment details with the `pfToken`.
4. Laravel wraps it with `HostedFieldsToken`.
5. The subscription request sends `pfToken` as a top-level property.

Tokens are typed with their expected 60-minute expiration. Empty or conflicting payment sources are rejected. This package never accepts raw card numbers or CVVs.

See BlueSnap's [Hosted Payment Fields token reference](https://developers.bluesnap.com/v8976-Tools/reference/create-hosted-payment-fields-token).
