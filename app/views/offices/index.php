<?php
use App\Helpers\View;
?>
<?php require __DIR__ . '/../partials/organization_tabs.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Offices</h5>
        <p class="text-muted mb-0">An office belongs to a campus, and is the appliance assignment target.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addOffice"><i class="bi bi-plus-lg me-1"></i>Add Office</button>
</div>

<form class="row g-2 mb-3" method="get" action="/offices">
    <div class="col-md-4"><input type="text" name="q" value="<?= View::e($search) ?>" class="form-control" placeholder="Search office, room, campus"></div>
    <div class="col-md-4">
        <select name="campus_id" class="form-select">
            <option value="">All campuses</option>
            <?php foreach ($campuses as $campus): ?>
                <option value="<?= (int) $campus['id'] ?>" <?= $campusId === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Search</button><a href="/offices" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Office</th><th>Room</th><th>Campus</th><th>Floor</th><th>Appliances</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($offices === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No offices found. Add one before assigning appliances.</td></tr>
            <?php endif; ?>
            <?php foreach ($offices as $office): ?>
                <tr>
                    <td class="fw-semibold"><?= View::e($office['name']) ?></td>
                    <td><span class="badge text-bg-light border"><?= View::e($office['room_number']) ?></span></td>
                    <td><?= View::e($office['campus_name']) ?></td>
                    <td><?= (int) $office['floor_level'] ?></td>
                    <td><?= (int) $office['appliance_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editOffice<?= (int) $office['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/offices/<?= (int) $office['id'] ?>/delete" method="post" class="d-inline"
                              data-confirm="This permanently deletes the office. Offices with appliances, users or history cannot be deleted."
                              data-confirm-title="Delete office?">
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

<?php foreach ($offices as $office): ?>
    <div class="modal fade" id="editOffice<?= (int) $office['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/offices/<?= (int) $office['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit Office</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3"><label class="form-label">Campus</label>
                        <select name="campus_id" class="form-select" required>
                            <?php foreach ($campuses as $campus): ?>
                                <option value="<?= (int) $campus['id'] ?>" <?= (int) $office['campus_id'] === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Office name</label><input class="form-control" name="name" required maxlength="100" value="<?= View::e($office['name']) ?>"></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">Room number</label><input class="form-control" name="room_number" required maxlength="30" value="<?= View::e($office['room_number']) ?>"></div>
                        <div class="col-6"><label class="form-label">Floor</label><input type="number" min="0" max="200" class="form-control" name="floor_level" value="<?= (int) $office['floor_level'] ?>"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addOffice" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="/offices" method="post">
            <div class="modal-header"><h5 class="modal-title">Add Office</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3"><label class="form-label">Campus</label>
                    <select name="campus_id" class="form-select" required>
                        <option value="">Select campus</option>
                        <?php foreach ($campuses as $campus): ?><option value="<?= (int) $campus['id'] ?>"><?= View::e($campus['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Office name</label><input class="form-control" name="name" required maxlength="100"></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Room number</label><input class="form-control" name="room_number" required maxlength="30"></div>
                    <div class="col-6"><label class="form-label">Floor</label><input type="number" min="0" max="200" class="form-control" name="floor_level" value="1"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Create office</button></div>
        </form>
    </div>
</div>
