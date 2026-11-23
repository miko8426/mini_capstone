<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Auth;

class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
}
