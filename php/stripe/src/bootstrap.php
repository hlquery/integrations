<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__, 4) . '/api/php/lib/autoload.php';

use Hlquery\Client;
use Hlquery\Integrations\Stripe\HlqueryStripeSync;
use Hlquery\Integrations\Stripe\StripeService;

/**
 * @return array{0: StripeService, 1: HlqueryStripeSync}
 */
function stripe_and_hlquery_services(): array
{
    // Build the StripeService with both API key and webhook secret.
    $stripe = new StripeService(
        (string)getenv('STRIPE_SECRET_KEY'),
        (string)getenv('STRIPE_WEBHOOK_SECRET')
    );

    $hlqueryUrl = (string)(getenv('HLQUERY_URL') ?: 'http://127.0.0.1:9200');
    $hlqueryToken = (string)(getenv('HLQUERY_TOKEN') ?: '');
    $hlqueryAuthMethod = (string)(getenv('HLQUERY_AUTH_METHOD') ?: 'bearer');

    $options = [];
    if ($hlqueryToken !== '') {
        $options['token'] = $hlqueryToken;
        $options['auth_method'] = $hlqueryAuthMethod;
    }

    $hlquery = new Client($hlqueryUrl, $options);
    $sync = new HlqueryStripeSync(
        $hlquery,
        (string)(getenv('HLQUERY_STRIPE_EVENTS_COLLECTION') ?: 'stripe_events'),
        (string)(getenv('HLQUERY_STRIPE_PAYMENTS_COLLECTION') ?: 'stripe_payments')
    );

    return [$stripe, $sync];
}
