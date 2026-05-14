<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$config = require __DIR__ . '/stripe.conf.php';
foreach ($config as $kv => $value) {
    if ($value === '') {
        continue;
    }

    putenv($kv . '=' . $value);
}

[$stripe, $sync] = stripe_and_hlquery_services();
$sync->ensureCollections();

echo "Creating a minimal checkout session via StripeService...\n";

$session = $stripe->createCheckoutSession(
    [
        [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'hlquery sponsorship',
                ],
                'unit_amount' => 5000,
            ],
            'quantity' => 1,
        ],
    ],
    'https://example.com/success',
    'https://example.com/cancel',
    ['source' => 'php-example']
);

$sync->storeCheckoutSession($session);

echo "Session id: " . ($session['id'] ?? 'unknown') . "\n";
echo "Session url: " . ($session['url'] ?? 'missing url') . "\n";

echo "Listing invoices via StripeService...\n";
$invoices = $stripe->listInvoices(['limit' => 3]);
foreach (($invoices['data'] ?? []) as $invoice) {
    echo sprintf(
        "- invoice %s status=%s amount=%d %s\n",
        $invoice['id'] ?? '<missing>',
        $invoice['status'] ?? 'unknown',
        $invoice['amount_due'] ?? 0,
        $invoice['currency'] ?? ''
    );
}
