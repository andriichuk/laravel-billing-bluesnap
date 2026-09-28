# Configuration

Publish `billing-bluesnap.php`, select `bluesnap` in the core billing configuration, and set:

```dotenv
BILLING_DRIVER=bluesnap
BLUESNAP_USERNAME=
BLUESNAP_PASSWORD=
BLUESNAP_MERCHANT_ID=
BLUESNAP_ENVIRONMENT=sandbox
BLUESNAP_CHECKOUT_HOST=
BLUESNAP_API_VERSION=3.0
BLUESNAP_CURRENCY=USD
BLUESNAP_WEBHOOK_SECRET=
BLUESNAP_WEBHOOK_TIMESTAMP_TOLERANCE=300
BLUESNAP_WEBHOOK_VERIFY_SIGNATURE=true
BLUESNAP_WEBHOOK_VERIFY_IP=false
```

Supported environments are `sandbox` and `production`. The merchant ID is optional for API-only integrations and required when `hostedCheckoutUrl()` is called because Hosted Payment Page URLs include it; it may be given as an integer or a numeric string, and an empty value counts as unconfigured. Currency must be a three-letter ISO 4217 code. Signature verification may only be disabled when Laravel is running in `local` or `testing`; production fails closed.

`BLUESNAP_CHECKOUT_HOST` is optional. The SDK defaults to `https://sandbox.bluesnap.com` in sandbox and `https://checkout.bluesnap.com` in production; the production default is unverified against a live account, so confirm it before going live. Set it only when BlueSnap gives your account a different checkout origin; it must be an HTTPS origin without a path.

The SDK uses PSR-18 and PSR-17. Bind one `ClientInterface`, `RequestFactoryInterface`, and `StreamFactoryInterface` in the application container. This driver deliberately does not impose an HTTP transport.

Configuration validation is lazy. Invalid or missing credentials fail with `InvalidBlueSnapConfiguration` when the driver is first resolved, not during package discovery or unrelated console commands.
