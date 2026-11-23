<?php
use App\Helpers\View;

$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);

if ($pages <= 1) {
    return;
}

$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Pagination">
    <small class="text-muted"><?= number_format($total) ?> record(s) &middot; page <?= $page ?> of <?= $pages ?></small>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= View::e(View::query(['page' => max(1, $page - 1)])) ?>">Previous</a>
        </li>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= View::e(View::query(['page' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= View::e(View::query(['page' => min($pages, $page + 1)])) ?>">Next</a>
        </li>
    </ul>
</nav>
