# Webhooks

The shared core endpoint is:

```text
GET|POST /billing/webhooks/bluesnap
```

POST bodies are parsed as `application/x-www-form-urlencoded`, sanitized, and normalized into core billing events. The original body is never reconstructed before verification.

The verifier requires `Bls-Ipn-Timestamp` and `Bls-Signature`, performs a case-insensitive header lookup, calculates HMAC-SHA256 over `timestamp + exact raw body`, and compares lowercase hexadecimal signatures in constant time. The timestamp must match `YYYY-MM-DD HH:MM:SS.zzz`; the default replay window is 300 seconds.

BlueSnap's dashboard probe is an empty GET. The optional core `HandlesWebhookProbes` contract lets this driver return the required empty 200 response through the shared route.

Configure the destination with:

```bash
php artisan billing:bluesnap:webhook https://example.com/billing/webhooks/bluesnap
```

Optional IP verification uses BlueSnap's documented sandbox or production address list as defense in depth. It never replaces HMAC verification. See BlueSnap's [webhook setup](https://support.bluesnap.com/docs/ipn-setup/edit) and [webhook API](https://developers.bluesnap.com/reference/webhooks-ipns).
