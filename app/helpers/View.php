<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\Session;

/**
 * Minimal view renderer: renders a view inside the shared dashboard layout.
 */
class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        Session::start();

        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        require __DIR__ . '/../views/' . $layout . '.php';
    }

    /**
     * Escape output against XSS.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Build a URL query string from the current GET parameters, overriding some.
     *
     * @param array<string, mixed> $overrides
     */
    public static function query(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);
        $params = array_filter($params, static fn ($value) => $value !== '' && $value !== null);

        return http_build_query($params);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'under_maintenance' => 'Under Maintenance',
            'decommissioned' => 'Decommissioned',
            'archived' => 'Archived',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'under_maintenance' => 'warning',
            'decommissioned' => 'secondary',
            'archived' => 'dark',
            default => 'light',
        };
    }
}
