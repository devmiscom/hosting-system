<?php
/**
 * Client – Checkout page
 * Reads ?type=plan|service &id=N
 * POST: select gateway and create order then redirect to payment
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireClient();
require_once INCLUDES_PATH . '/payment.php';

$db     = Database::getInstance();
$p      = DB_PREFIX;
$userId = (int)$_SESSION['user_id'];

$type = get('type'); // plan | service
$id   = (int)get('id');
$error= '';

if (!in_array($type, ['plan', 'service']) || !$id) {
    flashMessage('error', 'Selecione um plano ou serviço válido.');
    redirect(BASE_URL . '/client/plans');
}

// Load the item
$table = $type === 'plan' ? $p . 'plans' : $p . 'services';
$item  = $db->fetch("SELECT * FROM {$table} WHERE id=? AND active=1 LIMIT 1", [$id]);

if (!$item) {
    flashMessage('error', 'Item não encontrado.');
    redirect(BASE_URL . '/client/plans');
}

$activeGateways = getActiveGateways();
if (!$activeGateways) {
    flashMessage('error', 'Nenhum gateway de pagamento configurado. Contacte o suporte.');
    redirect(BASE_URL . '/client/plans');
}

// ── Handle POST: create order and redirect ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/client/checkout?type=' . urlencode($type) . '&id=' . $id);
    }

    $gateway = post('gateway');
    if (!in_array($gateway, $activeGateways)) {
        $error = 'Gateway de pagamento inválido.';
    } else {
        $currency = getSetting('currency', 'BRL');
        $user     = Auth::getCurrentUser();

        try {
            $db->beginTransaction();

            // Create order
            $db->execute(
                "INSERT INTO {$p}orders (user_id, item_type, item_id, amount, currency, billing_cycle, status, gateway)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$userId, $type, $id, $item['price'], $currency, $item['billing_cycle'], 'pending', $gateway]
            );
            $orderId = (int)$db->lastInsertId();

            // Create pending transaction
            $db->execute(
                "INSERT INTO {$p}transactions (order_id, user_id, amount, currency, gateway, status) VALUES (?,?,?,?,?,'pending')",
                [$orderId, $userId, $item['price'], $currency, $gateway]
            );

            $db->commit();

            // Build order array for payment processor
            $orderData = [
                'id'         => $orderId,
                'user_email' => $user['email'],
                'amount'     => $item['price'],
                'currency'   => $currency,
                'item_name'  => $item['name'],
                'billing_cycle' => $item['billing_cycle'],
            ];

            $successUrl = BASE_URL . '/payment/success?order_id=' . $orderId . '&gateway=' . urlencode($gateway);
            $cancelUrl  = BASE_URL . '/payment/cancel?order_id='  . $orderId . '&gateway=' . urlencode($gateway);

            $processor  = PaymentFactory::create($gateway);
            $paymentUrl = $processor->createCheckout($orderData, $successUrl, $cancelUrl);

            redirect($paymentUrl);
        } catch (Throwable $e) {
            $db->rollback();
            error_log('Checkout error: ' . $e->getMessage());
            $error = 'Erro ao processar pagamento. Tente novamente.';
        }
    }
}

$gatewayLabels = ['mercadopago'=>'MercadoPago','paypal'=>'PayPal','stripe'=>'Stripe'];
$gatewayIcons  = ['mercadopago'=>'bi-credit-card-2-front','paypal'=>'bi-paypal','stripe'=>'bi-stripe'];
$cycleLabels   = ['one_time'=>'Pagamento Único','monthly'=>'Mensal','yearly'=>'Anual'];

ob_start();
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <h3 class="fw-bold mb-4"><i class="bi bi-cart-check me-2"></i>Checkout</h3>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <!-- Order summary -->
      <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Resumo do Pedido</strong></div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= sanitize($item['name']) ?></div>
              <div class="text-muted small">
                <?= sanitize(ucfirst($type)) ?> &bull;
                <?= sanitize($cycleLabels[$item['billing_cycle']] ?? $item['billing_cycle']) ?>
              </div>
              <?php if ($item['description']): ?>
                <div class="small text-muted mt-1"><?= sanitize(truncate($item['description'], 100)) ?></div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div class="fs-4 fw-bold"><?= formatCurrency($item['price']) ?></div>
              <div class="text-muted small"><?= sanitize(getSetting('currency', 'BRL')) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment method form -->
      <div class="card shadow-sm">
        <div class="card-header"><strong>Forma de Pagamento</strong></div>
        <div class="card-body">
          <form method="POST"
                action="<?= sanitize(BASE_URL) ?>/client/checkout?type=<?= urlencode($type) ?>&id=<?= $id ?>">
            <?= csrfField() ?>
            <div class="row g-3 mb-4">
              <?php foreach ($activeGateways as $gw): ?>
              <div class="col-md-4">
                <input type="radio" class="btn-check" name="gateway" id="gw_<?= sanitize($gw) ?>"
                       value="<?= sanitize($gw) ?>" required>
                <label class="btn btn-outline-secondary w-100 d-flex flex-column align-items-center py-3"
                       for="gw_<?= sanitize($gw) ?>">
                  <i class="bi <?= sanitize($gatewayIcons[$gw] ?? 'bi-credit-card') ?> fs-2 mb-1"></i>
                  <?= sanitize($gatewayLabels[$gw] ?? ucfirst($gw)) ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-lock-fill me-2"></i>Pagar <?= formatCurrency($item['price']) ?>
              </button>
            </div>
            <div class="text-center mt-2 text-muted small">
              <i class="bi bi-shield-check me-1"></i>Pagamento 100% seguro
            </div>
          </form>
        </div>
      </div>

      <div class="mt-3">
        <a href="<?= sanitize(BASE_URL) ?>/client/plans" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Voltar para Planos</a>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Checkout';
require_once VIEWS_PATH . '/layout.php';
