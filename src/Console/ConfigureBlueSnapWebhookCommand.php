<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBillingBlueSnap\Console;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBillingBlueSnap\BlueSnapDriver;
use Illuminate\Console\Command;

final class ConfigureBlueSnapWebhookCommand extends Command
{
    /** @var string */
    protected $signature = 'billing:bluesnap:webhook {url : Public HTTPS billing webhook URL} {--disable=* : BlueSnap notification flags to disable}';

    /** @var string */
    protected $description = 'Configure the BlueSnap webhook destination and notification types';

    private const array FLAGS = [
        'sendAuthOnly', 'sendCancellation', 'sendCancelOnRenewal', 'sendCharge', 'sendChargeback',
        'sendChargebackStatusChanged', 'sendContractChange', 'sendCancellationRefund', 'sendSubscriptionReminder',
        'sendDecline', 'sendRefund', 'sendRecurring', 'sendCcFailure', 'sendSubCcFailure', 'sendPaymentUpdate',
        'sendPaymentUpdateFailure', 'sendAccountUpdater', 'sendFraudDecline', 'sendChargePending', 'sendShopperDeleted',
    ];

    public function handle(BillingManager $billing): int
    {
        $url = $this->argument('url');

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
            $this->components->error('The BlueSnap webhook URL must be a valid HTTPS URL.');

            return self::INVALID;
        }
        $driver = $billing->driver('bluesnap');

        if (! $driver instanceof BlueSnapDriver) {
            $this->components->error('The registered [bluesnap] billing driver is invalid.');

            return self::FAILURE;
        }
        $disabled = $this->option('disable');
        $disabled = is_array($disabled) ? array_values(array_filter($disabled, 'is_string')) : [];
        $destination = ['ipnUrl' => $url];

        foreach (self::FLAGS as $flag) {
            $destination[$flag] = ! in_array($flag, $disabled, true);
        }
        $driver->webhooks()->configure([
            'ipnDestinations' => [$destination],
            'ensureNotificationReceipt' => false,
            'receiveAffiliateNotifications' => false,
        ]);
        $this->components->info('BlueSnap webhook configuration updated.');

        return self::SUCCESS;
    }
}
