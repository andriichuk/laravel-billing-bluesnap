# Laravel Billing BlueSnap

The official BlueSnap driver for [`andriichuk/laravel-billing`](https://github.com/andriichuk/laravel-billing), powered by the framework-agnostic [`andriichuk/bluesnap-php-sdk`](https://github.com/andriichuk/bluesnap-php-sdk).

## Requirements

- PHP 8.5+
- Laravel 13
- A PSR-18 HTTP client and PSR-17 request/stream factories bound in Laravel's container
- BlueSnap API credentials, merchant ID, and a webhook security-header secret

## Installation

```bash
composer require andriichuk/laravel-billing-bluesnap
php artisan vendor:publish --tag=billing-bluesnap-config
```

Select the driver in `config/billing.php`:

```php
'default' => env('BILLING_DRIVER', 'bluesnap'),

'drivers' => [
    'bluesnap' => config('billing-bluesnap'),
],
```

The package does not force an HTTP implementation. Bind your application's PSR-18 client and PSR-17 factories to `Psr\Http\Client\ClientInterface`, `Psr\Http\Message\RequestFactoryInterface`, and `Psr\Http\Message\StreamFactoryInterface`.

Credentials are validated only when the `bluesnap` driver is resolved, so discovery, route listing, and configuration caching remain usable without live secrets.

## Usage

All portable operations use the core contracts. BlueSnap-only features are exposed by `BlueSnapDriver`:

```php
use Andriichuk\LaravelBilling\Billing;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;

$driver = Billing::driver('bluesnap');
assert($driver instanceof BlueSnapDriver);

$token = $driver->hostedFields()->createToken();
$paymentMethod = $token->toPaymentMethodReference();

$checkoutUrl = $driver->hostedCheckoutUrl([
    'plan_id' => 3173219,
    'return_url' => route('billing.bluesnap.return'),
    'merchant_transaction_id' => 'subscription-42',
    'email' => 'ada@example.com',
]);
```

The driver supports vaulted shoppers, subscriptions and trials, Hosted Payment Page checkout, plan and quantity changes, cancellation modes, transactions, refunds, payment-method updates, signed webhooks, and targeted reconciliation. It intentionally does not advertise pausing, usage billing, provider-independent proration, invoices, automatic tax, or metered billing.

See the [configuration](docs/configuration.md), [customers](docs/customers.md), [subscriptions](docs/subscriptions.md), [Hosted Payment Pages](docs/hosted-payment-page.md), [payment sources](docs/payment-sources.md), [webhooks](docs/webhooks.md), and [migration guide](docs/migration-from-cashier-bluesnap.md).

## Quality

```bash
composer check
```

## License

MIT
