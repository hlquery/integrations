# MySQL PHP Integration

Lightweight example that shows how to ingest data from a MySQL table into hlquery using the shared PHP client.

## What it includes

- `example.php`: connects to MySQL, queries a configurable table, upserts results into an hlquery collection, and runs a sample search.
- `mysql.conf.php`: configuration template for MySQL + hlquery credentials.
- `bootstrap.php`: helper that builds `Hlquery\Client` using the same credential set.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `curl`, and `json` extensions enabled.
- A MySQL server accessible with the credentials in `mysql.conf.php`.
- An hlquery HTTP endpoint (`HLQUERY_URL`) with optional auth token/method.

## Configuration

Duplicate `mysql.conf.php`, fill in real connection info for both MySQL and hlquery, plus optional tuning:

```php
'MYSQL_HOST' => 'db.example.com',
'MYSQL_PORT' => 3306,
'MYSQL_DATABASE' => 'saas',
'MYSQL_USERNAME' => 'saas_user',
'MYSQL_PASSWORD' => 'secret',
'MYSQL_SOURCE_TABLE' => 'customers',
'MYSQL_BATCH_SIZE' => 200,
'MYSQL_STATUS_FILTER' => 'active',
'MYSQL_DELETE_STALE' => true,
'HLQUERY_URL' => 'https://hlquery.internal',
'HLQUERY_TOKEN' => 'your_token',
'HLQUERY_AUTH_METHOD' => 'bearer',
'HLQUERY_MYSQL_COLLECTION' => 'mysql_customers',
```

## Running the example

```bash
php example.php
```

The script:

1. Loads the config file and builds a PDO connection.
2. Ensures the hlquery collection exists with typed fields.
3. Reads the configured MySQL table in batches and upserts each row via `documents()->add`/`update`, optionally removing stale documents.
4. Executes a small hlquery search to verify the synced data.
5. Prints progress and errors to STDOUT/STDERR.

Adapt the SQL query or collection schema to match the MySQL table you want to sync.

## Hooking into your workflow

- Use this script as a cron job, webhook handler, or CLI task whenever you want to mirror MySQL rows into hlquery.
- Re-use `bootstrap.php` to obtain the hlquery client in other scripts: it reads the same config so your credentials stay centralized.
