<?php
use App\Helpers\View;

$appliance = $appliance ?? null;
$old = $old ?? [];
$errors = $errors ?? [];
$isEdit = $appliance !== null;
$action = $isEdit ? '/appliances/' . (int) $appliance['id'] . '/update' : '/appliances';

$value = static function (string $key, mixed $default = '') use ($old, $appliance): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($appliance !== null && array_key_exists($key, $appliance)) {
        return $appliance[$key];
    }
    return $default;
};

$selectedCampus = (int) $value('campus_id', 0);
$selectedOffice = (int) $value('office_id', 0);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-1"><?= $isEdit ? 'Edit Appliance' : 'Add Appliance' ?></h5>
        <p class="text-muted mb-0">Every appliance must be assigned to an existing office.</p>
    </div>
    <a href="<?= $isEdit ? '/appliances/' . (int) $appliance['id'] : '/appliances' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= View::e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?= View::e($action) ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Appliance Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Appliance Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="100" required
                                   value="<?= View::e($value('name')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"<?= (int) $value('category_id', 0) === (int) $category['id'] ? ' selected' : '' ?>>
                                        <?= View::e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="brand_model">Brand / Model</label>
                            <input type="text" class="form-control" id="brand_model" name="brand_model" maxlength="100"
                                   value="<?= View::e($value('brand_model')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="100000" required
                                   value="<?= View::e($value('quantity', 1)) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= View::e($status) ?>"<?= (string) $value('status', 'active') === $status ? ' selected' : '' ?>>
                                        <?= View::e(View::statusLabel($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="voltage">Voltage (V) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="voltage" name="voltage" step="0.01" min="1" max="1000" required
                                   value="<?= View::e($value('voltage', '220')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="wattage">Wattage (W) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="wattage" name="wattage" step="0.01" min="0.01" required
                                   value="<?= View::e($value('wattage')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="daily_operating_hours">Daily Operating Hours <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="daily_operating_hours" name="daily_operating_hours"
                                   step="0.01" min="0" max="24" required
                                   value="<?= View::e($value('daily_operating_hours', '8')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="usage_frequency">Usage Frequency <span class="text-danger">*</span></label>
                            <select class="form-select" id="usage_frequency" name="usage_frequency" required>
                                <?php foreach ($frequencies as $frequency): ?>
                                    <option value="<?= View::e($frequency) ?>"<?= (string) $value('usage_frequency', 'daily') === $frequency ? ' selected' : '' ?>>
                                        <?= View::e(ucfirst($frequency)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="col-md-4">
                                <label class="form-label">Date Added</label>
                                <input type="text" class="form-control" disabled
                                       value="<?= View::e(date('M j, Y g:i A', strtotime((string) $appliance['created_at']))) ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Office Assignment</div>
                <div class="card-body">
                    <p class="text-muted small">
                        Choose the campus first. The office list only contains offices that already exist.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="campus_id">Campus <span class="text-danger">*</span></label>
                        <select class="form-select" id="campus_id" name="campus_id" required>
                            <option value="">Select campus</option>
                            <?php foreach ($campuses as $campus): ?>
                                <option value="<?= (int) $campus['id'] ?>"<?= $selectedCampus === (int) $campus['id'] ? ' selected' : '' ?>>
                                    <?= View::e($campus['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="office_id">Office <span class="text-danger">*</span></label>
                        <select class="form-select" id="office_id" name="office_id" required>
                            <option value="">Select office</option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?= (int) $office['id'] ?>"
                                        data-campus="<?= (int) $office['campus_id'] ?>"<?= $selectedOffice === (int) $office['id'] ? ' selected' : '' ?>>
                                    <?= View::e($office['name']) ?> (<?= View::e($office['campus_name']) ?>)<?php if (!empty($office['room_number'])): ?> (Room <?= View::e($office['room_number']) ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-danger d-none" id="officeHint">
                            Select a campus to choose an office.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-save me-1"></i><?= $isEdit ? 'Save Changes' : 'Add Appliance' ?>
        </button>
        <a href="<?= $isEdit ? '/appliances/' . (int) $appliance['id'] : '/appliances' ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const campus = document.getElementById('campus_id');
        const office = document.getElementById('office_id');
        const officeHint = document.getElementById('officeHint');

        const applyOfficeFilter = () => {
            const campusId = campus.value;
            let selectedIsValid = false;

            Array.from(office.options).forEach((option) => {
                if (option.value === '') {
                    return;
                }
                const matches = !campusId || option.dataset.campus === campusId;
                option.hidden = !matches;
                option.disabled = !matches;
                if (option.selected && matches) {
                    selectedIsValid = true;
                }
            });

            office.disabled = !campusId;
            officeHint.classList.toggle('d-none', Boolean(campusId));
            if (!selectedIsValid) {
                office.value = '';
            }
        };

        campus?.addEventListener('change', () => {
            office.value = '';
            applyOfficeFilter();
        });

        applyOfficeFilter();
    });
</script>
