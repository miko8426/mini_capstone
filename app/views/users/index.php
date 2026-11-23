<?php
use App\Helpers\View;

$field = static function (array $user, string $name): string {
    return View::e($user[$name] ?? '');
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">User Management</h5>
        <p class="text-muted mb-0">Assign roles and organizational scope. Non-admin users only see records in their assigned scope.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUser"><i class="bi bi-person-plus me-1"></i>Add User</button>
</div>

<form class="row g-2 mb-3" method="get" action="/users">
    <div class="col-md-4"><input type="text" name="q" value="<?= View::e($search) ?>" class="form-control" placeholder="Search name or email"></div>
    <div class="col-md-3">
        <select name="role_id" class="form-select"><option value="">All roles</option>
            <?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $roleId === (int) $role['id'] ? 'selected' : '' ?>><?= View::e($role['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select"><option value="">All statuses</option>
            <?php foreach (['active', 'inactive', 'suspended'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Search</button><a href="/users" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Scope</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No users match the filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="fw-semibold"><?= View::e($user['first_name'] . ' ' . $user['last_name']) ?><?= (int) $user['id'] === $currentUserId ? ' <span class="badge text-bg-success">You</span>' : '' ?></td>
                    <td><?= View::e($user['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= View::e($user['role_name']) ?></span></td>
                    <td>
                        <small class="text-muted">
                            <?= !empty($user['office_name']) ? View::e($user['office_name']) . (!empty($user['campus_name']) ? ' (' . View::e($user['campus_name']) . ')' : '') : View::e($user['campus_name'] ?? 'Institution-wide') ?>
                        </small>
                    </td>
                    <td><span class="badge text-bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'suspended' ? 'danger' : 'secondary') ?>"><?= View::e(ucfirst($user['status'])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editUser<?= (int) $user['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form action="/users/<?= (int) $user['id'] ?>/status" method="post" class="d-inline"
                              data-confirm="Change this account status? Deactivated users cannot log in." data-confirm-title="Change account status?">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button class="btn btn-sm btn-outline-<?= $user['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                <i class="bi bi-<?= $user['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <?php if ((int) $user['id'] !== $currentUserId): ?>
                            <button class="btn btn-sm btn-outline-danger" title="Delete account" data-bs-toggle="modal" data-bs-target="#deleteUser<?= (int) $user['id'] ?>"><i class="bi bi-trash"></i></button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" title="You cannot delete your own account" disabled><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>

<?php
$userModalFields = static function (array $user = []) use ($roles, $campuses, $offices): void {
    $selectedRole = (int) ($user['role_id'] ?? 0);
    $selectedCampus = (int) ($user['campus_id'] ?? 0);
    $selectedOffice = (int) ($user['office_id'] ?? 0);
    ?>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">First name</label><input class="form-control" name="first_name" required maxlength="50" value="<?= View::e($user['first_name'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" required maxlength="50" value="<?= View::e($user['last_name'] ?? '') ?>"></div>
    </div>
    <div class="mb-3 mt-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="100" value="<?= View::e($user['email'] ?? '') ?>"></div>
    <div class="mb-3"><label class="form-label">Password <?= $user === [] ? '' : '(leave blank to keep current)' ?></label><input type="password" class="form-control" name="password" <?= $user === [] ? 'required' : '' ?> minlength="8"></div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Role</label>
            <select name="role_id" class="form-select" required>
                <option value="">Select role</option>
                <?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $selectedRole === (int) $role['id'] ? 'selected' : '' ?>><?= View::e($role['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6"><label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach (['active', 'inactive', 'suspended'] as $option): ?><option value="<?= $option ?>" <?= ($user['status'] ?? 'active') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <hr>
    <p class="text-muted small mb-2">Access scope (optional; most specific assignment limits what the user can see).</p>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Campus</label>
            <select name="campus_id" class="form-select js-user-campus"><option value="">None</option>
                <?php foreach ($campuses as $campus): ?><option value="<?= (int) $campus['id'] ?>" <?= $selectedCampus === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6"><label class="form-label">Office</label>
            <select name="office_id" class="form-select js-user-office"><option value="">None</option>
                <?php foreach ($offices as $office): ?>
                    <option value="<?= (int) $office['id'] ?>"
                            data-campus="<?= (int) $office['campus_id'] ?>"
                            <?= $selectedOffice === (int) $office['id'] ? 'selected' : '' ?>>
                        <?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
};
?>

<?php foreach ($users as $user): ?>
    <div class="modal fade" id="editUser<?= (int) $user['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" action="/users/<?= (int) $user['id'] ?>/update" method="post">
                <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <?php $userModalFields($user); ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save changes</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($users as $user): ?>
    <?php if ((int) $user['id'] === $currentUserId) { continue; } ?>
    <div class="modal fade" id="deleteUser<?= (int) $user['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="/users/<?= (int) $user['id'] ?>/delete" method="post">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <p class="mb-2">You are about to permanently delete the account of</p>
                    <p class="mb-3"><strong><?= View::e($user['first_name'] . ' ' . $user['last_name']) ?></strong><br><span class="text-muted"><?= View::e($user['email']) ?></span></p>
                    <div class="alert alert-warning py-2 small mb-3">
                        The account is moved to the Recycle Bin, where it can be restored or permanently deleted. Usage records and audit logs it created stay but are no longer linked to it.
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="deletePassword<?= (int) $user['id'] ?>">Confirm with your password</label>
                        <input type="password" class="form-control" id="deletePassword<?= (int) $user['id'] ?>" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <div class="form-text">Enter the password of your own administrator account to authorize this deletion.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete account</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="addUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="/users" method="post">
            <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <?php $userModalFields(); ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Create user</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach((modal) => {
        const campusSelect = modal.querySelector('.js-user-campus');
        const officeSelect = modal.querySelector('.js-user-office');
        if (!campusSelect || !officeSelect) return;

        const syncOffices = () => {
            const campusId = campusSelect.value;
            Array.from(officeSelect.options).forEach((opt) => {
                if (!opt.value) return; // Keep "None" visible
                const matches = !campusId || opt.dataset.campus === campusId;
                opt.hidden = !matches;
                opt.disabled = !matches;
            });
            const currentOpt = officeSelect.selectedOptions[0];
            if (currentOpt && currentOpt.value && campusId && currentOpt.dataset.campus !== campusId) {
                officeSelect.value = '';
            }
        };

        campusSelect.addEventListener('change', syncOffices);

        officeSelect.addEventListener('change', () => {
            const opt = officeSelect.selectedOptions[0];
            if (opt && opt.dataset.campus) {
                campusSelect.value = opt.dataset.campus;
                syncOffices();
            }
        });

        // Initialize filtering on modal show
        modal.addEventListener('show.bs.modal', syncOffices);
        syncOffices();
    });
});
</script>
