<?php
use App\Helpers\View;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Electricity Rates</h5>
        <p class="text-muted mb-0">Rates are effective-dated. The latest rate on or before a reporting period is applied and frozen in history.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRate"><i class="bi bi-plus-lg me-1"></i>Add Rate</button>
</div>

<div class="alert alert-light border">
    <i class="bi bi-info-circle me-1 text-success"></i>
    Updating a rate never changes already-saved historical records. Rate periods cannot share the same effective date.
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Effective date</th><th class="text-end">Rate / kWh</th><th>Currency</th><th>Status</th><th>Recorded</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($rates === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No rates configured. Usage calculations require a rate.</td></tr>
            <?php endif; ?>
            <?php foreach ($rates as $rate): ?>
                <tr>
                    <td class="fw-semibold"><?= View::e($rate['effective_date']) ?></td>
                    <td class="text-end">PHP <?= rtrim(rtrim(number_format((float) $rate['rate_per_kwh'], 4), '0'), '.') ?></td>
                    <td><?= View::e($rate['currency']) ?></td>
                    <td><span class="badge text-bg-<?= (int) $rate['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $rate['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td><small class="text-muted"><?= View::e($rate['created_at']) ?></small></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editRate<?= (int) $rate['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/rates/<?= (int) $rate['id'] ?>/delete" method="post" class="d-inline"
                              data-confirm="Deleting this rate does not change saved historical records." data-confirm-title="Delete rate?">
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

<?php foreach ($rates as $rate): ?>
    <div class="modal fade" id="editRate<?= (int) $rate['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/rates/<?= (int) $rate['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit Electricity Rate</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Rate per kWh</label><input type="number" step="0.0001" min="0.0001" class="form-control" name="rate_per_kwh" required value="<?= View::e($rate['rate_per_kwh']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Currency</label><input class="form-control" name="currency" maxlength="3" required value="<?= View::e($rate['currency']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date" required value="<?= View::e($rate['effective_date']) ?>"></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="rateActive<?= (int) $rate['id'] ?>" <?= (int) $rate['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="rateActive<?= (int) $rate['id'] ?>">Active</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addRate" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="/rates" method="post">
            <div class="modal-header"><h5 class="modal-title">Add Electricity Rate</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3"><label class="form-label">Rate per kWh</label><input type="number" step="0.0001" min="0.0001" class="form-control" name="rate_per_kwh" required></div>
                <div class="mb-3"><label class="form-label">Currency</label><input class="form-control" name="currency" maxlength="3" value="PHP" required></div>
                <div class="mb-3"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date" required value="<?= date('Y-m-d') ?>"></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="newRateActive" checked><label class="form-check-label" for="newRateActive">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add rate</button></div>
        </form>
    </div>
</div>
