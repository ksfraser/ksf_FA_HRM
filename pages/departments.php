<?php
$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

// Handle department insert
if (isset($_POST['save_dept'])) {
    insert_department($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments');
    exit;
}

// Handle team insert
if (isset($_POST['save_team'])) {
    insert_team($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

// Handle team update
if (isset($_POST['update_team'])) {
    update_team($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

// Handle role insert (clone from dictionary)
if (isset($_POST['save_role'])) {
    insert_role($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

$selected_dept = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
$show_add_form = isset($_GET['add']);
$show_team_form = isset($_GET['add_team']);
$show_role_form = isset($_GET['add_role']);

$dept_list = db_query("SELECT * FROM " . TB_PREF . "fa_departments ORDER BY department_code");
$role_dict = get_role_dictionary();

$teams = null;
$roles = null;
$positions = null;
if ($selected_dept > 0) {
    $teams = get_teams_for_department($selected_dept);
    $roles = get_roles_for_department($selected_dept);
    $positions = get_positions_for_department($selected_dept);
}

function render_team_tree($teams, $parentId, $deptId, $depth = 0)
{
    if (!$teams) return;
    $has_children = false;

    db_data_seek($teams);
    while ($row = db_fetch_assoc($teams)) {
        if ((int)$row['parent_team_id'] !== (int)$parentId) continue;
        $has_children = true;

        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        $badge = $row['is_active']
            ? '<span class="badge badge-success">' . _("Active") . '</span>'
            : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';

        echo '<tr>';
        echo '<td>' . $indent . ($depth > 0 ? '<i class="fa fa-level-down-alt"></i> ' : '<i class="fa fa-users"></i> ')
             . '<strong>' . html_entity_decode($row['team_code']) . '</strong> - '
             . html_entity_decode($row['team_name']) . '</td>';
        echo '<td>' . html_entity_decode($row['description'] ?? '') . '</td>';
        echo '<td class="text-center">' . $badge . '</td>';
        echo '<td class="text-right"><a href="?view=departments&dept=' . $deptId . '&edit_team=' . (int)$row['team_id'] . '" class="btn btn-outline-secondary btn-sm">' . _("Edit") . '</a></td>';
        echo '</tr>';

        render_team_tree($teams, $row['team_id'], $deptId, $depth + 1);
    }

    if (!$has_children && $parentId === null) {
        // Top-level check done
    }
}
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Departments"); ?></h5>
        <a href="?view=departments&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Department"); ?>
        </a>
    </div>
    <div class="card-body">
        <?php if ($show_add_form): ?>
        <div id="add-form" class="mb-4">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=departments">
                <input type="hidden" name="save_dept" value="1">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="department_code"><?php echo _("Code"); ?></label>
                            <input type="text" class="form-control" id="department_code" name="department_code" required maxlength="20">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="department_name"><?php echo _("Name"); ?></label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required maxlength="100">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="description"><?php echo _("Description"); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="1"></textarea>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active"><?php echo _("Active"); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=departments" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>
        <?php endif; ?>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th><?php echo _("Description"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($dept_list && db_num_rows($dept_list)) {
                while ($row = db_fetch_assoc($dept_list)) {
                    $badge = $row['is_active']
                        ? '<span class="badge badge-success">' . _("Active") . '</span>'
                        : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                    $active = ($selected_dept == $row['department_id']) ? 'table-active' : '';
                    echo '<tr class="' . $active . '">';
                    echo '<td><strong>' . html_entity_decode($row['department_code']) . '</strong></td>';
                    echo '<td>' . html_entity_decode($row['department_name']) . '</td>';
                    echo '<td>' . html_entity_decode($row['description']) . '</td>';
                    echo '<td class="text-center">' . $badge . '</td>';
                    echo '<td class="text-right"><a href="?view=departments&dept=' . (int)$row['department_id'] . '" class="btn btn-outline-primary btn-sm">' . _("Teams & Roles") . '</a></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5" class="text-center text-muted">' . _("No departments found.") . '</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selected_dept > 0): ?>
<?php
$dept = db_fetch_assoc(db_query("SELECT * FROM " . TB_PREF . "fa_departments WHERE department_id = " . $selected_dept));
?>

<div class="row">
    <!-- Teams Panel -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><?php echo _("Teams") . ' — ' . html_entity_decode($dept['department_name']); ?></h6>
                <a href="?view=departments&dept=<?php echo $selected_dept; ?>&add_team=1" class="btn btn-primary btn-sm">
                    <?php echo _("Add Team"); ?>
                </a>
            </div>
            <div class="card-body">
                <?php if ($show_team_form): ?>
                <div class="mb-3 p-3 bg-light rounded">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=departments&dept=<?php echo $selected_dept; ?>">
                        <input type="hidden" name="save_team" value="1">
                        <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?php echo _("Code"); ?></label>
                                    <input type="text" class="form-control" name="team_code" required maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo _("Name"); ?></label>
                                    <input type="text" class="form-control" name="team_name" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?php echo _("Parent Team"); ?></label>
                                    <select class="form-control" name="parent_team_id">
                                        <option value="0"><?php echo _("-- None (Top Level) --"); ?></option>
                                        <?php
                                        if ($teams) {
                                            db_data_seek($teams);
                                            while ($t = db_fetch_assoc($teams)) {
                                                echo '<option value="' . (int)$t['team_id'] . '">' . htmlspecialchars($t['team_code'] . ' - ' . $t['team_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" name="is_active" value="1" checked>
                                        <label class="form-check-label"><?php echo _("Active"); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo _("Description"); ?></label>
                            <input type="text" class="form-control" name="description">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                        <a href="?view=departments&dept=<?php echo $selected_dept; ?>" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
                    </form>
                </div>
                <?php endif; ?>

                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo _("Team"); ?></th>
                            <th><?php echo _("Description"); ?></th>
                            <th class="text-center"><?php echo _("Status"); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($teams && db_num_rows($teams)) {
                        render_team_tree($teams, null, $selected_dept);
                    } else {
                        echo '<tr><td colspan="4" class="text-muted text-center">' . _("No teams. Add one above.") . '</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Roles Panel -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><?php echo _("Roles") . ' — ' . html_entity_decode($dept['department_name']); ?></h6>
                <a href="?view=departments&dept=<?php echo $selected_dept; ?>&add_role=1" class="btn btn-primary btn-sm">
                    <?php echo _("Clone Role"); ?>
                </a>
            </div>
            <div class="card-body">
                <?php if ($show_role_form): ?>
                <div class="mb-3 p-3 bg-light rounded">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=departments&dept=<?php echo $selected_dept; ?>">
                        <input type="hidden" name="save_role" value="1">
                        <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo _("Clone From (Dictionary)"); ?></label>
                                    <select class="form-control" name="role_dict_id" id="role_dict_select">
                                        <option value="0"><?php echo _("-- Custom (no source) --"); ?></option>
                                        <?php
                                        if ($role_dict) {
                                            while ($rd = db_fetch_assoc($role_dict)) {
                                                echo '<option value="' . (int)$rd['role_dict_id'] . '">' . htmlspecialchars($rd['role_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo _("Role Name"); ?></label>
                                    <input type="text" class="form-control" name="role_name" id="role_name_input" required maxlength="100">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo _("Description"); ?></label>
                            <input type="text" class="form-control" name="description">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                        <a href="?view=departments&dept=<?php echo $selected_dept; ?>" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
                    </form>
                </div>
                <?php endif; ?>

                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo _("Role Name"); ?></th>
                            <th><?php echo _("Source"); ?></th>
                            <th><?php echo _("Description"); ?></th>
                            <th class="text-center"><?php echo _("Status"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($roles && db_num_rows($roles)) {
                        while ($r = db_fetch_assoc($roles)) {
                            $badge = $r['is_active']
                                ? '<span class="badge badge-success">' . _("Active") . '</span>'
                                : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                            $source = $r['role_dict_id'] ? html_entity_decode($r['dict_name'] ?? '—') : '<em>' . _("Custom") . '</em>';
                            echo '<tr>';
                            echo '<td><strong>' . html_entity_decode($r['role_name']) . '</strong></td>';
                            echo '<td>' . $source . '</td>';
                            echo '<td>' . html_entity_decode($r['description'] ?? '') . '</td>';
                            echo '<td class="text-center">' . $badge . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-muted text-center">' . _("No roles. Clone one from dictionary above.") . '</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($positions && db_num_rows($positions)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><?php echo _("Positions") . ' — ' . html_entity_decode($dept['department_name']); ?></h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th><?php echo _("Code"); ?></th>
                            <th><?php echo _("Team"); ?></th>
                            <th><?php echo _("Role"); ?></th>
                            <th class="text-center"><?php echo _("Status"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    db_data_seek($positions);
                    while ($p = db_fetch_assoc($positions)) {
                        $badge = $p['is_active']
                            ? '<span class="badge badge-success">' . _("Active") . '</span>'
                            : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                        echo '<tr>';
                        echo '<td><strong>' . html_entity_decode($p['position_code']) . '</strong></td>';
                        echo '<td>' . html_entity_decode($p['team_code'] ?? '—') . ' - ' . html_entity_decode($p['team_name'] ?? '') . '</td>';
                        echo '<td>' . html_entity_decode($p['role_name']) . '</td>';
                        echo '<td class="text-center">' . $badge . '</td>';
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('toggle-add').addEventListener('click', function(e) {
    e.preventDefault();
    var form = document.getElementById('add-form');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    } else {
        window.location.href = '?view=departments&add=1';
    }
});

var dictSelect = document.getElementById('role_dict_select');
var nameInput = document.getElementById('role_name_input');
if (dictSelect && nameInput) {
    dictSelect.addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        if (this.value > 0) {
            nameInput.value = selected.text;
        }
    });
}
</script>
