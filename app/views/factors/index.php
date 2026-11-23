<?php
use App\Helpers\View;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Carbon Emission Factors</h5>
        <p class="text-muted mb-0">Effective-dated kg CO2e per kWh factors used by the calculation engine.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFactor"><i class="bi bi-plus-lg me-1"></i>Add Factor</button>
</div>

<div class="alert alert-light border">
    <i class="bi bi-info-circle me-1 text-success"></i>
    Updating a factor never changes already-saved historical records. The applied factor is stored in each snapshot.
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Factor name</th><th class="text-end">kg CO2e / kWh</th><th>Effective date</th><th>Source</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($factors === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No emission factors configured. Usage calculations require a factor.</td></tr>
            <?php endif; ?>
            <?php foreach ($factors as $factor): ?>
                <tr>
                    <td class="fw-semibold"><?= View::e($factor['factor_name']) ?></td>
                    <td class="text-end"><?= number_format((float) $factor['kg_co2_per_kwh'], 6) ?></td>
                    <td><?= View::e($factor['effective_date']) ?></td>
                    <td><small><?= View::e($factor['source'] ?? '—') ?></small></td>
                    <td><span class="badge text-bg-<?= (int) $factor['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $factor['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editFactor<?= (int) $factor['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/emission-factors/<?= (int) $factor['id'] ?>/delete" method="post" class="d-inline"
                              data-confirm="Deleting this factor does not change saved historical records." data-confirm-title="Delete factor?">
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

<?php foreach ($factors as $factor): ?>
    <div class="modal fade" id="editFactor<?= (int) $factor['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/emission-factors/<?= (int) $factor['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit Emission Factor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Factor name</label><input class="form-control" name="factor_name" required maxlength="100" value="<?= View::e($factor['factor_name']) ?>"></div>
                    <div class="mb-3"><label class="form-label">kg CO2e per kWh</label><input type="number" step="0.000001" min="0.000001" class="form-control" name="kg_co2_per_kwh" required value="<?= View::e($factor['kg_co2_per_kwh']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Source</label><input class="form-control" name="source" maxlength="255" value="<?= View::e($factor['source'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date" required value="<?= View::e($factor['effective_date']) ?>"></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="factorActive<?= (int) $factor['id'] ?>" <?= (int) $factor['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="factorActive<?= (int) $factor['id'] ?>">Active</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addFactor" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="/emission-factors" method="post">
            <div class="modal-header"><h5 class="modal-title">Add Emission Factor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3"><label class="form-label">Factor name</label><input class="form-control" name="factor_name" required maxlength="100"></div>
                <div class="mb-3"><label class="form-label">kg CO2e per kWh</label><input type="number" step="0.000001" min="0.000001" class="form-control" name="kg_co2_per_kwh" required></div>
                <div class="mb-3"><label class="form-label">Source</label><input class="form-control" name="source" maxlength="255"></div>
                <div class="mb-3"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date" required value="<?= date('Y-m-d') ?>"></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="newFactorActive" checked><label class="form-check-label" for="newFactorActive">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add factor</button></div>
        </form>
    </div>
</div>
