<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ElectricityRate;
use DateTimeImmutable;

class ElectricityRateController extends BaseController
{
    private ElectricityRate $rates;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->rates = new ElectricityRate();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->rates->paginate($page);

        View::render('rates/index', [
            'pageTitle' => 'Electricity Rates',
            'active' => 'rates',
            'rates' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/rates');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/rates');
        }

        $id = $this->rates->create($data['rate'], $data['currency'], $data['effective_date'], $data['active']);
        Audit::record('CREATE', 'electricity_rates', $id, 'Created rate PHP ' . $data['rate'] . ' effective ' . $data['effective_date']);
        Session::flash('success', 'Electricity rate added. New calculations from that effective date will use it.');
        $this->redirect('/rates');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/rates');

        if ($this->rates->find($id) === null) {
            Session::flash('error', 'Electricity rate not found.');
            $this->redirect('/rates');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/rates');
        }

        $this->rates->update($id, $data['rate'], $data['currency'], $data['effective_date'], $data['active']);
        Audit::record('UPDATE', 'electricity_rates', $id, 'Updated rate to PHP ' . $data['rate'] . ' effective ' . $data['effective_date']);
        Session::flash('success', 'Electricity rate updated. Existing historical records keep their original rate.');
        $this->redirect('/rates');
    }

    public function delete(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/rates');

        $rate = $this->rates->find($id);
        if ($rate === null) {
            Session::flash('error', 'Electricity rate not found.');
            $this->redirect('/rates');
        }

        if ($this->rates->paginate(1, 2)['total'] <= 1) {
            Session::flash('error', 'At least one electricity rate must remain configured.');
            $this->redirect('/rates');
        }

        $this->rates->delete($id);
        Audit::record('DELETE', 'electricity_rates', $id, 'Deleted rate effective ' . $rate['effective_date']);
        Session::flash('success', 'Electricity rate deleted. Historical records are unaffected.');
        $this->redirect('/rates');
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input, ?int $exceptId = null): array
    {
        $date = trim((string) ($input['effective_date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $data = [
            'rate' => (float) ($input['rate_per_kwh'] ?? 0),
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'PHP'))),
            'effective_date' => $date,
            'active' => !empty($input['is_active']),
        ];
        $errors = [];

        if ($data['rate'] <= 0 || $data['rate'] > 10000) {
            $errors[] = 'Rate per kWh must be greater than 0 and at most 10,000.';
        }
        if (!preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            $errors[] = 'Currency must be a 3-letter code such as PHP.';
        }
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $errors[] = 'Select a valid effective date.';
        } elseif ($this->rates->effectiveDateExists($date, $exceptId)) {
            $errors[] = 'Another rate already starts on that effective date. Rate periods must not overlap.';
        }

        return [$data, $errors];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can manage electricity rates.');
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
