<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Appliance;
use App\Models\ConsumptionSnapshot;
use App\Models\ElectricityRate;
use App\Models\EmissionFactor;
use App\Services\CalculationService;
use App\Services\NotificationService;
use App\Services\UsageService;

class UsageController extends BaseController
{
    private Appliance $appliances;
    private ConsumptionSnapshot $snapshots;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->appliances = new Appliance();
        $this->snapshots = new ConsumptionSnapshot();
    }

    public function index(): void
    {
        $filters = Auth::applyScope([]);
        $recent = $this->snapshots->paginate($filters, 1, 8);

        View::render('usage/index', [
            'pageTitle' => 'Usage Monitoring',
            'active' => 'usage',
            'appliances' => $this->appliances->allActive(Auth::scope()),
            'recent' => $recent['items'],
            'rates' => (new ElectricityRate())->applicableOn(date('Y-m-d')),
            'factors' => (new EmissionFactor())->applicableOn(date('Y-m-d')),
            'csrfToken' => Session::generateCsrfToken(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(): void
    {
        $this->guardManage();
        $this->guardCsrf('/usage');

        $applianceId = (int) ($_POST['appliance_id'] ?? 0);
        $appliance = $applianceId > 0 ? $this->appliances->find($applianceId) : null;

        if ($appliance === null || !Auth::allows($appliance)) {
            Session::flash('error', 'The selected appliance does not exist or is outside your assigned scope.');
            $this->redirect('/usage');
        }

        if ($appliance['archived_at'] !== null) {
            Session::flash('error', 'Archived appliances cannot receive new usage records.');
            $this->redirect('/usage');
        }

        $service = new UsageService();
        $outcome = $service->record($appliance, $_POST, (int) Session::get('user_id'));

        if ($outcome['errors'] !== []) {
            http_response_code(422);
            $filters = Auth::applyScope([]);
            View::render('usage/index', [
                'pageTitle' => 'Usage Monitoring',
                'active' => 'usage',
                'appliances' => $this->appliances->allActive(Auth::scope()),
                'recent' => $this->snapshots->paginate($filters, 1, 8)['items'],
                'rates' => (new ElectricityRate())->applicableOn(date('Y-m-d')),
                'factors' => (new EmissionFactor())->applicableOn(date('Y-m-d')),
                'csrfToken' => Session::generateCsrfToken(),
                'errors' => $outcome['errors'],
                'old' => $_POST,
            ]);
            return;
        }

        $result = $outcome['result'] ?? [];
        Audit::record(
            'CREATE',
            'consumption_snapshots',
            $outcome['snapshot_id'],
            sprintf('Recorded usage for %s: %s kWh', $result['appliance_name'] ?? '', number_format((float) ($result['total_kwh'] ?? 0), 2))
        );

        // Evaluate thresholds for the reporting month (duplicates are prevented).
        $monthKey = substr((string) ($_POST['period_start'] ?? date('Y-m-d')), 0, 7);
        (new NotificationService())->evaluate($monthKey);

        Session::flash(
            'success',
            sprintf(
                'Usage saved. %s kWh consumed, PHP %s cost, %s kg CO2e emitted.',
                number_format((float) ($result['total_kwh'] ?? 0), 2),
                number_format((float) ($result['cost'] ?? 0), 2),
                number_format((float) ($result['co2_kg'] ?? 0), 2)
            )
        );
        $this->redirect('/history/' . $outcome['snapshot_id']);
    }

    private function guardManage(): void
    {
        if (!Auth::canManage()) {
            http_response_code(403);
            exit('Access Denied: your role cannot record usage.');
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
