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

$action = (string)($data['action'] ?? '');
$params = is_array($data['params'] ?? null) ? $data['params'] : [];

if ($action === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action']);
    exit;
}

try {
    [$stripe, $sync] = stripe_and_hlquery_services();
    $result = null;

    // Route actions to the appropriate StripeService helper.
    switch ($action) {
        case 'create_customer':
            $result = $stripe->createCustomer($params);
            break;

        case 'update_customer':
            $customerId = (string)($params['customer_id'] ?? '');
            if ($customerId === '') {
                throw new InvalidArgumentException('params.customer_id is required');
            }
            unset($params['customer_id']);
            $result = $stripe->updateCustomer($customerId, $params);
            break;

        case 'create_payment_intent':
            $result = $stripe->createPaymentIntent($params);
            break;

        case 'retrieve_payment_intent':
            $paymentIntentId = (string)($params['payment_intent_id'] ?? '');
            if ($paymentIntentId === '') {
                throw new InvalidArgumentException('params.payment_intent_id is required');
            }
            $result = $stripe->retrievePaymentIntent($paymentIntentId);
            break;

        case 'capture_payment_intent':
            $paymentIntentId = (string)($params['payment_intent_id'] ?? '');
            if ($paymentIntentId === '') {
                throw new InvalidArgumentException('params.payment_intent_id is required');
            }
            unset($params['payment_intent_id']);
            $result = $stripe->capturePaymentIntent($paymentIntentId, $params);
            break;

        case 'cancel_payment_intent':
            $paymentIntentId = (string)($params['payment_intent_id'] ?? '');
            if ($paymentIntentId === '') {
                throw new InvalidArgumentException('params.payment_intent_id is required');
            }
            unset($params['payment_intent_id']);
            $result = $stripe->cancelPaymentIntent($paymentIntentId, $params);
            break;

        case 'create_refund':
            $result = $stripe->createRefund($params);
            break;

        case 'create_subscription':
            $result = $stripe->createSubscription($params);
            break;

        case 'cancel_subscription':
            $subscriptionId = (string)($params['subscription_id'] ?? '');
            if ($subscriptionId === '') {
                throw new InvalidArgumentException('params.subscription_id is required');
            }
            unset($params['subscription_id']);
            $result = $stripe->cancelSubscription($subscriptionId, $params);
            break;

        case 'create_billing_portal_session':
            $result = $stripe->createBillingPortalSession($params);
            break;

        case 'list_invoices':
            $result = $stripe->listInvoices($params);
            break;

        case 'list_charges':
            $result = $stripe->listCharges($params);
            break;

        case 'retrieve_checkout_session':
            $sessionId = (string)($params['session_id'] ?? '');
            if ($sessionId === '') {
                throw new InvalidArgumentException('params.session_id is required');
            }
            $result = $stripe->retrieveCheckoutSession($sessionId);
            break;

        case 'expire_checkout_session':
            $sessionId = (string)($params['session_id'] ?? '');
            if ($sessionId === '') {
                throw new InvalidArgumentException('params.session_id is required');
            }
            $result = $stripe->expireCheckoutSession($sessionId);
            break;

        default:
            throw new InvalidArgumentException('Unknown action: ' . $action);
    }

    if (is_array($result) && isset($result['id']) && str_starts_with((string)$result['id'], 'cs_')) {
        $sync->ensureCollections();
        $sync->storeCheckoutSession($result);
    }

    echo json_encode([
        'action' => $action,
        'result' => $result,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
