<?php
/**
 * Admin – Transactions list with filters
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;

// Filters
$filterGateway = get('gateway');
$filterStatus  = get('status');
$filterDate    = get('date');
$perPage       = 25;
$page          = max(1, (int)get('p', '1'));
$offset        = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];

if ($filterGateway) {
    $where   .= ' AND t.gateway = ?';
    $params[] = $filterGateway;
}
if ($filterStatus) {
    $where   .= ' AND t.status = ?';
    $params[] = $filterStatus;
}
if ($filterDate) {
    $where   .= ' AND DATE(t.created_at) = ?';
    $params[] = $filterDate;
}

$total = (int)($db->fetch(
    "SELECT COUNT(*) AS c FROM {$p}transactions t {$where}",
    $params
)['c'] ?? 0);

$transactions = $db->fetchAll(
    "SELECT t.*, u.name AS user_name, u.email AS user_email
       FROM {$p}transactions t
       LEFT JOIN {$p}users u ON u.id = t.user_id
      {$where}
      ORDER BY t.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Gateway and status options for filter dropdowns
$gateways = $db->fetchAll("SELECT DISTINCT gateway FROM {$p}transactions ORDER BY gateway");
$statuses = ['pending','completed','failed','refunded'];

// Revenue summary
$totalRevenue  = $db->fetch("SELECT COALESCE(SUM(amount),0) AS s FROM {$p}transactions WHERE status='completed'"         )['s'] ?? 0;
$pendingAmount = $db->fetch("SELECT COALESCE(SUM(amount),0) AS s FROM {$p}transactions WHERE status='pending'"           )['s'] ?? 0;
$refundAmount  = $db->fetch("SELECT COALESCE(SUM(amount),0) AS s FROM {$p}transactions WHERE status='refunded'"          )['s'] ?? 0;

// Build base URL for pagination
$qParts = [];
if ($filterGateway) $qParts[] = 'gateway=' . urlencode($filterGateway);
if ($filterStatus)  $qParts[] = 'status='  . urlencode($filterStatus);
if ($filterDate)    $qParts[] = 'date='    . urlencode($filterDate);
$paginationBase = BASE_URL . '/admin/transactions' . ($qParts ? '?' . implode('&', $qParts) : '');

ob_start();
?>
<div class="container-fluid">
  <h3 class="fw-bold mb-4"><i class="bi bi-receipt me-2"></i>Transações</h3>

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="text-muted small">Receita Confirmada</div>
          <div class="fs-4 fw-bold text-success"><?= formatCurrency($totalRevenue) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="text-muted small">Pendente</div>
          <div class="fs-4 fw-bold text-warning"><?= formatCurrency($pendingAmount) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
          <div class="text-muted small">Reembolsado</div>
          <div class="fs-4 fw-bold text-info"><?= formatCurrency($refundAmount) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form class="row g-2 mb-3" method="GET" action="<?= sanitize(BASE_URL) ?>/admin/transactions">
    <div class="col-md-3">
      <select class="form-select" name="gateway">
        <option value="">Todos os Gateways</option>
        <?php foreach ($gateways as $g): ?>
          <option value="<?= sanitize($g['gateway']) ?>" <?= $filterGateway === $g['gateway'] ? 'selected' : '' ?>>
            <?= sanitize(ucfirst($g['gateway'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select" name="status">
        <option value="">Todos os Status</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= sanitize(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <input type="date" class="form-control" name="date" value="<?= sanitize($filterDate) ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
      <a href="<?= sanitize(BASE_URL) ?>/admin/transactions" class="btn btn-outline-secondary">Limpar</a>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Cliente</th>
              <th>Gateway</th>
              <th>ID Gateway</th>
              <th>Método</th>
              <th>Valor</th>
              <th>Status</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><?= (int)$tx['id'] ?></td>
              <td>
                <?= sanitize($tx['user_name']) ?>
                <br><small class="text-muted"><?= sanitize($tx['user_email']) ?></small>
              </td>
              <td><?= sanitize(ucfirst($tx['gateway'])) ?></td>
              <td><small class="text-muted font-monospace"><?= sanitize(truncate($tx['gateway_transaction_id'] ?? '-', 20)) ?></small></td>
              <td><?= sanitize($tx['payment_method'] ?? '-') ?></td>
              <td class="fw-semibold"><?= formatCurrency($tx['amount'], $tx['currency']) ?></td>
              <td><?= statusBadge($tx['status']) ?></td>
              <td class="small"><?= formatDate($tx['created_at'], 'd/m/Y H:i') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma transação encontrada.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
      <?= paginationLinks($total, $perPage, $page, $paginationBase) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Transações';
require_once VIEWS_PATH . '/layout.php';
