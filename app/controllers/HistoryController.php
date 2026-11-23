<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ConsumptionSnapshot;
use App\Models\Organization;

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

        View::render('history/index', [
            'pageTitle' => 'Consumption History',
            'active' => 'history',
            'records' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'totals' => $snapshots->totals($filters),
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
