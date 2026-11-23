<?php
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\View;
use App\Models\Notification;

$active = $active ?? '';

$navItems = [
    ['key' => 'dashboard', 'href' => '/dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    ['key' => 'appliances', 'href' => '/appliances', 'icon' => 'bi-plug', 'label' => 'Appliances'],
    ['key' => 'categories', 'href' => '/appliance-categories', 'icon' => 'bi-tags', 'label' => 'Categories'],
    ['key' => 'usage', 'href' => '/usage', 'icon' => 'bi-clock-history', 'label' => 'Record Usage'],
    ['key' => 'history', 'href' => '/history', 'icon' => 'bi-journal-text', 'label' => 'History'],
    ['key' => 'analytics', 'href' => '/analytics', 'icon' => 'bi-graph-up', 'label' => 'Analytics'],
    ['key' => 'reports', 'href' => '/reports', 'icon' => 'bi-file-earmark-pdf', 'label' => 'PDF Reports'],
];

$adminItems = [
    ['key' => 'organization', 'href' => '/campuses', 'icon' => 'bi-buildings', 'label' => 'Organization'],
    ['key' => 'rates', 'href' => '/rates', 'icon' => 'bi-currency-exchange', 'label' => 'Electricity Rates'],
    ['key' => 'factors', 'href' => '/emission-factors', 'icon' => 'bi-cloud-haze2', 'label' => 'Emission Factors'],
    ['key' => 'thresholds', 'href' => '/thresholds', 'icon' => 'bi-sliders', 'label' => 'Thresholds'],
    ['key' => 'users', 'href' => '/users', 'icon' => 'bi-people', 'label' => 'Users'],
    ['key' => 'audit', 'href' => '/audit-logs', 'icon' => 'bi-shield-check', 'label' => 'Audit Logs'],
];

$unread = 0;
try {
    $unread = (new Notification())->unreadCount((int) Session::get('user_id'));
} catch (\Throwable $e) {
    $unread = 0;
}

$renderItems = static function (array $items) use ($active): void {
    foreach ($items as $item) {
        echo '<a href="' . View::e($item['href']) . '" class="sidebar-link' . ($active === $item['key'] ? ' active' : '') . '">';
        echo '<i class="bi ' . View::e($item['icon']) . ' me-2"></i>' . View::e($item['label']);
        echo '</a>';
    }
};
?>
<div class="sidebar d-flex flex-column" id="appSidebar">
    <div class="p-3 pb-3 border-bottom border-white-10">
        <a href="/dashboard" class="text-decoration-none d-flex align-items-center gap-2 text-white">
            <img src="/assets/img/chmsu-logo.jpg" alt="CHMSU Logo" class="rounded-circle shadow-sm" style="width: 42px; height: 42px; object-fit: cover; border: 2px solid rgba(255,255,255,0.85); background: #fff; flex-shrink: 0;">
            <div class="text-start text-truncate">
                <div class="sidebar-brand fs-5 fw-bold lh-sm text-truncate">Eco Metrics</div>
                <div class="text-white-50 small lh-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">CHMSU ENERGY</div>
            </div>
        </a>
    </div>

    <div class="sidebar-body flex-grow-1 px-3 py-2">
        <nav class="nav flex-column gap-1">
            <?php $renderItems($navItems); ?>
        </nav>

        <?php if (Auth::canAdminister()): ?>
            <div class="sidebar-section mt-3">Administration</div>
            <nav class="nav flex-column gap-1">
                <?php $renderItems($adminItems); ?>
            </nav>
        <?php endif; ?>

        <a href="/notifications" class="sidebar-link mt-2 d-flex align-items-center">
            <i class="bi bi-bell me-2"></i>Notifications
            <?php if ($unread > 0): ?><span class="badge text-bg-danger ms-auto"><?= $unread ?></span><?php endif; ?>
        </a>
    </div>

    <div class="p-3 border-top border-white-10 mt-auto">
        <div class="text-white-50 small mb-2">
            <i class="bi bi-person-circle me-1"></i><?= View::e((string) Session::get('user_name', 'User')) ?>
            <div><?= View::e((string) Session::get('user_role', '')) ?></div>
        </div>
        <a href="/logout" class="sidebar-link"
           data-confirm="Are you sure you want to log out of your account?"
           data-confirm-title="Log out?"
           data-confirm-button="Yes, log out"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
    </div>
</div>
