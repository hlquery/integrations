const mysql = require('mysql2/promise');

const { loadConfig, mysqlHlqueryClient } = require('./bootstrap');

function normalizeDocument(row) {
  const created =
    row.created_at instanceof Date
      ? row.created_at.toISOString()
      : row.created_at
      ? String(row.created_at)
      : new Date().toISOString();

  return {
    id: row.id ? String(row.id) : `mysql_${Date.now()}_${Math.random().toString(36).slice(2)}`,
    name: row.name || 'unknown',
    email: row.email || '',
    status: row.status || 'unknown',
    created_at: created
  };
}

async function ensureCollection(client, collection) {
  try {
    await client.collections().create(collection, {
      fields: [
        { name: 'id', type: 'string' },
        { name: 'name', type: 'string' },
        { name: 'email', type: 'string' },
        { name: 'status', type: 'string' },
        { name: 'created_at', type: 'string' }
      ]
    });
    console.log(`Created collection ${collection}`);
  } catch (err) {
    console.log(`Collection ${collection} may already exist: ${err.message}`);
  }
}

async function fetchRows(connection, table, limit, offset, filters) {
  const clauses = [];
  const params = [];

  Object.entries(filters).forEach(([key, value]) => {
    clauses.push(`${key} = ?`);
    params.push(value);
  });

  let sql = `SELECT id, name, email, status, created_at FROM ${table}`;
  if (clauses.length) {
    sql += ` WHERE ${clauses.join(' AND ')}`;
  }
  sql += ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
  params.push(limit, offset);

  const [rows] = await connection.execute(sql, params);
  return rows;
}

async function upsertDocument(client, collection, document) {
  const add = await client.documents().add(collection, document);
  if (add.isSuccess()) {
    console.log(`Added ${document.id}`);
    return;
  }

  const alreadyExists = add.getStatusCode() === 409 || /(already exists)/i.test(add.getError() || '');
  if (alreadyExists) {
    const update = await client.documents().update(collection, document.id, document);
    console.log(update.isSuccess() ? `Updated ${document.id}` : `Failed update ${document.id}`);
    return;
  }

  console.log(`Failed to store ${document.id}: ${add.getError()}`);
}

async function collectDocumentIds(client, collection) {
  const response = await client.documents().list(collection, { limit: 1000 });
  if (!response.isSuccess()) {
    return [];
  }
  const body = response.getBody();
  return (body.documents || []).map((doc) => doc.id).filter(Boolean);
}

async function deleteStaleDocuments(client, collection, incomingIds) {
  const existing = await collectDocumentIds(client, collection);
  const stale = existing.filter((id) => !incomingIds.includes(id));
  for (const id of stale) {
    await client.documents().delete(collection, id);
    console.log(`Removed stale document ${id}`);
  }
}

async function run() {
  const config = loadConfig();
  const client = mysqlHlqueryClient(config);
  const connection = await mysql.createConnection({
    host: config.MYSQL_HOST,
    port: config.MYSQL_PORT,
    user: config.MYSQL_USERNAME,
    password: config.MYSQL_PASSWORD,
    database: config.MYSQL_DATABASE
  });

  try {
    await ensureCollection(client, config.HLQUERY_MYSQL_COLLECTION);

    const filters = {};
    if (config.MYSQL_STATUS_FILTER) {
      filters.status = config.MYSQL_STATUS_FILTER;
    }

    let offset = 0;
    let totalProcessed = 0;
    const processedIds = [];
    while (true) {
      const rows = await fetchRows(connection, config.MYSQL_SOURCE_TABLE, config.MYSQL_BATCH_SIZE, offset, filters);
      if (!rows.length) {
        break;
      }

      for (const row of rows) {
        const document = normalizeDocument(row);
        await upsertDocument(client, config.HLQUERY_MYSQL_COLLECTION, document);
        processedIds.push(document.id);
        totalProcessed += 1;
      }

      offset += config.MYSQL_BATCH_SIZE;
    }

    if (config.MYSQL_DELETE_STALE) {
      await deleteStaleDocuments(client, config.HLQUERY_MYSQL_COLLECTION, processedIds);
    }

    console.log(`Fetched ${totalProcessed} rows; running validation search...`);
    const search = await client.search(config.HLQUERY_MYSQL_COLLECTION, {
      q: 'status:active',
      query_by: 'name,email',
      limit: 5
    });

    if (search.isSuccess()) {
      console.log('Active customers found:', (search.getBody().hits || []).map((hit) => hit.name));
    } else {
      console.log('Search failed:', search.getError());
    }
  } finally {
    await connection.end();
  }
}

run().catch((err) => {
  console.error('MySQL sync failed:', err);
  process.exit(1);
});
