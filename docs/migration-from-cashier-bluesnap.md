# Migrating from cashier-bluesnap

This driver does not ship its own `Billable` trait, models, migrations, webhook ledger, queue job, or generic events. Those responsibilities now belong to `andriichuk/laravel-billing`.

1. Install and migrate the core package.
2. Replace the Cashier-specific billable trait with the core `Billable` concern.
3. Configure `billing.default` and `billing.drivers.bluesnap`.
4. Preserve existing BlueSnap vaulted shopper, subscription, and transaction IDs in the corresponding core provider-ID columns.
5. Replace raw string payment tokens with `HostedFieldsToken` or `VaultedShopperReference`.
6. Point BlueSnap to `/billing/webhooks/bluesnap`; do not keep the old package webhook route active.
7. Run targeted dry-run reconciliation before enabling writes:

```bash
php artisan billing:reconcile --driver=bluesnap --model=subscription --id=123 --dry-run
```

Reconciliation requires a model and provider ID because BlueSnap does not expose a single complete feed for every resource type. The driver follows the subscription or transaction back to its vaulted shopper and uses `merchantShopperId` to identify the billable model.
