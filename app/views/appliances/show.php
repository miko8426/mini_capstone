<?php
use App\Helpers\View;

$isArchived = $appliance['archived_at'] !== null;
$displayStatus = $isArchived ? 'archived' : (string) $appliance['status'];
$quantity = max(1, (int) $appliance['quantity']);
$wattage = (float) $appliance['wattage'];
$hours = (float) $appliance['daily_operating_hours'];
$dailyPerUnit = ($wattage * $hours) / 1000;
$dailyTotal = $dailyPerUnit * $quantity;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h5 class="mb-1"><?= View::e($appliance['name']) ?></h5>
        <div class="text-muted small">
            <?= View::e($appliance['brand_model'] ?: 'No brand / model recorded') ?>
        </div>
        <span class="badge text-bg-<?= View::e(View::statusBadgeClass($displayStatus)) ?> mt-2">
            <?= View::e(View::statusLabel($displayStatus)) ?>
        </span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/appliances" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if ($canManage && !$isArchived): ?>
            <a href="/appliances/<?= (int) $appliance['id'] ?>/edit" class="btn btn-success">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Appliance Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Appliance Category</dt>
                    <dd class="col-sm-7"><?= View::e($appliance['category_name']) ?></dd>

                    <dt class="col-sm-5">Quantity</dt>
                    <dd class="col-sm-7"><?= number_format($quantity) ?> unit<?= $quantity === 1 ? '' : 's' ?></dd>

                    <dt class="col-sm-5">Voltage</dt>
                    <dd class="col-sm-7"><?= number_format((float) $appliance['voltage'], 2) ?> V</dd>

                    <dt class="col-sm-5">Wattage</dt>
                    <dd class="col-sm-7"><?= number_format($wattage, 2) ?> W</dd>

                    <dt class="col-sm-5">Daily Operating Hours</dt>
                    <dd class="col-sm-7"><?= number_format($hours, 2) ?> hour<?= $hours == 1.0 ? '' : 's' ?></dd>

                    <dt class="col-sm-5">Usage Frequency</dt>
                    <dd class="col-sm-7"><?= View::e(ucfirst((string) $appliance['usage_frequency'])) ?></dd>

                    <dt class="col-sm-5">Date Added</dt>
                    <dd class="col-sm-7"><?= View::e(date('M j, Y g:i A', strtotime((string) $appliance['created_at']))) ?></dd>

                    <dt class="col-sm-5">Last Updated</dt>
                    <dd class="col-sm-7"><?= View::e(date('M j, Y g:i A', strtotime((string) $appliance['updated_at']))) ?></dd>

                    <?php if ($isArchived): ?>
                        <dt class="col-sm-5">Archived On</dt>
                        <dd class="col-sm-7"><?= View::e(date('M j, Y g:i A', strtotime((string) $appliance['archived_at']))) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Location &amp; Assignment</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Campus</dt>
                    <dd class="col-sm-7"><?= View::e($appliance['campus_name']) ?></dd>

                    <dt class="col-sm-5">Office</dt>
                    <dd class="col-sm-7"><?= View::e($appliance['office_name']) ?></dd>

                    <dt class="col-sm-5">Room</dt>
                    <dd class="col-sm-7"><?= View::e($appliance['room_number'] ?: '—') ?></dd>
                </dl>
                <hr>
                <h6 class="text-muted">Estimated Energy Use</h6>
                <div class="d-flex justify-content-between">
                    <span>Per unit / day</span>
                    <strong><?= number_format($dailyPerUnit, 3) ?> kWh</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>All units / day</span>
                    <strong><?= number_format($dailyTotal, 3) ?> kWh</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>All units / 30 days</span>
                    <strong><?= number_format($dailyTotal * 30, 2) ?> kWh</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
    <div class="card mt-3">
        <div class="card-header bg-white fw-semibold">Status Management</div>
        <div class="card-body">
            <?php if ($isArchived): ?>
                <p class="text-muted mb-3">This appliance is archived. Restore it to change its status again.</p>
                <form method="POST" action="/appliances/<?= (int) $appliance['id'] ?>/restore"
                      data-confirm="The appliance will return to the active inventory."
                      data-confirm-title="Restore appliance?"
                      data-confirm-button="Restore">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <button type="submit" class="btn btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Appliance
                    </button>
                </form>
            <?php else: ?>
                <div class="row g-2 align-items-end">
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="status">Appliance Status</label>
                        <form method="POST" action="/appliances/<?= (int) $appliance['id'] ?>/status" id="statusForm">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= View::e($status) ?>"<?= (string) $appliance['status'] === $status ? ' selected' : '' ?>>
                                        <?= View::e(View::statusLabel($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="col-auto">
                        <button type="submit" form="statusForm" class="btn btn-success">Update Status</button>
                    </div>
                    <div class="col-auto ms-sm-auto">
                        <form method="POST" action="/appliances/<?= (int) $appliance['id'] ?>/archive"
                              data-confirm="The appliance will be removed from the active inventory but historical data is kept."
                              data-confirm-title="Archive appliance?"
                              data-confirm-button="Archive">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-archive me-1"></i>Archive Appliance
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
