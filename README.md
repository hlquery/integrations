# hlquery Integrations

This directory contains example integrations that sync external systems into hlquery collections.

## Available Integrations

| Integration | Path | Purpose |
| --- | --- | --- |
| MySQL PHP | `php/mysql` | Reads rows from a MySQL table and upserts them into an hlquery collection. |
| MySQL Node.js | `node/mysql` | Node.js version of the MySQL-to-hlquery sync flow. |
| Stripe PHP | `php/stripe` | Creates checkout sessions, verifies webhooks, exposes Stripe actions, and stores Stripe events/payments in hlquery. |
| Stripe Node.js | `node/stripe` | Express implementation of the Stripe checkout, webhook, and action endpoints. |

Each integration has its own README with endpoint details and examples.

## Shared Requirements

- A running hlquery HTTP API endpoint.
- Credentials for the source system, such as MySQL or Stripe.
- A target hlquery collection name. Defaults are provided in each config file.
- Do not commit real credentials. Use environment variables or local config files ignored by git.

## Setup

### PHP MySQL

```bash
cd etc/integrations/php/mysql
php -l bootstrap.php
php -l example.php
php example.php
```

Configure `mysql.conf.php` or export matching environment values before running against a real database.

### Node.js MySQL

```bash
cd etc/integrations/node/mysql
npm install
npm run example
```

The example reads MySQL rows in batches, ensures the hlquery collection exists, upserts documents, optionally removes stale documents, and runs a validation search.

### PHP Stripe

```bash
cd etc/integrations/php/stripe
composer install
php -S 127.0.0.1:8080 -t public
```

In another terminal, forward Stripe webhooks:

```bash
stripe listen --forward-to http://127.0.0.1:8080/webhook.php
```

Set `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `HLQUERY_URL`, and optional hlquery auth values before starting the server.

### Node.js Stripe

```bash
cd etc/integrations/node/stripe
npm install
npm start
```

In another terminal, forward Stripe webhooks:

```bash
stripe listen --forward-to http://127.0.0.1:8080/webhook
```

Set `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `HLQUERY_URL`, and optional hlquery auth values before starting the server.

## Configuration Variables

Common hlquery settings:

```bash
export HLQUERY_URL=http://127.0.0.1:9200
export HLQUERY_TOKEN=
export HLQUERY_AUTH_METHOD=bearer
```

MySQL settings:

```bash
export MYSQL_HOST=127.0.0.1
export MYSQL_PORT=3306
export MYSQL_DATABASE=app
export MYSQL_USERNAME=app
export MYSQL_PASSWORD=secret
export MYSQL_SOURCE_TABLE=customers
export MYSQL_BATCH_SIZE=200
export MYSQL_STATUS_FILTER=active
export MYSQL_DELETE_STALE=false
export HLQUERY_MYSQL_COLLECTION=mysql_customers
```

Stripe settings:

```bash
export STRIPE_SECRET_KEY=sk_test_...
export STRIPE_WEBHOOK_SECRET=whsec_...
export HLQUERY_STRIPE_EVENTS_COLLECTION=stripe_events
export HLQUERY_STRIPE_PAYMENTS_COLLECTION=stripe_payments
export PORT=8080
```

## Validation

Run these checks after editing integrations:

```bash
find etc/integrations/php -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find etc/integrations/node -type f -name '*.js' -print0 | xargs -0 -n1 node --check
npm --prefix etc/integrations/node/mysql install --package-lock-only --ignore-scripts
npm --prefix etc/integrations/node/stripe install --package-lock-only --ignore-scripts
composer validate --working-dir=etc/integrations/php/stripe --no-check-publish
php -r "require 'etc/integrations/php/mysql/bootstrap.php'; echo function_exists('mysql_hlqueryclient') ? 'ok' : 'missing';"
```

Full end-to-end validation also requires live MySQL, Stripe, and hlquery services with valid credentials.
