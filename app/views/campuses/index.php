<?php
use App\Helpers\View;
?>
<?php require __DIR__ . '/../partials/organization_tabs.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Campuses</h5>
        <p class="text-muted mb-0">A campus contains offices and appliances.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCampus">
        <i class="bi bi-plus-lg me-1"></i>Add Campus
    </button>
</div>

<form class="row g-2 mb-3" method="get" action="/campuses">
    <div class="col-md-4">
        <input type="text" name="q" value="<?= View::e($search) ?>" class="form-control" placeholder="Search name, code or address">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Search</button>
        <a href="/campuses" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th><th>Code</th><th>Address</th><th>Offices</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($campuses === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No campuses found. Add the first campus.</td></tr>
            <?php endif; ?>
            <?php foreach ($campuses as $campus): ?>
                <tr>
                    <td class="fw-semibold"><?= View::e($campus['name']) ?></td>
                    <td><span class="badge text-bg-light border"><?= View::e($campus['code']) ?></span></td>
                    <td><?= View::e($campus['address'] ?? '—') ?></td>
                    <td><?= (int) $campus['office_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editCampus<?= (int) $campus['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/campuses/<?= (int) $campus['id'] ?>/delete" method="post" class="d-inline"
                              data-confirm="This permanently deletes the campus. Campuses with offices or users cannot be deleted."
                              data-confirm-title="Delete campus?">
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

<?php foreach ($campuses as $campus): ?>
    <div class="modal fade" id="editCampus<?= (int) $campus['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/campuses/<?= (int) $campus['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit Campus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Name</label>
                        <input class="form-control" name="name" required maxlength="100" value="<?= View::e($campus['name']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Code</label>
                        <input class="form-control" name="code" required maxlength="20" value="<?= View::e($campus['code']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Address</label>
                        <input class="form-control" name="address" maxlength="255" value="<?= View::e($campus['address'] ?? '') ?>"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addCampus" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="/campuses" method="post">
            <div class="modal-header"><h5 class="modal-title">Add Campus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="100"></div>
                <div class="mb-3"><label class="form-label">Code</label><input class="form-control" name="code" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Address</label><input class="form-control" name="address" maxlength="255"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Create campus</button></div>
        </form>
    </div>
</div>
