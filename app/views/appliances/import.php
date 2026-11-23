<?php

use App\Helpers\View;

$preview = $preview ?? null;
$errors = $errors ?? [];
$columns = $columns ?? [];
$requiredColumns = $requiredColumns ?? [];
$maxRows = $maxRows ?? 500;
$maxBytes = $maxBytes ?? 2097152;
$old = $old ?? [];

$rowValue = static function (array $row, string $key): string {
    return (string) ($row['values'][$key] ?? '');
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Import Appliances</h5>
        <p class="text-muted mb-0">Upload a CSV or Excel file, review the validation result, then confirm the import.</p>
    </div>
    <a href="/appliances" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-1"></i>Import stopped</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= View::e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Step 1 &middot; Upload File</div>
            <div class="card-body">
                <form method="POST" action="/appliances/import" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="import_file">CSV or Excel file</label>
                        <input type="file" class="form-control" id="import_file" name="import_file"
                               accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        <div class="form-text">
                            <?= number_format((int) $maxBytes / 1048576, 0) ?> MB maximum, up to <?= number_format((int) $maxRows) ?> rows.
                            The first sheet of an .xlsx workbook is read.
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-shield-check me-1"></i>Validate File
                        </button>
                        <a href="/appliances/import/template.csv" class="btn btn-outline-secondary">
                            <i class="bi bi-download me-1"></i>CSV Template
                        </a>
                    </div>
                </form>

                <hr>

                <h6 class="text-muted">Expected columns</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Heading</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($columns as $key => $label): ?>
                                <tr>
                                    <td><?= View::e($label) ?></td>
                                    <td>
                                        <?php if (in_array($key, $requiredColumns, true)): ?>
                                            <span class="badge text-bg-danger">Yes</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-light text-dark">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Campus, office and category must already exist in the system. Frequency defaults to
                    <em>daily</em> and status to <em>active</em>. Blank rows are ignored.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Step 2 &middot; Review Validation</span>
                <?php if ($preview !== null): ?>
                    <span class="text-muted small"><?= View::e($preview['file']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($preview === null): ?>
                    <p class="text-muted mb-0">
                        Nothing has been imported yet. Choose a file above and the system will check every row
                        before anything is stored.
                    </p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-success"><?= number_format((int) $preview['valid']) ?> valid row<?= (int) $preview['valid'] === 1 ? '' : 's' ?></span>
                        <span class="badge text-bg-<?= (int) $preview['invalid'] > 0 ? 'danger' : 'secondary' ?>"><?= number_format((int) $preview['invalid']) ?> invalid row<?= (int) $preview['invalid'] === 1 ? '' : 's' ?></span>
                    </div>

                    <div class="table-responsive" style="max-height: 24rem; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Appliance</th>
                                    <th>Campus / Office</th>
                                    <th>Category</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Watt</th>
                                    <th class="text-end">Hrs</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['rows'] as $row): ?>
                                    <?php $isValid = $row['errors'] === []; ?>
                                    <tr class="<?= $isValid ? '' : 'table-danger' ?>">
                                        <td class="text-muted small"><?= (int) $row['line'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= View::e($rowValue($row, 'name')) ?></div>
                                            <?php if ($rowValue($row, 'brand_model') !== ''): ?>
                                                <div class="text-muted small"><?= View::e($rowValue($row, 'brand_model')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <div><?= View::e($rowValue($row, 'campus')) ?></div>
                                            <div class="text-muted"><?= View::e($rowValue($row, 'office')) ?></div>
                                        </td>
                                        <td class="small"><?= View::e($rowValue($row, 'category')) ?></td>
                                        <td class="text-end"><?= View::e($rowValue($row, 'quantity')) ?></td>
                                        <td class="text-end"><?= View::e($rowValue($row, 'wattage')) ?></td>
                                        <td class="text-end"><?= View::e($rowValue($row, 'daily_operating_hours')) ?></td>
                                        <td>
                                            <?php if ($isValid): ?>
                                                <span class="badge text-bg-success">Valid</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">Invalid</span>
                                                <ul class="text-danger small mb-0 ps-3 mt-1">
                                                    <?php foreach ($row['errors'] as $error): ?>
                                                        <li><?= View::e($error) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <?php if ((int) $preview['valid'] > 0): ?>
                        <p class="text-muted small">
                            <?= number_format((int) $preview['valid']) ?> row<?= (int) $preview['valid'] === 1 ? '' : 's' ?>
                            will be added to the inventory<?= (int) $preview['invalid'] > 0 ? ', and the invalid rows above will be skipped' : '' ?>.
                            This writes to the database.
                        </p>
                        <form method="POST" action="/appliances/import/confirm" class="d-flex flex-wrap gap-2"
                              data-confirm="<?= (int) $preview['valid'] ?> appliance(s) will be added to the inventory."
                              data-confirm-title="Confirm import?"
                              data-confirm-button="Import">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i>Import <?= number_format((int) $preview['valid']) ?> Valid Row<?= (int) $preview['valid'] === 1 ? '' : 's' ?>
                            </button>
                            <a href="/appliances/import" class="btn btn-outline-secondary">Upload a different file</a>
                        </form>
                    <?php else: ?>
                        <p class="text-danger small mb-2">
                            No row passed validation, so nothing can be imported. Fix the problems above in your file and upload it again.
                        </p>
                        <a href="/appliances/import" class="btn btn-outline-secondary">Upload a corrected file</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
