<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Configuration;
use Andriichuk\BlueSnap\Environment;
use Andriichuk\LaravelBillingBlueSnap\Exceptions\InvalidBlueSnapConfiguration;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapCustomerGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapReconciler;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapSubscriptionGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapTransactionGateway;
use Andriichuk\LaravelBillingBlueSnap\Gateways\BlueSnapWebhookGateway;
use Andriichuk\LaravelBillingBlueSnap\HostedFields\PaymentFieldsTokenService;
use Andriichuk\LaravelBillingBlueSnap\Mappers\CustomerMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\ExceptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\PaymentSourceMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\SubscriptionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\TransactionMapper;
use Andriichuk\LaravelBillingBlueSnap\Mappers\WebhookMapper;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapEventNormalizer;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapPayloadSanitizer;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapSignatureVerifier;
use Andriichuk\LaravelBillingBlueSnap\Webhooks\BlueSnapWebhookParser;
use Illuminate\Contracts\Foundation\Application;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class BlueSnapManager
{
    private const array SANDBOX_IPS = ['141.226.140.200', '141.226.141.200', '141.226.142.200', '141.226.143.200'];

    private const array PRODUCTION_IPS = ['141.226.140.100', '141.226.141.100', '141.226.142.100', '141.226.143.100'];

    public function __construct(private Application $app) {}

    /** @param array<string, mixed> $config */
    public function driver(array $config): BlueSnapDriver
    {
        $client = $this->client($config);
        $currency = is_string($config['currency'] ?? null) ? strtoupper($config['currency']) : 'USD';

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidBlueSnapConfiguration('BlueSnap currency must be a three-letter ISO 4217 code.');
        }
        $sanitizer = new BlueSnapPayloadSanitizer;
        $paymentSources = new PaymentSourceMapper;
        $exceptions = new ExceptionMapper;
        $customerMapper = new CustomerMapper($sanitizer, $paymentSources);
        $subscriptionMapper = new SubscriptionMapper($sanitizer, $paymentSources, $currency);
        $transactionMapper = new TransactionMapper($sanitizer);
        $customers = new BlueSnapCustomerGateway($client, $customerMapper, $exceptions);
        $subscriptions = new BlueSnapSubscriptionGateway($client, $subscriptionMapper, $exceptions);
        $transactions = new BlueSnapTransactionGateway($client, $transactionMapper, $exceptions);
        $webhookConfig = is_array($config['webhook'] ?? null) ? $config['webhook'] : [];
        $verifySignature = (bool) ($webhookConfig['verify_signature'] ?? true);

        if (! $verifySignature && ! $this->app->environment(['local', 'testing'])) {
            throw new InvalidBlueSnapConfiguration('BlueSnap webhook signature verification may only be disabled in local or testing environments.');
        }
        $environment = $this->environment($config['environment'] ?? 'sandbox');
        $allowedIps = $webhookConfig['allowed_ips'] ?? [];

        if (! is_array($allowedIps) || $allowedIps === []) {
            $allowedIps = $environment === Environment::Sandbox ? self::SANDBOX_IPS : self::PRODUCTION_IPS;
        }
        $allowedIps = array_values(array_filter($allowedIps, 'is_string'));
        $secret = is_string($webhookConfig['secret'] ?? null) ? $webhookConfig['secret'] : null;
        $verifier = new BlueSnapSignatureVerifier(
            secret: $secret,
            timestampTolerance: (int) ($webhookConfig['timestamp_tolerance'] ?? 300),
            verifySignature: $verifySignature,
            verifyIp: (bool) ($webhookConfig['verify_ip'] ?? false),
            allowedIps: $allowedIps,
        );
        $webhookMapper = new WebhookMapper($sanitizer, new BlueSnapEventNormalizer);
        $webhooks = new BlueSnapWebhookGateway($client, $verifier, new BlueSnapWebhookParser($webhookMapper), $exceptions);
        $reconciler = new BlueSnapReconciler($customers, $subscriptions, $transactions);

        return new BlueSnapDriver($customers, $subscriptions, $transactions, $webhooks, $reconciler, new PaymentFieldsTokenService($client, $exceptions));
    }

    /** @param array<string, mixed> $config */
    private function client(array $config): BlueSnapClient
    {
        $username = is_string($config['username'] ?? null) ? trim($config['username']) : '';
        $password = is_string($config['password'] ?? null) ? $config['password'] : '';

        if ($username === '' || $password === '') {
            throw new InvalidBlueSnapConfiguration('BlueSnap username and password are required when resolving the billing driver.');
        }

        foreach ([ClientInterface::class, RequestFactoryInterface::class, StreamFactoryInterface::class] as $contract) {
            if (! $this->app->bound($contract)) {
                throw new InvalidBlueSnapConfiguration("A PSR implementation for [{$contract}] must be bound in the container.");
            }
        }
        $apiVersion = is_string($config['api_version'] ?? null) ? $config['api_version'] : '3.0';

        return new BlueSnapClient(
            new Configuration($username, $password, $this->environment($config['environment'] ?? 'sandbox'), $apiVersion, 'andriichuk/laravel-billing-bluesnap'),
            $this->app->make(ClientInterface::class),
            $this->app->make(RequestFactoryInterface::class),
            $this->app->make(StreamFactoryInterface::class),
        );
    }

    private function environment(mixed $environment): Environment
    {
        return match ($environment) {
            'sandbox', Environment::Sandbox, Environment::Sandbox->value => Environment::Sandbox,
            'production', Environment::Production, Environment::Production->value => Environment::Production,
            default => throw new InvalidBlueSnapConfiguration('BlueSnap environment must be sandbox or production.'),
        };
    }
}
