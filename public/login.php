<?php
/**
 * Login page
 */
require_once dirname(__DIR__) . '/config/config.php';

// Already logged in
if (Auth::isLoggedIn()) {
    redirect(Auth::isAdmin() ? BASE_URL . '/admin' : BASE_URL . '/client');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        $error = translate('invalid_csrf');
    } else {
        $email    = post('email');
        $password = post('password');

        if (!$email || !$password) {
            $error = translate('fill_all_fields');
        } elseif (Auth::login($email, $password)) {
            $dest = Auth::isAdmin() ? BASE_URL . '/admin' : BASE_URL . '/client';
            redirect($dest);
        } else {
            $error = translate('invalid_credentials');
        }
    }
}

ob_start();
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow-sm mt-4">
        <div class="card-header text-white text-center" style="background:var(--primary-color)">
          <h5 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i><?= sanitize(translate('login')) ?></h5>
        </div>
        <div class="card-body p-4">
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
          <?php endif; ?>
          <form method="POST" action="<?= sanitize(BASE_URL) ?>/login" novalidate>
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('email')) ?></label>
              <input type="email" class="form-control" name="email"
                     value="<?= sanitize(post('email')) ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= sanitize(translate('password')) ?></label>
              <input type="password" class="form-control" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= sanitize(translate('login')) ?></button>
          </form>
        </div>
        <?php if (getSetting('allow_registration', 'yes') === 'yes'): ?>
        <div class="card-footer text-center small text-muted">
          <?= sanitize(translate('no_account')) ?>
          <a href="<?= sanitize(BASE_URL) ?>/register"><?= sanitize(translate('register')) ?></a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = translate('login');
require_once VIEWS_PATH . '/layout.php';
