<?php
use App\Helpers\View;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Consumption Thresholds</h5>
        <p class="text-muted mb-0">Administrators define monthly kWh limits for campuses, offices or appliances.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addThreshold"><i class="bi bi-plus-lg me-1"></i>Add Threshold</button>
</div>

<div class="card card-body mb-3">
    <form class="row g-2 align-items-end" method="post" action="/thresholds/evaluate">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="col-md-3">
            <label class="form-label">Check threshold alerts for month</label>
            <input type="month" class="form-control" name="month" value="<?= View::e($monthKey) ?>">
        </div>
        <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-bell me-1"></i>Evaluate &amp; notify</button></div>
        <div class="col">
            <small class="text-muted">Duplicate alerts for the same threshold and month are automatically prevented.</small>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Scope</th><th>Record</th><th class="text-end">Monthly limit (kWh)</th><th class="text-end">Alert at</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($thresholds === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No thresholds configured. Add one to enable alerts.</td></tr>
            <?php endif; ?>
            <?php foreach ($thresholds as $threshold): ?>
                <tr>
                    <td class="text-capitalize fw-semibold"><?= View::e($threshold['scope_type']) ?></td>
                    <td><?= View::e($threshold['scope_name']) ?></td>
                    <td class="text-end"><?= number_format((float) $threshold['monthly_kwh_limit'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $threshold['alert_percentage'], 2) ?>%</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editThreshold<?= (int) $threshold['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/thresholds/<?= (int) $threshold['id'] ?>/delete" method="post" class="d-inline"
                              data-confirm="Deleting this threshold also removes its generated notifications." data-confirm-title="Delete threshold?">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>

<?php
$scopeSelect = static function (string $name, array $options, ?string $selected = null): void {
    echo '<select name="' . $name . '" class="form-select" required><option value="">Select scope</option>';
    foreach ($options as $type => $records) {
        if ($records === []) {
            continue;
        }
        echo '<optgroup label="' . ucfirst($type) . '">';
        foreach ($records as $id => $label) {
            $value = $type . ':' . $id;
            $isSelected = $selected === $value ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $isSelected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';
};
?>

<?php foreach ($thresholds as $threshold): ?>
    <div class="modal fade" id="editThreshold<?= (int) $threshold['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/thresholds/<?= (int) $threshold['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit Threshold</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Scope</label>
                        <?php $scopeSelect('scope', $scopeOptions, $threshold['scope_type'] . ':' . $threshold['scope_id']); ?>
                    </div>
                    <div class="mb-3"><label class="form-label">Monthly limit (kWh)</label><input type="number" step="0.01" min="0.01" class="form-control" name="monthly_kwh_limit" required value="<?= View::e($threshold['monthly_kwh_limit']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Alert at (%)</label><input type="number" step="0.01" min="1" max="100" class="form-control" name="alert_percentage" required value="<?= View::e($threshold['alert_percentage']) ?>"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addThreshold" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="/thresholds" method="post">
            <div class="modal-header"><h5 class="modal-title">Add Threshold</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3"><label class="form-label">Scope</label><?php $scopeSelect('scope', $scopeOptions); ?></div>
                <div class="mb-3"><label class="form-label">Monthly limit (kWh)</label><input type="number" step="0.01" min="0.01" class="form-control" name="monthly_kwh_limit" required></div>
                <div class="mb-3"><label class="form-label">Alert at (%)</label><input type="number" step="0.01" min="1" max="100" class="form-control" name="alert_percentage" value="80" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add threshold</button></div>
        </form>
    </div>
</div>
