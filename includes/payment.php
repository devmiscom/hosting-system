<?php
/**
 * Payment processor classes for MercadoPago, PayPal, and Stripe
 */

// ─── Base class ───────────────────────────────────────────────────────────────

abstract class PaymentProcessor {
    protected array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    abstract public function createCheckout(array $order, string $successUrl, string $cancelUrl): string;
    abstract public function handleWebhook(array $payload, array $headers): array;
    abstract public function getStatus(string $gatewayOrderId): string;
}

// ─── MercadoPago ─────────────────────────────────────────────────────────────

class MercadoPagoProcessor extends PaymentProcessor {

    private function getHeaders(): array {
        return [
            'Authorization: Bearer ' . $this->config['access_token'],
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . generateRandomToken(32),
        ];
    }

    private function request(string $method, string $endpoint, array $data = []): array {
        $url = 'https://api.mercadopago.com' . $endpoint;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('MercadoPago cURL error: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('MercadoPago invalid response (HTTP ' . $httpCode . ')');
        }
        return $decoded;
    }

    public function createCheckout(array $order, string $successUrl, string $cancelUrl): string {
        $body = [
            'items' => [[
                'title'      => $order['item_name'] ?? 'Pedido #' . $order['id'],
                'quantity'   => 1,
                'unit_price' => (float)$order['amount'],
                'currency_id'=> $order['currency'] ?? 'BRL',
            ]],
            'payer'            => ['email' => $order['user_email'] ?? ''],
            'back_urls'        => [
                'success' => $successUrl,
                'failure' => $cancelUrl,
                'pending' => $successUrl,
            ],
            'auto_return'      => 'approved',
            'external_reference' => (string)$order['id'],
            'notification_url'   => BASE_URL . '/payment/webhook?gateway=mercadopago',
        ];

        $result = $this->request('POST', '/checkout/preferences', $body);

        if (empty($result['init_point'])) {
            throw new RuntimeException('MercadoPago: could not create preference. ' . ($result['message'] ?? ''));
        }

        // Save preference_id as gateway_order_id
        $db = Database::getInstance();
        $db->execute(
            'UPDATE ' . DB_PREFIX . 'orders SET gateway_order_id = ?, updated_at = NOW() WHERE id = ?',
            [$result['id'], $order['id']]
        );

        return $result['init_point'];
    }

    public function handleWebhook(array $payload, array $headers): array {
        $type   = $payload['type'] ?? '';
        $dataId = $payload['data']['id'] ?? '';

        if ($type !== 'payment' || !$dataId) {
            return ['status' => 'ignored'];
        }

        $payment = $this->request('GET', '/v1/payments/' . $dataId);

        $mpStatus  = $payment['status'] ?? 'pending';
        $extRef    = $payment['external_reference'] ?? '';
        $orderId   = (int)$extRef;

        $statusMap = [
            'approved'    => 'completed',
            'pending'     => 'pending',
            'in_process'  => 'pending',
            'rejected'    => 'failed',
            'cancelled'   => 'failed',
            'refunded'    => 'refunded',
            'charged_back'=> 'refunded',
        ];
        $status = $statusMap[$mpStatus] ?? 'pending';

        return [
            'order_id'               => $orderId,
            'status'                 => $status,
            'gateway_transaction_id' => (string)$dataId,
            'amount'                 => $payment['transaction_amount'] ?? 0,
            'payment_method'         => $payment['payment_type_id'] ?? '',
            'raw'                    => $payment,
        ];
    }

    public function getStatus(string $gatewayOrderId): string {
        try {
            $result = $this->request('GET', '/checkout/preferences/' . $gatewayOrderId);
            return 'pending';
        } catch (Throwable) {
            return 'pending';
        }
    }
}

// Stripe zero-decimal currencies (amount is not multiplied by 100)
const STRIPE_ZERO_DECIMAL_CURRENCIES = ['bif','clp','gnf','mga','pyg','rwf','ugx','vnd','xaf','xof'];

// ─── PayPal ───────────────────────────────────────────────────────────────────

class PayPalProcessor extends PaymentProcessor {

