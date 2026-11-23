<?php
use App\Helpers\View;

$filters = $filters ?? [];
$statusOptions = [
    '' => 'All active statuses',
    'active' => 'Active',
    'under_maintenance' => 'Under Maintenance',
    'decommissioned' => 'Decommissioned',
    'archived' => 'Archived',
];
$offices = $offices ?? [];
$summary = $summary ?? [];
$stats = $stats ?? ['count' => 0, 'total_quantity' => 0, 'kwh_per_day' => 0];
$search = (string) ($filters['search'] ?? '');
$hasFilters = $search !== ''
    || (int) ($filters['category_id'] ?? 0) > 0
    || (int) ($filters['campus_id'] ?? 0) > 0
    || (int) ($filters['office_id'] ?? 0) > 0
    || (string) ($filters['status'] ?? '') !== '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Appliance Inventory</h5>
        <p class="text-muted mb-0">Assign appliances to existing offices and track their operating profile.</p>
    </div>
    <?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <a href="/appliances/import" class="btn btn-outline-success"><i class="bi bi-upload me-1"></i>Import File</a>
            <a href="/appliances/create" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add Appliance</a>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Matching Appliances</h6>
                <h3 class="mb-0"><?= number_format((int) $stats['count']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Units</h6>
                <h3 class="mb-0"><?= number_format((int) $stats['total_quantity']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Estimated Daily Consumption</h6>
                <h3 class="mb-0"><?= number_format((float) $stats['kwh_per_day'], 2) ?> <small class="fs-6 text-muted">kWh</small></h3>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/appliances" class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="filterSearch">Search</label>
                <input type="search" class="form-control" id="filterSearch" name="search"
                       value="<?= View::e($search) ?>" placeholder="Name, brand, office, campus...">
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="filterCategory">Category</label>
                <select class="form-select" id="filterCategory" name="category_id">
                    <option value="">All categories</option>
                    <?php foreach ($categories ?? [] as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= (int) ($filters['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>>
                            <?= View::e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="filterCampus">Campus</label>
                <select class="form-select" id="filterCampus" name="campus_id">
                    <option value="">All campuses</option>
                    <?php foreach ($campuses ?? [] as $campus): ?>
                        <option value="<?= (int) $campus['id'] ?>"<?= (int) ($filters['campus_id'] ?? 0) === (int) $campus['id'] ? ' selected' : '' ?>>
                            <?= View::e($campus['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <label class="form-label" for="filterOffice">Office</label>
                <select class="form-select" id="filterOffice" name="office_id">
                    <option value="">All offices</option>
                    <?php foreach ($offices as $office): ?>
                        <option value="<?= (int) $office['id'] ?>"
                                data-campus="<?= (int) $office['campus_id'] ?>"<?= (int) ($filters['office_id'] ?? 0) === (int) $office['id'] ? ' selected' : '' ?>>
                            <?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label" for="filterStatus">Status</label>
                <select class="form-select" id="filterStatus" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= View::e($value) ?>"<?= (string) ($filters['status'] ?? '') === $value ? ' selected' : '' ?>>
                            <?= View::e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($hasFilters): ?>
                    <a class="btn btn-outline-secondary" href="/appliances">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($canManage): ?>
            <form id="bulkDeleteForm" method="POST" action="/appliances/bulk-delete"
                  class="d-flex flex-wrap align-items-center gap-2 mb-3"
                  data-confirm="The selected appliances are removed from the active inventory. Their usage history is kept and they can be restored from the Archived filter."
                  data-confirm-title="Delete selected appliances?"
                  data-confirm-button="Delete selected">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? '') ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkDeleteButton" disabled>
                    <i class="bi bi-trash me-1"></i>Delete Selected
                </button>
                <span class="text-muted small" id="bulkSelectionCount">0 selected</span>
            </form>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <?php if ($canManage): ?>
                            <th class="text-center" style="width: 36px;">
                                <input class="form-check-input" type="checkbox" id="bulkSelectAll"
                                       title="Select all on this page" aria-label="Select all appliances on this page">
                            </th>
                        <?php endif; ?>
                        <th>Appliance</th>
                        <th>Category</th>
                        <th>Campus / Office</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Wattage</th>
                        <th class="text-end">Hours/Day</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appliances)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? 9 : 8 ?>" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No appliances match the current filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appliances as $appliance): ?>
                            <?php
                            $isArchived = $appliance['archived_at'] !== null;
                            $status = $isArchived ? 'archived' : (string) $appliance['status'];
                            ?>
                            <tr>
                                <?php if ($canManage): ?>
                                    <td class="text-center">
                                        <?php if (!$isArchived): ?>
                                            <input class="form-check-input js-bulk-check" type="checkbox" name="ids[]"
                                                   value="<?= (int) $appliance['id'] ?>" form="bulkDeleteForm"
                                                   aria-label="Select <?= View::e($appliance['name']) ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <a href="/appliances/<?= (int) $appliance['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= View::e($appliance['name']) ?>
                                    </a>
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
                                <td class="text-end"><?= number_format((float) $appliance['wattage'], 2) ?> W</td>
                                <td class="text-end"><?= number_format((float) $appliance['daily_operating_hours'], 2) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= View::e(View::statusBadgeClass($status)) ?>">
                                        <?= View::e(View::statusLabel($status)) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="/appliances/<?= (int) $appliance['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($canManage && !$isArchived): ?>
                                        <a href="/appliances/<?= (int) $appliance['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="/appliances/<?= (int) $appliance['id'] ?>/archive" class="d-inline"
                                              data-confirm="The appliance will be removed from the active inventory but its history is kept."
                                              data-confirm-title="Archive appliance?"
                                              data-confirm-button="Archive">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                                        </form>
                                    <?php elseif ($canManage && $isArchived): ?>
                                        <form method="POST" action="/appliances/<?= (int) $appliance['id'] ?>/restore" class="d-inline"
                                              data-confirm="The appliance will return to the active inventory."
                                              data-confirm-title="Restore appliance?"
                                              data-confirm-button="Restore">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <span class="text-muted small">
                Showing <?= count($appliances) ?> of <?= number_format((int) $total) ?> appliance<?= (int) $total === 1 ? '' : 's' ?>
            </span>
            <?php if ($pages > 1): ?>
                <nav aria-label="Appliance pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                            <a class="page-link" href="/appliances?<?= View::e(View::query(['page' => max(1, $page - 1)])) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                                <a class="page-link" href="/appliances?<?= View::e(View::query(['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                            <a class="page-link" href="/appliances?<?= View::e(View::query(['page' => min($pages, $page + 1)])) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <?php foreach (['campus' => 'By Campus', 'office' => 'By Office'] as $key => $label): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= $key === 'campus' ? ' active' : '' ?>" data-bs-toggle="tab"
                            data-bs-target="#summary-<?= $key ?>" type="button" role="tab">
                        <?= View::e($label) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <?php foreach (['campus', 'office'] as $key): ?>
                <?php $rows = $summary[$key] ?? []; ?>
                <div class="tab-pane fade<?= $key === 'campus' ? ' show active' : '' ?>" id="summary-<?= $key ?>" role="tabpanel">
                    <?php if (empty($rows)): ?>
                        <p class="text-muted mb-0">No active appliances to summarise.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th><?= ucfirst($key) ?></th>
                                        <th class="text-center">Appliances</th>
                                        <th class="text-center">Units</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><?= View::e($row['name']) ?></td>
                                            <td class="text-center"><?= number_format((int) $row['appliance_count']) ?></td>
                                            <td class="text-center"><?= number_format((int) $row['total_quantity']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Multi selection toolbar: select rows, then delete them in one request.
        const bulkForm = document.getElementById('bulkDeleteForm');
        const bulkButton = document.getElementById('bulkDeleteButton');
        const bulkCount = document.getElementById('bulkSelectionCount');
        const selectAll = document.getElementById('bulkSelectAll');
        const checkboxes = () => Array.from(document.querySelectorAll('.js-bulk-check'));

        const syncBulkSelection = () => {
            const boxes = checkboxes();
            const selected = boxes.filter((box) => box.checked).length;
            if (bulkCount) bulkCount.textContent = selected + ' selected';
            if (bulkButton) bulkButton.disabled = selected === 0;
            if (selectAll) {
                selectAll.checked = boxes.length > 0 && selected === boxes.length;
                selectAll.indeterminate = selected > 0 && selected < boxes.length;
            }
            if (bulkForm) delete bulkForm.dataset.confirmed;
        };

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes().forEach((box) => { box.checked = selectAll.checked; });
                syncBulkSelection();
            });
        }
        checkboxes().forEach((box) => box.addEventListener('change', syncBulkSelection));
        syncBulkSelection();

        const campus = document.getElementById('filterCampus');
        const office = document.getElementById('filterOffice');

        const filterOffices = () => {
            if (!campus || !office) return;
            const campusId = campus.value;
            Array.from(office.options).forEach((opt) => {
                if (!opt.value) return;
                const matches = !campusId || opt.dataset.campus === campusId;
                opt.hidden = !matches;
                opt.disabled = !matches;
            });
            const currentOpt = office.selectedOptions[0];
            if (currentOpt && currentOpt.value && campusId && currentOpt.dataset.campus !== campusId) {
                office.value = '';
            }
        };

        campus?.addEventListener('change', () => {
            office.value = '';
            filterOffices();
        });

        filterOffices();
    });
</script>
