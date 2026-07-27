<?php
$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

if (isset($_POST['save_position'])) {
    insert_position($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=positions');
    exit;
}

if (isset($_POST['update_position'])) {
    update_position($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=positions');
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_row = get_position((int)$_GET['edit']);
    $show_form = true;
}

$departments = get_departments();

// Pre-fetch teams/roles for selected department (for Add form)
$selected_dept = 0;
if ($show_form) {
    $selected_dept = $edit_mode ? ($edit_row['department_id'] ?? 0) : (int)($_POST['department_id'] ?? 0);
}
$teams_for_dept = $selected_dept > 0 ? get_teams_for_department($selected_dept) : null;
$roles_for_dept = $selected_dept > 0 ? get_roles_for_department($selected_dept) : null;

$positions = get_positions_list();
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Positions"); ?></h5>
        <a href="?view=positions&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Position"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=positions">
                <?php if ($edit_mode && $edit_row): ?>
                    <input type="hidden" name="update_position" value="1">
                    <input type="hidden" name="position_id" value="<?php echo (int)$edit_row['position_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="save_position" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Department"); ?></label>
                            <select class="form-control" name="department_id" id="dept_select" required>
                                <option value=""><?php echo _("-- Select Department --"); ?></option>
                            <?php
                            if ($departments) {
                                while ($d = db_fetch_assoc($departments)) {
                                    $sel = ($selected_dept == $d['department_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$d['department_id'] . '" ' . $sel . '>'
                                         . htmlspecialchars($d['department_code'] . ' - ' . $d['department_name'])
                                         . '</option>';
                                }
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Team"); ?></label>
                            <select class="form-control" name="team_id" id="team_select">
                                <option value="0"><?php echo _("-- No Team (General) --"); ?></option>
                            <?php
                            if ($teams_for_dept) {
                                while ($t = db_fetch_assoc($teams_for_dept)) {
                                    $sel = ($edit_mode && $edit_row['team_id'] == $t['team_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$t['team_id'] . '" ' . $sel . '>'
                                         . htmlspecialchars($t['team_code'] . ' - ' . $t['team_name'])
                                         . '</option>';
                                }
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Role"); ?></label>
                            <select class="form-control" name="role_id" id="role_select" required>
                                <option value=""><?php echo _("-- Select Role --"); ?></option>
                            <?php
                            if ($roles_for_dept) {
                                while ($r = db_fetch_assoc($roles_for_dept)) {
                                    $sel = ($edit_mode && $edit_row['role_id'] == $r['role_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$r['role_id'] . '" ' . $sel . '>'
                                         . htmlspecialchars($r['role_name'])
                                         . '</option>';
                                }
                            }
                            ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1"
                                    <?php echo (!$edit_mode || ($edit_row['is_active'] ?? 1)) ? 'checked' : ''; ?>>
                                <label class="form-check-label"><?php echo _("Active"); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo _("Description"); ?></label>
                            <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php if ($edit_mode && $edit_row): ?>
                        <div class="form-group">
                            <label><?php echo _("Position Code"); ?></label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_row['position_code']); ?>" readonly disabled>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label><?php echo _("Position Code"); ?></label>
                            <input type="text" class="form-control" value="<?php echo _("Auto-generated"); ?>" readonly disabled>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=positions" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Department"); ?></th>
                    <th><?php echo _("Team"); ?></th>
                    <th><?php echo _("Role"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($positions && db_num_rows($positions)) {
                while ($row = db_fetch_assoc($positions)) {
                    $badge = $row['is_active']
                        ? '<span class="badge badge-success">' . _("Active") . '</span>'
                        : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                    echo '<tr>';
                    echo '<td><strong>' . html_entity_decode($row['position_code']) . '</strong></td>';
                    echo '<td>' . html_entity_decode($row['department_code'] ?? '') . '</td>';
                    echo '<td>' . html_entity_decode($row['team_code'] ?? '—') . '</td>';
                    echo '<td>' . html_entity_decode($row['role_name'] ?? '') . '</td>';
                    echo '<td class="text-center">' . $badge . '</td>';
                    echo '<td class="text-right"><a href="?view=positions&edit=' . (int)$row['position_id'] . '" class="btn btn-outline-secondary btn-sm">' . _("Edit") . '</a></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6" class="text-center text-muted">' . _("No positions found.") . '</td></tr>';
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
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    } else {
        window.location.href = '?view=positions&add=1';
    }
});

var deptSelect = document.getElementById('dept_select');
if (deptSelect) {
    deptSelect.addEventListener('change', function() {
        var deptId = this.value;
        if (deptId > 0) {
            window.location.href = '?view=positions&add=1&dept=' + deptId;
        }
    });
}
</script>
