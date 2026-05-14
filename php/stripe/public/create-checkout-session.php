<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: '{}', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// Pull required fields and fall back to safe defaults.
$priceId = (string)($data['price_id'] ?? '');
$quantity = (int)($data['quantity'] ?? 1);
$successUrl = (string)($data['success_url'] ?? '');
$cancelUrl = (string)($data['cancel_url'] ?? '');
$metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

if ($priceId === '' || $successUrl === '' || $cancelUrl === '') {
    http_response_code(400);
    echo json_encode(['error' => 'price_id, success_url, and cancel_url are required']);
    exit;
}

try {
    [$stripe, $sync] = stripe_and_hlquery_services();
    $session = $stripe->createCheckoutSession(
        [[
            'price' => $priceId,
            'quantity' => max(1, $quantity),
        ]],
        $successUrl,
        $cancelUrl,
        $metadata
    );

    $sync->ensureCollections();
    $sync->storeCheckoutSession($session);

    echo json_encode([
        'id' => $session['id'] ?? null,
        'url' => $session['url'] ?? null,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
