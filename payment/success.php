<?php
/**
 * Payment success handler
 * Called after gateway redirects the user back.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/payment.php';

$orderId  = (int)get('order_id');
$gateway  = get('gateway');
$sessionId= get('session_id'); // Stripe CHECKOUT_SESSION_ID

if (!$orderId || !$gateway) {
    flashMessage('error', 'Parâmetros de retorno inválidos.');
    redirect(BASE_URL . '/client');
}

$db    = Database::getInstance();
$p     = DB_PREFIX;
$order = $db->fetch("SELECT * FROM {$p}orders WHERE id=? LIMIT 1", [$orderId]);

if (!$order) {
    flashMessage('error', 'Pedido não encontrado.');
    redirect(BASE_URL . '/client');
}

// Require logged-in client who owns the order (or admin)
if (Auth::isLoggedIn() && !Auth::isAdmin() && (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
    flashMessage('error', 'Acesso negado.');
    redirect(BASE_URL . '/client');
}

$alreadyActive = $order['status'] === 'active';

if (!$alreadyActive) {
    try {
        if ($gateway === 'paypal') {
            // Capture the PayPal order
            $paypalOrderId = get('token'); // PayPal appends ?token=ORDER_ID
            if ($paypalOrderId) {
                $processor = PaymentFactory::create('paypal');
                /** @var PayPalProcessor $processor */
                $capture    = $processor->captureOrder($paypalOrderId);
                $ppStatus   = $capture['status'] ?? 'CREATED';
                $txId       = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
                $amount     = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? $order['amount'];

                $result = [
                    'order_id'               => $orderId,
                    'status'                 => $ppStatus === 'COMPLETED' ? 'completed' : 'pending',
                    'gateway_transaction_id' => $txId,
                    'amount'                 => $amount,
                    'payment_method'         => 'paypal',
                    'raw'                    => $capture,
                ];
                processPaymentResult($result);
            }
        } elseif ($gateway === 'stripe' && $sessionId) {
            // Verify stripe session via API
            $processor = PaymentFactory::create('stripe');
            $status    = $processor->getStatus($sessionId);
            $result    = [
                'order_id'               => $orderId,
                'status'                 => $status,
                'gateway_transaction_id' => $sessionId,
                'amount'                 => $order['amount'],
                'payment_method'         => 'stripe',
                'raw'                    => ['session_id' => $sessionId],
            ];
            processPaymentResult($result);
        } elseif ($gateway === 'mercadopago') {
            // MercadoPago sends payment_id / status / external_reference in query string
            $mpPaymentId = get('payment_id');
            $mpStatus    = get('status'); // approved, pending, etc.
            if ($mpPaymentId) {
                $statusMap = ['approved'=>'completed','pending'=>'pending','rejected'=>'failed'];
                $result    = [
                    'order_id'               => $orderId,
                    'status'                 => $statusMap[$mpStatus] ?? 'pending',
                    'gateway_transaction_id' => $mpPaymentId,
                    'amount'                 => $order['amount'],
                    'payment_method'         => 'mercadopago',
                    'raw'                    => $_GET,
                ];
                processPaymentResult($result);
            }
        }

        // Re-fetch order to get updated status
        $order = $db->fetch("SELECT * FROM {$p}orders WHERE id=? LIMIT 1", [$orderId]);
    } catch (Throwable $e) {
        error_log('Payment success handler error: ' . $e->getMessage());
    }
}

$orderStatus = $order['status'] ?? 'pending';

ob_start();
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6 text-center">
      <?php if ($orderStatus === 'active'): ?>
        <div style="font-size:72px">✅</div>
        <h2 class="fw-bold mt-3 text-success">Pagamento Confirmado!</h2>
        <p class="text-muted">Seu pedido foi ativado com sucesso. Obrigado!</p>
        <div class="d-flex justify-content-center gap-3 mt-4">
          <a href="<?= sanitize(BASE_URL) ?>/client" class="btn btn-primary">Meu Painel</a>
          <a href="<?= sanitize(BASE_URL) ?>/client/invoices" class="btn btn-outline-secondary">Ver Faturas</a>
        </div>
      <?php else: ?>
        <div style="font-size:72px">⏳</div>
        <h2 class="fw-bold mt-3 text-warning">Pagamento Pendente</h2>
        <p class="text-muted">Seu pagamento está sendo processado. Você receberá uma confirmação em breve.</p>
        <div class="d-flex justify-content-center gap-3 mt-4">
          <a href="<?= sanitize(BASE_URL) ?>/client" class="btn btn-primary">Meu Painel</a>
          <a href="<?= sanitize(BASE_URL) ?>/client/invoices" class="btn btn-outline-secondary">Ver Faturas</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Resultado do Pagamento';
require_once VIEWS_PATH . '/layout.php';
