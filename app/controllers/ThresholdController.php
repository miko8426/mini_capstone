<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Appliance;
use App\Models\Campus;
use App\Models\Office;
use App\Models\Threshold;
use App\Services\NotificationService;

class ThresholdController extends BaseController
{
    private Threshold $thresholds;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->thresholds = new Threshold();
    }

    public function index(): void
    {
        $this->guardAdmin();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->thresholds->paginate($page);

        View::render('thresholds/index', [
            'pageTitle' => 'Consumption Thresholds',
            'active' => 'thresholds',
            'thresholds' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'scopeOptions' => $this->scopeOptions(),
            'csrfToken' => Session::generateCsrfToken(),
            'monthKey' => date('Y-m'),
        ]);
    }

    public function store(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/thresholds');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/thresholds');
        }

        $id = $this->thresholds->create($data['scope_type'], $data['scope_id'], $data['limit'], $data['alert']);
        Audit::record('CREATE', 'consumption_thresholds', $id, 'Created threshold for ' . $data['scope_type'] . ' #' . $data['scope_id']);
        Session::flash('success', 'Consumption threshold created.');
        $this->redirect('/thresholds');
    }

    public function update(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/thresholds');

        if ($this->thresholds->find($id) === null) {
            Session::flash('error', 'Threshold not found.');
            $this->redirect('/thresholds');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/thresholds');
        }

        $this->thresholds->update($id, $data['scope_type'], $data['scope_id'], $data['limit'], $data['alert']);
        Audit::record('UPDATE', 'consumption_thresholds', $id, 'Updated threshold');
        Session::flash('success', 'Consumption threshold updated.');
        $this->redirect('/thresholds');
    }

    public function delete(int $id): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/thresholds');

        if ($this->thresholds->find($id) === null) {
            Session::flash('error', 'Threshold not found.');
            $this->redirect('/thresholds');
        }

        $this->thresholds->delete($id);
        Audit::record('DELETE', 'consumption_thresholds', $id, 'Deleted threshold');
        Session::flash('success', 'Consumption threshold deleted.');
        $this->redirect('/thresholds');
    }

    public function evaluate(): void
    {
        $this->guardAdmin();
        $this->guardCsrf('/thresholds');

        $monthKey = trim((string) ($_POST['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            Session::flash('error', 'Select a valid reporting month.');
            $this->redirect('/thresholds');
        }

        $result = (new NotificationService())->evaluate($monthKey);
        Audit::record('EVALUATE', 'consumption_thresholds', null, sprintf('Evaluated %s: %d threshold(s), %d notification(s) created', $monthKey, $result['checked'], $result['created']));

        $message = sprintf(
            'Checked %d threshold(s) for %s; %d threshold(s) exceeded and %d new notification(s) created.',
            $result['checked'],
            $monthKey,
            count($result['exceeded']),
            $result['created']
        );
        Session::flash($result['exceeded'] === [] ? 'success' : 'warning', $message);
        $this->redirect('/thresholds');
    }

    /** @return array<string, array<string, string>> */
    private function scopeOptions(): array
    {
        $options = [
            'campus' => [],
            'office' => [],
            'appliance' => [],
        ];

        foreach ((new Campus())->all() as $row) {
            $options['campus'][(string) $row['id']] = $row['name'];
        }
        foreach ((new Office())->paginate('', 0, 1, 1000)['items'] as $row) {
            $options['office'][(string) $row['id']] = $row['name'] . ' (' . $row['campus_name'] . ')';
        }
        foreach ((new Appliance())->allActive() as $row) {
            $options['appliance'][(string) $row['id']] = $row['name'] . ' (' . $row['office_name'] . ')';
        }

        return $options;
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validate(array $input, ?int $exceptId = null): array
    {
        $scope = trim((string) ($input['scope'] ?? ''));
        [$scopeType, $scopeIdRaw] = array_pad(explode(':', $scope, 2), 2, '');
        $scopeId = (int) $scopeIdRaw;
        $data = [
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'limit' => (float) ($input['monthly_kwh_limit'] ?? 0),
            'alert' => (float) ($input['alert_percentage'] ?? 80),
        ];
        $errors = [];

        $options = $this->scopeOptions();
        if (!in_array($scopeType, Threshold::SCOPE_TYPES, true)) {
            $errors[] = 'Select a valid threshold scope.';
        } elseif (!isset($options[$scopeType][(string) $scopeId])) {
            $errors[] = 'Select a valid record for the chosen scope.';
        } elseif ($this->thresholds->exists($scopeType, $scopeId, $exceptId)) {
            $errors[] = 'A threshold already exists for that record.';
        }

        if ($data['limit'] <= 0 || $data['limit'] > 100000000) {
            $errors[] = 'Monthly kWh limit must be greater than 0.';
        }
        if ($data['alert'] < 1 || $data['alert'] > 100) {
            $errors[] = 'Alert percentage must be between 1 and 100.';
        }

        return [$data, $errors];
    }

    private function guardAdmin(): void
    {
        if (!Auth::canAdminister()) {
            http_response_code(403);
            exit('Access Denied: only a System Administrator can manage thresholds.');
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
