<?php
use App\Helpers\View;

$today = new DateTimeImmutable('today');
$oldStart = $old['period_start'] ?? $today->format('Y-m-01');
$oldEnd = $old['period_end'] ?? $today->format('Y-m-t');
$rate = $rates['rate_per_kwh'] ?? null;
$factor = $factors['kg_co2_per_kwh'] ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Record Appliance Usage</h5>
        <p class="text-muted mb-0">PHP performs the official calculation; the preview is for convenience only.</p>
    </div>
    <a href="/history" class="btn btn-outline-success"><i class="bi bi-clock-history me-1"></i>Usage history</a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <strong>Please fix the following:</strong>
        <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= View::e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Usage input</div>
            <div class="card-body">
                <?php if ($appliances === []): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-plug fs-2 d-block mb-2"></i>
                        No active appliances are available in your scope.
                        <?php if (\App\Helpers\Auth::canManage()): ?><a href="/appliances/create">Add an appliance first.</a><?php endif; ?>
                    </div>
                <?php else: ?>
                <form method="post" action="/usage" id="usageForm">
                    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label">Appliance</label>
                        <select class="form-select" name="appliance_id" id="applianceSelect" required>
                            <option value="">Select an appliance</option>
                            <?php foreach ($appliances as $appliance): ?>
                                <option value="<?= (int) $appliance['id'] ?>"
                                        data-quantity="<?= (int) $appliance['quantity'] ?>"
                                        data-wattage="<?= View::e($appliance['wattage']) ?>"
                                        data-hours="<?= View::e($appliance['daily_operating_hours']) ?>"
                                        <?= (int) ($old['appliance_id'] ?? 0) === (int) $appliance['id'] ? 'selected' : '' ?>>
                                    <?= View::e($appliance['name'] . ' — ' . $appliance['office_name'] . (!empty($appliance['campus_name']) ? ' (' . $appliance['campus_name'] . ')' : '') . ' (' . $appliance['quantity'] . ' × ' . $appliance['wattage'] . 'W)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Operating hours / day</label>
                            <input type="number" step="0.01" min="0" max="24" class="form-control" name="hours_per_day" id="hoursInput" value="<?= View::e($old['hours_per_day'] ?? '') ?>" placeholder="e.g. 8">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Days / week</label>
                            <input type="number" step="0.5" min="1" max="7" class="form-control" name="days_per_week" value="<?= View::e($old['days_per_week'] ?? '5') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reporting period</label>
                            <div class="input-group">
                                <input type="date" class="form-control" name="period_start" id="periodStart" value="<?= View::e($oldStart) ?>" required>
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control" name="period_end" id="periodEnd" value="<?= View::e($oldEnd) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" class="form-control" name="notes" maxlength="255" value="<?= View::e($old['notes'] ?? '') ?>" placeholder="e.g. October meter reading">
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button class="btn btn-success"><i class="bi bi-calculator me-1"></i>Calculate &amp; save</button>
                        <span class="text-muted small">Rate: <strong><?= $rate !== null ? 'PHP ' . rtrim(rtrim(number_format((float) $rate, 4), '0'), '.') . '/kWh' : 'not configured' ?></strong>
                            &middot; Factor: <strong><?= $factor !== null ? rtrim(rtrim(number_format((float) $factor, 6), '0'), '.') . ' kg CO2e/kWh' : 'not configured' ?></strong></span>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Live preview (not official)</div>
            <div class="card-body">
                <dl class="row mb-0" id="previewList">
                    <dt class="col-6">Daily consumption</dt><dd class="col-6" id="previewDaily">— kWh</dd>
                    <dt class="col-6">Weekly consumption</dt><dd class="col-6" id="previewWeekly">— kWh</dd>
                    <dt class="col-6">Period consumption</dt><dd class="col-6" id="previewPeriod">— kWh</dd>
                    <dt class="col-6">Estimated cost</dt><dd class="col-6" id="previewCost">—</dd>
                    <dt class="col-6">Carbon emissions</dt><dd class="col-6 mb-0" id="previewCo2">—</dd>
                </dl>
                <p class="text-muted small mt-3 mb-0">
                    Formula: (quantity × wattage × hours × days_per_week÷7) ÷ 1000. Period consumption uses the actual inclusive date range.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white fw-semibold">Recent recorded usage</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Period</th><th>Appliance</th><th>Office</th><th class="text-end">kWh</th><th class="text-end">Cost</th><th class="text-end">CO2e</th><th></th></tr></thead>
            <tbody>
            <?php if ($recent === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No usage records yet. Recorded calculations will appear here.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?= View::e($row['period_start']) ?> to <?= View::e($row['period_end']) ?></td>
                    <td><?= View::e($row['appliance_name']) ?></td>
                    <td><?= View::e($row['office_name']) ?></td>
                    <td class="text-end"><?= number_format((float) $row['total_kwh'], 2) ?></td>
                    <td class="text-end">PHP <?= number_format((float) $row['electricity_cost'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $row['co2_kg'], 2) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-success" href="/history/<?= (int) $row['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(() => {
    const rate = <?= json_encode($rate !== null ? (float) $rate : 0) ?>;
    const factor = <?= json_encode($factor !== null ? (float) $factor : 0) ?>;
    const select = document.getElementById('applianceSelect');
    const hours = document.getElementById('hoursInput');
    const start = document.getElementById('periodStart');
    const end = document.getElementById('periodEnd');
    const daysPerWeekInput = document.querySelector('input[name="days_per_week"]');
    if (!select || !hours) return;

    const fmt = (value, digits = 2) => value.toLocaleString(undefined, {minimumFractionDigits: digits, maximumFractionDigits: digits});

    function update() {
        const option = select.selectedOptions[0];
        if (!option || !option.value) return;
        const quantity = Number(option.dataset.quantity || 0);
        const wattage = Number(option.dataset.wattage || 0);
        const hoursValue = Number(hours.value || option.dataset.hours || 0);
        const daysPerWeek = Number(daysPerWeekInput ? daysPerWeekInput.value || 7 : 7);
        const effectiveHours = hoursValue * (daysPerWeek / 7);
        const daily = (quantity * wattage * effectiveHours) / 1000;

        let days = 0;
        if (start.value && end.value) {
            const from = new Date(start.value + 'T00:00:00');
            const to = new Date(end.value + 'T00:00:00');
            days = Math.max(0, Math.round((to - from) / 86400000) + 1);
        }

        const period = daily * days;
        document.getElementById('previewDaily').textContent = fmt(daily) + ' kWh';
        document.getElementById('previewWeekly').textContent = fmt(daily * 7) + ' kWh';
        document.getElementById('previewPeriod').textContent = fmt(period) + ' kWh (' + days + ' days)';
        document.getElementById('previewCost').textContent = 'PHP ' + fmt(period * rate);
        document.getElementById('previewCo2').textContent = fmt(period * factor) + ' kg CO2e';
    }

    ['change', 'input'].forEach((eventName) => {
        select.addEventListener(eventName, update);
        hours.addEventListener(eventName, update);
        start.addEventListener(eventName, update);
        end.addEventListener(eventName, update);
        if (daysPerWeekInput) daysPerWeekInput.addEventListener(eventName, update);
    });
    update();
})();
</script>
