<?php
/**
 * Public home page – shows active plans and services
 */
require_once dirname(__DIR__) . '/config/config.php';

$db       = Database::getInstance();
$plans    = $db->fetchAll('SELECT * FROM ' . DB_PREFIX . 'plans WHERE active = 1 ORDER BY sort_order, price');
$services = $db->fetchAll('SELECT * FROM ' . DB_PREFIX . 'services WHERE active = 1 ORDER BY sort_order, price');
$siteName = getSetting('site_name', 'Hosting System');

ob_start();
?>
<!-- Hero -->
<div class="container-fluid py-5 text-white text-center" style="background:linear-gradient(135deg,var(--primary-color),var(--secondary-color))">
  <div class="container py-3">
    <h1 class="display-5 fw-bold"><?= sanitize($siteName) ?></h1>
    <p class="lead mb-4">Hospedagem profissional, domínios, SEO e muito mais.</p>
    <?php if (!Auth::isLoggedIn()): ?>
      <a href="<?= sanitize(BASE_URL) ?>/register" class="btn btn-light btn-lg me-2">Começar Agora</a>
      <a href="<?= sanitize(BASE_URL) ?>/login" class="btn btn-outline-light btn-lg">Entrar</a>
    <?php else: ?>
      <a href="<?= sanitize(BASE_URL) ?>/client/plans" class="btn btn-light btn-lg">Ver Planos</a>
    <?php endif; ?>
  </div>
</div>

<!-- Plans section -->
<?php if ($plans): ?>
<section class="container my-5">
  <h2 class="text-center fw-bold mb-4">Planos de Hospedagem</h2>
  <div class="row g-4 justify-content-center">
    <?php foreach ($plans as $plan):
      $features = [];
      if (!empty($plan['features'])) {
          $decoded = json_decode($plan['features'], true);
          if (is_array($decoded)) $features = $decoded;
      }
      $cycleLabels = ['one_time'=>'único','monthly'=>'mês','yearly'=>'ano'];
      $cycleLabel  = $cycleLabels[$plan['billing_cycle']] ?? $plan['billing_cycle'];
    ?>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header text-center text-white" style="background:var(--primary-color)">
          <h5 class="mb-0"><?= sanitize($plan['name']) ?></h5>
        </div>
        <div class="card-body text-center">
          <div class="display-6 fw-bold my-3">
            <?= formatCurrency($plan['price']) ?>
            <span class="fs-6 fw-normal text-muted">/<?= sanitize($cycleLabel) ?></span>
          </div>
          <?php if ($plan['description']): ?>
            <p class="text-muted small"><?= sanitize($plan['description']) ?></p>
          <?php endif; ?>
          <?php if ($features): ?>
          <ul class="list-unstyled text-start mt-3">
            <?php foreach ($features as $feat): ?>
              <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?= sanitize($feat) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <div class="card-footer text-center bg-transparent">
          <?php if (Auth::isLoggedIn()): ?>
            <a href="<?= sanitize(BASE_URL) ?>/client/checkout?type=plan&id=<?= (int)$plan['id'] ?>" class="btn btn-primary w-100">Contratar</a>
          <?php else: ?>
            <a href="<?= sanitize(BASE_URL) ?>/register" class="btn btn-primary w-100">Começar</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Services section -->
<?php if ($services): ?>
<section class="container-fluid bg-light py-5">
  <div class="container">
    <h2 class="text-center fw-bold mb-4">Serviços Adicionais</h2>
    <div class="row g-4 justify-content-center">
      <?php foreach ($services as $svc):
        $typeIcons = ['domain'=>'bi-globe','seo'=>'bi-bar-chart-line','development'=>'bi-code-slash','other'=>'bi-tools'];
        $icon = $typeIcons[$svc['type']] ?? 'bi-tools';
        $cycleLabels = ['one_time'=>'único','monthly'=>'mês','yearly'=>'ano'];
        $cycleLabel  = $cycleLabels[$svc['billing_cycle']] ?? $svc['billing_cycle'];
      ?>
      <div class="col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm text-center">
          <div class="card-body">
            <i class="bi <?= sanitize($icon) ?> display-4" style="color:var(--secondary-color)"></i>
            <h5 class="mt-3"><?= sanitize($svc['name']) ?></h5>
            <?php if ($svc['description']): ?>
              <p class="text-muted small"><?= sanitize($svc['description']) ?></p>
            <?php endif; ?>
            <div class="fw-bold mt-2">
              <?= formatCurrency($svc['price']) ?>
              <span class="text-muted fw-normal small">/<?= sanitize($cycleLabel) ?></span>
            </div>
          </div>
          <div class="card-footer bg-transparent">
            <?php if (Auth::isLoggedIn()): ?>
              <a href="<?= sanitize(BASE_URL) ?>/client/checkout?type=service&id=<?= (int)$svc['id'] ?>" class="btn btn-outline-primary btn-sm w-100">Contratar</a>
            <?php else: ?>
              <a href="<?= sanitize(BASE_URL) ?>/register" class="btn btn-outline-primary btn-sm w-100">Começar</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="container my-5 text-center">
  <h3 class="fw-bold">Pronto para começar?</h3>
  <p class="text-muted">Crie sua conta agora e escolha o plano ideal para o seu negócio.</p>
  <?php if (getSetting('allow_registration', 'yes') === 'yes'): ?>
    <a href="<?= sanitize(BASE_URL) ?>/register" class="btn btn-primary btn-lg">Criar Conta Grátis</a>
  <?php else: ?>
    <a href="<?= sanitize(BASE_URL) ?>/login" class="btn btn-primary btn-lg">Entrar</a>
  <?php endif; ?>
</section>
<?php
$content   = ob_get_clean();
$pageTitle = 'Início';
require_once VIEWS_PATH . '/layout.php';
