<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;

class AuthController
{
    public function showLogin()
    {
        if (Auth::isLoggedIn()) {
            header('Location: /dashboard');
            exit;
        }
        require_once __DIR__ . '/../views/home/login.php';
    }

    public function login()
    {
        Session::start();
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $token = $_POST['csrf_token'] ?? '';

        if (!Session::verifyCsrfToken($token)) {
            die("CSRF token validation failed.");
        }

        if ($email && $password !== '' && Auth::attemptLogin($email, $password)) {
            Audit::record('LOGIN', 'auth', (int) Session::get('user_id'), 'Successful login for ' . $email);
            echo json_encode(['status' => 'success', 'redirect' => '/dashboard']);
        } else {
            Audit::record('LOGIN_FAILED', 'auth', null, 'Failed login attempt for ' . substr((string) ($_POST['email'] ?? ''), 0, 100));
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        }
    }

    public function logout()
    {
        if (Auth::isLoggedIn()) {
            Audit::record('LOGOUT', 'auth', (int) Session::get('user_id'), 'User logged out');
        }
        Auth::logout();
        header('Location: /login');
        exit;
    }
}
