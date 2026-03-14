<?php
/**
 * Main layout template
 * Variables expected from the including file:
 *   $pageTitle (string)     – <title> suffix
 *   $content   (string)     – rendered page HTML
 *   $bodyClass (string)     – optional extra body class
 */

$siteName      = defined('DB_HOST') ? getSetting('site_name', 'Hosting System') : 'Hosting System';
$primaryColor  = defined('DB_HOST') ? getSetting('primary_color', '#0d6efd')    : '#0d6efd';
$secondaryColor= defined('DB_HOST') ? getSetting('secondary_color', '#6610f2')  : '#6610f2';
$logoUrl       = defined('DB_HOST') ? getSetting('logo_url', '')                : '';

$isAdmin  = Auth::isAdmin();
$isClient = Auth::isClient();
$user     = Auth::isLoggedIn() ? Auth::getCurrentUser() : null;
$flashes  = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= sanitize($pageTitle ?? $siteName) ?> | <?= sanitize($siteName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= sanitize(BASE_URL) ?>/assets/css/style.css">
  <style>
    :root {
      --primary-color: <?= sanitize($primaryColor) ?>;
      --secondary-color: <?= sanitize($secondaryColor) ?>;
    }
  </style>
</head>
<body class="<?= sanitize($bodyClass ?? '') ?>">

<!-- ── Navbar ── -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:var(--primary-color)">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= sanitize(BASE_URL) ?>/">
      <?php if ($logoUrl): ?>
        <img src="<?= sanitize($logoUrl) ?>" alt="Logo" height="30" class="me-1">
      <?php else: ?>
        <i class="bi bi-cloud-fill"></i>
      <?php endif; ?>
      <?= sanitize($siteName) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">

      <?php if ($isAdmin): ?>
      <!-- Admin navigation -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/admin"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-box-seam"></i> Catálogo</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= sanitize(BASE_URL) ?>/admin/plans"><i class="bi bi-hdd-stack"></i> Planos</a></li>
            <li><a class="dropdown-item" href="<?= sanitize(BASE_URL) ?>/admin/services"><i class="bi bi-gear"></i> Serviços</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/admin/clients"><i class="bi bi-people"></i> Clientes</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/admin/transactions"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-sliders"></i> Config.</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= sanitize(BASE_URL) ?>/admin/settings"><i class="bi bi-gear-fill"></i> Configurações</a></li>
            <li><a class="dropdown-item" href="<?= sanitize(BASE_URL) ?>/admin/translations"><i class="bi bi-translate"></i> Traduções</a></li>
          </ul>
        </li>
      </ul>

      <?php elseif ($isClient): ?>
      <!-- Client navigation -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/client"><i class="bi bi-house"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/client/plans"><i class="bi bi-hdd-stack"></i> Planos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/client/invoices"><i class="bi bi-receipt"></i> Faturas</a></li>
      </ul>

      <?php else: ?>
      <!-- Public navigation -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/">Início</a></li>
      </ul>
      <?php endif; ?>

      <!-- Right side -->
      <ul class="navbar-nav ms-auto">
        <?php if ($user): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?= sanitize($user['name']) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($isClient): ?>
                <li><a class="dropdown-item" href="<?= sanitize(BASE_URL) ?>/client/profile"><i class="bi bi-person"></i> Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
              <?php endif; ?>
              <li><a class="dropdown-item text-danger" href="<?= sanitize(BASE_URL) ?>/logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= sanitize(BASE_URL) ?>/login"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
          <?php if (defined('DB_HOST') && getSetting('allow_registration', 'yes') === 'yes'): ?>
            <li class="nav-item"><a class="nav-link btn btn-outline-light btn-sm ms-2 px-3" href="<?= sanitize(BASE_URL) ?>/register">Cadastrar</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

    </div>
  </div>
</nav>

<!-- ── Flash messages ── -->
<?php if ($flashes): ?>
<div class="container mt-3" id="flash-messages">
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= sanitize($f['type'] === 'error' ? 'danger' : $f['type']) ?> alert-dismissible fade show" role="alert">
      <?= sanitize($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Page content ── -->
<main class="py-4">
  <?= $content ?? '' ?>
</main>

<!-- ── Footer ── -->
<footer class="py-3 mt-auto border-top">
  <div class="container text-center text-muted small">
    &copy; <?= date('Y') ?> <?= sanitize($siteName) ?>. Todos os direitos reservados.
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= sanitize(BASE_URL) ?>/assets/js/main.js"></script>
</body>
</html>
