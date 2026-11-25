<?php
use App\Helpers\View;

$estimate = $estimate ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Consumption History</h5>
        <p class="text-muted mb-0">Immutable snapshots: appliance specifications, rate and emission factor are frozen at calculation time.<?= $estimate !== null ? ' Periods without recorded usage show live estimates.' : '' ?></p>
    </div>
    <a href="/usage" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Record usage</a>
</div>

<?php $statSuffix = $estimate !== null ? ' · estimated' : ''; ?>
<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Total kWh<?= View::e($statSuffix) ?></h6><h4 class="mb-0"><?= number_format($totals['total_kwh'], 2) ?></h4></div></div></div>
    <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Electricity cost<?= View::e($statSuffix) ?></h6><h4 class="mb-0">PHP <?= number_format($totals['total_cost'], 2) ?></h4></div></div></div>
    <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Carbon emissions<?= View::e($statSuffix) ?></h6><h4 class="mb-0"><?= number_format($totals['total_co2'], 2) ?> kg</h4></div></div></div>
</div>

<?php if ($estimate !== null): ?>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            <strong>No usage has been recorded for this period, so these are live estimates.</strong>
            <div class="small">Active appliances accrue from the later of <?= View::e($estimate['period_start']) ?> and the date they were added, through <?= View::e($estimate['period_end']) ?>, using the rate of PHP <?= number_format((float) $estimate['rate_per_kwh'], 4) ?>/kWh and <?= number_format((float) $estimate['emission_factor'], 2) ?> kg CO2e/kWh. Recorded snapshots always take precedence.</div>
        </div>
    </div>
<?php endif; ?>

<form class="card card-body mb-3" method="get" action="/history">
    <div class="row g-2">
        <div class="col-md-3"><input type="text" name="q" value="<?= View::e($filters['search']) ?>" class="form-control" placeholder="Search appliance, office, category"></div>
        <div class="col-md-2"><input type="date" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="form-control" title="From date"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="form-control" title="To date"></div>
        <div class="col-md-2">
            <select name="campus_id" class="form-select" id="historyCampus"><option value="">All campuses</option>
                <?php foreach ($campuses as $campus): ?><option value="<?= (int) $campus['id'] ?>" <?= (int) $filters['campus_id'] === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="office_id" class="form-select" id="historyOffice"><option value="">All offices</option>
                <?php foreach ($offices as $office): ?><option value="<?= (int) $office['id'] ?>" data-campus="<?= (int) $office['campus_id'] ?>" <?= (int) $filters['office_id'] === (int) $office['id'] ? 'selected' : '' ?>><?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-funnel me-1"></i>Filter</button><a href="/history" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const campus = document.getElementById('historyCampus');
    const office = document.getElementById('historyOffice');
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

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Period</th><th>Appliance</th><th>Office</th><th>Category</th><th class="text-end">kWh</th><th class="text-end">Rate</th><th class="text-end">Cost</th><th class="text-end">CO2e</th><th></th></tr></thead>
            <tbody>
            <?php if ($records === []): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No historical records match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($records as $record): ?>
                <?php $isEstimate = !empty($record['estimated']); ?>
                <tr>
                    <td><small><?= View::e($record['period_start']) ?><br><?= View::e($record['period_end']) ?></small></td>
                    <td class="fw-semibold"><?= View::e($record['appliance_name']) ?></td>
                    <td><?= View::e($record['office_name']) ?><?= !empty($record['campus_name']) ? ' <small class="text-muted">(' . View::e($record['campus_name']) . ')</small>' : '' ?></td>
                    <td><?= View::e($record['category_name']) ?></td>
                    <td class="text-end"><?= number_format((float) $record['total_kwh'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $record['rate_per_kwh'], 4) ?></td>
                    <td class="text-end">PHP <?= number_format((float) $record['electricity_cost'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $record['co2_kg'], 2) ?></td>
                    <td class="text-end">
                        <?php if ($isEstimate): ?>
                            <span class="badge bg-secondary" title="Projected from appliance specifications">Estimated</span>
                        <?php else: ?>
                            <a href="/history/<?= (int) $record['id'] ?>" class="btn btn-sm btn-outline-success">Details</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
