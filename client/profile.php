<?php
/**
 * Client – Profile (change name, email, password)
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireClient();

$db     = Database::getInstance();
$p      = DB_PREFIX;
$userId = (int)$_SESSION['user_id'];
$user   = Auth::getCurrentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/client/profile');
    }

    $formAction = post('form_action');

    if ($formAction === 'update_profile') {
        $name  = post('name');
        $email = strtolower(post('email'));

        if (!$name)                                          $errors[] = 'Nome é obrigatório.';
        if (!$email)                                         $errors[] = 'E-mail é obrigatório.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'E-mail inválido.';

        if (!$errors) {
            // Check if email is taken by another user
            $existing = $db->fetch(
                "SELECT id FROM {$p}users WHERE email=? AND id != ? LIMIT 1",
                [$email, $userId]
            );
            if ($existing) {
                $errors[] = 'Este e-mail já está em uso.';
            } else {
                $db->execute(
                    "UPDATE {$p}users SET name=?, email=?, updated_at=NOW() WHERE id=?",
                    [$name, $email, $userId]
                );
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $user = Auth::getCurrentUser();
                flashMessage('success', 'Perfil atualizado com sucesso.');
                redirect(BASE_URL . '/client/profile');
            }
        }
    }

    if ($formAction === 'change_password') {
        $currentPass = post('current_password');
        $newPass     = post('new_password');
        $confirmPass = post('confirm_password');

        if (!$currentPass)                  $errors[] = 'Informe a senha atual.';
        if (!$newPass)                      $errors[] = 'Informe a nova senha.';
        elseif (strlen($newPass) < 8)       $errors[] = 'A nova senha deve ter ao menos 8 caracteres.';
        elseif ($newPass !== $confirmPass)  $errors[] = 'As novas senhas não coincidem.';

        if (!$errors) {
            $dbUser = $db->fetch("SELECT password FROM {$p}users WHERE id=? LIMIT 1", [$userId]);
            if (!password_verify($currentPass, $dbUser['password'])) {
                $errors[] = 'Senha atual incorreta.';
            } else {
                Auth::changePassword($userId, $newPass);
                flashMessage('success', 'Senha alterada com sucesso.');
                redirect(BASE_URL . '/client/profile');
            }
        }
    }
}

ob_start();
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <h3 class="fw-bold mb-4"><i class="bi bi-person-fill me-2"></i>Meu Perfil</h3>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Profile info -->
      <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Informações Pessoais</strong></div>
        <div class="card-body">
          <form method="POST" action="<?= sanitize(BASE_URL) ?>/client/profile">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="update_profile">
            <div class="mb-3">
              <label class="form-label">Nome completo</label>
              <input type="text" class="form-control" name="name"
                     value="<?= sanitize($user['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">E-mail</label>
              <input type="email" class="form-control" name="email"
                     value="<?= sanitize($user['email'] ?? '') ?>" required autocomplete="username">
            </div>
            <div class="text-muted small mb-3">
              Membro desde <?= formatDate($user['created_at'] ?? '', 'd/m/Y') ?>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          </form>
        </div>
      </div>

      <!-- Change password -->
      <div class="card shadow-sm">
        <div class="card-header"><strong>Alterar Senha</strong></div>
        <div class="card-body">
          <form method="POST" action="<?= sanitize(BASE_URL) ?>/client/profile">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="change_password">
            <div class="mb-3">
              <label class="form-label">Senha Atual</label>
              <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
            </div>
            <div class="mb-3">
              <label class="form-label">Nova Senha</label>
              <input type="password" class="form-control" name="new_password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmar Nova Senha</label>
              <input type="password" class="form-control" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-warning">Alterar Senha</button>
          </form>
        </div>
      </div>

      <div class="mt-3">
        <a href="<?= sanitize(BASE_URL) ?>/client" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Voltar ao Dashboard</a>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Meu Perfil';
require_once VIEWS_PATH . '/layout.php';
