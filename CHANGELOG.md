# Changelog

## 0.4.0 - 2026-09-28

- Add a streaming, cursor- and page-size-aware subscription sweep while retaining targeted customer, subscription, and transaction reconciliation.
- Return provider identity to the core for billable resolution instead of relying on `merchantShopperId`; Hosted Payment Page applications can resolve their own `merchantTransactionId` convention through the core application resolver.
- Remove recursive vaulted-shopper lookup, including the self-referencing shopper path that could issue unbounded API requests.
- Reconciliation repairs now emit the core lifecycle events when projection state changes. Applications with existing listeners will start receiving these intended reconciliation events.
