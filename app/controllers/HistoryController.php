<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ConsumptionSnapshot;
use App\Models\Organization;
use App\Services\ApplianceEstimateService;
use DateTimeImmutable;

class HistoryController extends BaseController
{
    private ConsumptionSnapshot $snapshots;
    private Organization $organization;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->snapshots = new ConsumptionSnapshot();
        $this->organization = new Organization();
    }

    public function index(): void
    {
        $snapshots = new ConsumptionSnapshot();
        $filters = $this->filters();
        $filters = Auth::applyScope($filters);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $snapshots->paginate($filters, $page, 15);

        $records = $result['items'];
        $totals = $snapshots->totals($filters);
        $estimate = null;

        // No recorded snapshots for a window that reaches today: show live
        // estimates instead, so the page agrees with the Dashboard/Analytics.
        if ($result['total'] === 0 && $this->windowReachesToday($filters)) {
            $estimate = (new ApplianceEstimateService())->range(
                $filters,
                $filters['date_from'] !== '' ? $filters['date_from'] : null,
                $filters['date_to'] !== '' ? $filters['date_to'] : null
            );

            if ($estimate['appliances'] === []) {
                $estimate = null;
            } else {
                $records = array_map(static fn (array $appliance): array => [
                    'id' => null,
                    'estimated' => true,
                    'period_start' => $appliance['active_from'],
                    'period_end' => $estimate['period_end'],
                    'appliance_name' => $appliance['name'],
                    'office_name' => $appliance['office_name'],
                    'campus_name' => $appliance['campus_name'],
                    'category_name' => $appliance['category_name'],
                    'total_kwh' => $appliance['total_kwh'],
                    'rate_per_kwh' => $estimate['rate_per_kwh'],
                    'electricity_cost' => $appliance['total_cost'],
                    'co2_kg' => $appliance['total_co2'],
                ], $estimate['appliances']);

                $totals = [
                    'total_kwh' => $estimate['total_kwh'],
                    'total_cost' => $estimate['total_cost'],
                    'total_co2' => $estimate['total_co2'],
                ];
                $result['total'] = count($records);
                $result['pages'] = 1;
                $result['page'] = 1;
            }
        }

        View::render('history/index', [
            'pageTitle' => 'Consumption History',
            'active' => 'history',
            'records' => $records,
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'totals' => $totals,
            'estimate' => $estimate,
            'campuses' => $this->organization->campuses(Auth::scope()),
            'offices' => $this->organization->offices(Auth::scope()),
        ]);
    }

    public function show(int $id): void
    {
        $record = $this->snapshots->find($id);
        if ($record === null || !$this->allowed($record)) {
            http_response_code(404);
            View::render('errors/404', [
                'pageTitle' => 'Not Found',
                'active' => 'history',
                'message' => 'The requested historical record does not exist or is outside your assigned scope.',
            ]);
            exit;
        }

        View::render('history/show', [
            'pageTitle' => 'Historical Record #' . $id,
            'active' => 'history',
            'record' => $record,
        ]);
    }

    /**
     * True when the filter window is open-ended or runs up to today, i.e. the
     * period is still in progress and can be projected.
     *
     * @param array<string, mixed> $filters
     */
    private function windowReachesToday(array $filters): bool
    {
        $today = new DateTimeImmutable('today');
        $to = trim((string) ($filters['date_to'] ?? ''));
        $from = trim((string) ($filters['date_from'] ?? ''));

        if ($to !== '') {
            $end = DateTimeImmutable::createFromFormat('Y-m-d', $to);
            if (!$end instanceof DateTimeImmutable || $end->format('Y-m-d') !== $to) {
                return false;
            }

            return $end >= $today;
        }

        // Open-ended window: only project when it starts in the current month.
        if ($from !== '') {
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $from);

            return $start instanceof DateTimeImmutable
                && $start->format('Y-m-d') === $from
                && $start->format('Y-m') === $today->format('Y-m');
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'campus_id' => (int) ($_GET['campus_id'] ?? 0),
            'office_id' => (int) ($_GET['office_id'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $record */
    private function allowed(array $record): bool
    {
        foreach (Auth::scope() as $key => $value) {
            if ((int) ($record[$key] ?? 0) !== $value) {
                return false;
            }
        }

        return true;
    }
}
