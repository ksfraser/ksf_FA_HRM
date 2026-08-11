<?php
$path_to_root = "../../..";
$page_security = 'SA_ksf_FA_HRMMANAGE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Grade.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/GradeRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/GradeService.php");

use ksfraser\FrontAccounting\HRM\Service\GradeService;

$service = new GradeService();

if (isset($_POST['save_grade'])) {
    $service->create($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=grades');
    exit;
}

if (isset($_POST['update_grade'])) {
    $service->update((int)$_POST['grade_id'], $_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=grades');
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_row = $service->getById((int)$_GET['edit']);
    $show_form = true;
}

$grades = $service->listAll();
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Grades"); ?></h5>
        <a href="?view=grades&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Grade"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=grades">
                <?php if ($edit_mode && $edit_row): ?>
                    <input type="hidden" name="update_grade" value="1">
                    <input type="hidden" name="grade_id" value="<?php echo (int)$edit_row['grade_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="save_grade" value="1">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="grade_code"><?php echo _("Code"); ?></label>
                            <input type="text" class="form-control" id="grade_code" name="grade_code" required maxlength="20"
                                value="<?php echo htmlspecialchars($edit_row['grade_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="grade_name"><?php echo _("Name"); ?></label>
                            <input type="text" class="form-control" id="grade_name" name="grade_name" required maxlength="100"
                                value="<?php echo htmlspecialchars($edit_row['grade_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="min_salary"><?php echo _("Min Salary"); ?></label>
                            <input type="number" class="form-control" id="min_salary" name="min_salary" step="0.01" min="0"
                                value="<?php echo htmlspecialchars($edit_row['min_salary'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="max_salary"><?php echo _("Max Salary"); ?></label>
                            <input type="number" class="form-control" id="max_salary" name="max_salary" step="0.01" min="0"
                                value="<?php echo htmlspecialchars($edit_row['max_salary'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                    <?php echo (!$edit_mode || ($edit_row['is_active'] ?? 1)) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active"><?php echo _("Active"); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="description"><?php echo _("Description"); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=grades" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th class="text-right"><?php echo _("Min Salary"); ?></th>
                    <th class="text-right"><?php echo _("Max Salary"); ?></th>
                    <th><?php echo _("Description"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($grades)): ?>
                <?php foreach ($grades as $grade): ?>
                    <tr>
                        <td><?php echo html_entity_decode($grade->getGradeCode()); ?></td>
                        <td><?php echo html_entity_decode($grade->getGradeName()); ?></td>
                        <td class="text-right"><?php echo price_format($grade->getMinSalary()); ?></td>
                        <td class="text-right"><?php echo price_format($grade->getMaxSalary()); ?></td>
                        <td><?php echo html_entity_decode($grade->getDescription() ?? ''); ?></td>
                        <td class="text-center">
                            <?php if ($grade->isActive()): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a href="?view=grades&edit=<?php echo (int)$grade->getGradeId(); ?>" class="btn btn-outline-secondary btn-sm"><?php echo _("Edit"); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted"><?php echo _("No grades found."); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('toggle-add').addEventListener('click', function(e) {
    e.preventDefault();
    var form = document.getElementById('add-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
});
</script>
