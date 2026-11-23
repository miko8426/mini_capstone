<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\EmissionFactor;
use DateTimeImmutable;

class EmissionFactorController extends BaseController
{
    private EmissionFactor $factors;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->factors = new EmissionFactor();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->factors->paginate($page);

        View::render('factors/index', [
            'pageTitle' => 'Emission Factors',
            'active' => 'factors',
            'factors' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/emission-factors');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/emission-factors');
        }

        $id = $this->factors->create($data['name'], $data['factor'], $data['source'], $data['effective_date'], $data['active']);
        Audit::record('CREATE', 'emission_factors', $id, 'Created emission factor ' . $data['name']);
        Session::flash('success', 'Emission factor added.');
        $this->redirect('/emission-factors');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/emission-factors');

        if ($this->factors->find($id) === null) {
            Session::flash('error', 'Emission factor not found.');
            $this->redirect('/emission-factors');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/emission-factors');
        }

        $this->factors->update($id, $data['name'], $data['factor'], $data['source'], $data['effective_date'], $data['active']);
        Audit::record('UPDATE', 'emission_factors', $id, 'Updated emission factor ' . $data['name']);
        Session::flash('success', 'Emission factor updated. Existing historical records keep their original factor.');
        $this->redirect('/emission-factors');
    }

    public function delete(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/emission-factors');

        $factor = $this->factors->find($id);
        if ($factor === null) {
            Session::flash('error', 'Emission factor not found.');
            $this->redirect('/emission-factors');
        }

        if ($this->factors->paginate(1, 2)['total'] <= 1) {
            Session::flash('error', 'At least one emission factor must remain configured.');
            $this->redirect('/emission-factors');
        }

        $this->factors->delete($id);
        Audit::record('DELETE', 'emission_factors', $id, 'Deleted emission factor ' . $factor['factor_name']);
        Session::flash('success', 'Emission factor deleted. Historical records are unaffected.');
        $this->redirect('/emission-factors');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input, ?int $exceptId = null): array
    {
        $date = trim((string) ($input['effective_date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $data = [
            'name' => trim((string) ($input['factor_name'] ?? '')),
            'factor' => (float) ($input['kg_co2_per_kwh'] ?? 0),
            'source' => trim((string) ($input['source'] ?? '')),
            'effective_date' => $date,
            'active' => !empty($input['is_active']),
        ];
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Factor name is required.';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors[] = 'Factor name must not exceed 100 characters.';
        }
        if ($data['factor'] <= 0 || $data['factor'] > 100) {
            $errors[] = 'Emission factor must be greater than 0 and at most 100 kg CO2e per kWh.';
        }
        if (mb_strlen($data['source']) > 255) {
            $errors[] = 'Source must not exceed 255 characters.';
        }
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $errors[] = 'Select a valid effective date.';
        } elseif ($this->factors->effectiveDateExists($date, $exceptId)) {
            $errors[] = 'Another emission factor already starts on that effective date.';
        }

        $data['source'] = $data['source'] === '' ? null : $data['source'];

        return [$data, $errors];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can manage emission factors.');
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
