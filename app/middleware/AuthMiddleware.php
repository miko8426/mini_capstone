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

        // Pick up campus/office/role changes an administrator made after this
        // session started, and end sessions whose account was disabled.
        Auth::syncSession();

        if (Auth::user()['id'] === 0) {
            header('Location: /login');
            exit;
        }
    }
}
