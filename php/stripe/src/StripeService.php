<?php

declare(strict_types=1);

namespace Hlquery\Integrations\Stripe;

use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use UnexpectedValueException;

final class StripeService
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct(string $secretKey, string $webhookSecret = '')
    {
        if ($secretKey === '') {
            throw new \InvalidArgumentException('Missing STRIPE_SECRET_KEY.');
        }

        $this->secretKey = $secretKey;
        $this->webhookSecret = $webhookSecret;

        // Configure stripe-php with the provided secret key.
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): array {
        // Create a single-use checkout session for the requested items.
        $session = Session::create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);

        return $session->toArray();
    }

    /**
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        // Convenience wrapper for retrieving a checkout session from Stripe.

        return Session::retrieve($sessionId)->toArray();
    }

    /**
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function expireCheckoutSession(string $sessionId): array
    {
        // Short-lived session expiration helper for server-side cancellations.
        return Session::expire($sessionId)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createCustomer(array $params): array
    {
        // Customer management helpers mirror Stripe API shapes.
        return Customer::create($params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function updateCustomer(string $customerId, array $params): array
    {
        return Customer::update($customerId, $params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createPaymentIntent(array $params): array
    {
        return PaymentIntent::create($params)->toArray();
    }

    /**
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return PaymentIntent::retrieve($paymentIntentId)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function capturePaymentIntent(string $paymentIntentId, array $params = []): array
    {
        return PaymentIntent::capture($paymentIntentId, $params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function cancelPaymentIntent(string $paymentIntentId, array $params = []): array
    {
        return PaymentIntent::cancel($paymentIntentId, $params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createRefund(array $params): array
    {
        return Refund::create($params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createSubscription(array $params): array
    {
        return Subscription::create($params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function cancelSubscription(string $subscriptionId, array $params = []): array
    {
        return Subscription::cancel($subscriptionId, $params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(array $params): array
    {
        return BillingPortalSession::create($params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function listInvoices(array $params = []): array
    {
        return Invoice::all($params)->toArray();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws ApiErrorException
     */
    public function listCharges(array $params = []): array
    {
        return Charge::all($params)->toArray();
    }

    /**
     * @throws SignatureVerificationException|UnexpectedValueException
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        if ($this->webhookSecret === '') {
            throw new \InvalidArgumentException('Missing STRIPE_WEBHOOK_SECRET.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
    }
}
