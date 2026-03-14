<?php
/**
 * Admin – Plans CRUD
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;
$action = get('action');
$id     = (int)get('id');
$error  = '';

// ── Handle POST (create/edit) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/admin/plans');
    }

    $postAction = post('form_action');
    $name        = post('name');
    $description = post('description');
    $price       = (float)post('price');
    $billing     = post('billing_cycle');
    $active      = (int)(bool)post('active');
    $sortOrder   = (int)post('sort_order');
    $rawFeatures = post('features'); // one per line

    if (!$name || $price < 0) {
        $error = 'Nome e preço são obrigatórios.';
    } else {
        $validCycles = ['one_time', 'monthly', 'yearly'];
        if (!in_array($billing, $validCycles)) $billing = 'monthly';

        $featureLines = array_filter(array_map('trim', explode("\n", $rawFeatures)));
        $featuresJson = $featureLines ? json_encode(array_values($featureLines)) : null;

        if ($postAction === 'create') {
            $db->execute(
                "INSERT INTO {$p}plans (name, description, price, billing_cycle, features, active, sort_order) VALUES (?,?,?,?,?,?,?)",
                [$name, $description, $price, $billing, $featuresJson, $active, $sortOrder]
            );
            flashMessage('success', 'Plano criado com sucesso.');
            redirect(BASE_URL . '/admin/plans');
        } elseif ($postAction === 'edit' && $id) {
            $db->execute(
                "UPDATE {$p}plans SET name=?, description=?, price=?, billing_cycle=?, features=?, active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                [$name, $description, $price, $billing, $featuresJson, $active, $sortOrder, $id]
            );
            flashMessage('success', 'Plano atualizado.');
            redirect(BASE_URL . '/admin/plans');
        }
    }
} elseif ($action === 'delete' && $id) {
    // Soft delete (deactivate)
    $db->execute("UPDATE {$p}plans SET active=0, updated_at=NOW() WHERE id=?", [$id]);
    flashMessage('success', 'Plano desativado.');
    redirect(BASE_URL . '/admin/plans');
} elseif ($action === 'activate' && $id) {
    $db->execute("UPDATE {$p}plans SET active=1, updated_at=NOW() WHERE id=?", [$id]);
    flashMessage('success', 'Plano ativado.');
    redirect(BASE_URL . '/admin/plans');
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$plans  = $db->fetchAll("SELECT * FROM {$p}plans ORDER BY sort_order, name");
$editPlan = null;
if ($action === 'edit' && $id) {
    $editPlan = $db->fetch("SELECT * FROM {$p}plans WHERE id=?", [$id]);
}

$cycleLabels = ['one_time'=>'Pagamento Único','monthly'=>'Mensal','yearly'=>'Anual'];

ob_start();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-hdd-stack me-2"></i>Planos</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal">
      <i class="bi bi-plus-lg me-1"></i>Novo Plano
    </button>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Nome</th><th>Preço</th><th>Ciclo</th><th>Ordem</th><th>Status</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php foreach ($plans as $plan):
              $features = json_decode($plan['features'] ?? '[]', true) ?? [];
            ?>
            <tr>
              <td><?= (int)$plan['id'] ?></td>
              <td>
                <strong><?= sanitize($plan['name']) ?></strong>
                <?php if ($plan['description']): ?>
                  <br><small class="text-muted"><?= sanitize(truncate($plan['description'], 60)) ?></small>
                <?php endif; ?>
                <?php if ($features): ?>
                  <br><small class="text-muted"><?= count($features) ?> recurso(s)</small>
                <?php endif; ?>
              </td>
              <td><?= formatCurrency($plan['price']) ?></td>
              <td><?= sanitize($cycleLabels[$plan['billing_cycle']] ?? $plan['billing_cycle']) ?></td>
              <td><?= (int)$plan['sort_order'] ?></td>
              <td><?= $plan['active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
              <td>
                <a href="<?= sanitize(BASE_URL) ?>/admin/plans?action=edit&id=<?= (int)$plan['id'] ?>"
                   class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                <?php if ($plan['active']): ?>
                  <a href="<?= sanitize(BASE_URL) ?>/admin/plans?action=delete&id=<?= (int)$plan['id'] ?>"
                     class="btn btn-sm btn-outline-warning" title="Desativar"
                     onclick="return confirm('Desativar este plano?')"><i class="bi bi-eye-slash"></i></a>
                <?php else: ?>
                  <a href="<?= sanitize(BASE_URL) ?>/admin/plans?action=activate&id=<?= (int)$plan['id'] ?>"
                     class="btn btn-sm btn-outline-success" title="Ativar"><i class="bi bi-eye"></i></a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$plans): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhum plano cadastrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Plan Modal (create / edit) -->
<?php
$modalTitle  = $editPlan ? 'Editar Plano' : 'Novo Plano';
$formAction  = $editPlan ? 'edit' : 'create';
$featuresStr = '';
if ($editPlan) {
    $fArr = json_decode($editPlan['features'] ?? '[]', true);
    $featuresStr = is_array($fArr) ? implode("\n", $fArr) : '';
}
?>
<div class="modal fade <?= $editPlan ? 'show' : '' ?>" id="planModal" tabindex="-1"
     <?= $editPlan ? 'style="display:block" aria-modal="true"' : '' ?>>
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/plans<?= $editPlan ? '?action=edit&id=' . (int)$editPlan['id'] : '' ?>">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= sanitize($formAction) ?>">
        <div class="modal-header">
          <h5 class="modal-title"><?= sanitize($modalTitle) ?></h5>
          <a href="<?= sanitize(BASE_URL) ?>/admin/plans" class="btn-close"></a>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nome *</label>
              <input type="text" class="form-control" name="name" value="<?= sanitize($editPlan['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Preço (<?= sanitize(getSetting('currency','BRL')) ?>) *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="price"
                     value="<?= number_format((float)($editPlan['price'] ?? 0), 2, '.', '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Ciclo de Cobrança</label>
              <select class="form-select" name="billing_cycle">
                <?php foreach ($cycleLabels as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($editPlan['billing_cycle'] ?? 'monthly') === $v ? 'selected' : '' ?>><?= sanitize($l) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Ordem</label>
              <input type="number" class="form-control" name="sort_order" value="<?= (int)($editPlan['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="active">
                <option value="1" <?= ($editPlan['active'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($editPlan['active'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="description" rows="2"><?= sanitize($editPlan['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Recursos <small class="text-muted">(um por linha)</small></label>
              <textarea class="form-control" name="features" rows="5" placeholder="10 GB SSD&#10;5 E-mails&#10;SSL Grátis"><?= sanitize($featuresStr) ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= sanitize(BASE_URL) ?>/admin/plans" class="btn btn-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if ($editPlan): ?><div class="modal-backdrop fade show"></div><?php endif; ?>
<?php
$content   = ob_get_clean();
$pageTitle = 'Planos';
require_once VIEWS_PATH . '/layout.php';
