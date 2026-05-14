<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Hlquery\Client;

$config = require __DIR__ . '/mysql.conf.php';
$client = mysql_hlqueryclient($config);

$pdo = function () use ($config) {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['MYSQL_HOST'],
        $config['MYSQL_PORT'],
        $config['MYSQL_DATABASE']
    );

    return new \PDO($dsn, $config['MYSQL_USERNAME'], $config['MYSQL_PASSWORD'], [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
};

$table = $config['MYSQL_SOURCE_TABLE'] ?? 'customers';
$collection = $config['HLQUERY_MYSQL_COLLECTION'] ?? 'mysql_customers';
$batchSize = (int)($config['MYSQL_BATCH_SIZE'] ?? 100);

// Helpers ------------------------------------------------------------------

function ensureCollection(Client $client, string $collection): void
{
    try {
        $client->collections()->create($collection, [
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'email', 'type' => 'string'],
                ['name' => 'status', 'type' => 'string'],
                ['name' => 'created_at', 'type' => 'string'],
            ],
        ]);
        echo "Created hlquery collection {$collection}.\n";
    } catch (\Throwable $e) {
        echo "Collection {$collection} already exists or failed to create: " . $e->getMessage() . "\n";
    }
}

// Build a filterable batch query from the configured table.
function fetchRows(\PDO $pdo, string $table, int $limit, int $offset = 0, array $filters = []): array
{
    $sql = "SELECT id, name, email, status, created_at FROM {$table}";
    $clauses = [];
    $params = [];

    foreach ($filters as $column => $value) {
        $clauses[] = "{$column} = :{$column}";
        $params[$column] = $value;
    }

    if ($clauses) {
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $column => $value) {
        $stmt->bindValue(":{$column}", $value);
    }
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

// Insert or update the document, preserving existing IDs.
function upsertDocument(Client $client, string $collection, array $document): void
{
    $add = $client->documents()->add($collection, $document);
    if ($add->isSuccess()) {
        echo "Added {$document['id']}\n";
        return;
    }

    if (strpos($add->getError() ?? '', 'already exists') !== false) {
        $update = $client->documents()->update($collection, $document['id'], $document);
        echo $update->isSuccess() ? "Updated {$document['id']}\n" : "Failed update {$document['id']}\n";
        return;
    }

    echo "Failed storing {$document['id']}: " . ($add->getError() ?? 'unknown') . "\n";
}

// Pull existing IDs so we can optionally delete stale rows.
function collectExistingIds(Client $client, string $collection): array
{
    $res = $client->documents()->list($collection, ['limit' => 1000]);
    if (!$res->isSuccess()) {
        return [];
    }

    $body = $res->getBody();
    $ids = [];
    foreach ($body['documents'] ?? [] as $doc) {
        $ids[] = $doc['id'] ?? '';
    }

    return array_filter($ids);
}

// Remove documents that are no longer present in MySQL.
function deleteStaleDocuments(Client $client, string $collection, array $incomingIds): void
{
    $existing = collectExistingIds($client, $collection);
    $stale = array_diff($existing, $incomingIds);
    foreach ($stale as $id) {
        $client->documents()->delete($collection, $id);
        echo "Removed stale document {$id}\n";
    }
}

// Ensure the collection exists and open the database connection.
try {
    ensureCollection($client, $collection);
    $pdoConnection = $pdo();
} catch (\PDOException $e) {
    fwrite(STDERR, "MySQL connection failed: {$e->getMessage()}\n");
    exit(1);
}

$filters = [
    'status' => $config['MYSQL_STATUS_FILTER'] ?? 'active',
];

$offset = 0;
$processed = [];

echo "Syncing data from {$table} into {$collection}...\n";
while (true) {
    $rows = fetchRows($pdoConnection, $table, $batchSize, $offset, $filters);
    if (!$rows) {
        break;
    }

    foreach ($rows as $row) {
        $doc = [
            'id' => (string)($row['id'] ?? uniqid('mysql_', true)),
            'name' => $row['name'] ?? 'unknown',
            'email' => $row['email'] ?? '',
            'status' => $row['status'] ?? 'unknown',
            'created_at' => $row['created_at'] ?? '',
        ];
        upsertDocument($client, $collection, $doc);
        $processed[] = $doc['id'];
    }

    $offset += $batchSize;
}

if (!empty($config['MYSQL_DELETE_STALE'])) {
    deleteStaleDocuments($client, $collection, $processed);
}

echo "Executing hlquery search for synced customers...\n";
// Run a quick hlquery search to validate the synced documents.
$search = $client->search($collection, [
    'q' => 'status:active',
    'query_by' => 'name,email',
    'limit' => 5,
]);

if ($search->isSuccess()) {
    echo "Recent active customers:\n";
    foreach ($search->getBody()['hits'] ?? [] as $hit) {
        echo "- {$hit['name']} ({$hit['email']})\n";
    }
} else {
    echo "Search failed: " . ($search->getError() ?? 'unknown error') . "\n";
}

echo "Sync complete: inserted/updated " . count($processed) . " records.\n";
