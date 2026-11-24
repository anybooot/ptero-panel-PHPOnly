<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Native secure session and CSRF protection manager.
 */
class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_samesite', 'Lax');

        $secure = Config::get('APP_URL', '');
        $isHttps = str_starts_with((string)$secure, 'https://')
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        if ($isHttps) {
            @ini_set('session.cookie_secure', '1');
        }

        // On restrictive shared / free hosts, check if default session path is writable
        $savePath = @session_save_path();
        if (!empty($savePath) && !@is_writable($savePath)) {
            $fallback = sys_get_temp_dir();
            if (@is_writable($fallback)) {
                @session_save_path($fallback);
            }
        }

        if (!headers_sent()) {
            @session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('pterodactyl_session');
            @session_start();
        }
        self::$started = (session_status() === PHP_SESSION_ACTIVE);

        if (empty($_SESSION['_csrf_token'])) {
            try {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $_SESSION['_csrf_token'] = md5(uniqid((string)mt_rand(), true)) . md5(microtime(true) . 'csrf');
            }
        }
    }

    public static function getCsrfToken(): string
    {
        self::start();
        return $_SESSION['_csrf_token'] ?? '';
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::start();
        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $val = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id($deleteOldSession);
            try {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $_SESSION['_csrf_token'] = md5(uniqid((string)mt_rand(), true)) . md5(microtime(true) . 'csrf');
            }
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            @setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => !empty($params['secure']),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        self::$started = false;
    }
}
