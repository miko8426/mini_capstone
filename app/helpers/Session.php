<?php

declare(strict_types=1);

namespace App\Helpers;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
                'gc_maxlifetime' => 3600,
            ]);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    /**
     * Store a one-time message that survives a redirect.
     */
    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Read and clear a one-time flash message.
     */
    public static function getFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }

    // CSRF Protection
    public static function generateCsrfToken(): string
    {
        self::start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
