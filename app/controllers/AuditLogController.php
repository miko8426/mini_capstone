<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\AuditLog;

class AuditLogController extends BaseController
{
    public function __construct()
    {
        AuthMiddleware::handle();
    }

    public function index(): void
    {
        $this->guardAdmin();

        $logs = new AuditLog();
        $filters = [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'entity' => trim((string) ($_GET['entity'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $logs->paginate($filters, $page);

        View::render('audit/index', [
            'pageTitle' => 'Audit Logs',
            'active' => 'audit',
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'actions' => $logs->distinctActions(),
            'entities' => $logs->distinctEntities(),
        ]);
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can view audit logs.');
        }
    }
}
