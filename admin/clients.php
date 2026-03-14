<?php
/**
 * Admin – Clients management
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;
$action = get('action');
$id     = (int)get('id');
$error  = '';

// ── Handle POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/admin/clients');
    }

    $postAction = post('form_action');

    if ($postAction === 'create') {
        $name  = post('name');
        $email = strtolower(post('email'));
        $pass  = post('password') ?: generateRandomToken(8);

        if (!$name || !$email) {
            $error = 'Nome e e-mail são obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail inválido.';
        } else {
            $exists = $db->fetch("SELECT id FROM {$p}users WHERE email=? LIMIT 1", [$email]);
            if ($exists) {
                $error = 'Este e-mail já está cadastrado.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->execute(
                    "INSERT INTO {$p}users (name, email, password, role, active) VALUES (?,?,?,'client',1)",
                    [$name, $email, $hash]
                );
                flashMessage('success', "Cliente criado. Senha temporária: {$pass}");
                redirect(BASE_URL . '/admin/clients');
            }
        }
    } elseif ($postAction === 'reset_password') {
        $targetId = (int)post('client_id');
        $newPass  = post('new_password');
        if (!$targetId) {
            flashMessage('error', 'Cliente inválido.');
        } elseif (!$newPass || strlen($newPass) < 8) {
            flashMessage('error', 'Senha deve ter ao menos 8 caracteres.');
        } else {
            Auth::changePassword($targetId, $newPass);
            flashMessage('success', 'Senha redefinida com sucesso.');
        }
        redirect(BASE_URL . '/admin/clients');
    }
} elseif ($action === 'toggle' && $id) {
    $client = $db->fetch("SELECT active FROM {$p}users WHERE id=? AND role='client' LIMIT 1", [$id]);
    if ($client) {
        $newState = $client['active'] ? 0 : 1;
        $db->execute("UPDATE {$p}users SET active=?, updated_at=NOW() WHERE id=?", [$newState, $id]);
        flashMessage('success', $newState ? 'Cliente ativado.' : 'Cliente desativado.');
    }
    redirect(BASE_URL . '/admin/clients');
}

// ── Pagination & list ─────────────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int)get('p', '1'));
$search  = get('q');
$offset  = ($page - 1) * $perPage;

$where  = "WHERE role='client'";
$params = [];
if ($search) {
    $where   .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$total   = (int)($db->fetch("SELECT COUNT(*) AS c FROM {$p}users {$where}", $params)['c'] ?? 0);
$clients = $db->fetchAll(
    "SELECT u.*, (SELECT COUNT(*) FROM {$p}orders o WHERE o.user_id=u.id AND o.status='active') AS active_orders
       FROM {$p}users u {$where}
      ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
);

ob_start();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Clientes</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientModal">
      <i class="bi bi-person-plus me-1"></i>Novo Cliente
    </button>
  </div>

  <!-- Search bar -->
  <form class="mb-3 d-flex gap-2" method="GET" action="<?= sanitize(BASE_URL) ?>/admin/clients">
    <input type="text" class="form-control" name="q" placeholder="Buscar por nome ou e-mail..."
           value="<?= sanitize($search) ?>">
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    <?php if ($search): ?>
      <a href="<?= sanitize(BASE_URL) ?>/admin/clients" class="btn btn-outline-secondary">Limpar</a>
    <?php endif; ?>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Nome</th><th>E-mail</th><th>Planos Ativos</th><th>Status</th><th>Cadastro</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php foreach ($clients as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= sanitize($c['name']) ?></td>
              <td><?= sanitize($c['email']) ?></td>
              <td><?= (int)$c['active_orders'] ?></td>
              <td><?= $c['active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
              <td class="small"><?= formatDate($c['created_at'], 'd/m/Y') ?></td>
              <td class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#resetPwdModal"
                        data-client-id="<?= (int)$c['id'] ?>"
                        data-client-name="<?= sanitize($c['name']) ?>">
                  <i class="bi bi-key"></i>
                </button>
                <a href="<?= sanitize(BASE_URL) ?>/admin/clients?action=toggle&id=<?= (int)$c['id'] ?>"
                   class="btn btn-sm <?= $c['active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                   onclick="return confirm('Confirmar?')">
                  <i class="bi bi-<?= $c['active'] ? 'person-x' : 'person-check' ?>"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
      <?= paginationLinks($total, $perPage, $page, BASE_URL . '/admin/clients' . ($search ? '?q=' . urlencode($search) : '')) ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create Client Modal -->
<div class="modal fade" id="createClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/clients">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Criar Novo Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome *</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">E-mail *</label>
            <input type="email" class="form-control" name="email" required autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label">Senha <small class="text-muted">(deixe em branco para gerar automaticamente)</small></label>
            <input type="text" class="form-control" name="password" minlength="8" autocomplete="off">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar Cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/clients">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="reset_password">
        <input type="hidden" name="client_id" id="resetClientId">
        <div class="modal-header">
          <h5 class="modal-title">Redefinir Senha – <span id="resetClientName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nova Senha *</label>
          <input type="text" class="form-control" name="new_password" required minlength="8">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Redefinir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('resetPwdModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('resetClientId').value = btn.getAttribute('data-client-id');
    document.getElementById('resetClientName').textContent = btn.getAttribute('data-client-name');
});
</script>
<?php
$content   = ob_get_clean();
$pageTitle = 'Clientes';
require_once VIEWS_PATH . '/layout.php';
