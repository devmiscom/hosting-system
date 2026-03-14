<?php
/**
 * Admin – Translations Manager
 */
require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$p  = DB_PREFIX;

// ── Handle POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken(post('csrf_token'))) {
        flashMessage('error', translate('invalid_csrf'));
        redirect(BASE_URL . '/admin/translations');
    }

    $postAction = post('form_action');

    if ($postAction === 'add_language') {
        $code  = preg_replace('/[^a-zA-Z_]/', '', post('lang_code'));
        $name  = post('lang_name');
        $isDefault = (int)(bool)post('is_default');
        if ($code && $name) {
            if ($isDefault) {
                $db->execute("UPDATE {$p}languages SET is_default=0");
            }
            $db->execute(
                "INSERT IGNORE INTO {$p}languages (code, name, active, is_default) VALUES (?,?,1,?)",
                [$code, $name, $isDefault]
            );
            if ($isDefault) {
                $db->execute("UPDATE {$p}settings SET setting_value=?, updated_at=NOW() WHERE setting_key='default_language'", [$code]);
                clearSettingsCache();
            }
            flashMessage('success', 'Idioma adicionado.');
        }
        redirect(BASE_URL . '/admin/translations');
    }

    if ($postAction === 'save_translations') {
        $langId = (int)post('lang_id');
        if ($langId) {
            $strings = $_POST['strings'] ?? [];
            $stmt    = $db->getConnection()->prepare(
                "INSERT INTO {$p}translations (language_id, string_key, string_value)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE string_value=VALUES(string_value), updated_at=NOW()"
            );
            foreach ($strings as $key => $value) {
                $key   = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$key));
                $value = trim((string)$value);
                if ($key && strlen($key) <= 255) {
                    $stmt->execute([$langId, $key, $value]);
                }
            }
            clearTranslationsCache();
            flashMessage('success', 'Traduções salvas.');
        }
        redirect(BASE_URL . '/admin/translations?lang=' . $langId);
    }

    if ($postAction === 'add_string') {
        $langId   = (int)post('lang_id');
        $rawKey   = strtolower(trim(post('new_key')));
        $key      = preg_replace('/[^a-z0-9_]/', '', $rawKey);
        $value    = trim(post('new_value'));
        if (!$key) {
            flashMessage('error', 'Chave inválida. Use apenas letras minúsculas, números e underscore (_).');
        } elseif ($langId) {
            $db->execute(
                "INSERT INTO {$p}translations (language_id, string_key, string_value)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE string_value=VALUES(string_value), updated_at=NOW()",
                [$langId, $key, $value]
            );
            clearTranslationsCache();
            flashMessage('success', 'String adicionada.');
        }
        redirect(BASE_URL . '/admin/translations?lang=' . $langId);
    }
}

// ── Load data ──────────────────────────────────────────────────────────────────
$languages     = $db->fetchAll("SELECT * FROM {$p}languages ORDER BY name");
$selectedLangId = (int)get('lang');

$translations  = [];
$defaultStrings= [];
$activeLang    = null;

// Load default strings from file
$langFile = LANG_PATH . '/pt_BR.php';
if (file_exists($langFile)) {
    $defaultStrings = require $langFile;
    if (!is_array($defaultStrings)) $defaultStrings = [];
}

if ($selectedLangId) {
    $activeLang = $db->fetch("SELECT * FROM {$p}languages WHERE id=?", [$selectedLangId]);
    if ($activeLang) {
        $rows = $db->fetchAll(
            "SELECT string_key, string_value FROM {$p}translations WHERE language_id=?",
            [$selectedLangId]
        );
        foreach ($rows as $r) {
            $translations[$r['string_key']] = $r['string_value'];
        }
    }
}

ob_start();
?>
<div class="container-fluid">
  <h3 class="fw-bold mb-4"><i class="bi bi-translate me-2"></i>Traduções</h3>

  <div class="row g-4">
    <!-- Left: languages list + add language -->
    <div class="col-lg-3">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Idiomas</strong></div>
        <div class="list-group list-group-flush">
          <?php foreach ($languages as $lang): ?>
          <a href="<?= sanitize(BASE_URL) ?>/admin/translations?lang=<?= (int)$lang['id'] ?>"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selectedLangId == $lang['id'] ? 'active' : '' ?>">
            <?= sanitize($lang['name']) ?>
            <?php if ($lang['is_default']): ?>
              <span class="badge bg-success">Padrão</span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
          <?php if (!$languages): ?>
            <div class="list-group-item text-muted small">Nenhum idioma.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Add language form -->
      <div class="card shadow-sm">
        <div class="card-header"><strong>Adicionar Idioma</strong></div>
        <div class="card-body">
          <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/translations">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="add_language">
            <div class="mb-2">
              <label class="form-label form-label-sm">Código <small class="text-muted">(ex: en_US)</small></label>
              <input type="text" class="form-control form-control-sm" name="lang_code" required pattern="[a-zA-Z_]+">
            </div>
            <div class="mb-2">
              <label class="form-label form-label-sm">Nome</label>
              <input type="text" class="form-control form-control-sm" name="lang_name" required>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" value="1">
              <label class="form-check-label form-label-sm" for="isDefault">Definir como padrão</label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary w-100">Adicionar</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Right: translation editor -->
    <div class="col-lg-9">
      <?php if ($activeLang): ?>
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Editar: <?= sanitize($activeLang['name']) ?> (<?= sanitize($activeLang['code']) ?>)</strong>
          </div>
          <div class="card-body">

            <!-- Add new string -->
            <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/translations" class="row g-2 mb-4 border-bottom pb-4">
              <?= csrfField() ?>
              <input type="hidden" name="form_action" value="add_string">
              <input type="hidden" name="lang_id" value="<?= (int)$activeLang['id'] ?>">
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="new_key" placeholder="chave_nova (snake_case)" pattern="[a-z0-9_]+">
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" name="new_value" placeholder="Tradução...">
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-plus"></i> Adicionar</button>
              </div>
            </form>

            <form method="POST" action="<?= sanitize(BASE_URL) ?>/admin/translations">
              <?= csrfField() ?>
              <input type="hidden" name="form_action" value="save_translations">
              <input type="hidden" name="lang_id" value="<?= (int)$activeLang['id'] ?>">

              <?php
              // Merge: all default strings + any extra DB-only strings
              $allKeys = array_unique(array_merge(array_keys($defaultStrings), array_keys($translations)));
              sort($allKeys);
              ?>
              <div class="row row-cols-1 row-cols-md-2 g-2">
                <?php foreach ($allKeys as $key): ?>
                <div class="col">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text font-monospace" style="min-width:180px;max-width:180px;overflow:hidden;font-size:11px"
                          title="<?= sanitize($key) ?>"><?= sanitize($key) ?></span>
                    <input type="text" class="form-control"
                           name="strings[<?= sanitize($key) ?>]"
                           value="<?= sanitize($translations[$key] ?? $defaultStrings[$key] ?? '') ?>"
                           placeholder="<?= sanitize($defaultStrings[$key] ?? '') ?>">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Traduções</button>
              </div>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert-info">Selecione um idioma para editar suas traduções.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Traduções';
require_once VIEWS_PATH . '/layout.php';
