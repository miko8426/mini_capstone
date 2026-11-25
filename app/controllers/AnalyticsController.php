<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\ConsumptionSnapshot;
use App\Models\Organization;
use App\Services\ApplianceEstimateService;

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

        // No explicit date range means the monthly view: the current month up to
        // today, so figures reflect usage that is still accruing.
        $isMonthlyDefault = $filters['date_from'] === '' && $filters['date_to'] === '';
        if ($isMonthlyDefault) {
            $filters['date_from'] = date('Y-m-01');
            $filters['date_to'] = date('Y-m-d');
        }

        $totals = $snapshots->totals($filters);
        $trend = $snapshots->monthlyTrend($filters);
        $byCampus = $snapshots->summaryBy('campus', $filters);
        $byOffice = $snapshots->summaryBy('office', $filters);
        $byCategory = $snapshots->summaryBy('category', $filters);
        $topAppliances = $snapshots->summaryBy('appliance', $filters, 10);

        // Nothing recorded for this window? Project it from the active inventory
        // instead of showing a blank page. Only for ranges that reach today, so
        // closed historical periods keep their recorded-only meaning.
        $estimateMode = $totals['records'] === 0 && $filters['date_to'] >= date('Y-m-d');
        if ($estimateMode) {
            $estimate = (new ApplianceEstimateService())->range(
                $filters,
                $filters['date_from'] !== '' ? $filters['date_from'] : date('Y-m-01'),
                $filters['date_to'] !== '' ? $filters['date_to'] : date('Y-m-d')
            );

            $totals = [
                'records' => count($estimate['appliances']),
                'total_kwh' => $estimate['total_kwh'],
                'total_cost' => $estimate['total_cost'],
                'total_co2' => $estimate['total_co2'],
            ];
            $trend = $estimate['appliances'] === [] ? [] : [[
                'month' => substr($estimate['period_start'], 0, 7),
                'total_kwh' => $estimate['total_kwh'],
                'total_cost' => $estimate['total_cost'],
                'total_co2' => $estimate['total_co2'],
            ]];
            $byCampus = $this->groupEstimates($estimate['appliances'], 'campus');
            $byOffice = $this->groupEstimates($estimate['appliances'], 'office');
            $byCategory = $this->groupEstimates($estimate['appliances'], 'category');
            $topAppliances = array_slice($this->groupEstimates($estimate['appliances'], 'appliance'), 0, 10);
        }

        View::render('analytics/index', [
            'pageTitle' => 'Analytics',
            'active' => 'analytics',
            'filters' => $filters,
            'isMonthlyDefault' => $isMonthlyDefault,
            'estimateMode' => $estimateMode,
            'periodLabel' => (new \DateTimeImmutable($filters['date_from'] !== '' ? $filters['date_from'] : date('Y-m-01')))->format('F Y'),
            'totals' => $totals,
            'trend' => $trend,
            'byCampus' => $byCampus,
            'byOffice' => $byOffice,
            'byCategory' => $byCategory,
            'topAppliances' => $topAppliances,
            'campuses' => $organization->campuses(Auth::scope()),
            'offices' => $organization->offices(Auth::scope()),
        ]);
    }

    /**
     * Aggregate projected appliance rows into the same shape as
     * ConsumptionSnapshot::summaryBy() so the charts stay unchanged.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupEstimates(array $rows, string $level): array
    {
        $map = [
            'campus' => ['campus_id', 'campus_name'],
            'office' => ['office_id', 'office_name'],
            'category' => ['category_id', 'category_name'],
            'appliance' => ['id', 'name'],
        ];

        if (!isset($map[$level])) {
            return [];
        }

        [$idKey, $nameKey] = $map[$level];

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) ($row[$idKey] ?? 0);
            if (!isset($grouped[$id])) {
                $grouped[$id] = [
                    'id' => $id,
                    'name' => (string) ($row[$nameKey] ?? ''),
                    'campus_name' => (string) ($row['campus_name'] ?? ''),
                    'total_kwh' => 0.0,
                    'total_cost' => 0.0,
                    'total_co2' => 0.0,
                ];
            }
            $grouped[$id]['total_kwh'] += (float) $row['total_kwh'];
            $grouped[$id]['total_cost'] += (float) $row['total_cost'];
            $grouped[$id]['total_co2'] += (float) $row['total_co2'];
        }

        $result = array_values($grouped);
        usort($result, static fn (array $a, array $b): int => $b['total_kwh'] <=> $a['total_kwh']);

        return $result;
    }
}
