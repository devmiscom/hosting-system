<?php
/**
 * Main application router
 *
 * Parses the request URI and dispatches to the correct file.
 * All URLs are rewritten here by .htaccess.
 */

define('ROOT_PATH', __DIR__);
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('LANG_PATH', ROOT_PATH . '/lang');

// ── Installation check ────────────────────────────────────────────────────────
if (!file_exists(CONFIG_PATH . '/db.php')) {
    // Not installed yet – redirect to installer
    header('Location: /install/');
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once CONFIG_PATH . '/config.php';

// ── Resolve route from REQUEST_URI ────────────────────────────────────────────
$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName  = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$scriptName  = rtrim($scriptName === '/' ? '' : $scriptName, '/');

// Strip base path and query string
$path = parse_url($requestUri, PHP_URL_PATH);
if ($scriptName && str_starts_with($path, $scriptName)) {
    $path = substr($path, strlen($scriptName));
}
$path = '/' . ltrim($path ?: '/', '/');
$path = rtrim($path, '/') ?: '/';

// ── Route map ─────────────────────────────────────────────────────────────────
$route = $path;

// Public routes
if ($route === '/' || $route === '/home') {
    require ROOT_PATH . '/public/home.php';
    exit;
}

if ($route === '/login') {
    require ROOT_PATH . '/public/login.php';
    exit;
}

if ($route === '/logout') {
    if (Auth::isLoggedIn()) {
        Auth::logout();
    }
    redirect(BASE_URL . '/login');
}

if ($route === '/register') {
    require ROOT_PATH . '/public/register.php';
    exit;
}

// Admin routes
if (str_starts_with($route, '/admin')) {
    Auth::requireAdmin();

    $adminPage = substr($route, strlen('/admin')) ?: '/';
    $adminPage = ltrim($adminPage, '/') ?: 'index';

    // Only allow known admin pages
    $adminAllowed = ['index','plans','services','clients','transactions','settings','translations'];
    $adminFile    = in_array($adminPage, $adminAllowed) ? $adminPage : 'index';

    require ROOT_PATH . '/admin/' . $adminFile . '.php';
    exit;
}

// Client routes
if (str_starts_with($route, '/client')) {
    Auth::requireClient();

    $clientPage = substr($route, strlen('/client')) ?: '/';
    $clientPage = ltrim($clientPage, '/') ?: 'index';

    $clientAllowed = ['index','plans','checkout','invoices','profile'];
    $clientFile    = in_array($clientPage, $clientAllowed) ? $clientPage : 'index';

    require ROOT_PATH . '/client/' . $clientFile . '.php';
    exit;
}

// Payment routes
if (str_starts_with($route, '/payment')) {
    $paymentPage = substr($route, strlen('/payment')) ?: '/';
    $paymentPage = ltrim($paymentPage, '/') ?: 'success';

    $paymentAllowed = ['success','cancel','webhook'];
    if (!in_array($paymentPage, $paymentAllowed)) {
        $paymentPage = 'success';
    }

    require ROOT_PATH . '/payment/' . $paymentPage . '.php';
    exit;
}

// Install routes (blocked post-install by .htaccess, but handle here too)
if (str_starts_with($route, '/install')) {
    if (file_exists(CONFIG_PATH . '/installed.php')) {
        redirect(BASE_URL . '/');
    }
    require ROOT_PATH . '/install/index.php';
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────
http_response_code(404);
ob_start();
?>
<div class="container text-center py-5">
  <h1 class="display-1 fw-bold text-muted">404</h1>
  <p class="lead">Página não encontrada.</p>
  <a href="<?= sanitize(BASE_URL) ?>/" class="btn btn-primary mt-3">Voltar ao Início</a>
</div>
<?php
$content   = ob_get_clean();
$pageTitle = 'Página Não Encontrada';
require_once VIEWS_PATH . '/layout.php';
