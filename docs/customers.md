# Customers

Core customers map to BlueSnap vaulted shoppers. `billableType:billableId` is sent as `merchantShopperId`, while names are split into `firstName` and `lastName`.

The portable customer contract supports create, retrieve, and update. BlueSnap deletion is available as an explicit extension:

```php
$driver->customers()->deleteCustomer($customerReference);
```

Payment details must be captured with Hosted Payment Fields or supplied as an explicit opaque BlueSnap provider object. Raw PAN, CVV, and bank account numbers are rejected. Provider data returned to the core is recursively sanitized.

To update the vaulted shopper's payment method:

```php
$driver->updatePaymentMethod($customer, $hostedFieldsToken->toPaymentMethodReference());
```
