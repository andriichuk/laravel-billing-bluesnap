# Reconciliation

An argument-free BlueSnap reconciliation is a streaming subscription sweep:

```bash
php artisan billing:reconcile --driver=bluesnap
php artisan billing:reconcile --driver=bluesnap --dry-run
php artisan billing:reconcile --driver=bluesnap --cursor=343435 --page-size=250
```

The driver requests full subscription descriptions from BlueSnap with `pagesize`, `after`, and `gettotal`, yields each normalized subscription as it arrives, and advances `after` to the final subscription ID on the page. Page size must be between 1 and BlueSnap's maximum of 500. The cursor is a BlueSnap subscription ID and is exclusive.

BlueSnap's subscription-list API has no updated-since filter. Passing the core `--since` option therefore does not narrow a BlueSnap sweep: the driver performs the documented full scan. Use `--cursor` to resume from a known subscription ID.

Targeted reconciliation remains available for all supported resource types:

```bash
php artisan billing:reconcile --driver=bluesnap --model=subscription --id=343434
php artisan billing:reconcile --driver=bluesnap --model=customer --id=7895455
php artisan billing:reconcile --driver=bluesnap --model=transaction --id=1011671985
```

The driver returns provider identity and normalized data only. The core resolves ownership from its existing provider-ID rows. For resources first discovered during a sweep, the application must bind the core `ResolvesReconciliationBillables` contract. Hosted Payment Page applications commonly map BlueSnap's `merchantTransactionId` to an account there; that value is an application convention and is not interpreted by this driver.

Repaired subscriptions emit the core `SubscriptionUpdated` event only when the local projection changed. The event carries the persisted subscription and provider resource identity so listeners can re-read authoritative state under a lock. `--force` replays the event for support workflows, and `--dry-run` never writes or dispatches.
