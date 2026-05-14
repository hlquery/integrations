const Stripe = require('stripe');

class StripeService {
  constructor(secretKey, webhookSecret = '') {
    if (!secretKey) {
      throw new Error('Missing STRIPE_SECRET_KEY');
    }

    this.stripe = new Stripe(secretKey, { apiVersion: '2023-08-16' });
    this.webhookSecret = webhookSecret;
  }

  async createCheckoutSession({ lineItems, successUrl, cancelUrl, metadata = {} }) {
    return await this.stripe.checkout.sessions.create({
      mode: 'payment',
      line_items: lineItems,
      success_url: successUrl,
      cancel_url: cancelUrl,
      metadata
    });
  }

  async retrieveCheckoutSession(sessionId) {
    return await this.stripe.checkout.sessions.retrieve(sessionId);
  }

  async expireCheckoutSession(sessionId) {
    return await this.stripe.checkout.sessions.expire(sessionId);
  }

  async createCustomer(params) {
    return await this.stripe.customers.create(params);
  }

  async updateCustomer(customerId, params) {
    return await this.stripe.customers.update(customerId, params);
  }

  async createPaymentIntent(params) {
    return await this.stripe.paymentIntents.create(params);
  }

  async retrievePaymentIntent(paymentIntentId) {
    return await this.stripe.paymentIntents.retrieve(paymentIntentId);
  }

  async capturePaymentIntent(paymentIntentId, params = {}) {
    return await this.stripe.paymentIntents.capture(paymentIntentId, params);
  }

  async cancelPaymentIntent(paymentIntentId, params = {}) {
    return await this.stripe.paymentIntents.cancel(paymentIntentId, params);
  }

  async createRefund(params) {
    return await this.stripe.refunds.create(params);
  }

  async createSubscription(params) {
    return await this.stripe.subscriptions.create(params);
  }

  async cancelSubscription(subscriptionId, params = {}) {
    return await this.stripe.subscriptions.del(subscriptionId, params);
  }

  async createBillingPortalSession(params) {
    return await this.stripe.billingPortal.sessions.create(params);
  }

  async listInvoices(params = {}) {
    return await this.stripe.invoices.list(params);
  }

  async listCharges(params = {}) {
    return await this.stripe.charges.list(params);
  }

  constructWebhookEvent(payload, signatureHeader) {
    if (!this.webhookSecret) {
      throw new Error('Missing STRIPE_WEBHOOK_SECRET');
    }

    return this.stripe.webhooks.constructEvent(payload, signatureHeader, this.webhookSecret);
  }
}

module.exports = StripeService;
