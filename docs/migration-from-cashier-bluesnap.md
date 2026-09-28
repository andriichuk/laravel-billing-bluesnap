# Migrating from cashier-bluesnap

This driver does not ship its own `Billable` trait, models, migrations, webhook ledger, queue job, or generic events. Those responsibilities now belong to `andriichuk/laravel-billing`.

1. Install and migrate the core package.
2. Replace the Cashier-specific billable trait with the core `Billable` concern.
3. Configure `billing.default` and `billing.drivers.bluesnap`.
4. Preserve existing BlueSnap vaulted shopper, subscription, and transaction IDs in the corresponding core provider-ID columns.
5. Replace raw string payment tokens with `HostedFieldsToken` or `VaultedShopperReference`.
6. Point BlueSnap to `/billing/webhooks/bluesnap`; do not keep the old package webhook route active.
7. Bind the core `ResolvesReconciliationBillables` contract when BlueSnap resources created by Hosted Payment Pages may not have a local provider-ID row yet. The application can map its `merchantTransactionId` convention to an account; the driver deliberately does not interpret that application-owned value.
8. Run a dry-run sweep before enabling writes:

```bash
php artisan billing:reconcile --driver=bluesnap --dry-run
```

The driver pages through BlueSnap subscriptions. Targeted customer, subscription, and transaction reconciliation remains available with `--model` and `--id`. Billable resolution happens in the core from local provider-ID rows and then through the application resolver; the driver never follows `vaultedShopperId` or relies on `merchantShopperId` to infer an application model.
