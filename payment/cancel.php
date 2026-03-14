<?php
/**
 * Payment cancelled handler
 */
require_once dirname(__DIR__) . '/config/config.php';

$orderId = (int)get('order_id');
$gateway = get('gateway');

// Mark order as cancelled if still pending
if ($orderId) {
    try {
        $db    = Database::getInstance();
        $p     = DB_PREFIX;
        $db->execute(
            "UPDATE {$p}orders SET status='cancelled', updated_at=NOW() WHERE id=? AND status='pending'",
            [$orderId]
        );
        $db->execute(
            "UPDATE {$p}transactions SET status='failed', updated_at=NOW() WHERE order_id=? AND status='pending'",
            [$orderId]
        );
    } catch (Throwable $e) {
        error_log('Cancel handler error: ' . $e->getMessage());
    }
}

ob_start();
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6 text-center">
      <div style="font-size:72px">❌</div>
      <h2 class="fw-bold mt-3 text-danger">Pagamento Cancelado</h2>
      <p class="text-muted">Seu pagamento foi cancelado. Nenhuma cobrança foi realizada.</p>
      <p class="text-muted small">Se você enfrentou algum problema, tente novamente ou entre em contato com o suporte.</p>
      <div class="d-flex justify-content-center gap-3 mt-4">
        <a href="<?= sanitize(BASE_URL) ?>/client/plans" class="btn btn-primary">Ver Planos</a>
        <a href="<?= sanitize(BASE_URL) ?>/client" class="btn btn-outline-secondary">Meu Painel</a>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Pagamento Cancelado';
require_once VIEWS_PATH . '/layout.php';
