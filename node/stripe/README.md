# Stripe Node Integration

This package ports the PHP Stripe integration concepts into Node.js while still syncing Stripe events/checkouts into hlquery via the Node client.

## What it provides

- Express endpoints for `POST /create-checkout-session`, `POST /webhook`, and `POST /stripe-actions`.
- `src/stripeService.js` that wraps the Stripe SDK and exposes the same helpers for sessions, customers, payment intents, refunds, subscriptions, invoices, and charges.
- `src/hlqueryStripeSync.js` that makes sure `stripe_events` and `stripe_payments` exist inside hlquery and keeps them up to date.
- `example.js` that creates a checkout session, syncs it, and lists the latest invoices.
- Config helpers under `src/bootstrap.js` so every script uses the same credentials.

## Requirements

- Node.js 18+ (CommonJS environment, `Express` and `stripe` packages provided in this folder).
- `npm install` from this directory to fetch `express`, `body-parser`, and `stripe`.
- Access to an hlquery HTTP API endpoint (`api/node` client is referenced from this repository).
- Stripe secret key and webhook signing secret; keep them out of source control.

## Configuration

Edit `config/stripe.conf.js` or export the matching environment variables before running:

```bash
export STRIPE_SECRET_KEY=sk_test_...
export STRIPE_WEBHOOK_SECRET=whsec_...
export HLQUERY_URL=http://127.0.0.1:9200
export HLQUERY_TOKEN=
export HLQUERY_AUTH_METHOD=bearer
export HLQUERY_STRIPE_EVENTS_COLLECTION=stripe_events
export HLQUERY_STRIPE_PAYMENTS_COLLECTION=stripe_payments
export PORT=8080
```

The bootstrapper merges the JS config file with the exported env vars so every script (`server.js`, `example.js`) stays in sync.

## Running the server

```bash
cd etc/integrations/node/stripe
npx npm install
npm start
```

Use Stripe CLI to forward webhooks:

```bash
stripe listen --forward-to http://localhost:8080/webhook
```

## Endpoints

- `POST /create-checkout-session`: body must include `price_id`, `success_url`, `cancel_url`, and optional `quantity`/`metadata`. Stores the session in hlquery.
- `POST /webhook`: expects Stripe events with signature header `Stripe-Signature`.
- `POST /stripe-actions`: pass `action` (like `create_customer`, `create_payment_intent`, `list_invoices`, `retrieve_checkout_session`, etc.) and `params` to call the respective Stripe helper. Responses that return session IDs with `cs_` are stored automatically.

Check `server.js` for full action list and how hlquery storage is wired.

## Example script

```bash
npm run example
```

`example.js` creates a session, upserts it via `HlqueryStripeSync`, and prints the three most recent invoices.

## Credits

Licensed under BSD 3-clause. Drop the config file into `.gitignore` before adding real keys.
