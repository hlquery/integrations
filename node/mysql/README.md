# MySQL Node Integration

Node.js example that mirrors the PHP MySQL integration: it reads rows from a MySQL table and upserts them into hlquery via the Node client under `api/node`.

## What it includes

- `example.js`: connects to MySQL using `mysql2/promise`, batches rows from `customers` (configurable), upserts them into hlquery with `documents().add/update`, optionally drops stale documents, and runs a validation search.
- `bootstrap.js`: loads `config/mysql.conf.js` plus `process.env` and builds the shared `hlquery` client.
- `config/mysql.conf.js`: template that holds MySQL and hlquery credentials.

## Requirements

- Node.js 18+ and `npm install` from this directory to fetch dependencies.
- MySQL server accessible with the credentials set in `config/mysql.conf.js`.
- Active hlquery HTTP endpoint (`HLQUERY_URL`) with optional token/auth method.

## Configuration

Copy `config/mysql.conf.js` to a local file or set the following `NODE` env vars before running:

```bash
export MYSQL_HOST=db.example.com
export MYSQL_PORT=3306
export MYSQL_DATABASE=saas
export MYSQL_USERNAME=saas_user
export MYSQL_PASSWORD=secret
export MYSQL_SOURCE_TABLE=customers
export MYSQL_BATCH_SIZE=200
export MYSQL_STATUS_FILTER=active
export MYSQL_DELETE_STALE=true
export HLQUERY_URL=https://hlquery.internal
export HLQUERY_TOKEN=your_token
export HLQUERY_AUTH_METHOD=bearer
export HLQUERY_MYSQL_COLLECTION=mysql_customers
```

## Running

```bash
cd etc/integrations/node/mysql
npm install
npm run example
```

The script:

1. Builds a PDO-equivalent connection to MySQL.
2. Ensures the hlquery `mysql_customers` collection exists with typed fields.
3. Loads MySQL rows in batches, adds/updates each document, and optionally deletes stale hlquery records.
4. Executes a sample hlquery search to confirm the data landed.
5. Logs progress and errors to stdout.

## Reuse

- Treat `bootstrap.js` as your canonical hlquery client loader for other Node scripts—they all pull the same config.
- Drop this folder into a cron job, CLI task, or webhook handler so your MySQL system stays mirrored in hlquery.
