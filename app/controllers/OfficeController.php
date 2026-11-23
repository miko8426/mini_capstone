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

class OfficeController extends BaseController
{
    private Office $offices;
    private Campus $campuses;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->offices = new Office();
        $this->campuses = new Campus();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $search = trim((string) ($_GET['q'] ?? ''));
        $campusId = (int) ($_GET['campus_id'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->offices->paginate($search, $campusId, $page);

        View::render('offices/index', [
            'pageTitle' => 'Office Management',
            'active' => 'organization',
            'offices' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'search' => $search,
            'campusId' => $campusId,
            'campuses' => $this->campuses->all(),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/offices');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/offices');
        }

        $id = $this->offices->create($data['campus_id'], $data['room_number'], $data['name'], $data['floor_level']);
        Audit::record('CREATE', 'offices', $id, 'Created office ' . $data['name']);
        Session::flash('success', 'Office created successfully.');
        $this->redirect('/offices');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/offices');

        if ($this->offices->find($id) === null) {
            Session::flash('error', 'Office not found.');
            $this->redirect('/offices');
        }

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/offices');
        }

        $this->offices->update($id, $data['campus_id'], $data['room_number'], $data['name'], $data['floor_level']);
        Audit::record('UPDATE', 'offices', $id, 'Updated office ' . $data['name']);
        Session::flash('success', 'Office updated successfully.');
        $this->redirect('/offices');
    }

    public function delete(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/offices');

        $office = $this->offices->find($id);
        if ($office === null) {
            Session::flash('error', 'Office not found.');
            $this->redirect('/offices');
        }

        $deps = $this->offices->dependencyCounts($id);
        if ((int) $deps['appliances'] > 0) {
            Session::flash('error', 'Cannot delete this office because appliances are still assigned to it.');
            $this->redirect('/offices');
        }
        if ((int) $deps['users'] > 0) {
            Session::flash('error', 'Cannot delete this office because users are still assigned to it.');
            $this->redirect('/offices');
        }
        if ((int) $deps['snapshots'] > 0) {
            Session::flash('error', 'Cannot delete this office because historical consumption records reference it.');
            $this->redirect('/offices');
        }

        $this->offices->delete($id);
        Audit::record('DELETE', 'offices', $id, 'Deleted office ' . $office['name']);
        Session::flash('success', 'Office deleted successfully.');
        $this->redirect('/offices');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input): array
    {
        $data = [
            'campus_id' => (int) ($input['campus_id'] ?? 0),
            'room_number' => trim((string) ($input['room_number'] ?? '')),
            'name' => trim((string) ($input['name'] ?? '')),
            'floor_level' => (int) ($input['floor_level'] ?? 1),
        ];
        $errors = [];

        if ($data['campus_id'] <= 0 || $this->campuses->find($data['campus_id']) === null) {
            $errors[] = 'Select a valid campus.';
        }
        if ($data['name'] === '') {
            $errors[] = 'Office name is required.';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors[] = 'Office name must not exceed 100 characters.';
        }
        if ($data['room_number'] === '') {
            $errors[] = 'Room number is required.';
        } elseif (mb_strlen($data['room_number']) > 30) {
            $errors[] = 'Room number must not exceed 30 characters.';
        }
        if ($data['floor_level'] < 0 || $data['floor_level'] > 200) {
            $errors[] = 'Floor level must be between 0 and 200.';
        }

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
