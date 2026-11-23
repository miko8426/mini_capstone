<?php
use App\Helpers\View;

$query = $_GET;
unset($query['type']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">PDF Reports</h5>
        <p class="text-muted mb-0">Reports are generated from database records and immutable historical snapshots, with restrictions applied to your role.</p>
    </div>
</div>

<form class="card card-body mb-4" method="get" action="/reports">
    <div class="row g-2">
        <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="date_from" value="<?= View::e($_GET['date_from'] ?? '') ?>" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="date_to" value="<?= View::e($_GET['date_to'] ?? '') ?>" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">Campus</label>
            <select name="campus_id" class="form-select" id="reportCampus"><option value="">All</option>
                <?php foreach ($campuses as $campus): ?><option value="<?= (int) $campus['id'] ?>" <?= (int) ($_GET['campus_id'] ?? 0) === (int) $campus['id'] ? 'selected' : '' ?>><?= View::e($campus['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Office</label>
            <select name="office_id" class="form-select" id="reportOffice"><option value="">All</option>
                <?php foreach ($offices as $office): ?><option value="<?= (int) $office['id'] ?>" data-campus="<?= (int) $office['campus_id'] ?>" <?= (int) ($_GET['office_id'] ?? 0) === (int) $office['id'] ? 'selected' : '' ?>><?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-success"><i class="bi bi-funnel me-1"></i>Apply filters</button><a href="/reports" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const campus = document.getElementById('reportCampus');
    const office = document.getElementById('reportOffice');
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

<div class="row g-3">
    <?php foreach ($reports as $key => $label): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="badge rounded-pill text-bg-success"><i class="bi bi-file-earmark-pdf"></i></span>
                        <h6 class="mb-0"><?= View::e($label) ?></h6>
                    </div>
                    <p class="text-muted small mb-3">
                        <?php if ($key === 'inventory'): ?>Appliance specifications, office assignment and status.
                        <?php elseif ($key === 'sustainability'): ?>Institution-wide totals with campus and category contribution.
                        <?php elseif ($key === 'high-consumption'): ?>The twenty highest-consuming appliances for the selected filters.
                        <?php else: ?>Totals and breakdowns generated from saved consumption snapshots.
                        <?php endif; ?>
                    </p>
                    <a class="btn btn-success mt-auto" href="/reports/generate?<?= View::e(http_build_query(array_merge($query, ['type' => $key]))) ?>">
                        <i class="bi bi-download me-1"></i>Generate PDF
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
