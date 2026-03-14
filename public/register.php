<?php
/**
 * Registration page (only accessible when allow_registration = yes)
 */
require_once dirname(__DIR__) . '/config/config.php';

if (Auth::isLoggedIn()) {
    redirect(Auth::isAdmin() ? BASE_URL . '/admin' : BASE_URL . '/client');
}

if (getSetting('allow_registration', 'yes') !== 'yes') {
    flashMessage('error', translate('registration_disabled'));
    redirect(BASE_URL . '/login');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        $errors[] = translate('invalid_csrf');
    } else {
        $name      = post('name');
        $email     = strtolower(post('email'));
        $password  = post('password');
        $password2 = post('password2');

        if (!$name)                                    $errors[] = translate('name_required');
        if (!$email)                                   $errors[] = translate('email_required');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = translate('email_invalid');
        if (!$password)                                $errors[] = translate('password_required');
        elseif (strlen($password) < 8)                 $errors[] = translate('password_min_length');
        elseif ($password !== $password2)              $errors[] = translate('password_mismatch');

        if (!$errors) {
            $db = Database::getInstance();
            $exists = $db->fetch(
                'SELECT id FROM ' . DB_PREFIX . 'users WHERE email = ? LIMIT 1',
                [$email]
            );
            if ($exists) {
                $errors[] = translate('email_already_used');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->execute(
                    'INSERT INTO ' . DB_PREFIX . 'users (name, email, password, role, active) VALUES (?,?,?,\'client\',1)',
                    [$name, $email, $hash]
                );
                Auth::login($email, $password);
                flashMessage('success', translate('welcome_message'));
                redirect(BASE_URL . '/client');
            }
        }
    }
}

ob_start();
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow-sm mt-4">
        <div class="card-header text-white text-center" style="background:var(--primary-color)">
          <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i><?= sanitize(translate('register')) ?></h5>
        </div>
        <div class="card-body p-4">
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <form method="POST" action="<?= sanitize(BASE_URL) ?>/register" novalidate>
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('full_name')) ?></label>
              <input type="text" class="form-control" name="name" value="<?= sanitize($name) ?>" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('email')) ?></label>
              <input type="email" class="form-control" name="email" value="<?= sanitize($email) ?>" required autocomplete="username">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('password')) ?></label>
              <input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password">
              <div class="form-text"><?= sanitize(translate('password_hint')) ?></div>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('confirm_password')) ?></label>
              <input type="password" class="form-control" name="password2" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= sanitize(translate('create_account')) ?></button>
          </form>
        </div>
        <div class="card-footer text-center small text-muted">
          <?= sanitize(translate('already_have_account')) ?>
          <a href="<?= sanitize(BASE_URL) ?>/login"><?= sanitize(translate('login')) ?></a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = translate('register');
require_once VIEWS_PATH . '/layout.php';
