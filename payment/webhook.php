<?php
/**
 * Webhook handler for all payment gateways
 * URL: /payment/webhook?gateway=mercadopago|paypal|stripe
 */
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('LANG_PATH', ROOT_PATH . '/lang');

// No session for webhooks
require_once CONFIG_PATH . '/config.php';
require_once INCLUDES_PATH . '/payment.php';

header('Content-Type: application/json');

$gateway = $_GET['gateway'] ?? '';
if (!$gateway) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing gateway parameter']);
    exit;
}

// Read raw body
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    // Some gateways send form-encoded or other formats; try anyway
    $payload = [];
}

// Include raw body for signature verification
$payload['_raw'] = $rawBody;

// Collect all headers in lowercase-hyphenated format (standard HTTP format)
// Stripe uses 'HTTP_STRIPE_SIGNATURE' which we normalize to 'stripe-signature'
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $normalized = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$normalized] = $value;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
        $normalized = strtolower(str_replace('_', '-', $key));
        $headers[$normalized] = $value;
    }
}

try {
    $processor = PaymentFactory::create($gateway);
    $result    = $processor->handleWebhook($payload, $headers);

    if (!empty($result['order_id']) && $result['order_id'] > 0) {
        processPaymentResult($result);
        error_log(sprintf(
            'Webhook [%s] order=%d status=%s tx=%s',
            $gateway,
            $result['order_id'],
            $result['status'],
            $result['gateway_transaction_id'] ?? ''
        ));
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Webhook error [' . $gateway . ']: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
exit;
