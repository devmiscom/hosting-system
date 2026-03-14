<?php
/**
 * Admin – System Settings
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/admin/settings');
    }

    $fields = [
        'site_name', 'primary_color', 'secondary_color', 'logo_url',
        'allow_registration', 'currency', 'default_language',
        'active_gateways',
        'mp_access_token',
        'paypal_client_id', 'paypal_secret', 'paypal_mode',
        'stripe_secret_key', 'stripe_webhook_secret',
    ];

    // active_gateways comes as an array of checkboxes
    $activeGateways = $_POST['active_gateways'] ?? [];
    if (is_array($activeGateways)) {
        $_POST['active_gateways'] = implode(',', array_map('trim', $activeGateways));
    }

    $stmt = $db->getConnection()->prepare(
        "INSERT INTO {$p}settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );

    foreach ($fields as $field) {
        $value = isset($_POST[$field]) ? trim((string)$_POST[$field]) : '';
        $stmt->execute([$field, $value]);
    }

    clearSettingsCache();
    flashMessage('success', 'Configurações salvas com sucesso.');
    redirect(BASE_URL . '/admin/settings');
}

// Load all current settings
$rows = $db->fetchAll("SELECT setting_key, setting_value FROM {$p}settings");
$settings = [];
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$currencies = ['BRL'=>'Real Brasileiro (BRL)','USD'=>'Dólar (USD)','EUR'=>'Euro (EUR)','GBP'=>'Libra (GBP)'];
$languages  = $db->fetchAll("SELECT code, name FROM {$p}languages WHERE active=1 ORDER BY name");
$gateways   = ['mercadopago'=>'MercadoPago','paypal'=>'PayPal','stripe'=>'Stripe'];
$activeGwArr= array_filter(array_map('trim', explode(',', $settings['active_gateways'] ?? 'mercadopago')));

function sv(array $s, string $key, string $default = ''): string {
    return htmlspecialchars($s[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<div class="container-fluid">
  <h3 class="fw-bold mb-4"><i class="bi bi-gear-fill me-2"></i>Configurações do Sistema</h3>

  <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/settings">
    <?= csrfField() ?>

    <!-- General -->
    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>Geral</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome do Site</label>
            <input type="text" class="form-control" name="site_name" value="<?= sv($settings,'site_name','Hosting System') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">URL do Logo</label>
            <input type="url" class="form-control" name="logo_url" value="<?= sv($settings,'logo_url') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Cor Primária</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" id="colorPrimary"
                     value="<?= sv($settings,'primary_color','#0d6efd') ?>" oninput="document.getElementById('primaryHex').value=this.value">
              <input type="text" class="form-control" id="primaryHex" name="primary_color"
                     value="<?= sv($settings,'primary_color','#0d6efd') ?>" maxlength="7"
                     oninput="document.getElementById('colorPrimary').value=this.value">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cor Secundária</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" id="colorSecondary"
                     value="<?= sv($settings,'secondary_color','#6610f2') ?>" oninput="document.getElementById('secondaryHex').value=this.value">
              <input type="text" class="form-control" id="secondaryHex" name="secondary_color"
                     value="<?= sv($settings,'secondary_color','#6610f2') ?>" maxlength="7"
                     oninput="document.getElementById('colorSecondary').value=this.value">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Permitir Auto-cadastro</label>
            <select class="form-select" name="allow_registration">
              <option value="yes" <?= ($settings['allow_registration'] ?? 'yes') === 'yes' ? 'selected' : '' ?>>Sim</option>
              <option value="no"  <?= ($settings['allow_registration'] ?? 'yes') === 'no'  ? 'selected' : '' ?>>Não (admin cria contas)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Moeda</label>
            <select class="form-select" name="currency">
              <?php foreach ($currencies as $code => $label): ?>
                <option value="<?= $code ?>" <?= ($settings['currency'] ?? 'BRL') === $code ? 'selected' : '' ?>><?= sanitize($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Idioma Padrão</label>
            <select class="form-select" name="default_language">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= sanitize($lang['code']) ?>" <?= ($settings['default_language'] ?? 'pt_BR') === $lang['code'] ? 'selected' : '' ?>>
                  <?= sanitize($lang['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment gateways -->
    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>Gateways de Pagamento</strong></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Gateways Ativos</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($gateways as $gCode => $gLabel): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="active_gateways[]"
                     id="gw_<?= $gCode ?>" value="<?= $gCode ?>"
                     <?= in_array($gCode, $activeGwArr) ? 'checked' : '' ?>>
              <label class="form-check-label" for="gw_<?= $gCode ?>"><?= sanitize($gLabel) ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <hr>
        <h6 class="text-muted">MercadoPago</h6>
        <div class="mb-3">
          <label class="form-label">Access Token</label>
          <input type="password" class="form-control" name="mp_access_token" autocomplete="off"
                 value="<?= sv($settings,'mp_access_token') ?>" placeholder="APP_USR-...">
        </div>

        <hr>
        <h6 class="text-muted">PayPal</h6>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Client ID</label>
            <input type="password" class="form-control" name="paypal_client_id" autocomplete="off"
                   value="<?= sv($settings,'paypal_client_id') ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Secret</label>
            <input type="password" class="form-control" name="paypal_secret" autocomplete="off"
                   value="<?= sv($settings,'paypal_secret') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Modo</label>
            <select class="form-select" name="paypal_mode">
              <option value="sandbox" <?= ($settings['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
              <option value="live"    <?= ($settings['paypal_mode'] ?? 'sandbox') === 'live'    ? 'selected' : '' ?>>Produção</option>
            </select>
          </div>
        </div>

        <hr>
        <h6 class="text-muted">Stripe</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Secret Key</label>
            <input type="password" class="form-control" name="stripe_secret_key" autocomplete="off"
                   value="<?= sv($settings,'stripe_secret_key') ?>" placeholder="sk_...">
          </div>
          <div class="col-md-6">
            <label class="form-label">Webhook Secret</label>
            <input type="password" class="form-control" name="stripe_webhook_secret" autocomplete="off"
                   value="<?= sv($settings,'stripe_webhook_secret') ?>" placeholder="whsec_...">
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Configurações</button>
    </div>
  </form>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Configurações';
require_once VIEWS_PATH . '/layout.php';
