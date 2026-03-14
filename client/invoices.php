<?php
/**
 * Client – Invoices / Transactions
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireClient();

$db     = Database::getInstance();
$p      = DB_PREFIX;
$userId = (int)$_SESSION['user_id'];

$perPage = 15;
$page    = max(1, (int)get('p', '1'));
$offset  = ($page - 1) * $perPage;

$total = (int)($db->fetch(
    "SELECT COUNT(*) AS c FROM {$p}transactions WHERE user_id=?",
    [$userId]
)['c'] ?? 0);

$transactions = $db->fetchAll(
    "SELECT t.*, o.item_type, o.item_id
       FROM {$p}transactions t
       LEFT JOIN {$p}orders o ON o.id = t.order_id
      WHERE t.user_id = ?
      ORDER BY t.created_at DESC
      LIMIT {$perPage} OFFSET {$offset}",
    [$userId]
);

ob_start();
?>
<div class="container">
  <h3 class="fw-bold mb-4"><i class="bi bi-receipt me-2"></i>Minhas Faturas</h3>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Gateway</th>
              <th>Método</th>
              <th>ID Transação</th>
              <th>Valor</th>
              <th>Status</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><?= (int)$tx['id'] ?></td>
              <td><?= sanitize(ucfirst($tx['gateway'])) ?></td>
              <td><?= sanitize($tx['payment_method'] ?? '-') ?></td>
              <td class="font-monospace small"><?= sanitize(truncate($tx['gateway_transaction_id'] ?? '-', 24)) ?></td>
              <td class="fw-semibold"><?= formatCurrency($tx['amount'], $tx['currency']) ?></td>
              <td><?= statusBadge($tx['status']) ?></td>
              <td class="small"><?= formatDate($tx['created_at'], 'd/m/Y H:i') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
              <tr><td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-receipt display-4 d-block mb-2 opacity-25"></i>
                Nenhuma fatura encontrada.
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
      <?= paginationLinks($total, $perPage, $page, BASE_URL . '/client/invoices') ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="mt-3">
    <a href="<?= sanitize(BASE_URL) ?>/client" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Voltar ao Dashboard</a>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Faturas';
require_once VIEWS_PATH . '/layout.php';
