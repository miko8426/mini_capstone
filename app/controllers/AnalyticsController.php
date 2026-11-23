<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ConsumptionSnapshot;
use App\Models\Organization;

class AnalyticsController extends BaseController
{
    public function __construct()
    {
        AuthMiddleware::handle();
    }

    public function index(): void
    {
        $snapshots = new ConsumptionSnapshot();
        $organization = new Organization();
        $filters = Auth::applyScope([
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'campus_id' => (int) ($_GET['campus_id'] ?? 0),
            'office_id' => (int) ($_GET['office_id'] ?? 0),
        ]);

        // No explicit date range means the monthly view: default to the most
        // recent month that has recorded consumption inside the user's scope.
        $isMonthlyDefault = $filters['date_from'] === '' && $filters['date_to'] === '';
        if ($isMonthlyDefault) {
            $monthly = $snapshots->monthlyTotals($filters);
            $filters['date_from'] = $monthly['month'] . '-01';
            $filters['date_to'] = date('Y-m-t', (int) strtotime($filters['date_from']));
        }

        View::render('analytics/index', [
            'pageTitle' => 'Analytics',
            'active' => 'analytics',
            'filters' => $filters,
            'isMonthlyDefault' => $isMonthlyDefault,
            'periodLabel' => date('F Y', (int) strtotime($filters['date_from'])),
            'totals' => $snapshots->totals($filters),
            'trend' => $snapshots->monthlyTrend($filters),
            'byCampus' => $snapshots->summaryBy('campus', $filters),
            'byOffice' => $snapshots->summaryBy('office', $filters),
            'byCategory' => $snapshots->summaryBy('category', $filters),
            'topAppliances' => $snapshots->summaryBy('appliance', $filters, 10),
            'campuses' => $organization->campuses(Auth::scope()),
            'offices' => $organization->offices(Auth::scope()),
        ]);
    }
}
