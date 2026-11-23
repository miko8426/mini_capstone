<?php
use App\Helpers\View;

$dailyKwh = ((int) $record['quantity'] * (float) $record['wattage'] * (float) $record['daily_operating_hours']) / 1000;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Historical Record #<?= (int) $record['id'] ?></h5>
        <p class="text-muted mb-0">Saved <?= View::e($record['created_at']) ?> &middot; immutable snapshot</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/history" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Frozen appliance specifications</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Appliance</dt><dd class="col-sm-7"><?= View::e($record['appliance_name']) ?></dd>
                    <dt class="col-sm-5">Category</dt><dd class="col-sm-7"><?= View::e($record['category_name']) ?></dd>
                    <dt class="col-sm-5">Campus</dt><dd class="col-sm-7"><?= View::e($record['campus_name']) ?></dd>
                    <dt class="col-sm-5">Office</dt><dd class="col-sm-7"><?= View::e($record['office_name']) ?></dd>
                    <dt class="col-sm-5">Quantity</dt><dd class="col-sm-7"><?= (int) $record['quantity'] ?></dd>
                    <dt class="col-sm-5">Wattage</dt><dd class="col-sm-7"><?= number_format((float) $record['wattage'], 2) ?> W</dd>
                    <dt class="col-sm-5">Daily operating hours</dt><dd class="col-sm-7"><?= number_format((float) $record['daily_operating_hours'], 2) ?> h</dd>
                    <dt class="col-sm-5">Reporting period</dt><dd class="col-sm-7"><?= View::e($record['period_start']) ?> to <?= View::e($record['period_end']) ?> (<?= (int) $record['days_in_period'] ?> days)</dd>
                    <dt class="col-sm-5">Notes</dt><dd class="col-sm-7 mb-0"><?= View::e($record['notes'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Calculation breakdown</div>
            <div class="card-body">
                <p class="mb-2"><strong>Daily kWh</strong> = (<?= (int) $record['quantity'] ?> × <?= number_format((float) $record['wattage'], 2) ?> W × <?= number_format((float) $record['daily_operating_hours'], 2) ?> h) ÷ 1000 = <strong><?= number_format($dailyKwh, 4) ?> kWh</strong></p>
                <p class="mb-2"><strong>Period kWh</strong> = <?= number_format($dailyKwh, 4) ?> × <?= (int) $record['days_in_period'] ?> days = <strong><?= number_format((float) $record['total_kwh'], 4) ?> kWh</strong></p>
                <p class="mb-2"><strong>Cost</strong> = <?= number_format((float) $record['total_kwh'], 4) ?> × PHP <?= number_format((float) $record['rate_per_kwh'], 4) ?> = <strong>PHP <?= number_format((float) $record['electricity_cost'], 2) ?></strong></p>
                <p class="mb-0"><strong>Emissions</strong> = <?= number_format((float) $record['total_kwh'], 4) ?> × <?= number_format((float) $record['emission_factor'], 6) ?> = <strong><?= number_format((float) $record['co2_kg'], 4) ?> kg CO2e</strong></p>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card stat-card mb-3"><div class="card-body"><h6 class="text-muted mb-1">Electricity consumed</h6><h3 class="mb-0"><?= number_format((float) $record['total_kwh'], 2) ?> kWh</h3></div></div>
        <div class="card stat-card mb-3"><div class="card-body"><h6 class="text-muted mb-1">Electricity cost</h6><h3 class="mb-0">PHP <?= number_format((float) $record['electricity_cost'], 2) ?></h3></div></div>
        <div class="card stat-card mb-3"><div class="card-body"><h6 class="text-muted mb-1">Carbon emissions</h6><h3 class="mb-0"><?= number_format((float) $record['co2_kg'], 2) ?> kg CO2e</h3></div></div>
        <div class="alert alert-light border mb-0">
            <i class="bi bi-shield-lock me-1 text-success"></i>
            This record is read-only. Editing the appliance or the current electricity rate/factor will not change these values.
        </div>
    </div>
</div>
