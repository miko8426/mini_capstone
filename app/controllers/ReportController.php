<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Organization;
use App\Services\ReportService;

class ReportController extends BaseController
{
    public function __construct()
    {
        AuthMiddleware::handle();
    }

    public function index(): void
    {
        $organization = new Organization();

        View::render('reports/index', [
            'pageTitle' => 'PDF Reports',
            'active' => 'reports',
            'reports' => ReportService::REPORTS,
            'campuses' => $organization->campuses(Auth::scope()),
            'offices' => $organization->offices(Auth::scope()),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function generate(): void
    {
        $type = trim((string) ($_GET['type'] ?? ''));
        if (!array_key_exists($type, ReportService::REPORTS)) {
            http_response_code(404);
            exit('Unknown report type.');
        }

        $filters = Auth::applyScope([
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'campus_id' => (int) ($_GET['campus_id'] ?? 0),
            'office_id' => (int) ($_GET['office_id'] ?? 0),
            'status' => '',
        ]);

        Audit::record('EXPORT', 'reports', null, 'Generated PDF report: ' . ReportService::REPORTS[$type]);

        (new ReportService())->generate($type, $filters, (string) Session::get('user_name', ''));
        exit;
    }
}
