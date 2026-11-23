<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Appliance;
use App\Models\ConsumptionSnapshot;
use App\Models\Notification;
use App\Models\Organization;
use App\Services\CalculationService;

class DashboardController extends BaseController
{
    public function __construct()
    {
        AuthMiddleware::handle();
    }

    public function index(): void
    {
        $scope = Auth::scope();
        $snapshots = new ConsumptionSnapshot();
        $appliances = new Appliance();
        $organization = new Organization();
        $notifications = new Notification();

        // Dashboard statistics default to the latest recorded month (as a
        // single calendar-month aggregate) rather than an all-time total.
        $monthly = $snapshots->monthlyTotals($scope);
        $monthLabel = date('M Y', (int) strtotime($monthly['month'] . '-01'));
        $activeAppliances = $appliances->allActive($scope);

        $offices = $organization->offices();
        if ($scope !== []) {
            $offices = array_map(
                static fn (array $office): array => $office + ['office_id' => (int) $office['id']],
                $offices
            );
            $offices = array_values(array_filter($offices, static fn (array $office): bool => Auth::allows($office)));
        }

        // High-consumption appliances: estimated monthly kWh at or above the
        // 90th percentile of the active inventory in the user's scope.
        $estimates = [];
        foreach ($activeAppliances as $appliance) {
            $monthlyKwh = CalculationService::dailyKwh(
                (int) $appliance['quantity'],
                (float) $appliance['wattage'],
                (float) $appliance['daily_operating_hours']
            ) * 30;
            $estimates[] = ['appliance' => $appliance, 'monthly_kwh' => $monthlyKwh];
        }
        usort($estimates, static fn ($a, $b) => $b['monthly_kwh'] <=> $a['monthly_kwh']);
        $highCount = $estimates === [] ? 0 : max(1, (int) ceil(count($estimates) * 0.1));

        $userId = (int) Session::get('user_id');

        View::render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'active' => 'dashboard',
            'stats' => [
                'total_appliances' => count($activeAppliances),
                'total_consumption' => $monthly['total_kwh'],
                'total_cost' => $monthly['total_cost'],
                'total_emissions' => $monthly['total_co2'],
                'total_offices' => count($offices),
                'high_consumption' => $highCount,
                'month_label' => $monthLabel,
            ],
            'highConsumers' => array_slice($estimates, 0, 5),
            'notifications' => $notifications->forUser($userId, true, 5),
            'unreadNotifications' => $notifications->unreadCount($userId),
            'trend' => $snapshots->monthlyTrend($scope),
            'recent' => $snapshots->paginate($scope, 1, 5)['items'],
            'csrfToken' => Session::generateCsrfToken(),
            'canRecord' => Auth::canManage(),
        ]);
    }
}
