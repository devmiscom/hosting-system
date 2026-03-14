<?php
/**
 * Installation Wizard
 * Multi-step setup: requirements → database → tables + admin → complete
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('LANG_PATH', ROOT_PATH . '/lang');
define('BASE_URL', '');

if (session_status() === PHP_SESSION_NONE) {
    session_name('HOSTINGSYS_INSTALL');
    session_start();
}

// Block install if already installed
if (file_exists(CONFIG_PATH . '/installed.php')) {
    header('Location: /');
    exit;
}

$step  = (int)($_GET['step'] ?? $_SESSION['install_step'] ?? 1);
$error = '';
$info  = [];

// ─── Helper: test DB connection ───────────────────────────────────────────────
function testDbConnection(string $host, string $db, string $user, string $pass, int $port = 3306): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ─── Helper: run schema ───────────────────────────────────────────────────────
function runSchema(PDO $pdo, string $prefix): void {
    $p = $prefix;
    $statements = [
        "CREATE TABLE IF NOT EXISTS `{$p}settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) UNIQUE NOT NULL,
            `setting_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin','client') DEFAULT 'client',
            `active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}plans` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `price` DECIMAL(10,2) NOT NULL,
            `billing_cycle` ENUM('one_time','monthly','yearly') DEFAULT 'monthly',
            `features` JSON,
            `active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}services` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `type` ENUM('domain','seo','development','other') DEFAULT 'other',
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `price` DECIMAL(10,2) NOT NULL,
            `billing_cycle` ENUM('one_time','monthly','yearly') DEFAULT 'one_time',
            `active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `item_type` ENUM('plan','service') NOT NULL,
            `item_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(10) DEFAULT 'BRL',
            `billing_cycle` ENUM('one_time','monthly','yearly') DEFAULT 'one_time',
            `status` ENUM('pending','active','cancelled','expired') DEFAULT 'pending',
            `gateway` VARCHAR(50),
            `gateway_order_id` VARCHAR(255),
            `next_billing_date` DATE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `{$p}users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(10) DEFAULT 'BRL',
            `gateway` VARCHAR(50) NOT NULL,
            `gateway_transaction_id` VARCHAR(255),
            `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
            `payment_method` VARCHAR(100),
            `metadata` JSON,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`order_id`) REFERENCES `{$p}orders`(`id`),
            FOREIGN KEY (`user_id`) REFERENCES `{$p}users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}languages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(10) UNIQUE NOT NULL,
            `name` VARCHAR(50) NOT NULL,
            `active` TINYINT(1) DEFAULT 1,
            `is_default` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}translations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `language_id` INT NOT NULL,
            `string_key` VARCHAR(255) NOT NULL,
            `string_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_lang_key` (`language_id`, `string_key`),
            FOREIGN KEY (`language_id`) REFERENCES `{$p}languages`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

// ─── Helper: seed default settings ───────────────────────────────────────────
function seedSettings(PDO $pdo, string $prefix, string $siteName): void {
    $p        = $prefix;
    $defaults = [
        'site_name'          => $siteName,
        'primary_color'      => '#0d6efd',
        'secondary_color'    => '#6610f2',
        'logo_url'           => '',
        'allow_registration' => 'yes',
        'currency'           => 'BRL',
        'default_language'   => 'pt_BR',
        'active_gateways'    => 'mercadopago',
        'mp_access_token'    => '',
        'paypal_client_id'   => '',
        'paypal_secret'      => '',
        'paypal_mode'        => 'sandbox',
        'stripe_secret_key'  => '',
        'stripe_webhook_secret' => '',
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO `{$p}settings` (setting_key, setting_value) VALUES (?, ?)"
    );
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Seed default language
    $pdo->prepare("INSERT IGNORE INTO `{$p}languages` (code, name, active, is_default) VALUES ('pt_BR','Português (Brasil)',1,1)")->execute();
}

// ─── Process POST actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_db') {
        $dbHost    = trim($_POST['db_host'] ?? 'localhost');
        $dbPort    = (int)($_POST['db_port'] ?? 3306);
        $dbName    = trim($_POST['db_name'] ?? '');
        $dbUser    = trim($_POST['db_user'] ?? '');
        $dbPass    = $_POST['db_pass'] ?? '';
        $rawPrefix = $_POST['db_prefix'] ?? 'hs_';
        $dbPfx     = preg_replace('/[^a-z0-9_]/', '', strtolower($rawPrefix));

        if (!$dbName || !$dbUser) {
            $error = 'Preencha o nome do banco e o usuário.';
        } elseif (!$dbPfx) {
            $error = 'Prefixo inválido. Use apenas letras minúsculas, números e underscore.';
        } elseif ($rawPrefix !== $dbPfx) {
            $error = 'Prefixo inválido ("' . htmlspecialchars($rawPrefix) . '"). Use apenas letras minúsculas, números e _ (resultado seria: "' . $dbPfx . '").';
        } else {
            try {
                $pdo = testDbConnection($dbHost, $dbName, $dbUser, $dbPass, $dbPort);
                $_SESSION['install_db'] = compact('dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass', 'dbPfx');
                $_SESSION['install_step'] = 3;
                header('Location: ?step=3');
                exit;
            } catch (PDOException $e) {
                $error = 'Falha na conexão: ' . htmlspecialchars($e->getMessage());
            }
        }
        $step = 2;

    } elseif ($action === 'create_tables') {
        $db    = $_SESSION['install_db'] ?? [];
        $aName = trim($_POST['admin_name'] ?? '');
        $aEmail= strtolower(trim($_POST['admin_email'] ?? ''));
        $aPass = $_POST['admin_pass'] ?? '';
        $aPass2= $_POST['admin_pass2'] ?? '';
        $sName = trim($_POST['site_name'] ?? 'Hosting System');

        if (!$aName || !$aEmail || !$aPass) {
            $error = 'Preencha todos os campos do administrador.';
            $step  = 3;
        } elseif (!filter_var($aEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail inválido.';
            $step  = 3;
        } elseif ($aPass !== $aPass2) {
            $error = 'As senhas não coincidem.';
            $step  = 3;
        } elseif (strlen($aPass) < 8) {
            $error = 'A senha deve ter ao menos 8 caracteres.';
            $step  = 3;
        } else {
            try {
                $pdo = testDbConnection($db['dbHost'], $db['dbName'], $db['dbUser'], $db['dbPass'], $db['dbPort']);
                runSchema($pdo, $db['dbPfx']);
                seedSettings($pdo, $db['dbPfx'], $sName);

                $hash = password_hash($aPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare(
                    "INSERT INTO `{$db['dbPfx']}users` (name, email, password, role, active) VALUES (?,?,?,'admin',1)
                     ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin'"
                );
                $stmt->execute([$aName, $aEmail, $hash]);

                // Write db.php
                $dbConfig = "<?php\n"
                    . "define('DB_HOST',   " . var_export($db['dbHost'], true) . ");\n"
                    . "define('DB_PORT',   " . var_export($db['dbPort'], true) . ");\n"
                    . "define('DB_NAME',   " . var_export($db['dbName'], true) . ");\n"
                    . "define('DB_USER',   " . var_export($db['dbUser'], true) . ");\n"
                    . "define('DB_PASS',   " . var_export($db['dbPass'], true) . ");\n"
                    . "define('DB_PREFIX', " . var_export($db['dbPfx'], true) . ");\n";

                file_put_contents(CONFIG_PATH . '/db.php', $dbConfig);

                // Write installed.php
                file_put_contents(CONFIG_PATH . '/installed.php', "<?php\n// Installation completed on " . date('Y-m-d H:i:s') . "\n");

                $_SESSION['install_complete'] = true;
                $_SESSION['install_step']     = 4;
                header('Location: ?step=4');
                exit;
            } catch (Throwable $e) {
                $error = 'Erro ao criar tabelas: ' . htmlspecialchars($e->getMessage());
                $step  = 3;
            }
        }
    }
}

// ─── Requirement checks ───────────────────────────────────────────────────────
function getRequirements(): array {
    return [
        ['label' => 'PHP >= 8.0',        'ok' => PHP_VERSION_ID >= 80000,                'value' => PHP_VERSION],
        ['label' => 'Extensão PDO',       'ok' => extension_loaded('pdo'),                'value' => extension_loaded('pdo') ? 'OK' : 'Faltando'],
        ['label' => 'PDO MySQL',          'ok' => extension_loaded('pdo_mysql'),           'value' => extension_loaded('pdo_mysql') ? 'OK' : 'Faltando'],
        ['label' => 'Extensão cURL',      'ok' => extension_loaded('curl'),               'value' => extension_loaded('curl') ? 'OK' : 'Faltando'],
        ['label' => 'Extensão JSON',      'ok' => extension_loaded('json'),               'value' => extension_loaded('json') ? 'OK' : 'Faltando'],
        ['label' => 'Extensão mbstring',  'ok' => extension_loaded('mbstring'),           'value' => extension_loaded('mbstring') ? 'OK' : 'Faltando'],
        ['label' => 'config/ gravável',   'ok' => is_writable(CONFIG_PATH),               'value' => is_writable(CONFIG_PATH) ? 'OK' : 'Sem permissão'],
    ];
}

$requirements  = getRequirements();
$reqPassed     = array_reduce($requirements, fn($c, $r) => $c && $r['ok'], true);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação – Hosting System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body { background:#f0f4f8; }
.wizard-card { max-width:680px; margin:60px auto; }
.step-indicator span { display:inline-block; width:32px; height:32px; border-radius:50%; line-height:32px; text-align:center; font-weight:700; }
.step-indicator span.done { background:#198754; color:#fff; }
.step-indicator span.active { background:#0d6efd; color:#fff; }
.step-indicator span.todo { background:#dee2e6; color:#6c757d; }
</style>
</head>
<body>
<div class="container wizard-card">
  <div class="text-center mb-4">
    <h2 class="fw-bold">🚀 Hosting System – Instalação</h2>
    <div class="step-indicator mt-3 d-flex justify-content-center gap-3">
      <?php for ($i=1;$i<=4;$i++):
        $cls = $i < $step ? 'done' : ($i === $step ? 'active' : 'todo');
      ?>
        <span class="<?= $cls ?>"><?= $i ?></span>
      <?php endfor; ?>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <!-- ── STEP 1: Welcome & Requirements ── -->
  <?php if ($step === 1): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white"><strong>Passo 1 – Boas-vindas e Requisitos</strong></div>
    <div class="card-body">
      <p>Bem-vindo ao assistente de instalação do <strong>Hosting System</strong>. Verifique se todos os requisitos estão atendidos antes de continuar.</p>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Requisito</th><th>Status</th><th>Valor</th></tr></thead>
        <tbody>
          <?php foreach ($requirements as $r): ?>
          <tr class="<?= $r['ok'] ? 'table-success' : 'table-danger' ?>">
            <td><?= htmlspecialchars($r['label']) ?></td>
            <td><?= $r['ok'] ? '✅' : '❌' ?></td>
            <td><?= htmlspecialchars($r['value']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($reqPassed): ?>
        <a href="?step=2" class="btn btn-primary">Continuar →</a>
      <?php else: ?>
        <div class="alert alert-warning">Resolva os requisitos em vermelho antes de continuar.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── STEP 2: Database ── -->
  <?php elseif ($step === 2): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white"><strong>Passo 2 – Configuração do Banco de Dados</strong></div>
    <div class="card-body">
      <form method="POST" action="?step=2">
        <input type="hidden" name="action" value="save_db">
        <div class="mb-3">
          <label class="form-label">Host MySQL</label>
          <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Porta</label>
            <input type="number" class="form-control" name="db_port" value="<?= (int)($_POST['db_port'] ?? 3306) ?>">
          </div>
          <div class="col-md-8 mb-3">
            <label class="form-label">Nome do Banco</label>
            <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Usuário</label>
          <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input type="password" class="form-control" name="db_pass" autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label">Prefixo das Tabelas</label>
          <input type="text" class="form-control" name="db_prefix" value="<?= htmlspecialchars($_POST['db_prefix'] ?? 'hs_') ?>" pattern="[a-z0-9_]+">
          <div class="form-text">Somente letras minúsculas, números e _</div>
        </div>
        <button type="submit" class="btn btn-primary">Testar Conexão →</button>
      </form>
    </div>
  </div>

  <!-- ── STEP 3: Tables + Admin ── -->
  <?php elseif ($step === 3): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white"><strong>Passo 3 – Criar Tabelas e Conta Admin</strong></div>
    <div class="card-body">
      <?php $db = $_SESSION['install_db'] ?? []; ?>
      <div class="alert alert-info">
        Banco: <strong><?= htmlspecialchars($db['dbName'] ?? '') ?></strong> em
        <strong><?= htmlspecialchars($db['dbHost'] ?? '') ?></strong> —
        Prefixo: <strong><?= htmlspecialchars($db['dbPfx'] ?? '') ?></strong>
      </div>
      <form method="POST" action="?step=3">
        <input type="hidden" name="action" value="create_tables">
        <h5>Informações do Site</h5>
        <div class="mb-3">
          <label class="form-label">Nome do Site</label>
          <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Hosting System') ?>" required>
        </div>
        <hr>
        <h5>Conta Administrador</h5>
        <div class="mb-3">
          <label class="form-label">Nome completo</label>
          <input type="text" class="form-control" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required autocomplete="username">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Senha (mín. 8 caracteres)</label>
            <input type="password" class="form-control" name="admin_pass" required minlength="8" autocomplete="new-password">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Confirmar Senha</label>
            <input type="password" class="form-control" name="admin_pass2" required minlength="8" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn btn-success">Instalar →</button>
      </form>
    </div>
  </div>

  <!-- ── STEP 4: Complete ── -->
  <?php elseif ($step === 4): ?>
  <div class="card shadow-sm border-success">
    <div class="card-header bg-success text-white"><strong>Passo 4 – Instalação Concluída!</strong></div>
    <div class="card-body text-center">
      <div style="font-size:64px">🎉</div>
      <h4 class="mt-3">O sistema foi instalado com sucesso.</h4>
      <p class="text-muted">Por segurança, o acesso a este instalador está bloqueado automaticamente.</p>
      <div class="d-flex justify-content-center gap-3 mt-4">
        <a href="/" class="btn btn-outline-secondary">Página Inicial</a>
        <a href="/login" class="btn btn-primary">Fazer Login</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
