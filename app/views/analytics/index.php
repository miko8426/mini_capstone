<?php
use App\Helpers\View;

$json = static fn ($data): string => json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$trendLabels = array_map(static fn ($row) => $row['month'], $trend);
$trendKwh = array_map(static fn ($row) => round((float) $row['total_kwh'], 2), $trend);
$trendCost = array_map(static fn ($row) => round((float) $row['total_cost'], 2), $trend);
$trendCo2 = array_map(static fn ($row) => round((float) $row['total_co2'], 2), $trend);

$officeLabels = array_map(
    static fn (array $row): string => !empty($row['campus_name'])
        ? $row['name'] . ' (' . $row['campus_name'] . ')'
        : (string) $row['name'],
    $byOffice
);

$chartData = [
    'trend' => ['labels' => $trendLabels, 'kwh' => $trendKwh, 'cost' => $trendCost, 'co2' => $trendCo2],
    'campus' => ['labels' => array_column($byCampus, 'name'), 'values' => array_map(static fn ($r) => round((float) $r['total_kwh'], 2), $byCampus)],
    'office' => ['labels' => $officeLabels, 'values' => array_map(static fn ($r) => round((float) $r['total_kwh'], 2), $byOffice)],
    'category' => ['labels' => array_column($byCategory, 'name'), 'values' => array_map(static fn ($r) => round((float) $r['total_kwh'], 2), $byCategory)],
    'appliance' => ['labels' => array_column($topAppliances, 'name'), 'values' => array_map(static fn ($r) => round((float) $r['total_kwh'], 2), $topAppliances)],
];
$isMonthlyDefault = $isMonthlyDefault ?? false;
$estimateMode = $estimateMode ?? false;
$periodLabel = $periodLabel ?? '';
if ($isMonthlyDefault && $periodLabel !== '') {
    $periodText = $periodLabel;
} elseif ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
    $periodText = $filters['date_from'] . ' to ' . $filters['date_to'];
} elseif ($filters['date_from'] !== '') {
    $periodText = 'From ' . $filters['date_from'];
} elseif ($filters['date_to'] !== '') {
    $periodText = 'Up to ' . $filters['date_to'];
} else {
    $periodText = 'All recorded history';
}
$hasData = $trend !== [];
$palette = ['#2d5a27', '#50c878', '#86c98a', '#0f766e', '#84cc16', '#22c55e', '#16a34a', '#4ade80', '#065f46', '#a3e635'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Consumption Analytics</h5>
        <?php if ($estimateMode): ?>
            <p class="text-muted mb-0">No usage recorded for this period, so every figure and chart is projected from the active appliance inventory — from the 1st of the month (or the day each appliance was added) through today.</p>
        <?php else: ?>
            <p class="text-muted mb-0">Monthly view of immutable historical snapshots<?= $periodLabel !== '' ? ' — ' . View::e($periodLabel) : '' ?>.</p>
        <?php endif; ?>
    </div>
</div>

<form class="card card-body mb-4" method="get" action="/analytics">
    <div class="row g-2">
        <div class="col-md-2"><input type="date" name="date_from" value="<?= View::e($filters['date_from']) ?>" class="form-control" title="From"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="<?= View::e($filters['date_to']) ?>" class="form-control" title="To"></div>
        <div class="col-md-2"><select name="campus_id" class="form-select" id="analyticsCampus"><option value="">All campuses</option>
            <?php foreach ($campuses as $campus): ?><option value="<?= (int) $campus['id'] ?>" <?= (int) $filters['campus_id'] === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><select name="office_id" class="form-select" id="analyticsOffice"><option value="">All offices</option>
            <?php foreach ($offices as $office): ?><option value="<?= (int) $office['id'] ?>" data-campus="<?= (int) $office['campus_id'] ?>" <?= (int) $filters['office_id'] === (int) $office['id'] ? 'selected' : '' ?>><?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)</option><?php endforeach; ?>
        </select></div>
        <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-funnel me-1"></i>Apply filters</button><a href="/analytics" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const campus = document.getElementById('analyticsCampus');
    const office = document.getElementById('analyticsOffice');
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

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Total consumption</h6><h4 class="mb-0"><?= number_format($totals['total_kwh'], 2) ?> kWh</h4><div class="text-muted small mt-1"><?= View::e($periodText) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Total cost</h6><h4 class="mb-0">PHP <?= number_format($totals['total_cost'], 2) ?></h4><div class="text-muted small mt-1"><?= View::e($periodText) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1">Total emissions</h6><h4 class="mb-0"><?= number_format($totals['total_co2'], 2) ?> kg</h4><div class="text-muted small mt-1"><?= View::e($periodText) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><div class="card-body"><h6 class="text-muted mb-1"><?= $estimateMode ? 'Appliances counted' : 'Historical records' ?></h6><h4 class="mb-0"><?= number_format($totals['records']) ?></h4><div class="text-muted small mt-1"><?= View::e($periodText) ?></div></div></div></div>
</div>

