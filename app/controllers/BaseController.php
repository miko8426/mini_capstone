<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Session;

abstract class BaseController
{
    protected function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function validateCsrf(): void
    {
        if (!$this->csrfValid()) {
            $this->jsonResponse(['status' => 'error', 'message' => 'CSRF validation failed'], 403);
        }
    }

    protected function csrfValid(): bool
    {
        $token = (string) ($_POST['csrf_token'] ?? '');

        return Session::verifyCsrfToken($token);
    }
}
