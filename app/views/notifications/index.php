<?php
use App\Helpers\View;

$badge = static fn (string $type): string => match ($type) {
    'critical' => 'danger',
    'warning' => 'warning',
    default => 'info',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1">Notifications</h5>
        <p class="text-muted mb-0"><?= (int) $unreadCount ?> unread notification(s).</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/notifications?unread=1">Unread only</a>
        <form method="post" action="/notifications/read-all">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button class="btn btn-success"><i class="bi bi-check2-all me-1"></i>Mark all read</button>
        </form>
    </div>
</div>

<div class="card">
    <ul class="list-group list-group-flush">
        <?php if ($notifications === []): ?>
            <li class="list-group-item text-center text-muted py-4">No notifications. Threshold alerts will appear here.</li>
        <?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
            <li class="list-group-item <?= (int) $notification['is_read'] === 0 ? 'bg-light' : '' ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <span class="badge text-bg-<?= $badge((string) $notification['type']) ?> me-1"><?= View::e(ucfirst((string) $notification['type'])) ?></span>
                        <strong><?= View::e($notification['title']) ?></strong>
                        <p class="mb-1 mt-1"><?= View::e($notification['message']) ?></p>
                        <small class="text-muted"><?= View::e($notification['created_at']) ?></small>
                    </div>
                    <?php if ((int) $notification['is_read'] === 0): ?>
                        <form method="post" action="/notifications/<?= (int) $notification['id'] ?>/read">
                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                            <button class="btn btn-sm btn-outline-success">Mark read</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-check2 me-1"></i>Read</span>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
