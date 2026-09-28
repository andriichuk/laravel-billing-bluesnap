# Configuration

Publish `billing-bluesnap.php`, select `bluesnap` in the core billing configuration, and set:

```dotenv
BILLING_DRIVER=bluesnap
BLUESNAP_USERNAME=
BLUESNAP_PASSWORD=
BLUESNAP_ENVIRONMENT=sandbox
BLUESNAP_API_VERSION=3.0
BLUESNAP_CURRENCY=USD
BLUESNAP_WEBHOOK_SECRET=
BLUESNAP_WEBHOOK_TIMESTAMP_TOLERANCE=300
BLUESNAP_WEBHOOK_VERIFY_SIGNATURE=true
BLUESNAP_WEBHOOK_VERIFY_IP=false
```

Supported environments are `sandbox` and `production`. Currency must be a three-letter ISO 4217 code. Signature verification may only be disabled when Laravel is running in `local` or `testing`; production fails closed.

The SDK uses PSR-18 and PSR-17. Bind one `ClientInterface`, `RequestFactoryInterface`, and `StreamFactoryInterface` in the application container. This driver deliberately does not impose an HTTP transport.

Configuration validation is lazy. Invalid or missing credentials fail with `InvalidBlueSnapConfiguration` when the driver is first resolved, not during package discovery or unrelated console commands.
