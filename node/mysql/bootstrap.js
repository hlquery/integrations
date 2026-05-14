const path = require('path');

const Client = require(path.join(__dirname, '../../..', 'api', 'node'));

function loadConfig() {
  const base = require('./config/mysql.conf');
  return {
    MYSQL_HOST: process.env.MYSQL_HOST || base.MYSQL_HOST,
    MYSQL_PORT: process.env.MYSQL_PORT ? parseInt(process.env.MYSQL_PORT, 10) : base.MYSQL_PORT,
    MYSQL_DATABASE: process.env.MYSQL_DATABASE || base.MYSQL_DATABASE,
    MYSQL_USERNAME: process.env.MYSQL_USERNAME || base.MYSQL_USERNAME,
    MYSQL_PASSWORD: process.env.MYSQL_PASSWORD || base.MYSQL_PASSWORD,
    MYSQL_SOURCE_TABLE: process.env.MYSQL_SOURCE_TABLE || base.MYSQL_SOURCE_TABLE,
    MYSQL_BATCH_SIZE: process.env.MYSQL_BATCH_SIZE ? parseInt(process.env.MYSQL_BATCH_SIZE, 10) : base.MYSQL_BATCH_SIZE,
    MYSQL_STATUS_FILTER: process.env.MYSQL_STATUS_FILTER || base.MYSQL_STATUS_FILTER,
    MYSQL_DELETE_STALE: process.env.MYSQL_DELETE_STALE === 'true' || base.MYSQL_DELETE_STALE,
    HLQUERY_URL: process.env.HLQUERY_URL || base.HLQUERY_URL,
    HLQUERY_TOKEN: process.env.HLQUERY_TOKEN || base.HLQUERY_TOKEN,
    HLQUERY_AUTH_METHOD: process.env.HLQUERY_AUTH_METHOD || base.HLQUERY_AUTH_METHOD,
    HLQUERY_MYSQL_COLLECTION: process.env.HLQUERY_MYSQL_COLLECTION || base.HLQUERY_MYSQL_COLLECTION
  };
}

function mysqlHlqueryClient(config) {
  const options = {};
  if (config.HLQUERY_TOKEN) {
    options.token = config.HLQUERY_TOKEN;
    options.auth_method = config.HLQUERY_AUTH_METHOD || 'bearer';
  }

  return new Client(config.HLQUERY_URL, options);
}

module.exports = {
  loadConfig,
  mysqlHlqueryClient
};
