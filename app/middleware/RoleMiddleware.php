<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Auth;

class RoleMiddleware
{
    public static function check(string $requiredRole): void
    {
        if (!Auth::hasRole($requiredRole)) {
            // Unauthorized access
            http_response_code(403);
            die("Access Denied: You do not have the required permissions.");
        }
    }
}
