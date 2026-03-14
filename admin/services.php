<?php
/**
 * Admin – Services CRUD
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;
$action = get('action');
$id     = (int)get('id');
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/admin/services');
    }

    $postAction  = post('form_action');
    $type        = post('type');
    $name        = post('name');
    $description = post('description');
    $price       = (float)post('price');
    $billing     = post('billing_cycle');
    $active      = (int)(bool)post('active');
    $sortOrder   = (int)post('sort_order');

    $validTypes  = ['domain','seo','development','other'];
    $validCycles = ['one_time','monthly','yearly'];
    if (!in_array($type, $validTypes))   $type    = 'other';
    if (!in_array($billing, $validCycles)) $billing = 'one_time';

    if (!$name || $price < 0) {
        $error = 'Nome e preço são obrigatórios.';
    } else {
        if ($postAction === 'create') {
            $db->execute(
                "INSERT INTO {$p}services (type, name, description, price, billing_cycle, active, sort_order) VALUES (?,?,?,?,?,?,?)",
                [$type, $name, $description, $price, $billing, $active, $sortOrder]
            );
            flashMessage('success', 'Serviço criado.');
            redirect(BASE_URL . '/admin/services');
        } elseif ($postAction === 'edit' && $id) {
            $db->execute(
                "UPDATE {$p}services SET type=?, name=?, description=?, price=?, billing_cycle=?, active=?, sort_order=?, updated_at=NOW() WHERE id=?",
                [$type, $name, $description, $price, $billing, $active, $sortOrder, $id]
            );
            flashMessage('success', 'Serviço atualizado.');
            redirect(BASE_URL . '/admin/services');
        }
    }
} elseif ($action === 'delete' && $id) {
    $db->execute("UPDATE {$p}services SET active=0, updated_at=NOW() WHERE id=?", [$id]);
    flashMessage('success', 'Serviço desativado.');
    redirect(BASE_URL . '/admin/services');
} elseif ($action === 'activate' && $id) {
    $db->execute("UPDATE {$p}services SET active=1, updated_at=NOW() WHERE id=?", [$id]);
    flashMessage('success', 'Serviço ativado.');
    redirect(BASE_URL . '/admin/services');
}

$services = $db->fetchAll("SELECT * FROM {$p}services ORDER BY sort_order, name");
$editSvc  = null;
if ($action === 'edit' && $id) {
    $editSvc = $db->fetch("SELECT * FROM {$p}services WHERE id=?", [$id]);
}

$typeLabels  = ['domain'=>'Domínio','seo'=>'SEO','development'=>'Desenvolvimento','other'=>'Outro'];
$cycleLabels = ['one_time'=>'Pagamento Único','monthly'=>'Mensal','yearly'=>'Anual'];

ob_start();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Serviços</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#svcModal">
      <i class="bi bi-plus-lg me-1"></i>Novo Serviço
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
            <tr><th>#</th><th>Tipo</th><th>Nome</th><th>Preço</th><th>Ciclo</th><th>Status</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php foreach ($services as $svc): ?>
            <tr>
              <td><?= (int)$svc['id'] ?></td>
              <td><span class="badge bg-secondary"><?= sanitize($typeLabels[$svc['type']] ?? $svc['type']) ?></span></td>
              <td>
                <strong><?= sanitize($svc['name']) ?></strong>
                <?php if ($svc['description']): ?>
                  <br><small class="text-muted"><?= sanitize(truncate($svc['description'], 60)) ?></small>
                <?php endif; ?>
              </td>
              <td><?= formatCurrency($svc['price']) ?></td>
              <td><?= sanitize($cycleLabels[$svc['billing_cycle']] ?? $svc['billing_cycle']) ?></td>
              <td><?= $svc['active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
              <td>
                <a href="<?= sanitize(BASE_URL) ?>/admin/services?action=edit&id=<?= (int)$svc['id'] ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <?php if ($svc['active']): ?>
                  <a href="<?= sanitize(BASE_URL) ?>/admin/services?action=delete&id=<?= (int)$svc['id'] ?>"
                     class="btn btn-sm btn-outline-warning"
                     onclick="return confirm('Desativar?')"><i class="bi bi-eye-slash"></i></a>
                <?php else: ?>
                  <a href="<?= sanitize(BASE_URL) ?>/admin/services?action=activate&id=<?= (int)$svc['id'] ?>"
                     class="btn btn-sm btn-outline-success"><i class="bi bi-eye"></i></a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$services): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhum serviço cadastrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<?php
$modalTitle = $editSvc ? 'Editar Serviço' : 'Novo Serviço';
$formAction = $editSvc ? 'edit' : 'create';
?>
<div class="modal fade <?= $editSvc ? 'show' : '' ?>" id="svcModal" tabindex="-1"
     <?= $editSvc ? 'style="display:block" aria-modal="true"' : '' ?>>
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/services<?= $editSvc ? '?action=edit&id=' . (int)$editSvc['id'] : '' ?>">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= sanitize($formAction) ?>">
        <div class="modal-header">
          <h5 class="modal-title"><?= sanitize($modalTitle) ?></h5>
          <a href="<?= sanitize(BASE_URL) ?>/admin/services" class="btn-close"></a>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Tipo</label>
              <select class="form-select" name="type">
                <?php foreach ($typeLabels as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($editSvc['type'] ?? 'other') === $v ? 'selected' : '' ?>><?= sanitize($l) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Nome *</label>
              <input type="text" class="form-control" name="name" value="<?= sanitize($editSvc['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Preço *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="price"
                     value="<?= number_format((float)($editSvc['price'] ?? 0), 2, '.', '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Ciclo de Cobrança</label>
              <select class="form-select" name="billing_cycle">
                <?php foreach ($cycleLabels as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($editSvc['billing_cycle'] ?? 'one_time') === $v ? 'selected' : '' ?>><?= sanitize($l) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="active">
                <option value="1" <?= ($editSvc['active'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($editSvc['active'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Ordem</label>
              <input type="number" class="form-control" name="sort_order" value="<?= (int)($editSvc['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="description" rows="3"><?= sanitize($editSvc['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= sanitize(BASE_URL) ?>/admin/services" class="btn btn-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if ($editSvc): ?><div class="modal-backdrop fade show"></div><?php endif; ?>
<?php
$content   = ob_get_clean();
$pageTitle = 'Serviços';
require_once VIEWS_PATH . '/layout.php';
