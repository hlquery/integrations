const express = require('express');
const bodyParser = require('body-parser');

const { bootstrap } = require('./src/bootstrap');

const { config, stripeService, syncService } = bootstrap();
const app = express();
const jsonParser = express.json({ limit: '1mb' });

// Skip the JSON parser for webhooks so we can validate the raw payload.
app.use((req, res, next) => {
  if (req.path === '/webhook') {
    return next();
  }
  return jsonParser(req, res, next);
});

// Stripe webhook needs raw body bytes.
const rawParser = bodyParser.raw({ type: '*/*', limit: '1mb' });

app.post('/create-checkout-session', async (req, res) => {
  const { price_id: priceId, quantity = 1, success_url: successUrl, cancel_url: cancelUrl, metadata = {} } =
    req.body || {};

  if (!priceId || !successUrl || !cancelUrl) {
    return res.status(400).json({ error: 'price_id, success_url, and cancel_url are required' });
  }

  try {
    const session = await stripeService.createCheckoutSession({
      lineItems: [
        {
          price: priceId,
          quantity: Math.max(1, quantity)
        }
      ],
      successUrl,
      cancelUrl,
      metadata
    });

    await syncService.ensureCollections();
    await syncService.storeCheckoutSession(session);

    return res.json({ id: session.id, url: session.url });
  } catch (err) {
    return res.status(500).json({ error: err.message });
  }
});

app.post('/webhook', rawParser, async (req, res) => {
  const payload = req.body.toString('utf8');
  const signature = req.headers['stripe-signature'];

  if (!signature) {
    return res.status(400).json({ error: 'Missing Stripe-Signature header' });
  }

  try {
    const event = stripeService.constructWebhookEvent(payload, signature);
    await syncService.ensureCollections();
    await syncService.storeEvent(event);

    if (event.type === 'checkout.session.completed') {
      await syncService.storeCheckoutSession(event.data.object);
    }

    return res.json({ received: true });
  } catch (err) {
    return res.status(400).json({ error: err.message });
  }
});

const actionHandlers = {
  create_customer: (params) => stripeService.createCustomer(params),
  update_customer: (params) => {
    const customerId = params.customer_id;
    if (!customerId) {
      throw new Error('params.customer_id is required');
    }
    const payload = { ...params };
    delete payload.customer_id;
    return stripeService.updateCustomer(customerId, payload);
  },
  create_payment_intent: (params) => stripeService.createPaymentIntent(params),
  retrieve_payment_intent: (params) => {
    const id = params.payment_intent_id;
    if (!id) {
      throw new Error('params.payment_intent_id is required');
    }
    return stripeService.retrievePaymentIntent(id);
  },
  capture_payment_intent: (params) => {
    const id = params.payment_intent_id;
    if (!id) {
      throw new Error('params.payment_intent_id is required');
    }
    const payload = { ...params };
    delete payload.payment_intent_id;
    return stripeService.capturePaymentIntent(id, payload);
  },
  cancel_payment_intent: (params) => {
    const id = params.payment_intent_id;
    if (!id) {
      throw new Error('params.payment_intent_id is required');
    }
    const payload = { ...params };
    delete payload.payment_intent_id;
    return stripeService.cancelPaymentIntent(id, payload);
  },
  create_refund: (params) => stripeService.createRefund(params),
  create_subscription: (params) => stripeService.createSubscription(params),
  cancel_subscription: (params) => {
    const id = params.subscription_id;
    if (!id) {
      throw new Error('params.subscription_id is required');
    }
    const payload = { ...params };
    delete payload.subscription_id;
    return stripeService.cancelSubscription(id, payload);
  },
  create_billing_portal_session: (params) => stripeService.createBillingPortalSession(params),
  list_invoices: (params) => stripeService.listInvoices(params),
  list_charges: (params) => stripeService.listCharges(params),
  retrieve_checkout_session: (params) => {
    const id = params.session_id;
    if (!id) {
      throw new Error('params.session_id is required');
    }
    return stripeService.retrieveCheckoutSession(id);
  },
  expire_checkout_session: (params) => {
    const id = params.session_id;
    if (!id) {
      throw new Error('params.session_id is required');
    }
    return stripeService.expireCheckoutSession(id);
  }
};

app.post('/stripe-actions', async (req, res) => {
  const action = req.body?.action;
  const params = req.body?.params || {};

  if (!action || !actionHandlers[action]) {
    return res.status(400).json({ error: 'Unknown action' });
  }

  try {
    const result = await actionHandlers[action](params);
    if (result?.id && String(result.id).startsWith('cs_')) {
      await syncService.ensureCollections();
      await syncService.storeCheckoutSession(result);
    }
    return res.json({ action, result });
  } catch (err) {
    return res.status(500).json({ error: err.message });
  }
});

if (require.main === module) {
  const port = config.PORT || 8080;
  app.listen(port, () => {
    console.log(`Stripe integration listening on http://localhost:${port}`);
  });
}

module.exports = app;
