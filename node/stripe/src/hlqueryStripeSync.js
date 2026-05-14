class HlqueryStripeSync {
  constructor(client, eventsCollection = 'stripe_events', paymentsCollection = 'stripe_payments') {
    this.client = client;
    this.eventsCollection = eventsCollection;
    this.paymentsCollection = paymentsCollection;
  }

  async ensureCollections() {
    await this.ensureCollection(this.eventsCollection, this.eventsSchema());
    await this.ensureCollection(this.paymentsCollection, this.paymentsSchema());
  }

  eventsSchema() {
    return {
      fields: [
        { name: 'id', type: 'string' },
        { name: 'event_type', type: 'string' },
        { name: 'created_at', type: 'string' },
        { name: 'livemode', type: 'bool' },
        { name: 'object_id', type: 'string' },
        { name: 'customer_id', type: 'string' },
        { name: 'payment_status', type: 'string' },
        { name: 'amount_total', type: 'int' },
        { name: 'currency', type: 'string' }
      ]
    };
  }

  paymentsSchema() {
    return {
      fields: [
        { name: 'id', type: 'string' },
        { name: 'session_id', type: 'string' },
        { name: 'customer_id', type: 'string' },
        { name: 'payment_intent_id', type: 'string' },
        { name: 'status', type: 'string' },
        { name: 'payment_status', type: 'string' },
        { name: 'currency', type: 'string' },
        { name: 'amount_total', type: 'int' },
        { name: 'created_at', type: 'string' }
      ]
    };
  }

  async ensureCollection(name, schema) {
    const existing = await this.client.getCollection(name);
    if (existing.isSuccess()) {
      return;
    }

    const created = await this.client.collections().create(name, schema);
    if (!created.isSuccess()) {
      throw new Error(`Failed to create collection ${name}: ${created.getError()}`);
    }
  }

  async storeEvent(event) {
    const dataObject = event.data?.object ? event.data.object : {};
    const document = {
      id: event.id,
      event_type: event.type,
      created_at: event.created ? new Date(event.created * 1000).toISOString() : new Date().toISOString(),
      livemode: event.livemode,
      object_id: dataObject.id,
      customer_id: dataObject.customer,
      payment_status: dataObject.payment_status,
      amount_total: dataObject.amount_total,
      currency: dataObject.currency
    };

    await this.upsertDocument(this.eventsCollection, document.id, document);
  }

  async storeCheckoutSession(session) {
    if (!session?.id) {
      return;
    }

    const document = {
      id: session.id,
      session_id: session.id,
      customer_id: session.customer,
      payment_intent_id: session.payment_intent,
      status: session.status,
      payment_status: session.payment_status,
      currency: session.currency,
      amount_total: session.amount_total,
      created_at: session.created ? new Date(session.created * 1000).toISOString() : new Date().toISOString()
    };

    await this.upsertDocument(this.paymentsCollection, document.id, document);
  }

  async upsertDocument(collection, id, document) {
    if (!id) {
      return;
    }

    const add = await this.client.documents().add(collection, document);
    if (add.isSuccess()) {
      return;
    }

    const alreadyExists = add.getStatusCode() === 409 || /(already exists)/i.test(add.getError() || '');
    if (alreadyExists) {
      await this.client.documents().update(collection, id, document);
      return;
    }

    throw new Error(`Failed to store ${id} into ${collection}: ${add.getError()}`);
  }
}

module.exports = HlqueryStripeSync;
