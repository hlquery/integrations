const path = require('path');

const StripeService = require('./stripeService');
const HlqueryStripeSync = require('./hlqueryStripeSync');

function loadConfig() {
  const base = require('../config/stripe.conf');
  return {
    STRIPE_SECRET_KEY: process.env.STRIPE_SECRET_KEY || base.STRIPE_SECRET_KEY,
    STRIPE_WEBHOOK_SECRET: process.env.STRIPE_WEBHOOK_SECRET || base.STRIPE_WEBHOOK_SECRET,
    HLQUERY_URL: process.env.HLQUERY_URL || base.HLQUERY_URL,
    HLQUERY_TOKEN: process.env.HLQUERY_TOKEN || base.HLQUERY_TOKEN,
    HLQUERY_AUTH_METHOD: process.env.HLQUERY_AUTH_METHOD || base.HLQUERY_AUTH_METHOD,
    HLQUERY_STRIPE_EVENTS_COLLECTION:
      process.env.HLQUERY_STRIPE_EVENTS_COLLECTION || base.HLQUERY_STRIPE_EVENTS_COLLECTION,
    HLQUERY_STRIPE_PAYMENTS_COLLECTION:
      process.env.HLQUERY_STRIPE_PAYMENTS_COLLECTION || base.HLQUERY_STRIPE_PAYMENTS_COLLECTION,
    PORT: process.env.PORT ? parseInt(process.env.PORT, 10) : base.PORT
  };
}

function buildHlqueryClient(cfg) {
  const Client = require(path.join(__dirname, '../../../..', 'api', 'node'));
  const options = {};
  if (cfg.HLQUERY_TOKEN) {
    options.token = cfg.HLQUERY_TOKEN;
    options.auth_method = cfg.HLQUERY_AUTH_METHOD || 'bearer';
  }
  return new Client(cfg.HLQUERY_URL, options);
}

function bootstrap() {
  const config = loadConfig();
  const stripeService = new StripeService(config.STRIPE_SECRET_KEY, config.STRIPE_WEBHOOK_SECRET);
  const hlqueryClient = buildHlqueryClient(config);
  const syncService = new HlqueryStripeSync(
    hlqueryClient,
    config.HLQUERY_STRIPE_EVENTS_COLLECTION,
    config.HLQUERY_STRIPE_PAYMENTS_COLLECTION
  );
  return {
    config,
    stripeService,
    syncService,
    hlqueryClient
  };
}

module.exports = {
  bootstrap
};
