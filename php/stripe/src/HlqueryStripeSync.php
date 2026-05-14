<?php

declare(strict_types=1);

namespace Hlquery\Integrations\Stripe;

use Hlquery\Client;
use Hlquery\Response;

final class HlqueryStripeSync
{
    private Client $client;
    private string $eventsCollection;
    private string $paymentsCollection;

    public function __construct(Client $client, string $eventsCollection = 'stripe_events', string $paymentsCollection = 'stripe_payments')
    {
        $this->client = $client;
        $this->eventsCollection = $eventsCollection;
        $this->paymentsCollection = $paymentsCollection;
    }

    public function ensureCollections(): void
    {
        // Ensure hlquery stores have the expected schema before writing data.
        $this->ensureCollection($this->eventsCollection, [
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'event_type', 'type' => 'string'],
                ['name' => 'created_at', 'type' => 'string'],
                ['name' => 'livemode', 'type' => 'bool'],
                ['name' => 'object_id', 'type' => 'string'],
                ['name' => 'customer_id', 'type' => 'string'],
                ['name' => 'payment_status', 'type' => 'string'],
                ['name' => 'amount_total', 'type' => 'int'],
                ['name' => 'currency', 'type' => 'string'],
            ]
        ]);

        $this->ensureCollection($this->paymentsCollection, [
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'session_id', 'type' => 'string'],
                ['name' => 'customer_id', 'type' => 'string'],
                ['name' => 'payment_intent_id', 'type' => 'string'],
                ['name' => 'status', 'type' => 'string'],
                ['name' => 'payment_status', 'type' => 'string'],
                ['name' => 'currency', 'type' => 'string'],
                ['name' => 'amount_total', 'type' => 'int'],
                ['name' => 'created_at', 'type' => 'string'],
            ]
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    public function storeEvent(array $event): void
    {
        // Normalize the embedded Stripe object to pull core metadata.
        $obj = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        $document = [
            'id' => (string)($event['id'] ?? ''),
            'event_type' => (string)($event['type'] ?? 'unknown'),
            'created_at' => isset($event['created']) ? gmdate('c', (int)$event['created']) : gmdate('c'),
            'livemode' => (bool)($event['livemode'] ?? false),
            'object_id' => (string)($obj['id'] ?? ''),
            'customer_id' => (string)($obj['customer'] ?? ''),
            'payment_status' => (string)($obj['payment_status'] ?? ''),
            'amount_total' => (int)($obj['amount_total'] ?? 0),
            'currency' => (string)($obj['currency'] ?? ''),
        ];

        // Save or replace the event document to keep historical data.
        // Persist event metadata to the events collection (idempotent).
        $this->upsertDocument($this->eventsCollection, $document['id'], $document);
    }

    /**
     * @param array<string, mixed> $session
     */
    public function storeCheckoutSession(array $session): void
    {
        $id = (string)($session['id'] ?? '');
        if ($id === '') {
            return;
        }

        $document = [
            'id' => $id,
            'session_id' => $id,
            'customer_id' => (string)($session['customer'] ?? ''),
            'payment_intent_id' => (string)($session['payment_intent'] ?? ''),
            'status' => (string)($session['status'] ?? ''),
            'payment_status' => (string)($session['payment_status'] ?? ''),
            'currency' => (string)($session['currency'] ?? ''),
            'amount_total' => (int)($session['amount_total'] ?? 0),
            'created_at' => isset($session['created']) ? gmdate('c', (int)$session['created']) : gmdate('c'),
        ];

        // Keep the latest payment session snapshot per session ID.
        // Keep the `stripe_payments` collection aligned with Stripe sessions.
        $this->upsertDocument($this->paymentsCollection, $document['id'], $document);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function ensureCollection(string $name, array $schema): void
    {
        $check = $this->client->collections()->get($name);
        if ($check->isSuccess()) {
            return;
        }

        $create = $this->client->collections()->create($name, $schema);
        if (!$create->isSuccess()) {
            throw new \RuntimeException('Failed creating collection ' . $name . ': ' . $this->responseError($create));
        }
    }

    /**
     * @param array<string, mixed> $document
     */
    private function upsertDocument(string $collection, string $id, array $document): void
    {
        if ($id === '') {
            return;
        }

        $update = $this->client->documents()->update($collection, $id, $document);
        if ($update->isSuccess()) {
            return;
        }

        $add = $this->client->documents()->add($collection, $document);
        if (!$add->isSuccess()) {
            throw new \RuntimeException('Failed storing document in ' . $collection . ': ' . $this->responseError($add));
        }
    }

    private function responseError(Response $response): string
    {
        $body = $response->getBody();
        if (is_array($body)) {
            return (string)($body['error'] ?? $body['message'] ?? ('HTTP ' . $response->getStatusCode()));
        }

        return 'HTTP ' . $response->getStatusCode();
    }
}
