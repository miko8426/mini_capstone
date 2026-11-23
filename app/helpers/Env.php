<?php

declare(strict_types=1);

namespace App\Helpers;

class Env
{
    private static bool $loaded = false;
    private static array $variables = [];

    /**
     * Load environment file (.env)
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
        self::$loaded = true;
    }

    /**
     * Get environment variable value with default fallback
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded && file_exists(__DIR__ . '/../../.env')) {
            self::load(__DIR__ . '/../../.env');
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $_ENV[$key] ?? $default;
    }
}
