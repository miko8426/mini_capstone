<?php
use App\Helpers\View;

$users = $users ?? [];
$appliances = $appliances ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Recycle Bin</h5>
        <p class="text-muted mb-0">Deleted accounts and appliances are kept here. Restore them, or delete them permanently.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-person-x me-1"></i>Deleted accounts</span>
        <span class="badge text-bg-light border"><?= count($users) ?></span>
    </div>
    <?php if ($users !== []): ?>
        <div class="card-body border-bottom py-2">
            <form id="bulkPurgeUsersForm" method="POST" action="/recycle-bin/users/bulk-purge"
                  class="d-flex flex-wrap align-items-center gap-2 mb-0"
                  data-confirm="The selected accounts are removed for good. Usage records and audit logs they created stay but are no longer linked to them. This cannot be undone."
                  data-confirm-title="Delete selected accounts permanently?"
                  data-confirm-button="Delete permanently">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkPurgeUsersButton" disabled>
                    <i class="bi bi-trash me-1"></i>Delete Selected
                </button>
                <span class="text-muted small" id="bulkPurgeUsersCount">0 selected</span>
            </form>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 36px;">
                        <input class="form-check-input" type="checkbox" id="bulkPurgeUsersSelectAll"
                               title="Select all deleted accounts" aria-label="Select all deleted accounts">
                    </th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Scope</th>
                    <th>Deleted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        No deleted accounts.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="text-center">
                        <input class="form-check-input js-bulk-user" type="checkbox" name="ids[]"
                               value="<?= (int) $user['id'] ?>" form="bulkPurgeUsersForm"
                               aria-label="Select <?= View::e($user['first_name'] . ' ' . $user['last_name']) ?>">
                    </td>
                    <td class="fw-semibold"><?= View::e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= View::e($user['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= View::e($user['role_name']) ?></span></td>
                    <td>
                        <small class="text-muted">
                            <?= !empty($user['office_name']) ? View::e($user['office_name']) . (!empty($user['campus_name']) ? ' (' . View::e($user['campus_name']) . ')' : '') : View::e($user['campus_name'] ?? 'Institution-wide') ?>
                        </small>
                    </td>
                    <td><small class="text-muted"><?= View::e((string) ($user['deleted_at'] ?? '')) ?></small></td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="/recycle-bin/users/<?= (int) $user['id'] ?>/restore" class="d-inline"
                              data-confirm="The account will be able to log in again with its previous role and scope."
                              data-confirm-title="Restore account?"
                              data-confirm-button="Restore">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-success" title="Restore account"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </form>
                        <form method="POST" action="/recycle-bin/users/<?= (int) $user['id'] ?>/purge" class="d-inline"
                              data-confirm="This permanently removes the account. Usage records and audit logs it created stay but are no longer linked to it. This cannot be undone."
                              data-confirm-title="Delete account permanently?"
                              data-confirm-button="Delete permanently">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete permanently"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-plug me-1"></i>Deleted appliances</span>
        <span class="badge text-bg-light border"><?= (int) ($applianceTotal ?? count($appliances)) ?></span>
    </div>
    <?php if ($appliances !== []): ?>
        <div class="card-body border-bottom py-2">
            <form id="bulkPurgeAppliancesForm" method="POST" action="/recycle-bin/appliances/bulk-purge"
                  class="d-flex flex-wrap align-items-center gap-2 mb-0"
                  data-confirm="The selected appliances are deleted for good, together with their recorded usage history. This cannot be undone."
                  data-confirm-title="Delete selected appliances permanently?"
                  data-confirm-button="Delete permanently">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkPurgeAppliancesButton" disabled>
                    <i class="bi bi-trash me-1"></i>Delete Selected
                </button>
                <span class="text-muted small" id="bulkPurgeAppliancesCount">0 selected</span>
            </form>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 36px;">
                        <input class="form-check-input" type="checkbox" id="bulkPurgeAppliancesSelectAll"
                               title="Select all deleted appliances" aria-label="Select all deleted appliances">
                    </th>
                    <th>Appliance</th>
                    <th>Category</th>
                    <th>Campus / Office</th>
                    <th class="text-center">Qty</th>
                    <th>Deleted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($appliances === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                        No deleted appliances.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($appliances as $appliance): ?>
                <tr>
                    <td class="text-center">
                        <input class="form-check-input js-bulk-appliance" type="checkbox" name="ids[]"
                               value="<?= (int) $appliance['id'] ?>" form="bulkPurgeAppliancesForm"
                               aria-label="Select <?= View::e($appliance['name']) ?>">
                    </td>
                    <td>
                        <span class="fw-semibold"><?= View::e($appliance['name']) ?></span>
                        <?php if (!empty($appliance['brand_model'])): ?>
                            <div class="text-muted small"><?= View::e($appliance['brand_model']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($appliance['category_name']) ?></td>
                    <td>
                        <div><?= View::e($appliance['campus_name']) ?></div>
                        <div class="text-muted small">
                            <?= View::e($appliance['office_name']) ?>
                            <?php if (!empty($appliance['room_number'])): ?>
                                (<?= View::e($appliance['room_number']) ?>)
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center"><?= number_format((int) $appliance['quantity']) ?></td>
                    <td><small class="text-muted"><?= View::e((string) ($appliance['archived_at'] ?? '')) ?></small></td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="/recycle-bin/appliances/<?= (int) $appliance['id'] ?>/restore" class="d-inline"
                              data-confirm="The appliance returns to the active inventory with its previous details."
                              data-confirm-title="Restore appliance?"
                              data-confirm-button="Restore">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-success" title="Restore appliance"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </form>
                        <form method="POST" action="/recycle-bin/appliances/<?= (int) $appliance['id'] ?>/purge" class="d-inline"
                              data-confirm="This permanently deletes the appliance together with its recorded usage history. This cannot be undone."
                              data-confirm-title="Delete appliance permanently?"
                              data-confirm-button="Delete permanently">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete permanently"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Multi selection: pick rows, then delete them permanently in one request.
        const initBulkSelection = (formId, selectAllId, buttonId, countId, boxSelector) => {
            const form = document.getElementById(formId);
            const selectAll = document.getElementById(selectAllId);
            const button = document.getElementById(buttonId);
            const count = document.getElementById(countId);
            if (!form || !selectAll) return;

            const boxes = () => Array.from(document.querySelectorAll(boxSelector));
            const sync = () => {
                const all = boxes();
                const selected = all.filter((box) => box.checked).length;
                if (count) count.textContent = selected + ' selected';
                if (button) button.disabled = selected === 0;
                selectAll.checked = all.length > 0 && selected === all.length;
                selectAll.indeterminate = selected > 0 && selected < all.length;
                delete form.dataset.confirmed;
            };

            selectAll.addEventListener('change', () => {
                boxes().forEach((box) => { box.checked = selectAll.checked; });
                sync();
            });
            boxes().forEach((box) => box.addEventListener('change', sync));
            sync();
        };

        initBulkSelection('bulkPurgeUsersForm', 'bulkPurgeUsersSelectAll', 'bulkPurgeUsersButton', 'bulkPurgeUsersCount', '.js-bulk-user');
        initBulkSelection('bulkPurgeAppliancesForm', 'bulkPurgeAppliancesSelectAll', 'bulkPurgeAppliancesButton', 'bulkPurgeAppliancesCount', '.js-bulk-appliance');
    });
</script>
