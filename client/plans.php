<?php
/**
 * Client – Browse and purchase plans & services
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireClient();

$db  = Database::getInstance();
$p   = DB_PREFIX;

$plans    = $db->fetchAll("SELECT * FROM {$p}plans    WHERE active=1 ORDER BY sort_order, price");
$services = $db->fetchAll("SELECT * FROM {$p}services WHERE active=1 ORDER BY sort_order, price");

$cycleLabels = ['one_time'=>'único','monthly'=>'mês','yearly'=>'ano'];
$typeLabels  = ['domain'=>'Domínio','seo'=>'SEO','development'=>'Desenvolvimento','other'=>'Outro'];
$typeIcons   = ['domain'=>'bi-globe','seo'=>'bi-bar-chart-line','development'=>'bi-code-slash','other'=>'bi-tools'];

ob_start();
?>
<div class="container">
  <h3 class="fw-bold mb-4"><i class="bi bi-hdd-stack me-2"></i>Planos & Serviços</h3>

  <!-- Plans -->
  <?php if ($plans): ?>
  <h5 class="fw-semibold mb-3">Planos de Hospedagem</h5>
  <div class="row g-4 mb-5">
    <?php foreach ($plans as $plan):
      $features = json_decode($plan['features'] ?? '[]', true) ?? [];
      $cycleLabel = $cycleLabels[$plan['billing_cycle']] ?? $plan['billing_cycle'];
    ?>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header text-center text-white" style="background:var(--primary-color)">
          <h5 class="mb-0"><?= sanitize($plan['name']) ?></h5>
        </div>
        <div class="card-body">
          <div class="text-center mb-3">
            <span class="display-6 fw-bold"><?= formatCurrency($plan['price']) ?></span>
            <span class="text-muted small">/<?= sanitize($cycleLabel) ?></span>
          </div>
          <?php if ($plan['description']): ?>
            <p class="text-muted text-center small"><?= sanitize($plan['description']) ?></p>
          <?php endif; ?>
          <?php if ($features): ?>
          <ul class="list-unstyled mt-3">
            <?php foreach ($features as $feat): ?>
              <li class="py-1 border-bottom small">
                <i class="bi bi-check2-circle text-success me-2"></i><?= sanitize($feat) ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-transparent text-center">
          <a href="<?= sanitize(BASE_URL) ?>/client/checkout?type=plan&id=<?= (int)$plan['id'] ?>"
             class="btn btn-primary w-100">Contratar</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Services -->
  <?php if ($services): ?>
  <h5 class="fw-semibold mb-3">Serviços Adicionais</h5>
  <div class="row g-3">
    <?php foreach ($services as $svc):
      $cycleLabel = $cycleLabels[$svc['billing_cycle']] ?? $svc['billing_cycle'];
      $icon = $typeIcons[$svc['type']] ?? 'bi-tools';
    ?>
    <div class="col-md-4 col-lg-3">
      <div class="card h-100 shadow-sm text-center">
        <div class="card-body">
          <i class="bi <?= sanitize($icon) ?> display-4" style="color:var(--secondary-color)"></i>
          <h6 class="mt-3 fw-bold"><?= sanitize($svc['name']) ?></h6>
          <span class="badge bg-light text-dark mb-2"><?= sanitize($typeLabels[$svc['type']] ?? $svc['type']) ?></span>
          <?php if ($svc['description']): ?>
            <p class="text-muted small"><?= sanitize($svc['description']) ?></p>
          <?php endif; ?>
          <div class="fw-bold"><?= formatCurrency($svc['price']) ?> <span class="text-muted fw-normal small">/<?= sanitize($cycleLabel) ?></span></div>
        </div>
        <div class="card-footer bg-transparent">
          <a href="<?= sanitize(BASE_URL) ?>/client/checkout?type=service&id=<?= (int)$svc['id'] ?>"
             class="btn btn-outline-primary btn-sm w-100">Contratar</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$plans && !$services): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-hdd-stack display-3 d-block mb-3 opacity-25"></i>
      <p>Nenhum plano ou serviço disponível no momento.</p>
    </div>
  <?php endif; ?>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Planos';
require_once VIEWS_PATH . '/layout.php';
