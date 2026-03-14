<?php
/**
 * Main application configuration
 * Loads database credentials and sets application constants
 */

// ─── Error handling ────────────────────────────────────────────────────────────
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ─── Paths & URLs ──────────────────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('LANG_PATH', ROOT_PATH . '/lang');

// Determine BASE_URL dynamically if not set in db.php
// BASE_URL is the URL to the project root (where index.php lives).
// We derive it by finding the relative path from document root to index.php
// then stripping it from the scheme+host.
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // DOCUMENT_ROOT-relative path to this project's root index.php
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $indexPath = ROOT_PATH . '/index.php';
    $basePath  = '';
    if ($docRoot && str_starts_with($indexPath, $docRoot)) {
        $basePath = rtrim(dirname(substr($indexPath, strlen($docRoot))), '/');
        if ($basePath === '/') $basePath = '';
    }
    define('BASE_URL', $scheme . '://' . $host . $basePath);
}

// ─── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $sameSite = 'Lax';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
    session_name('HOSTINGSYS');
    session_start();
}

// ─── Load DB credentials (created by installer) ───────────────────────────────
$dbConfigFile = CONFIG_PATH . '/db.php';
if (file_exists($dbConfigFile)) {
    require_once $dbConfigFile;
    require_once CONFIG_PATH . '/database.php';
}

// ─── Autoload core includes ───────────────────────────────────────────────────
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/auth.php';
