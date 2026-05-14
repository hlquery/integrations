<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Stripe\Exception\SignatureVerificationException;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$signatureHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($signatureHeader === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe-Signature header']);
    exit;
}

try {
    [$stripe, $sync] = stripe_and_hlquery_services();
    // Verify the signature header before processing the event payload.
    $event = $stripe->constructWebhookEvent($payload, $signatureHeader);
    $eventArray = $event->toArray();
    $sync->ensureCollections();
    $sync->storeEvent($eventArray);

    switch ($event->type) {
        case 'checkout.session.completed':
            /** @var array<string, mixed> $session */
            $session = $event->data->object->toArray();
            $sync->storeCheckoutSession($session);

            // Replace this with your business logic (activate plan, persist payment, etc).
            error_log('Stripe checkout completed: ' . json_encode([
                'id' => $session['id'] ?? null,
                'customer' => $session['customer'] ?? null,
                'metadata' => $session['metadata'] ?? [],
            ]));
            break;

        default:
            // Keep endpoint resilient; ignore events you do not handle.
            break;
    }

    echo json_encode(['received' => true]);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
