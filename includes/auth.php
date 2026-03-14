<?php
/**
 * Auth class – session-based authentication for admin and client roles
 */
class Auth {

    /**
     * Attempt login. Returns true on success.
     */
    public static function login(string $email, string $password): bool {
        $db   = Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM ' . DB_PREFIX . 'users WHERE email = ? AND active = 1 LIMIT 1',
            [strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        return true;
    }

    /**
     * Log out the current user
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Check if a user is currently logged in
     */
    public static function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    /**
     * Check if current user has admin role
     */
    public static function isAdmin(): bool {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Check if current user has client role
     */
    public static function isClient(): bool {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'client';
    }

    /**
     * Get current user data from the database
     */
    public static function getCurrentUser(): array|false {
        if (!self::isLoggedIn()) {
            return false;
        }
        $db = Database::getInstance();
        return $db->fetch(
            'SELECT id, name, email, role, active, created_at FROM ' . DB_PREFIX . 'users WHERE id = ? LIMIT 1',
            [$_SESSION['user_id']]
        );
    }

    /**
     * Require admin access – redirect to login if not admin
     */
    public static function requireAdmin(): void {
        if (!self::isAdmin()) {
            flashMessage('error', translate('access_denied'));
            redirect(BASE_URL . '/login');
        }
    }

    /**
     * Require client access – redirect to login if not client
     */
    public static function requireClient(): void {
        if (!self::isClient()) {
            if (!self::isLoggedIn()) {
                flashMessage('warning', translate('please_login'));
                redirect(BASE_URL . '/login');
            }
            // Logged in but not a client (e.g. admin accessing client area)
            flashMessage('error', translate('access_denied'));
            redirect(BASE_URL . '/');
        }
    }

    /**
     * Require any logged-in user
     */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            flashMessage('warning', translate('please_login'));
            redirect(BASE_URL . '/login');
        }
    }

    /**
     * Change the password for the current user
     */
    public static function changePassword(int $userId, string $newPassword): bool {
        $db   = Database::getInstance();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $rows = $db->execute(
            'UPDATE ' . DB_PREFIX . 'users SET password = ?, updated_at = NOW() WHERE id = ?',
            [$hash, $userId]
        );
        return $rows > 0;
    }
}
