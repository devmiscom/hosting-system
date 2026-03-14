<?php
/**
 * Client Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireClient();

$db     = Database::getInstance();
$p      = DB_PREFIX;
$userId = (int)$_SESSION['user_id'];

// Active orders with plan/service names
$activeOrders = $db->fetchAll(
    "SELECT o.*,
            COALESCE(pl.name, sv.name) AS item_name,
            COALESCE(pl.billing_cycle, sv.billing_cycle) AS item_billing
       FROM {$p}orders o
       LEFT JOIN {$p}plans pl ON pl.id = o.item_id AND o.item_type='plan'
       LEFT JOIN {$p}services sv ON sv.id = o.item_id AND o.item_type='service'
      WHERE o.user_id = ? AND o.status = 'active'
      ORDER BY o.created_at DESC",
    [$userId]
);

// Recent transactions
$recentTx = $db->fetchAll(
    "SELECT * FROM {$p}transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

$cycleLabels = ['one_time'=>'Único','monthly'=>'Mensal','yearly'=>'Anual'];

ob_start();
?>
<div class="container">
  <h3 class="fw-bold mb-4"><i class="bi bi-house me-2"></i>Meu Painel</h3>

  <!-- Greeting -->
  <div class="alert alert-primary d-flex align-items-center gap-2">
    <i class="bi bi-hand-wave fs-4"></i>
    <span>Olá, <strong><?= sanitize($_SESSION['user_name']) ?></strong>! Bem-vindo de volta.</span>
  </div>

  <!-- Quick actions -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
      <a href="<?= sanitize(BASE_URL) ?>/client/plans" class="card text-decoration-none text-center h-100 border-0 shadow-sm hover-lift">
        <div class="card-body py-4">
          <i class="bi bi-hdd-stack-fill display-5" style="color:var(--primary-color)"></i>
          <div class="mt-2 fw-semibold">Ver Planos</div>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-md-3">
      <a href="<?= sanitize(BASE_URL) ?>/client/invoices" class="card text-decoration-none text-center h-100 border-0 shadow-sm hover-lift">
        <div class="card-body py-4">
          <i class="bi bi-receipt display-5 text-success"></i>
          <div class="mt-2 fw-semibold">Faturas</div>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-md-3">
      <a href="<?= sanitize(BASE_URL) ?>/client/profile" class="card text-decoration-none text-center h-100 border-0 shadow-sm hover-lift">
        <div class="card-body py-4">
          <i class="bi bi-person-fill display-5 text-info"></i>
          <div class="mt-2 fw-semibold">Meu Perfil</div>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-md-3">
      <a href="<?= sanitize(BASE_URL) ?>/" class="card text-decoration-none text-center h-100 border-0 shadow-sm hover-lift">
        <div class="card-body py-4">
          <i class="bi bi-shop display-5 text-warning"></i>
          <div class="mt-2 fw-semibold">Mais Serviços</div>
        </div>
      </a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Active plans/orders -->
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Serviços Ativos</strong>
          <a href="<?= sanitize(BASE_URL) ?>/client/plans" class="btn btn-sm btn-outline-primary">Contratar Mais</a>
        </div>
        <?php if ($activeOrders): ?>
        <div class="list-group list-group-flush">
          <?php foreach ($activeOrders as $order): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <span class="fw-semibold"><?= sanitize($order['item_name'] ?? 'Item #' . $order['item_id']) ?></span>
                <br>
                <small class="text-muted">
                  <?= sanitize(ucfirst($order['item_type'])) ?> &bull;
                  <?= sanitize($cycleLabels[$order['billing_cycle']] ?? $order['billing_cycle']) ?> &bull;
                  <?= formatCurrency($order['amount'], $order['currency']) ?>
                </small>
              </div>
              <div class="text-end">
                <?= statusBadge('active') ?>
                <?php if ($order['next_billing_date']): ?>
                  <br><small class="text-muted">Próx. <?= formatDate($order['next_billing_date']) ?></small>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-4">
          <i class="bi bi-hdd-stack display-4 d-block mb-2 opacity-25"></i>
          Nenhum serviço ativo.
          <div class="mt-2"><a href="<?= sanitize(BASE_URL) ?>/client/plans" class="btn btn-primary btn-sm">Ver Planos</a></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent transactions -->
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Faturas Recentes</strong>
          <a href="<?= sanitize(BASE_URL) ?>/client/invoices" class="btn btn-sm btn-outline-primary">Ver Todas</a>
        </div>
        <?php if ($recentTx): ?>
        <div class="list-group list-group-flush">
          <?php foreach ($recentTx as $tx): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center small">
            <div>
              <?= sanitize(ucfirst($tx['gateway'])) ?>
              <br><span class="text-muted"><?= formatDate($tx['created_at'], 'd/m/Y') ?></span>
            </div>
            <div class="text-end">
              <?= formatCurrency($tx['amount'], $tx['currency']) ?>
              <br><?= statusBadge($tx['status']) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted small py-3">Nenhuma fatura encontrada.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Meu Painel';
require_once VIEWS_PATH . '/layout.php';
