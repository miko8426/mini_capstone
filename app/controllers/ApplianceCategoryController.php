<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ApplianceCategory;

class ApplianceCategoryController extends BaseController
{
    private ApplianceCategory $categories;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->categories = new ApplianceCategory();
    }

    public function index(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->categories->paginate($search, $page);

        View::render('appliance_categories/index', [
            'pageTitle' => 'Appliance Categories',
            'active' => 'categories',
            'categories' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'search' => $search,
            'canManage' => Auth::canAdminister(),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->guardManage();
        $this->guardCsrf();

        [$name, $description, $errors] = $this->validate($_POST, null);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/appliance-categories');
        }

        $this->categories->create($name, $description);
        Session::flash('success', 'Appliance category created successfully.');
        $this->redirect('/appliance-categories');
    }

    public function update(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf();

        if ($this->categories->find($id) === null) {
            Session::flash('error', 'Appliance category not found.');
            $this->redirect('/appliance-categories');
        }

        [$name, $description, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/appliance-categories');
        }

        $this->categories->update($id, $name, $description);
        Session::flash('success', 'Appliance category updated successfully.');
        $this->redirect('/appliance-categories');
    }

    public function delete(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf();

        $category = $this->categories->find($id);
        if ($category === null) {
            Session::flash('error', 'Appliance category not found.');
            $this->redirect('/appliance-categories');
        }

        if ($this->categories->referenceCount($id) > 0) {
            Session::flash(
                'error',
                'Cannot delete "' . $category['name'] . '" because appliances are still assigned to it. '
                . 'Reassign those appliances to another category first.'
            );
            $this->redirect('/appliance-categories');
        }

        $this->categories->delete($id);
        Session::flash('success', 'Appliance category deleted successfully.');
        $this->redirect('/appliance-categories');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: string, 1: ?string, 2: array<int, string>}
     */
    private function validate(array $input, ?int $exceptId): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $errors = [];

        if ($name === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Category name must not exceed 100 characters.';
        } elseif ($this->categories->existsByName($name, $exceptId)) {
            $errors[] = 'A category with this name already exists.';
        }

        if (mb_strlen($description) > 255) {
            $errors[] = 'Description must not exceed 255 characters.';
        }

        return [$name, $description === '' ? null : $description, $errors];
    }

    private function guardManage(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only administrators can manage appliance categories.');
        }
    }

    private function guardCsrf(): void
    {
        if (!$this->csrfValid()) {
            Session::flash('error', 'Security token expired. Please try again.');
            $this->redirect('/appliance-categories');
        }
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
