<?php
$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

if (isset($_POST['save_employee'])) {
    insert_employee($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=employees');
    exit;
}

if (isset($_POST['update_employee'])) {
    update_employee($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=employees');
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_id = (int)$_GET['edit'];
    $edit_row = get_employee($edit_id);
    $show_form = true;
}

$departments = get_departments();
$positions = get_positions_list();
$grades = get_grades_list();
$employees = get_employees();
$employment_statuses = get_employment_statuses();
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
                            <?php
                            $persons_sql = "SELECT id, name FROM " . TB_PREF . "crm_persons ORDER BY name";
                            $persons = db_query($persons_sql);
                            if ($persons) {
                                while ($p = db_fetch_assoc($persons)) {
                                    $sel = (isset($edit_row['person_id']) && $edit_row['person_id'] == $p['id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$p['id'] . '" ' . $sel . '>' . htmlspecialchars($p['name']) . '</option>';
                                }
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="department_id"><?php echo _("Department"); ?></label>
                            <select class="form-control" id="department_id" name="department_id">
                                <option value=""><?php echo _("-- None --"); ?></option>
                            <?php
                            if ($departments) {
                                while ($d = db_fetch_assoc($departments)) {
                                    $sel = (isset($edit_row['department_id']) && $edit_row['department_id'] == $d['department_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$d['department_id'] . '" ' . $sel . '>' . htmlspecialchars($d['department_name']) . '</option>';
                                }
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
                            <?php
                            if ($positions) {
                                while ($p = db_fetch_assoc($positions)) {
                                    $sel = (isset($edit_row['position_id']) && $edit_row['position_id'] == $p['position_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$p['position_id'] . '" ' . $sel . '>' . htmlspecialchars($p['position_name']) . '</option>';
                                }
                            }
                            ?>
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
                            <?php
                            if ($grades) {
                                while ($g = db_fetch_assoc($grades)) {
                                    $sel = (isset($edit_row['grade_id']) && $edit_row['grade_id'] == $g['grade_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$g['grade_id'] . '" ' . $sel . '>' . htmlspecialchars($g['grade_name']) . '</option>';
                                }
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="reports_to_person_id"><?php echo _("Reports To"); ?></label>
                            <select class="form-control" id="reports_to_person_id" name="reports_to_person_id">
                                <option value=""><?php echo _("-- None --"); ?></option>
                            <?php
                            if ($employees) {
                                while ($e = db_fetch_assoc($employees)) {
                                    $sel = (isset($edit_row['reports_to_person_id']) && $edit_row['reports_to_person_id'] == $e['person_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$e['person_id'] . '" ' . $sel . '>' . htmlspecialchars($e['employee_code'] . ' - ' . ($e['person_name'] ?? '')) . '</option>';
                                }
                            }
                            ?>
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
            <?php
            if ($employees && db_num_rows($employees)) {
                while ($row = db_fetch_assoc($employees)) {
                    $badge = $row['is_active']
                        ? '<span class="badge badge-success">' . _("Active") . '</span>'
                        : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                    echo '<tr>';
                    echo '<td>' . html_entity_decode($row['employee_code'] ?? '') . '</td>';
                    echo '<td>' . html_entity_decode($row['person_name'] ?? '') . '</td>';
                    echo '<td>' . html_entity_decode($row['department_name'] ?? '') . '</td>';
                    echo '<td>' . html_entity_decode($row['position_name'] ?? '') . '</td>';
                    echo '<td>' . ($row['hire_date'] ?? '') . '</td>';
                    echo '<td class="text-center">' . $badge . '</td>';
                    echo '<td class="text-right"><a href="?view=employees&edit=' . (int)$row['employment_id'] . '" class="btn btn-outline-secondary btn-sm">' . _("Edit") . '</a></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7" class="text-center text-muted">' . _("No employees found.") . '</td></tr>';
            }
            ?>
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
