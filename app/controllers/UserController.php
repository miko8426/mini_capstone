<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Campus;
use App\Models\Office;
use App\Models\User;

class UserController extends BaseController
{
    private User $users;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->users = new User();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $search = trim((string) ($_GET['q'] ?? ''));
        $roleId = (int) ($_GET['role_id'] ?? 0);
        $status = (string) ($_GET['status'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->users->paginate($search, $roleId, $status, $page);

        View::render('users/index', [
            'pageTitle' => 'User Management',
            'active' => 'users',
            'users' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'search' => $search,
            'roleId' => $roleId,
            'status' => $status,
            'roles' => $this->users->roles(),
            'campuses' => (new Campus())->all(),
            'offices' => (new Office())->paginate('', 0, 1, 1000)['items'],
            'csrfToken' => Session::generateCsrfToken(),
            'currentUserId' => (int) Session::get('user_id'),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/users');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/users');
        }

        $id = $this->users->create($data);
        Audit::record('CREATE', 'users', $id, 'Created user ' . $data['email']);
        Session::flash('success', 'User account created.');
        $this->redirect('/users');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/users');

        if ($this->users->find($id) === null) {
            Session::flash('error', 'User not found.');
            $this->redirect('/users');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/users');
        }

        $this->users->update($id, $data);
        Audit::record('UPDATE', 'users', $id, 'Updated user ' . $data['email']);
        Session::flash('success', 'User account updated.');
        $this->redirect('/users');
    }

    public function setStatus(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/users');

        $user = $this->users->find($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            $this->redirect('/users');
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            Session::flash('error', 'Invalid account status.');
            $this->redirect('/users');
        }

        if ($id === (int) Session::get('user_id') && $status !== 'active') {
            Session::flash('error', 'You cannot deactivate your own account.');
            $this->redirect('/users');
        }

        if ($user['role_name'] === 'Super Admin' && $status !== 'active' && $this->users->activeAdminCount() <= 1) {
            Session::flash('error', 'At least one active System Administrator must remain.');
            $this->redirect('/users');
        }

        $this->users->setStatus($id, $status);
        Audit::record('UPDATE', 'users', $id, 'Changed status to ' . $status . ' for ' . $user['email']);
        Session::flash('success', 'Account status updated to ' . ucfirst($status) . '.');
        $this->redirect('/users');
    }

    public function destroy(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/users');

        $user = $this->users->find($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            $this->redirect('/users');
        }

        $currentId = (int) Session::get('user_id');
        if ($id === $currentId) {
            Session::flash('error', 'You cannot delete your own account.');
            $this->redirect('/users');
        }

        if ($user['role_name'] === 'Super Admin' && $user['status'] === 'active' && $this->users->activeAdminCount() <= 1) {
            Session::flash('error', 'At least one active System Administrator must remain.');
            $this->redirect('/users');
        }

        $password = (string) ($_POST['password'] ?? '');
        if ($password === '') {
            Session::flash('error', 'Enter your password to confirm the deletion.');
            $this->redirect('/users');
        }

        $current = $this->users->find($currentId);
        if ($current === null || !password_verify($password, (string) $current['password_hash'])) {
            Audit::record('DELETE', 'users', $id, 'Failed account deletion attempt (incorrect password) for ' . $user['email']);
            Session::flash('error', 'The password you entered is incorrect. The account was not deleted.');
            $this->redirect('/users');
        }

        $this->users->delete($id);
        Audit::record('DELETE', 'users', $id, 'Moved user ' . $user['email'] . ' to the Recycle Bin');
        Session::flash('success', 'User account moved to the Recycle Bin. An administrator can restore it from there.');
        $this->redirect('/users');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input, ?int $exceptId = null): array
    {
        $data = [
            'role_id' => (int) ($input['role_id'] ?? 0),
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'status' => (string) ($input['status'] ?? 'active'),
            'campus_id' => (int) ($input['campus_id'] ?? 0),
            'office_id' => (int) ($input['office_id'] ?? 0),
        ];
        $errors = [];

        $roleIds = array_map(static fn ($role) => (int) $role['id'], $this->users->roles());
        if (!in_array($data['role_id'], $roleIds, true)) {
            $errors[] = 'Select a valid role.';
        }
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        } elseif (mb_strlen($data['first_name']) > 50 || mb_strlen($data['last_name']) > 50) {
            $errors[] = 'Names must not exceed 50 characters.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($data['email'], $exceptId)) {
            $errors[] = 'A user with this email already exists.';
        }
        if ($exceptId === null && mb_strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($data['password'] !== '' && mb_strlen($data['password']) < 8) {
            $errors[] = 'A new password must be at least 8 characters.';
        }
        if (!in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
            $errors[] = 'Select a valid account status.';
        }
        if ($data['office_id'] > 0) {
            $office = (new Office())->find($data['office_id']);
            if ($office === null) {
                $errors[] = 'Selected office does not exist.';
            } else {
                $data['campus_id'] = (int) $office['campus_id'];
            }
        } elseif ($data['campus_id'] > 0 && (new Campus())->find($data['campus_id']) === null) {
            $errors[] = 'Selected campus does not exist.';
        }

        return [$data, $errors];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can manage users.');
        }
    }

    private function guardCsrf(string $redirectTo): void
    {
        if (!$this->csrfValid()) {
            Session::flash('error', 'Security token expired. Please try again.');
            $this->redirect($redirectTo);
        }
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