    private bool $sandbox;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->sandbox = ($config['mode'] ?? 'sandbox') === 'sandbox';
    }

    private function getBaseUrl(): string {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function getAccessToken(): string {
        $cacheKey = 'paypal_token_' . md5($this->config['client_id']);
        if (!empty($_SESSION[$cacheKey]) && ($_SESSION[$cacheKey . '_exp'] ?? 0) > time()) {
            return $_SESSION[$cacheKey];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->getBaseUrl() . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => $this->config['client_id'] . ':' . $this->config['secret'],
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($response['access_token'])) {
            throw new RuntimeException('PayPal authentication failed.');
        }

        $_SESSION[$cacheKey]          = $response['access_token'];
        $_SESSION[$cacheKey . '_exp'] = time() + (int)($response['expires_in'] ?? 3600) - 60;

        return $response['access_token'];
    }

    private function request(string $method, string $endpoint, array $data = []): array {
        $token = $this->getAccessToken();
        $url   = $this->getBaseUrl() . $endpoint;
        $ch    = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . generateRandomToken(20),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) throw new RuntimeException('PayPal cURL error: ' . $error);

        return json_decode($response, true) ?? [];
    }

    public function createCheckout(array $order, string $successUrl, string $cancelUrl): string {
        $currency = strtoupper($order['currency'] ?? 'BRL');
        $amount   = number_format((float)$order['amount'], 2, '.', '');

        $body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string)$order['id'],
                'description'  => $order['item_name'] ?? 'Pedido #' . $order['id'],
                'amount'       => ['currency_code' => $currency, 'value' => $amount],
            ]],
            'application_context' => [
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => getSetting('site_name', 'Hosting'),
                'locale'     => 'pt-BR',
                'user_action'=> 'PAY_NOW',
            ],
        ];

        $result = $this->request('POST', '/v2/checkout/orders', $body);

        if (empty($result['id'])) {
            throw new RuntimeException('PayPal: could not create order.');
        }

        $db = Database::getInstance();
        $db->execute(
            'UPDATE ' . DB_PREFIX . 'orders SET gateway_order_id = ?, updated_at = NOW() WHERE id = ?',
            [$result['id'], $order['id']]
        );

        foreach (($result['links'] ?? []) as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        throw new RuntimeException('PayPal: no approval URL returned.');
    }

    public function handleWebhook(array $payload, array $headers): array {
        $eventType    = $payload['event_type'] ?? '';
        $resource     = $payload['resource'] ?? [];
        $referenceId  = $resource['purchase_units'][0]['reference_id'] ?? ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
        $captureId    = $resource['id'] ?? '';
        $amount       = $resource['amount']['value'] ?? ($resource['purchase_units'][0]['amount']['value'] ?? 0);

        $statusMap = [
            'PAYMENT.CAPTURE.COMPLETED'  => 'completed',
            'PAYMENT.CAPTURE.DENIED'     => 'failed',
            'PAYMENT.CAPTURE.REVERSED'   => 'refunded',
            'PAYMENT.CAPTURE.REFUNDED'   => 'refunded',
            'CHECKOUT.ORDER.COMPLETED'   => 'completed',
        ];
        $status = $statusMap[$eventType] ?? 'pending';

        return [
            'order_id'               => (int)$referenceId,
            'status'                 => $status,
            'gateway_transaction_id' => $captureId,
            'amount'                 => $amount,
            'payment_method'         => 'paypal',
            'raw'                    => $resource,
        ];
    }

    public function getStatus(string $gatewayOrderId): string {
        $result = $this->request('GET', '/v2/checkout/orders/' . $gatewayOrderId);
        $ppStatus = $result['status'] ?? 'CREATED';
        return $ppStatus === 'COMPLETED' ? 'completed' : 'pending';
    }

    /**
     * Capture a PayPal order (call after return from PayPal)
     */
    public function captureOrder(string $paypalOrderId): array {
        return $this->request('POST', '/v2/checkout/orders/' . $paypalOrderId . '/capture');
    }
}

// ─── Stripe ───────────────────────────────────────────────────────────────────

class StripeProcessor extends PaymentProcessor {

