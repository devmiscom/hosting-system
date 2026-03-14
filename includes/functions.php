<?php
/**
 * Global helper functions
 */

// ─── Output & Sanitisation ────────────────────────────────────────────────────

/**
 * Sanitize a string for safe HTML output
 */
function sanitize(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL and exit
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ─── Flash Messages ───────────────────────────────────────────────────────────

/**
 * Store a flash message (type: success|error|warning|info)
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear flash messages
 */
function getFlash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Generate (or retrieve) the CSRF token for the current session
 */
function generateToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token
 */
function validateToken(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Return an HTML hidden input with the CSRF token
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . sanitize(generateToken()) . '">';
}

// ─── Settings ─────────────────────────────────────────────────────────────────

/** @var array<string,string> In-memory settings cache */
$_settingsCache = [];

/**
 * Get a setting value by key, with optional default
 */
function getSetting(string $key, string $default = ''): string {
    global $_settingsCache;

    // Populate cache once per request
    if (empty($_settingsCache)) {
        try {
            $db   = Database::getInstance();
            $rows = $db->fetchAll('SELECT setting_key, setting_value FROM ' . DB_PREFIX . 'settings');
            foreach ($rows as $row) {
                $_settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            // DB may not be ready during install
        }
    }

    return $_settingsCache[$key] ?? $default;
}

/**
 * Force-refresh the settings cache (call after saving settings)
 */
function clearSettingsCache(): void {
    global $_settingsCache;
    $_settingsCache = [];
}

// ─── Translations ─────────────────────────────────────────────────────────────

/** @var array<string,string> In-memory translations cache */
$_translationsCache = [];
$_translationsCacheLoaded = false;

/**
 * Translate a string key for the active language
 */
function translate(string $key): string {
    global $_translationsCache, $_translationsCacheLoaded;

    if (!$_translationsCacheLoaded) {
        $_translationsCacheLoaded = true;

        // First load the PT-BR file as fallback
        $langFile = LANG_PATH . '/pt_BR.php';
        if (file_exists($langFile)) {
            $fileStrings = require $langFile;
            if (is_array($fileStrings)) {
                $_translationsCache = $fileStrings;
            }
        }

        // Then overlay DB translations for the active language
        try {
            $db          = Database::getInstance();
            $langCode    = getSetting('default_language', 'pt_BR');
            $lang        = $db->fetch(
                'SELECT id FROM ' . DB_PREFIX . 'languages WHERE code = ? AND active = 1 LIMIT 1',
                [$langCode]
            );
            if ($lang) {
                $rows = $db->fetchAll(
                    'SELECT string_key, string_value FROM ' . DB_PREFIX . 'translations WHERE language_id = ?',
                    [$lang['id']]
                );
                foreach ($rows as $row) {
                    if ($row['string_value'] !== null && $row['string_value'] !== '') {
                        $_translationsCache[$row['string_key']] = $row['string_value'];
                    }
                }
            }
        } catch (Throwable) {
            // DB may not be ready during install
        }
    }

    return $_translationsCache[$key] ?? $key;
}

/**
 * Force-refresh the translations cache
 */
function clearTranslationsCache(): void {
    global $_translationsCache, $_translationsCacheLoaded;
    $_translationsCache       = [];
    $_translationsCacheLoaded = false;
}

// ─── Formatting ───────────────────────────────────────────────────────────────

/**
 * Format a decimal value as currency
 */
function formatCurrency(float|string $amount, string $currency = ''): string {
    if ($currency === '') {
        $currency = getSetting('currency', 'BRL');
    }
    $amount = (float)$amount;
    $symbols = [
        'BRL' => 'R$',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . ' ' . number_format($amount, 2, ',', '.');
}

/**
 * Format a date/datetime string
 */
function formatDate(string $dateStr, string $format = 'd/m/Y'): string {
    if (empty($dateStr)) return '-';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Throwable) {
        return $dateStr;
    }
}

// ─── Strings & Tokens ─────────────────────────────────────────────────────────

/**
 * Generate a random alphanumeric token
 */
function generateRandomToken(int $length = 32): string {
    return bin2hex(random_bytes((int)ceil($length / 2)));
}

/**
 * Return the badge HTML for a given status value
 */
function statusBadge(string $status): string {
    $map = [
        'active'    => 'success',
        'completed' => 'success',
        'pending'   => 'warning',
        'cancelled' => 'secondary',
        'expired'   => 'secondary',
        'failed'    => 'danger',
        'refunded'  => 'info',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . sanitize(translate($status)) . '</span>';
}

/**
 * Truncate a string to a given length
 */
function truncate(string $str, int $length = 80, string $suffix = '…'): string {
    if (mb_strlen($str) <= $length) {
        return $str;
    }
    return mb_substr($str, 0, $length) . $suffix;
}

/**
 * Get active payment gateways from settings
 */
function getActiveGateways(): array {
    $raw = getSetting('active_gateways', 'mercadopago');
    return array_filter(array_map('trim', explode(',', $raw)));
}

/**
 * Build a pagination HTML string
 */
function paginationLinks(int $total, int $perPage, int $currentPage, string $baseUrl): string {
    if ($total <= $perPage) return '';
    $pages = (int)ceil($total / $perPage);
    $html  = '<nav><ul class="pagination pagination-sm flex-wrap">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $sep    = str_contains($baseUrl, '?') ? '&' : '?';
        $url    = sanitize($baseUrl . $sep . 'p=' . $i);
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Return POST value with optional sanitization
 */
function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

/**
 * Return GET value
 */
function get(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}
