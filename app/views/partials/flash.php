<?php
use App\Helpers\Session;
use App\Helpers\View;

$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
?>
<?php if ($flashSuccess !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= View::e($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($flashError !== null): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?= View::e($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
