<?php
$path_to_root = "../../..";
$page_security = 'SA_HRM_EMPLOYEE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Employee.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/EmployeeRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/PositionRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/GradeRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/LookupRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Exception/EmployeeNotFoundException.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Exception/ValidationException.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/EmployeeService.php");

use ksfraser\FrontAccounting\HRM\Service\EmployeeService;

$service = new EmployeeService();

if (isset($_POST['save_employee'])) {
    $service->hire($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=employees');
    exit;
}

if (isset($_POST['update_employee'])) {
    $service->updateEmployee((int)$_POST['employment_id'], $_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=employees');
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_row = $service->getById((int)$_GET['edit']);
    $show_form = true;
}

$dropdowns = $service->getFormDropdowns();

// Department DDL via hook — HRM owns this UI component
$deptData = ['active_only' => true, 'blank_label' => _("-- None --")];
$departmentOptions = hook_invoke('ksf_FA_HRM', 'getDepartmentDDL', $deptData);

$employees = $service->listAll();
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Employees"); ?></h5>
        <a href="?view=employees&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Employee"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=employees">
                <?php if ($edit_mode && $edit_row): ?>
                    <input type="hidden" name="update_employee" value="1">
                    <input type="hidden" name="employment_id" value="<?php echo (int)$edit_row['employment_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="save_employee" value="1">
                <?php endif; ?>

                <h6 class="mb-3"><?php echo $edit_mode ? _("Edit Employee") : _("New Employee"); ?></h6>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="employee_code"><?php echo _("Employee Code"); ?></label>
                            <input type="text" class="form-control" id="employee_code" name="employee_code" required maxlength="20"
                                value="<?php echo htmlspecialchars($edit_row['employee_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="person_id"><?php echo _("Contact (CRM Person)"); ?></label>
                            <select class="form-control" id="person_id" name="person_id" required>
                                <option value=""><?php echo _("-- Select Contact --"); ?></option>
                            <?php foreach ($dropdowns['persons'] as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>"
                                    <?php echo (isset($edit_row['person_id']) && $edit_row['person_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="department_id"><?php echo _("Department"); ?></label>
                            <select class="form-control" id="department_id" name="department_id">
                            <?php
                            $selected_dept_id = $edit_row['department_id'] ?? 0;
                            foreach ($departmentOptions as $optHtml) {
                                if ($selected_dept_id > 0) {
                                    $optHtml = str_replace(
                                        'value="' . (int)$selected_dept_id . '"',
                                        'value="' . (int)$selected_dept_id . '" selected',
                                        $optHtml
                                    );
                                }
                                echo $optHtml;
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="position_id"><?php echo _("Position"); ?></label>
                            <select class="form-control" id="position_id" name="position_id">
                                <option value=""><?php echo _("-- None --"); ?></option>
                            <?php foreach ($dropdowns['positions'] as $p): ?>
                                <option value="<?php echo (int)$p->getPositionId(); ?>"
                                    <?php echo (isset($edit_row['position_id']) && $edit_row['position_id'] == $p->getPositionId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p->getPositionCode()); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="grade_id"><?php echo _("Grade"); ?></label>
                            <select class="form-control" id="grade_id" name="grade_id">
                                <option value=""><?php echo _("-- None --"); ?></option>
                            <?php foreach ($dropdowns['grades'] as $g): ?>
                                <option value="<?php echo (int)$g->getGradeId(); ?>"
                                    <?php echo (isset($edit_row['grade_id']) && $edit_row['grade_id'] == $g->getGradeId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g->getGradeName()); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="reports_to_person_id"><?php echo _("Reports To"); ?></label>
                            <select class="form-control" id="reports_to_person_id" name="reports_to_person_id">
                                <option value=""><?php echo _("-- None --"); ?></option>
                            <?php foreach ($dropdowns['employees'] as $e): ?>
                                <option value="<?php echo (int)$e->getPersonId(); ?>"
                                    <?php echo (isset($edit_row['reports_to_person_id']) && $edit_row['reports_to_person_id'] == $e->getPersonId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e->getEmployeeCode() . ' - ' . ($e->toArray()['person_name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="hire_date"><?php echo _("Hire Date"); ?></label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date"
                                value="<?php echo htmlspecialchars($edit_row['hire_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="probation_end_date"><?php echo _("Probation End Date"); ?></label>
                            <input type="date" class="form-control" id="probation_end_date" name="probation_end_date"
                                value="<?php echo htmlspecialchars($edit_row['probation_end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="is_active"><?php echo _("Status"); ?></label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                    <?php echo (!$edit_mode || ($edit_row['is_active'] ?? 1)) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active"><?php echo _("Active Employee"); ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=employees" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th><?php echo _("Department"); ?></th>
                    <th><?php echo _("Position"); ?></th>
                    <th><?php echo _("Hire Date"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($employees)): ?>
                <?php foreach ($employees as $row): ?>
                    <tr>
                        <td><?php echo html_entity_decode($row->getEmployeeCode() ?? ''); ?></td>
                        <td><?php echo html_entity_decode($row->toArray()['person_name'] ?? ''); ?></td>
                        <td><?php echo html_entity_decode($row->toArray()['department_name'] ?? ''); ?></td>
                        <td><?php echo html_entity_decode($row->toArray()['position_code'] ?? ''); ?></td>
                        <td><?php echo $row->getHireDate() ?? ''; ?></td>
                        <td class="text-center">
                            <?php if ($row->isActive()): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a href="?view=employees&edit=<?php echo (int)$row->getEmploymentId(); ?>" class="btn btn-outline-secondary btn-sm"><?php echo _("Edit"); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted"><?php echo _("No employees found."); ?></td></tr>
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
