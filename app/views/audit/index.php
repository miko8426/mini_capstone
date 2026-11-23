<?php
use App\Helpers\View;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Audit Logs</h5>
        <p class="text-muted mb-0">Administrative and security events recorded by the system.</p>
    </div>
</div>

<form class="card card-body mb-3" method="get" action="/audit-logs">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="<?= View::e($filters['search']) ?>" class="form-control" placeholder="Search action, entity, details, user"></div>
        <div class="col-md-2"><select name="action" class="form-select"><option value="">All actions</option>
            <?php foreach ($actions as $action): ?><option value="<?= View::e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= View::e($action) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><select name="entity" class="form-select"><option value="">All entities</option>
            <?php foreach ($entities as $entity): ?><option value="<?= View::e($entity) ?>" <?= $filters['entity'] === $entity ? 'selected' : '' ?>><?= View::e($entity) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="form-control" title="From"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="form-control" title="To"></div>
        <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-funnel me-1"></i>Filter</button><a href="/audit-logs" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No audit records match the filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-nowrap"><small><?= View::e($log['created_at']) ?></small></td>
                    <td><small><?= View::e($log['email'] ?? 'System') ?><br><?= View::e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></small></td>
                    <td><span class="badge text-bg-light border"><?= View::e($log['action']) ?></span></td>
                    <td><small><?= View::e($log['entity']) ?><?= $log['entity_id'] !== null ? ' #' . (int) $log['entity_id'] : '' ?></small></td>
                    <td><small><?= View::e($log['details'] ?? '—') ?></small></td>
                    <td><small class="text-muted"><?= View::e($log['ip_address'] ?? '—') ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
