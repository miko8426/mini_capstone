<?php
use App\Helpers\Session;
use App\Helpers\View;

$pageTitle = $pageTitle ?? 'Dashboard';
$active = $active ?? '';
$userRole = (string) Session::get('user_role', '');
$userName = (string) Session::get('user_name', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle) ?> | Eco Metrics</title>
    <link rel="icon" type="image/jpeg" href="/assets/img/chmsu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --forest-green: #2d5a27; --emerald-green: #50c878; --light-gray: #f4f6f4; }
        body { background: var(--light-gray); }
        .app-shell { min-height: 100vh; }
        /* Allow the main column to shrink below its content width so charts and
           wide rows never force horizontal page scrolling on smaller windows. */
        .min-vw-0 { min-width: 0; }
        .sidebar {
            width: 250px; background: var(--forest-green); color: #fff;
            height: 100vh; max-height: 100vh; position: sticky; top: 0;
            display: flex; flex-direction: column; flex-shrink: 0;
        }
        .sidebar-brand { color: #fff; font-weight: 700; }
        .sidebar-body {
            overflow-y: auto; overflow-x: hidden; flex: 1 1 auto;
            scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.25) rgba(0,0,0,0.1);
        }
        .sidebar-body::-webkit-scrollbar { width: 4px; }
        .sidebar-body::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .sidebar-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.25); border-radius: 4px; }
        .border-white-10 { border-color: rgba(255,255,255,0.12) !important; }
        .sidebar-section {
            color: rgba(255,255,255,0.55); font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.08em; padding: 0.35rem 0.9rem; font-weight: 600;
        }
        .sidebar-link {
            color: rgba(255,255,255,0.86); text-decoration: none; display: block;
            padding: 0.55rem 0.85rem; border-radius: 0.5rem; transition: background 0.15s ease;
            font-size: 0.92rem;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .sidebar-link.active { background: var(--emerald-green); color: #10240d; font-weight: 600; }
        .nav-top { background: #fff; border-bottom: 1px solid #dee2e6; }
        .stat-card { border: 0; border-left: 5px solid var(--emerald-green); box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .page-title { color: var(--forest-green); font-weight: 700; }
        .btn-success { background: var(--forest-green); border-color: var(--forest-green); }
        .btn-success:hover { background: #23471f; border-color: #23471f; }
        .table > :not(caption) > * > * { vertical-align: middle; }
        @media (max-width: 991.98px) {
            .sidebar { width: 100%; height: auto; max-height: none; min-height: auto; position: static; }
            .sidebar-body { overflow-y: visible; }
            .app-shell { flex-direction: column !important; }
        }
        @media print {
            .sidebar, .nav-top { display: none !important; }
            main { padding: 0 !important; }
        }
    </style>
</head>
<body>
<div class="d-flex app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="flex-grow-1 min-vw-0">
        <nav class="navbar nav-top px-3 px-lg-4 py-2">
            <div class="d-flex align-items-center gap-2">
                <img src="/assets/img/chmsu-logo.jpg" alt="CHMSU Logo" class="rounded-circle d-lg-none" style="width: 32px; height: 32px; object-fit: cover;">
                <span class="navbar-brand mb-0 page-title"><?= View::e($pageTitle) ?></span>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="/notifications" class="btn btn-sm btn-outline-success position-relative" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if (($unread ?? 0) > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= (int) $unread ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= View::e($userName !== '' ? $userName : $userRole) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header"><?= View::e($userName !== '' ? $userName : 'User') ?></h6></li>
                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-shield-lock me-1"></i><?= View::e($userRole) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($userRole === 'Super Admin'): ?>
                            <li><a class="dropdown-item" href="/recycle-bin"><i class="bi bi-recycle me-2"></i>Recycle Bin</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="/logout"
                               data-confirm="Are you sure you want to log out of your account?"
                               data-confirm-title="Log out?"
                               data-confirm-button="Yes, log out"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
    // DataTables is opt-in per table to preserve server-side pagination elsewhere.
    document.querySelectorAll('table.js-datatable').forEach((table) => {
        if (window.jQuery && window.jQuery.fn.DataTable) {
            window.jQuery(table).DataTable({pageLength: 10, order: []});
        }
    });

    // Yes/No confirmation for links that leave the current session (e.g. logout)
    const confirmLink = (link) => {
        link.addEventListener('click', (event) => {
            if (link.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            Swal.fire({
                icon: 'question',
                title: link.dataset.confirmTitle || 'Are you sure?',
                text: link.dataset.confirm || '',
                showCancelButton: true,
                confirmButtonColor: '#2d5a27',
                cancelButtonColor: '#6c757d',
                confirmButtonText: link.dataset.confirmButton || 'Yes',
                cancelButtonText: 'No',
            }).then((result) => {
                if (result.isConfirmed) {
                    link.dataset.confirmed = '1';
                    window.location.href = link.getAttribute('href');
                }
            });
        });
    };
    document.querySelectorAll('a[data-confirm]').forEach(confirmLink);

    // Confirmation dialogs for destructive actions
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirm,
                showCancelButton: true,
                confirmButtonColor: '#2d5a27',
                cancelButtonColor: '#6c757d',
                confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });
</script>
</body>
</html>
