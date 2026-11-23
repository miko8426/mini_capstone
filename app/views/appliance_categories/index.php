<?php
use App\Helpers\View;

$search = $search ?? '';
$page = $page ?? 1;
$pages = $pages ?? 1;
$total = $total ?? 0;
$csrfToken = $csrfToken ?? '';
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-1">Appliance Categories</h5>
                <p class="text-muted mb-0"><?= number_format((int) $total) ?> categor<?= (int) $total === 1 ? 'y' : 'ies' ?> registered.</p>
            </div>
            <?php if ($canManage): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#categoryModal" id="newCategoryBtn">
                    <i class="bi bi-plus-lg me-1"></i>Add Category
                </button>
            <?php endif; ?>
        </div>

        <form method="GET" action="/appliance-categories" class="row g-2 mb-3">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" name="q" value="<?= View::e($search) ?>" placeholder="Search categories">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-success" type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="btn btn-outline-secondary" href="/appliance-categories">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-center">Appliances</th>
                        <th>Date Added</th>
                        <?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? 5 : 4 ?>" class="text-center text-muted py-4">
                                No appliance categories found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="fw-semibold"><?= View::e($category['name']) ?></td>
                                <td class="text-muted"><?= View::e($category['description'] ?? '—') ?></td>
                                <td class="text-center">
                                    <a href="/appliances?category_id=<?= (int) $category['id'] ?>" class="badge text-bg-light border text-decoration-none">
                                        <?= (int) $category['appliance_count'] ?>
                                    </a>
                                </td>
                                <td><?= View::e(date('M j, Y', strtotime((string) $category['created_at']))) ?></td>
                                <?php if ($canManage): ?>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-category"
                                                data-bs-toggle="modal" data-bs-target="#categoryModal"
                                                data-id="<?= (int) $category['id'] ?>"
                                                data-name="<?= View::e($category['name']) ?>"
                                                data-description="<?= View::e($category['description'] ?? '') ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="POST" action="/appliance-categories/<?= (int) $category['id'] ?>/delete"
                                              class="d-inline"
                                              data-confirm="Appliances still assigned to this category must be reassigned first."
                                              data-confirm-title="Delete category?"
                                              data-confirm-button="Delete">
                                            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav aria-label="Category pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="/appliance-categories?<?= View::e(View::query(['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                            <a class="page-link" href="/appliance-categories?<?= View::e(View::query(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                        <a class="page-link" href="/appliance-categories?<?= View::e(View::query(['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="categoryForm" action="/appliance-categories">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <div class="mb-3">
                    <label class="form-label" for="categoryName">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="categoryName" name="name" maxlength="100" required>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="categoryDescription">Description</label>
                    <textarea class="form-control" id="categoryDescription" name="description" rows="3" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="categorySubmitBtn">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('categoryModal');
        const form = document.getElementById('categoryForm');
        const title = document.getElementById('categoryModalTitle');
        const nameInput = document.getElementById('categoryName');
        const descriptionInput = document.getElementById('categoryDescription');

        document.getElementById('newCategoryBtn')?.addEventListener('click', () => {
            form.action = '/appliance-categories';
            title.textContent = 'Add Category';
            nameInput.value = '';
            descriptionInput.value = '';
        });

        document.querySelectorAll('.edit-category').forEach((button) => {
            button.addEventListener('click', () => {
                form.action = '/appliance-categories/' + button.dataset.id + '/update';
                title.textContent = 'Edit Category';
                nameInput.value = button.dataset.name || '';
                descriptionInput.value = button.dataset.description || '';
            });
        });

        modal?.addEventListener('hidden.bs.modal', () => {
            form.reset();
        });
    });
</script>
<?php endif; ?>
