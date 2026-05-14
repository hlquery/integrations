<?php
declare(strict_types=1);

return [
    'STRIPE_SECRET_KEY' => 'sk_test_your_secret_key',
    'STRIPE_WEBHOOK_SECRET' => 'whsec_your_webhook_secret',
    'HLQUERY_URL' => 'http://127.0.0.1:9200',
    'HLQUERY_TOKEN' => '',
    'HLQUERY_AUTH_METHOD' => 'bearer',
    'HLQUERY_STRIPE_EVENTS_COLLECTION' => 'stripe_events',
    'HLQUERY_STRIPE_PAYMENTS_COLLECTION' => 'stripe_payments',
];
