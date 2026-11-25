<?php
use App\Helpers\View;

$monthLabel = (string) ($stats['month_label'] ?? date('M Y'));

$cards = [
    ['label' => 'Total Appliances', 'value' => number_format((int) $stats['total_appliances']), 'icon' => 'bi-plug', 'href' => '/appliances', 'period' => ''],
    ['label' => 'Estimated Monthly Electricity Consumption', 'value' => number_format((float) $stats['total_consumption'], 2) . ' kWh', 'icon' => 'bi-lightning-charge', 'href' => '/history', 'period' => $monthLabel],
    ['label' => 'Estimated Monthly Electricity Expenses', 'value' => 'PHP ' . number_format((float) $stats['total_cost'], 2), 'icon' => 'bi-cash-stack', 'href' => '/analytics', 'period' => $monthLabel],
    ['label' => 'Estimated Monthly Carbon Emissions', 'value' => number_format((float) $stats['total_emissions'], 2) . ' kg', 'icon' => 'bi-cloud', 'href' => '/analytics', 'period' => $monthLabel],
    ['label' => 'Total Offices', 'value' => number_format((int) $stats['total_offices']), 'icon' => 'bi-door-open', 'href' => '/offices', 'period' => ''],
    ['label' => 'High-Consumption Appliances', 'value' => number_format((int) $stats['high_consumption']), 'icon' => 'bi-fire', 'href' => '/analytics', 'period' => ''],
];

$trendLabels = array_map(static fn ($row) => $row['month'], $trend);
$trendKwh = array_map(static fn ($row) => round((float) $row['total_kwh'], 2), $trend);
$json = static fn ($data): string => json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Institutional Overview</h5>
        <p class="text-muted mb-0">Every active appliance accrues daily usage from the 1st of the month (or the day it was added) through today, so estimates keep updating without recorded usage.</p>
    </div>
    <?php if ($canRecord): ?><a href="/usage" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Record usage</a><?php endif; ?>
</div>

<div class="row g-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-sm-6 col-xl-4">
            <a class="text-decoration-none text-reset" href="<?= View::e($card['href']) ?>">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="text-muted mb-2"><?= View::e($card['label']) ?></h6>
                            <i class="bi <?= View::e($card['icon']) ?> text-success fs-5"></i>
                        </div>
                        <h3 class="mb-0"><?= View::e($card['value']) ?></h3>
                        <?php if (($card['period'] ?? '') !== ''): ?>
                            <div class="text-muted small mt-1"><?= View::e($card['period']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Monthly electricity consumption</span>
                <a href="/analytics" class="btn btn-sm btn-outline-success">Open analytics</a>
            </div>
            <div class="card-body">
                <?php if ($trend === []): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-graph-up fs-1 d-block mb-2"></i>
                        No consumption history yet. Record usage to populate this chart.
                    </div>
                <?php else: ?>
                    <canvas id="dashboardTrend" height="120"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Notifications</span>
                <a href="/notifications" class="badge text-bg-success text-decoration-none"><?= (int) $unreadNotifications ?> unread</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if ($notifications === []): ?>
                    <li class="list-group-item text-muted text-center py-4">No unread notifications.</li>
                <?php endif; ?>
                <?php foreach ($notifications as $notification): ?>
                    <li class="list-group-item">
                        <span class="badge text-bg-<?= $notification['type'] === 'critical' ? 'danger' : 'warning' ?> mb-1"><?= View::e(ucfirst((string) $notification['type'])) ?></span>
                        <div class="small fw-semibold"><?= View::e($notification['title']) ?></div>
                        <div class="small text-muted"><?= View::e($notification['message']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent consumption records</span>
                <a href="/history" class="btn btn-sm btn-outline-success">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Period</th><th>Appliance</th><th class="text-end">kWh</th><th class="text-end">CO2e</th></tr></thead>
                    <tbody>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No historical records yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><a href="/history/<?= (int) $row['id'] ?>"><?= View::e($row['period_start']) ?> to <?= View::e($row['period_end']) ?></a></td>
                            <td><?= View::e($row['appliance_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $row['total_kwh'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $row['co2_kg'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Top estimated monthly consumers</div>
            <div class="card-body">
                <?php if ($highConsumers === []): ?>
                    <div class="text-muted text-center py-4">No active appliances to analyze.</div>
                <?php else: ?>
                    <?php foreach ($highConsumers as $item): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <div class="fw-semibold small"><?= View::e($item['appliance']['name']) ?></div>
                                <div class="text-muted small"><?= View::e($item['appliance']['office_name']) ?><?= !empty($item['appliance']['campus_name']) ? ' (' . View::e($item['appliance']['campus_name']) . ')' : '' ?></div>
                            </div>
                            <span class="badge text-bg-success"><?= number_format((float) $item['monthly_kwh'], 2) ?> kWh/mo</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($trend !== []): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
new Chart(document.getElementById('dashboardTrend'), {
    type: 'line',
    data: {
        labels: <?= $json($trendLabels) ?>,
        datasets: [{label: 'kWh', data: <?= $json($trendKwh) ?>, borderColor: '#2d5a27', backgroundColor: 'rgba(80,200,120,0.15)', fill: true, tension: 0.3}]
    },
    options: {responsive: true, scales: {y: {beginAtZero: true}}}
});
});
</script>
<?php endif; ?>
