<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Appliance;
use App\Models\Campus;
use App\Models\ConsumptionSnapshot;
use App\Models\Office;

class HomeController
{
    public function index(): void
    {
        Session::start();

        $campuses = (new Campus())->all();
        $officeCount = (new Office())->paginate('', 0, 1, 1)['total'] ?? 0;
        $applianceCount = (new Appliance())->paginate([], 1, 1)['total'] ?? 0;

        $snapshots = new ConsumptionSnapshot();
        $totals = $snapshots->totals();

        $isLoggedIn = Auth::isLoggedIn();
        $userName = (string) Session::get('user_name', '');
        $userRole = (string) Session::get('user_role', '');

        require_once __DIR__ . '/../views/home/index.php';
    }
}
