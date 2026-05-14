const { bootstrap } = require('./src/bootstrap');

async function runExample() {
  const { stripeService, syncService } = bootstrap();
  await syncService.ensureCollections();

  console.log('Creating checkout session...');
  const session = await stripeService.createCheckoutSession({
    lineItems: [
      {
        price_data: {
          currency: 'usd',
          product_data: {
            name: 'hlquery sponsorship'
          },
          unit_amount: 6500
        },
        quantity: 1
      }
    ],
    successUrl: 'https://example.com/success',
    cancelUrl: 'https://example.com/cancel',
    metadata: { source: 'node-example' }
  });

  await syncService.storeCheckoutSession(session);

  console.log('Session created:', session.id);
  console.log('Checkout URL:', session.url);

  console.log('Listing recent invoices...');
  const invoices = await stripeService.listInvoices({ limit: 3 });
  (invoices.data || []).forEach((invoice) => {
    console.log(
      `- invoice ${invoice.id} status=${invoice.status} amount=${invoice.amount_due} ${invoice.currency}`
    );
  });
}

runExample().catch((err) => {
  console.error('Example failed:', err);
  process.exit(1);
});