<?php if (!$hasData): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-bar-chart-line fs-1 d-block mb-2"></i>
        No historical consumption records match the selected filters, and no active appliances fall inside this period. Record usage or add appliances to populate analytics.
    </div></div>
<?php else: ?>
<div class="row g-4">
    <div class="col-12">
        <div class="card"><div class="card-header bg-white fw-semibold">Consumption, cost and emission trend by month</div>
            <div class="card-body" style="height:320px"><canvas id="trendChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Consumption by campus</div><div class="card-body"><canvas id="campusChart" height="240"></canvas></div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white fw-semibold">Consumption by office</div><div class="card-body"><canvas id="officeChart" height="240"></canvas></div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Consumption by appliance category</div><div class="card-body"><canvas id="categoryChart" height="240"></canvas></div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white fw-semibold">High-consumption appliances (top 10)</div><div class="card-body"><canvas id="applianceChart" height="240"></canvas></div></div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const data = <?= $json($chartData) ?>;
    const palette = <?= $json($palette) ?>;
    const peso = (value) => 'PHP ' + Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: data.trend.labels,
            datasets: [
                {label: 'kWh', data: data.trend.kwh, borderColor: palette[0], backgroundColor: 'rgba(45,90,39,0.12)', fill: true, tension: 0.3, yAxisID: 'y'},
                {label: 'CO2e (kg)', data: data.trend.co2, borderColor: palette[1], backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y'},
                {label: 'Cost (PHP)', data: data.trend.cost, borderColor: palette[3], backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y1'},
            ],
        },
        options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
            layout: {padding: {right: 12}},
            scales: {y: {beginAtZero: true, title: {display: true, text: 'kWh / kg CO2e'}},
                     y1: {beginAtZero: true, position: 'right', grid: {drawOnChartArea: false}, title: {display: true, text: 'PHP'}}}}
    });

    const bar = (id, labels, values, horizontal = false, doughnut = false) => {
        new Chart(document.getElementById(id), {
            type: doughnut ? 'doughnut' : (horizontal ? 'bar' : 'bar'),
            data: {labels: labels, datasets: [{label: 'kWh', data: values, backgroundColor: labels.map((_, i) => palette[i % palette.length]), borderWidth: 1}]},
            options: {
                responsive: true,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {legend: {display: doughnut}, tooltip: {callbacks: {label: (ctx) => ' ' + Number(ctx.parsed.x ?? ctx.parsed.y ?? ctx.parsed).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' kWh'}}},
                scales: doughnut ? {} : {x: {beginAtZero: true}, y: {beginAtZero: true}},
            }
        });
    };

    bar('campusChart', data.campus.labels, data.campus.values, false, true);
    bar('officeChart', data.office.labels, data.office.values, true);
    bar('categoryChart', data.category.labels, data.category.values, false, true);
    bar('applianceChart', data.appliance.labels, data.appliance.values, true);
});
</script>
<?php endif; ?>
