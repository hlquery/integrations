module.exports = {
  MYSQL_HOST: '127.0.0.1',
  MYSQL_PORT: 3306,
  MYSQL_DATABASE: 'my_database',
  MYSQL_USERNAME: 'root',
  MYSQL_PASSWORD: '',
  MYSQL_SOURCE_TABLE: 'customers',
  MYSQL_BATCH_SIZE: 100,
  MYSQL_STATUS_FILTER: 'active',
  MYSQL_DELETE_STALE: true,
  HLQUERY_URL: 'http://127.0.0.1:9200',
  HLQUERY_TOKEN: '',
  HLQUERY_AUTH_METHOD: 'bearer',
  HLQUERY_MYSQL_COLLECTION: 'mysql_customers'
};
