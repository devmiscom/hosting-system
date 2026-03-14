<?php
/**
 * Admin Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;

// Stats
$totalClients  = $db->fetch("SELECT COUNT(*) AS c FROM {$p}users WHERE role='client'"                )['c'] ?? 0;
$activeOrders  = $db->fetch("SELECT COUNT(*) AS c FROM {$p}orders WHERE status='active'"             )['c'] ?? 0;
$totalRevenue  = $db->fetch("SELECT COALESCE(SUM(amount),0) AS s FROM {$p}transactions WHERE status='completed'")['s'] ?? 0;
$pendingOrders = $db->fetch("SELECT COUNT(*) AS c FROM {$p}orders WHERE status='pending'"            )['c'] ?? 0;

// Recent transactions
$recentTx = $db->fetchAll(
    "SELECT t.*, u.name AS user_name, u.email AS user_email
       FROM {$p}transactions t
       LEFT JOIN {$p}users u ON u.id = t.user_id
      ORDER BY t.created_at DESC LIMIT 10"
);

// Recent registrations
$recentClients = $db->fetchAll(
    "SELECT id, name, email, created_at FROM {$p}users WHERE role='client' ORDER BY created_at DESC LIMIT 5"
);

ob_start();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
    <span class="text-muted small"><?= formatDate(date('Y-m-d'), 'd/m/Y') ?></span>
  </div>

  <!-- Stats cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 bg-primary bg-opacity-10"><i class="bi bi-people-fill fs-3 text-primary"></i></div>
          <div>
            <div class="text-muted small">Clientes</div>
            <div class="fs-3 fw-bold"><?= (int)$totalClients ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 bg-success bg-opacity-10"><i class="bi bi-hdd-stack-fill fs-3 text-success"></i></div>
          <div>
            <div class="text-muted small">Planos Ativos</div>
            <div class="fs-3 fw-bold"><?= (int)$activeOrders ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 bg-warning bg-opacity-10"><i class="bi bi-clock-fill fs-3 text-warning"></i></div>
          <div>
            <div class="text-muted small">Pedidos Pendentes</div>
            <div class="fs-3 fw-bold"><?= (int)$pendingOrders ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 bg-info bg-opacity-10"><i class="bi bi-currency-dollar fs-3 text-info"></i></div>
          <div>
            <div class="text-muted small">Receita Total</div>
            <div class="fs-4 fw-bold"><?= formatCurrency($totalRevenue) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Recent transactions -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Transações Recentes</strong>
          <a href="<?= sanitize(BASE_URL) ?>/admin/transactions" class="btn btn-sm btn-outline-primary">Ver Todas</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
              <thead class="table-light">
                <tr><th>Cliente</th><th>Gateway</th><th>Valor</th><th>Status</th><th>Data</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                  <td><?= sanitize($tx['user_name']) ?><br><small class="text-muted"><?= sanitize($tx['user_email']) ?></small></td>
                  <td><?= sanitize(ucfirst($tx['gateway'])) ?></td>
                  <td><?= formatCurrency($tx['amount'], $tx['currency']) ?></td>
                  <td><?= statusBadge($tx['status']) ?></td>
                  <td class="small"><?= formatDate($tx['created_at'], 'd/m/Y H:i') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recentTx): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Nenhuma transação ainda.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent clients -->
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Novos Clientes</strong>
          <a href="<?= sanitize(BASE_URL) ?>/admin/clients" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
        <ul class="list-group list-group-flush">
          <?php foreach ($recentClients as $c): ?>
          <li class="list-group-item d-flex align-items-center gap-2">
            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                 style="width:32px;height:32px;font-size:14px"><?= strtoupper(mb_substr($c['name'],0,1)) ?></div>
            <div>
              <div class="small fw-semibold"><?= sanitize($c['name']) ?></div>
              <div class="small text-muted"><?= sanitize($c['email']) ?></div>
            </div>
          </li>
          <?php endforeach; ?>
          <?php if (!$recentClients): ?>
            <li class="list-group-item text-center text-muted small py-3">Nenhum cliente cadastrado.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Dashboard';
require_once VIEWS_PATH . '/layout.php';
