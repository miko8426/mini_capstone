<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Models\Appliance;
use App\Models\ApplianceCategory;
use App\Models\Organization;
use App\Services\ApplianceImportService;
use RuntimeException;
use Throwable;

class ApplianceController extends BaseController
{
    private Appliance $appliances;
    private ApplianceCategory $categories;
    private Organization $organization;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->appliances = new Appliance();
        $this->categories = new ApplianceCategory();
        $this->organization = new Organization();
    }

    public function index(): void
    {
        $filters = Auth::applyScope($this->filters());
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->appliances->paginate($filters, $page);

        View::render('appliances/index', [
            'pageTitle' => 'Appliance Inventory',
            'active' => 'appliances',
            'appliances' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'stats' => $this->appliances->stats($filters),
            'categories' => $this->categories->all(),
            'campuses' => $this->organization->campuses(Auth::scope()),
            'offices' => $this->scopedOffices(),
            'summary' => [
                'campus' => $this->appliances->summaryBy('campus', $filters),
                'office' => $this->appliances->summaryBy('office', $filters),
            ],
            'canManage' => Auth::canManage(),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->guardManage();
        $this->renderForm(null, [], []);
    }

    public function store(): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances/create');

        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm(null, $errors, $_POST);
            return;
        }

        $id = $this->appliances->create($data);
        Session::flash('success', 'Appliance added and assigned to its office.');
        $this->redirect('/appliances/' . $id);
    }

    /**
     * Show the bulk import page (upload form, or a validated preview).
     */
    public function import(): void
    {
        $this->guardManage();
        $this->renderImport(null, []);
    }

    /**
     * Parse and validate an uploaded CSV / Excel file without writing anything.
     */
    public function previewImport(): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances/import');

        $service = new ApplianceImportService();
        $errors = [];
        $preview = null;

        try {
            $file = $this->uploadedImportFile();
            $parsed = $service->parse($file['path'], $file['name']);
            if ($parsed['errors'] !== []) {
                $errors = $parsed['errors'];
            } else {
                $validated = $service->validate($parsed['rows'], Auth::scope());
                $preview = [
                    'file' => $file['name'],
                    'rows' => $validated['rows'],
                    'valid' => $validated['valid'],
                    'invalid' => $validated['invalid'],
                ];

                // Confirmation reloads the parsed rows from the session so the
                // browser cannot alter the payload before it is inserted.
                Session::set('appliance_import', [
                    'file' => $file['name'],
                    'rows' => $parsed['rows'],
                    'created' => time(),
                ]);
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors !== []) {
            http_response_code(422);
        }

        $this->renderImport($preview, $errors, ['file' => $_FILES['import_file']['name'] ?? '']);
    }

    /**
     * Re-validate the previewed rows and insert the valid ones.
     */
    public function confirmImport(): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances/import');

        $stored = Session::get('appliance_import');
        if (!is_array($stored) || !isset($stored['rows']) || !is_array($stored['rows'])) {
            Session::flash('error', 'There is no import waiting to be confirmed. Please upload the file again.');
            $this->redirect('/appliances/import');
        }

        if (time() - (int) ($stored['created'] ?? 0) > 1800) {
            Session::delete('appliance_import');
            Session::flash('error', 'The import preview expired after 30 minutes. Please upload the file again.');
            $this->redirect('/appliances/import');
        }

        $service = new ApplianceImportService();
        $validated = $service->validate($stored['rows'], Auth::scope());
        $importable = array_values(array_filter(
            $validated['rows'],
            static fn (array $row): bool => $row['errors'] === [] && $row['data'] !== null
        ));

        if ($importable === []) {
            http_response_code(422);
            $this->renderImport([
                'file' => (string) ($stored['file'] ?? ''),
                'rows' => $validated['rows'],
                'valid' => $validated['valid'],
                'invalid' => $validated['invalid'],
            ], ['No row passed validation, so nothing was imported.']);
            return;
        }

        $result = $service->persist($importable);
        if ($result['errors'] !== []) {
            http_response_code(422);
            $this->renderImport([
                'file' => (string) ($stored['file'] ?? ''),
                'rows' => $validated['rows'],
                'valid' => $validated['valid'],
                'invalid' => $validated['invalid'],
            ], $result['errors']);
            return;
        }

        Session::delete('appliance_import');
        Audit::record(
            'IMPORT',
            'appliances',
            null,
            sprintf('Imported %d appliance(s) from %s', $result['inserted'], (string) ($stored['file'] ?? 'upload'))
        );

        $message = sprintf('%d appliance%s imported successfully.', $result['inserted'], $result['inserted'] === 1 ? '' : 's');
        if ($validated['invalid'] > 0) {
            $message .= sprintf(
                ' %d invalid row%s %s skipped.',
                $validated['invalid'],
                $validated['invalid'] === 1 ? '' : 's',
                $validated['invalid'] === 1 ? 'was' : 'were'
            );
        }
        Session::flash('success', $message);
        $this->redirect('/appliances');
    }

    /**
     * Download a CSV template with the expected columns.
     */
    public function templateCsv(): void
    {
        $this->guardManage();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="appliance-import-template.csv"');
        header('Cache-Control: no-store');
        echo (new ApplianceImportService())->templateCsv();
        exit;
    }

    public function show(int $id): void
    {
        $appliance = $this->findOr404($id);

        View::render('appliances/show', [
            'pageTitle' => $appliance['name'],
            'active' => 'appliances',
            'appliance' => $appliance,
            'statuses' => Appliance::STATUSES,
            'canManage' => Auth::canManage(),
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    public function edit(int $id): void
    {
        $this->guardManage();
        $appliance = $this->findOr404($id);
        $this->renderForm($appliance, [], []);
    }

    public function update(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances/' . $id . '/edit');

        $appliance = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($appliance, $errors, $_POST);
            return;
        }

        $this->appliances->update($id, $data);
        Session::flash('success', 'Appliance updated successfully.');
        $this->redirect('/appliances/' . $id);
    }

    public function updateStatus(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances/' . $id);

        $appliance = $this->findOr404($id);
        if ($appliance['archived_at'] !== null) {
            Session::flash('error', 'Archived appliances cannot change status. Restore the record first.');
            $this->redirect('/appliances/' . $id);
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, Appliance::STATUSES, true)) {
            Session::flash('error', 'Invalid appliance status selected.');
            $this->redirect('/appliances/' . $id);
        }

        $this->appliances->updateStatus($id, $status);
        Session::flash('success', 'Appliance status updated to ' . View::statusLabel($status) . '.');
        $this->redirect('/appliances/' . $id);
    }

    /**
     * Delete several selected appliances from the active inventory at once.
     */
    public function bulkDelete(): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances');

        $raw = $_POST['ids'] ?? [];
        $ids = is_array($raw)
            ? array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)))
            : [];

        if ($ids === []) {
            Session::flash('error', 'Select at least one appliance to delete.');
            $this->redirect('/appliances');
        }

        // Never touch records outside the user's scope or already archived rows.
        $deletable = [];
        foreach ($ids as $id) {
            $appliance = $this->appliances->find($id);
            if ($appliance === null || $appliance['archived_at'] !== null || !Auth::allows($appliance)) {
                continue;
            }
            $deletable[] = $id;
        }

        if ($deletable === []) {
            Session::flash('error', 'None of the selected appliances can be deleted.');
            $this->redirect('/appliances');
        }

        $deleted = $this->appliances->archiveMany($deletable);
        Audit::record(
            'DELETE',
            'appliances',
            null,
            'Deleted ' . $deleted . ' appliance(s) from the inventory (ids: ' . implode(', ', $deletable) . ')'
        );

        $message = $deleted . ' appliance' . ($deleted === 1 ? '' : 's') . ' deleted from the active inventory.';
        $skipped = count($ids) - $deleted;
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' selected record' . ($skipped === 1 ? ' was' : 's were')
                . ' skipped (already archived or outside your access).';
        }
        Session::flash('success', $message . ' Usage history is kept; deleted appliances can be restored from the Archived filter.');
        $this->redirect('/appliances');
    }

    public function archive(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances');

        $this->findOr404($id);
        if ($this->appliances->archive($id)) {
            Session::flash('success', 'Appliance archived. It is excluded from the active inventory.');
        } else {
            Session::flash('error', 'Appliance is already archived.');
        }
        $this->redirect('/appliances');
    }

    public function restore(int $id): void
    {
        $this->guardManage();
        $this->guardCsrf('/appliances?status=archived');

        $this->findOr404($id);
        if ($this->appliances->restore($id)) {
            Session::flash('success', 'Appliance restored to the active inventory.');
        } else {
            Session::flash('error', 'Appliance is not archived.');
        }
        $this->redirect('/appliances?status=archived');
    }

    /**
     * @param array<string, mixed>|null $appliance
     * @param array<int, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderForm(?array $appliance, array $errors, array $old): void
    {
        View::render('appliances/form', [
            'pageTitle' => $appliance === null ? 'Add Appliance' : 'Edit Appliance',
            'active' => 'appliances',
            'appliance' => $appliance,
            'errors' => $errors,
            'old' => $old,
            'categories' => $this->categories->all(),
            'campuses' => $this->organization->campuses(Auth::scope()),
            'offices' => $this->scopedOffices(),
            'statuses' => Appliance::STATUSES,
            'frequencies' => Appliance::USAGE_FREQUENCIES,
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validate(array $input): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'brand_model' => trim((string) ($input['brand_model'] ?? '')),
            'category_id' => (int) ($input['category_id'] ?? 0),
            'office_id' => (int) ($input['office_id'] ?? 0),
            'campus_id' => (int) ($input['campus_id'] ?? 0),
            'quantity' => (int) ($input['quantity'] ?? 0),
            'voltage' => (float) ($input['voltage'] ?? 0),
            'wattage' => (float) ($input['wattage'] ?? 0),
            'daily_operating_hours' => (float) ($input['daily_operating_hours'] ?? 0),
            'usage_frequency' => (string) ($input['usage_frequency'] ?? ''),
            'status' => (string) ($input['status'] ?? ''),
        ];
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Appliance name is required.';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors[] = 'Appliance name must not exceed 100 characters.';
        }

        if (mb_strlen($data['brand_model']) > 100) {
            $errors[] = 'Brand / model must not exceed 100 characters.';
        }

        if ($data['category_id'] <= 0 || $this->categories->find($data['category_id']) === null) {
            $errors[] = 'Select a valid appliance category.';
        }

        // An office must exist before an appliance can be assigned to it.
        $office = $data['office_id'] > 0 ? $this->organization->findOffice($data['office_id']) : null;
        if ($office === null) {
            $errors[] = 'Selected office does not exist. An office must exist before an appliance can be assigned.';
        } else {
            $office['office_id'] = (int) $office['id'];
            if (!Auth::allows($office)) {
                $errors[] = 'The selected office is outside your assigned scope.';
            }
            if ($data['campus_id'] > 0 && (int) $office['campus_id'] !== $data['campus_id']) {
                $errors[] = 'The selected office does not belong to the chosen campus.';
            }
        }

        if ($data['quantity'] < 1) {
            $errors[] = 'Quantity must be at least 1.';
        } elseif ($data['quantity'] > 100000) {
            $errors[] = 'Quantity is too large.';
        }

        if ($data['voltage'] <= 0 || $data['voltage'] > 1000) {
            $errors[] = 'Voltage must be a number between 1 and 1000 volts.';
        }

        if ($data['wattage'] <= 0 || $data['wattage'] > 1000000) {
            $errors[] = 'Wattage must be a number greater than 0.';
        }

        if ($data['daily_operating_hours'] < 0 || $data['daily_operating_hours'] > 24) {
            $errors[] = 'Daily operating hours must be between 0 and 24.';
        }

        if (!in_array($data['usage_frequency'], Appliance::USAGE_FREQUENCIES, true)) {
            $errors[] = 'Select a valid usage frequency.';
        }

        if (!in_array($data['status'], Appliance::STATUSES, true)) {
            $errors[] = 'Select a valid appliance status.';
        }

        return [$data, $errors];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'category_id' => (int) ($_GET['category_id'] ?? 0),
            'campus_id' => (int) ($_GET['campus_id'] ?? 0),
            'office_id' => (int) ($_GET['office_id'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
        ];

        if (!in_array($filters['status'], array_merge(Appliance::STATUSES, ['archived']), true)) {
            $filters['status'] = '';
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(int $id): array
    {
        $appliance = $this->appliances->find($id);
        if ($appliance === null || !Auth::allows($appliance)) {
            http_response_code(404);
            View::render('errors/404', [
                'pageTitle' => 'Not Found',
                'active' => 'appliances',
                'message' => 'The requested appliance does not exist or has been permanently removed.',
            ]);
            exit;
        }

        return $appliance;
    }

    /**
     * Office choices limited to the user's organizational scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scopedOffices(): array
    {
        $offices = array_map(
            static fn (array $office): array => $office + ['office_id' => (int) $office['id']],
            $this->organization->offices()
        );

        return array_values(array_filter($offices, static fn (array $office): bool => Auth::allows($office)));
    }

    /**
     * @param array<string, mixed>|null $preview
     * @param array<int, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderImport(?array $preview, array $errors, array $old = []): void
    {
        View::render('appliances/import', [
            'pageTitle' => 'Import Appliances',
            'active' => 'appliances',
            'preview' => $preview,
            'errors' => $errors,
            'old' => $old,
            'columns' => ApplianceImportService::COLUMNS,
            'requiredColumns' => ApplianceImportService::REQUIRED_COLUMNS,
            'maxRows' => ApplianceImportService::MAX_ROWS,
            'maxBytes' => ApplianceImportService::MAX_BYTES,
            'csrfToken' => Session::generateCsrfToken(),
        ]);
    }

    /**
     * Validate the uploaded file entry before it is parsed.
     *
     * @return array{path: string, name: string}
     */
    private function uploadedImportFile(): array
    {
        $file = $_FILES['import_file'] ?? null;
        if (!is_array($file) || !isset($file['error'])) {
            throw new RuntimeException('Choose a CSV or Excel file to import.');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ((int) $file['error']) {
                UPLOAD_ERR_NO_FILE => 'Choose a CSV or Excel file to import.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the 2 MB limit.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                default => 'The file could not be uploaded. Please try again.',
            });
        }

        $name = (string) ($file['name'] ?? '');
        $path = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $path === '' || !is_uploaded_file($path)) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        if ($size > ApplianceImportService::MAX_BYTES) {
            throw new RuntimeException('The file is larger than the 2 MB limit.');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Only .csv and .xlsx files can be imported.');
        }

        return ['path' => $path, 'name' => $name];
    }

    private function guardManage(): void
    {
        if (!Auth::canManage()) {
            http_response_code(403);
            exit('Access Denied: your role cannot modify appliance inventory.');
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
