<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Campus;

class CampusController extends BaseController
{
    private Campus $campuses;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->campuses = new Campus();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->campuses->paginate($search, $page);

        View::render('campuses/index', [
            'pageTitle' => 'Campus Management',
            'active' => 'organization',
            'campuses' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'search' => $search,
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/campuses');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/campuses');
        }

        $id = $this->campuses->create($data['name'], $data['code'], $data['address']);
        Audit::record('CREATE', 'campuses', $id, 'Created campus ' . $data['name']);
        Session::flash('success', 'Campus created successfully.');
        $this->redirect('/campuses');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/campuses');

        if ($this->campuses->find($id) === null) {
            Session::flash('error', 'Campus not found.');
            $this->redirect('/campuses');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/campuses');
        }

        $this->campuses->update($id, $data['name'], $data['code'], $data['address']);
        Audit::record('UPDATE', 'campuses', $id, 'Updated campus ' . $data['name']);
        Session::flash('success', 'Campus updated successfully.');
        $this->redirect('/campuses');
    }

    public function delete(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/campuses');

        $campus = $this->campuses->find($id);
        if ($campus === null) {
            Session::flash('error', 'Campus not found.');
            $this->redirect('/campuses');
        }

        $deps = $this->campuses->dependencyCounts($id);
        if ((int) $deps['offices'] > 0) {
            Session::flash('error', 'Cannot delete this campus because offices are still assigned to it.');
            $this->redirect('/campuses');
        }
        if ((int) $deps['users'] > 0) {
            Session::flash('error', 'Cannot delete this campus because users are still assigned to it.');
            $this->redirect('/campuses');
        }

        $this->campuses->delete($id);
        Audit::record('DELETE', 'campuses', $id, 'Deleted campus ' . $campus['name']);
        Session::flash('success', 'Campus deleted successfully.');
        $this->redirect('/campuses');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input, ?int $exceptId = null): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'code' => strtoupper(trim((string) ($input['code'] ?? ''))),
            'address' => trim((string) ($input['address'] ?? '')),
        ];
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Campus name is required.';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors[] = 'Campus name must not exceed 100 characters.';
        } elseif ($this->campuses->exists('name', $data['name'], $exceptId)) {
            $errors[] = 'A campus with this name already exists.';
        }

        if ($data['code'] === '') {
            $errors[] = 'Campus code is required.';
        } elseif (mb_strlen($data['code']) > 20) {
            $errors[] = 'Campus code must not exceed 20 characters.';
        } elseif ($this->campuses->exists('code', $data['code'], $exceptId)) {
            $errors[] = 'A campus with this code already exists.';
        }

        if (mb_strlen($data['address']) > 255) {
            $errors[] = 'Address must not exceed 255 characters.';
        }

        $data['address'] = $data['address'] === '' ? null : $data['address'];

        return [$data, $errors];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: organization records can only be managed by a System Administrator.');
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
