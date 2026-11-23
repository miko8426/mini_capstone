<?php
use App\Helpers\View;
?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-search display-4 text-muted"></i>
        <h4 class="mt-3">Not Found</h4>
        <p class="text-muted"><?= View::e($message ?? 'The requested record could not be found.') ?></p>
        <a href="/appliances" class="btn btn-success"><i class="bi bi-arrow-left me-1"></i>Back to inventory</a>
    </div>
</div>