    private function request(string $method, string $endpoint, array $data = []): array {
        $url = 'https://api.stripe.com/v1' . $endpoint;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->config['secret_key'] . ':',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) throw new RuntimeException('Stripe cURL error: ' . $error);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Stripe invalid response.');
        }
        if (!empty($decoded['error'])) {
            throw new RuntimeException('Stripe error: ' . ($decoded['error']['message'] ?? 'Unknown'));
        }
        return $decoded;
    }

    public function createCheckout(array $order, string $successUrl, string $cancelUrl): string {
        $currency = strtolower($order['currency'] ?? 'brl');
        // Stripe amounts are in smallest currency unit (zero-decimal currencies are not multiplied)
        $multiplier = in_array($currency, STRIPE_ZERO_DECIMAL_CURRENCIES) ? 1 : 100;
        $unitAmount = (int)round((float)$order['amount'] * $multiplier);

        $data = [
            'mode'                  => 'payment',
            'success_url'           => $successUrl . '?session_id={CHECKOUT_SESSION_ID}&gateway=stripe&order_id=' . $order['id'],
            'cancel_url'            => $cancelUrl . '?gateway=stripe&order_id=' . $order['id'],
            'line_items[0][price_data][currency]'            => $currency,
            'line_items[0][price_data][unit_amount]'         => $unitAmount,
            'line_items[0][price_data][product_data][name]'  => $order['item_name'] ?? 'Pedido #' . $order['id'],
            'line_items[0][quantity]'                        => 1,
            'metadata[order_id]'    => $order['id'],
            'client_reference_id'   => (string)$order['id'],
        ];

        $result = $this->request('POST', '/checkout/sessions', $data);

        if (empty($result['url'])) {
            throw new RuntimeException('Stripe: could not create checkout session.');
        }

        $db = Database::getInstance();
        $db->execute(
            'UPDATE ' . DB_PREFIX . 'orders SET gateway_order_id = ?, updated_at = NOW() WHERE id = ?',
            [$result['id'], $order['id']]
        );

        return $result['url'];
    }

    public function handleWebhook(array $payload, array $headers): array {
        $webhookSecret = $this->config['webhook_secret'] ?? '';
        if ($webhookSecret) {
            // Headers are normalized to lowercase-hyphenated format by webhook.php
            $sigHeader = $headers['stripe-signature'] ?? '';
            if (!$this->verifyStripeSignature($payload['_raw'] ?? '', $sigHeader, $webhookSecret)) {
                throw new RuntimeException('Stripe webhook signature verification failed.');
            }
        }

        $type    = $payload['type'] ?? '';
        $object  = $payload['data']['object'] ?? [];
        $orderId = (int)($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);

        $statusMap = [
            'checkout.session.completed'       => 'completed',
            'payment_intent.succeeded'         => 'completed',
            'payment_intent.payment_failed'    => 'failed',
            'charge.refunded'                  => 'refunded',
        ];
        $status = $statusMap[$type] ?? 'pending';

        $amount = 0;
        if (isset($object['amount_total'])) {
            $amount = $object['amount_total'] / 100;
        } elseif (isset($object['amount'])) {
            $amount = $object['amount'] / 100;
        }

        return [
            'order_id'               => $orderId,
            'status'                 => $status,
            'gateway_transaction_id' => $object['payment_intent'] ?? $object['id'] ?? '',
            'amount'                 => $amount,
            'payment_method'         => 'stripe',
            'raw'                    => $object,
        ];
    }

    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool {
        $parts     = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v]   = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }
        $timestamp = $parts['t'] ?? '';
        $v1        = $parts['v1'] ?? '';
        if (!$timestamp || !$v1) return false;

        $signedPayload = $timestamp . '.' . $payload;
        $expected      = hash_hmac('sha256', $signedPayload, $secret);
        return hash_equals($expected, $v1);
    }

    public function getStatus(string $gatewayOrderId): string {
        try {
            $result = $this->request('GET', '/checkout/sessions/' . $gatewayOrderId);
            return $result['payment_status'] === 'paid' ? 'completed' : 'pending';
        } catch (Throwable) {
            return 'pending';
        }
    }
}

// ─── Factory ──────────────────────────────────────────────────────────────────

class PaymentFactory {

    public static function create(string $gateway): PaymentProcessor {
        return match (strtolower($gateway)) {
            'mercadopago' => new MercadoPagoProcessor([
                'access_token' => getSetting('mp_access_token', ''),
            ]),
            'paypal' => new PayPalProcessor([
                'client_id' => getSetting('paypal_client_id', ''),
                'secret'    => getSetting('paypal_secret', ''),
                'mode'      => getSetting('paypal_mode', 'sandbox'),
            ]),
            'stripe' => new StripeProcessor([
                'secret_key'     => getSetting('stripe_secret_key', ''),
                'webhook_secret' => getSetting('stripe_webhook_secret', ''),
            ]),
            default => throw new InvalidArgumentException('Unknown payment gateway: ' . $gateway),
        };
    }
}

// ─── Order helper ─────────────────────────────────────────────────────────────

/**
 * Update order + transaction status in the database
 */
function processPaymentResult(array $result): void {
    if (empty($result['order_id']) || empty($result['status'])) {
        return;
    }

    $db      = Database::getInstance();
    $orderId = (int)$result['order_id'];
    $status  = $result['status'];

    $orderStatus = match ($status) {
        'completed' => 'active',
        'refunded'  => 'cancelled',
        'failed'    => 'cancelled',
        default     => 'pending',
    };

    $db->execute(
        'UPDATE ' . DB_PREFIX . 'orders SET status = ?, updated_at = NOW() WHERE id = ?',
        [$orderStatus, $orderId]
    );

    // Upsert transaction
    $existing = $db->fetch(
        'SELECT id FROM ' . DB_PREFIX . 'transactions WHERE order_id = ? LIMIT 1',
        [$orderId]
    );

    $order  = $db->fetch('SELECT * FROM ' . DB_PREFIX . 'orders WHERE id = ? LIMIT 1', [$orderId]);
    $userId = $order['user_id'] ?? 0;

    if ($existing) {
        $db->execute(
            'UPDATE ' . DB_PREFIX . 'transactions SET status = ?, gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?',
            [$status, $result['gateway_transaction_id'] ?? null, $existing['id']]
        );
    } else {
        $db->execute(
            'INSERT INTO ' . DB_PREFIX . 'transactions (order_id, user_id, amount, currency, gateway, gateway_transaction_id, status, payment_method, metadata) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $orderId,
                $userId,
                $result['amount'] ?? ($order['amount'] ?? 0),
                $order['currency'] ?? 'BRL',
                $order['gateway'] ?? '',
                $result['gateway_transaction_id'] ?? null,
                $status,
                $result['payment_method'] ?? null,
                json_encode($result['raw'] ?? []),
            ]
        );
    }
}
