# Stripe PHP Integration

Minimal Stripe integration scaffold in PHP with:

- Checkout Session creation endpoint
- Signed webhook verification endpoint
- Full Stripe utility functions endpoint (customers, intents, refunds, subscriptions, invoices)
- Sync to hlquery using the shared API client under `api/php`

## What it does

- Bootstraps Stripe and the hlquery PHP client (`api/php`) via `bootstrap.php`.
- Exposes `public/create-checkout-session.php`, `public/webhook.php`, and `public/stripe-actions.php`.
- Persists Stripe events and checkout payments into hlquery collections (`stripe_events`, `stripe_payments`).
- Demonstrates usage through `example.php`; credentials live in `stripe.conf.php`.
- Shows how to list hlquery collections, clients, and documents that mirror billing data so you can build dashboards or sync workflows.

## Requirements

- PHP 8.1+, `curl` and `json` extensions enabled.
- Composer to install `stripe/stripe-php`.
- Access to an hlquery HTTP API endpoint (default `http://127.0.0.1:9200`); set `HLQUERY_TOKEN`/`HLQUERY_AUTH_METHOD` when auth is required.
- Valid Stripe secret key and webhook signing secret; treat them like other credentials.

## Integrating with hlquery

1. **List what hlquery already has.** Use the PHP client under `api/php/lib/Client.php` to inspect collections, documents, keys, etc. For example:

```php
<?php
use Hlquery\Client;

$client = new Client(getenv('HLQUERY_URL') ?: 'http://127.0.0.1:9200', [
    'token' => getenv('HLQUERY_TOKEN') ?: null,
    'auth_method' => getenv('HLQUERY_AUTH_METHOD') ?: 'bearer',
]);

$collections = $client->listCollections(0, 20);
echo json_encode($collections->getBody(), JSON_PRETTY_PRINT);
```

2. **Write Stripe data back into hlquery.** `src/HlqueryStripeSync.php` demonstrates how to ensure the `stripe_events` and `stripe_payments` collections exist and how to insert documents after each webhook or checkout session. The calls use `documents()->add`/`update`, so the same pattern works anywhere else in your app.

3. **Query clients and invoices stored in hlquery.** Use `$client->search()` or `$client->documents()` to build your dashboard or automation. For instance, fetch recent payments for a customer ID pulled from a Stripe webhook:

```php
$payments = $client->search('stripe_payments', [
    'q' => "customer_id:{$customerId}",
    'query_by' => 'session_id,customer_id',
    'sort_by' => 'created_at:desc',
    'limit' => 5,
]);
```

4. **Hook into Stripe endpoints.** When you call `public/create-checkout-session.php`, `public/webhook.php`, or `public/stripe-actions.php`, `bootstrap.php` already wires up the same hlquery client so every action can re-use the same collections, tokens, and configurations.

## Files

- `composer.json` - dependencies and autoload
- `src/StripeService.php` - Stripe wrapper
- `src/HlqueryStripeSync.php` - stores Stripe events/payments in hlquery
- `src/bootstrap.php` - loads Stripe + `api/php` client
- `public/create-checkout-session.php` - create checkout session
- `public/webhook.php` - verify and handle Stripe events
- `public/stripe-actions.php` - generic endpoint for many Stripe operations
- `.env.example` - required environment variables
- `stripe.conf.php` - template to keep Stripe/hlquery credentials in PHP
- `example.php` - runnable script exercising checkout sessions + invoices via the PHP client
- `LICENSE` - BSD 3-clause license for this integration

## Credentials

1. Copy `stripe.conf.php` to `stripe.local.php` (gitignored) and supply:
   - `STRIPE_SECRET_KEY`: your Stripe secret key (starts with `sk_`).
   - `STRIPE_WEBHOOK_SECRET`: Stripe CLI/portal webhook signing secret (`whsec_...`).
   - Optional hlquery values (`HLQUERY_URL`, `HLQUERY_TOKEN`, `HLQUERY_AUTH_METHOD`, `HLQUERY_STRIPE_*_COLLECTION`).
2. Alternatively export the same keys as environment variables before invoking the scripts.
3. `bootstrap.php` merges both so `create-checkout-session.php`, `webhook.php`, `stripe-actions.php`, and `example.php` can all consume the same credentials.

## Setup

```bash
cd /path/to/stripe/php
composer install
```

Set environment variables:

```bash
export STRIPE_SECRET_KEY=sk_test_...
export STRIPE_WEBHOOK_SECRET=whsec_...
export HLQUERY_URL=http://127.0.0.1:9200
export HLQUERY_TOKEN=
export HLQUERY_AUTH_METHOD=bearer
export HLQUERY_STRIPE_EVENTS_COLLECTION=stripe_events
export HLQUERY_STRIPE_PAYMENTS_COLLECTION=stripe_payments
```

Run local server:

```bash
php -S 127.0.0.1:8080 -t public
```

## Create Checkout Session

Request:

```bash
curl -sS -X POST http://127.0.0.1:8080/create-checkout-session.php \
  -H "Content-Type: application/json" \
  -d '{
    "price_id":"price_123",
    "quantity":1,
    "success_url":"https://example.com/success",
    "cancel_url":"https://example.com/cancel",
    "metadata":{"user_id":"123"}
  }'
```

Response:

```json
{
  "id": "cs_test_...",
  "url": "https://checkout.stripe.com/c/pay/..."
}
```

`create-checkout-session.php` also stores the session in hlquery collection `stripe_payments` using the PHP client from `api/php`.

## Webhook

Use the Stripe CLI in another terminal:

```bash
stripe listen --forward-to localhost:8080/webhook.php
```

Copy the webhook signing secret (`whsec_...`) to `STRIPE_WEBHOOK_SECRET`.

`webhook.php` verifies signatures and stores:

- Event documents in `stripe_events`
- Checkout session payment data in `stripe_payments` for `checkout.session.completed`

## Generic Stripe Functions Endpoint

Use `POST /stripe-actions.php` with:

- `action`: operation name
- `params`: action parameters

Supported actions:

- `create_customer`
- `update_customer`
- `create_payment_intent`
- `retrieve_payment_intent`
- `capture_payment_intent`
- `cancel_payment_intent`
- `create_refund`
- `create_subscription`
- `cancel_subscription`
- `create_billing_portal_session`
- `list_invoices`
- `list_charges`
- `retrieve_checkout_session`
- `expire_checkout_session`

Example:

```bash
curl -sS -X POST http://127.0.0.1:8080/stripe-actions.php \
  -H "Content-Type: application/json" \
  -d '{
    "action":"create_customer",
    "params":{
      "email":"user@example.com",
      "name":"Test User"
    }
  }'
```

## Running example.php

Copy `stripe.conf.php` to `stripe.local.php`, fill in your Stripe keys and optional hlquery credentials, then run:

```bash
php example.php
```
