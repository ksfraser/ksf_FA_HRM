<?php
$path_to_root = "../../..";
$page_security = 'SA_HRM_DEPARTMENT';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Department.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Team.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Role.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/RoleDictionary.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Position.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/DepartmentRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/TeamRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/RoleRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/PositionRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/OrgHierarchyService.php");

use ksfraser\FrontAccounting\HRM\Service\OrgHierarchyService;

$service = new OrgHierarchyService();

if (isset($_POST['save_dept'])) {
    $service->saveDepartment($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments');
    exit;
}

if (isset($_POST['save_team'])) {
    $service->saveTeam($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

if (isset($_POST['update_team'])) {
    $service->updateTeam((int)$_POST['team_id'], $_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

if (isset($_POST['save_role'])) {
    $service->saveRole($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=departments&dept=' . (int)$_POST['department_id']);
    exit;
}

$selected_dept = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
$show_add_form = isset($_GET['add']);
$show_team_form = isset($_GET['add_team']);
$show_role_form = isset($_GET['add_role']);

$dept_list = $service->listDepartments();
$role_dict = $service->getRoleDictionary();

$teams = null;
$roles = null;
$positions = null;
if ($selected_dept > 0) {
    $teams = $service->getTeamsForDepartment($selected_dept);
    $roles = $service->getRolesForDepartment($selected_dept);
    $positions = $service->getPositionsForDepartment($selected_dept);
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
            <?php if (!empty($dept_list)): ?>
                <?php foreach ($dept_list as $row): ?>
                    <tr class="<?php echo ($selected_dept == $row->getDepartmentId()) ? 'table-active' : ''; ?>">
                        <td><strong><?php echo html_entity_decode($row->getDepartmentCode() ?? ''); ?></strong></td>
                        <td><?php echo html_entity_decode($row->getDepartmentName()); ?></td>
                        <td><?php echo html_entity_decode($row->getDescription() ?? ''); ?></td>
                        <td class="text-center">
                            <?php if ($row->isActive()): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a href="?view=departments&dept=<?php echo (int)$row->getDepartmentId(); ?>" class="btn btn-outline-primary btn-sm"><?php echo _("Teams & Roles"); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted"><?php echo _("No departments found."); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selected_dept > 0):
$dept = $service->getDepartment($selected_dept);
?>

<div class="row">
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
                                        <?php foreach ($teams as $t): ?>
                                            <option value="<?php echo (int)$t->getTeamId(); ?>">
                                                <?php echo htmlspecialchars($t->getTeamCode() . ' - ' . $t->getTeamName()); ?>
                                            </option>
                                        <?php endforeach; ?>
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
                    <thead><tr>
                        <th><?php echo _("Team"); ?></th>
                        <th><?php echo _("Description"); ?></th>
                        <th class="text-center"><?php echo _("Status"); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if (!empty($teams)): ?>
                        <?php foreach ($teams as $t): ?>
                            <tr>
                                <td><strong><?php echo html_entity_decode($t->getTeamCode()); ?></strong> - <?php echo html_entity_decode($t->getTeamName()); ?></td>
                                <td><?php echo html_entity_decode($t->getDescription() ?? ''); ?></td>
                                <td class="text-center">
                                    <?php if ($t->isActive()): ?>
                                        <span class="badge badge-success"><?php echo _("Active"); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-muted text-center"><?php echo _("No teams. Add one above."); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
                                        <?php foreach ($role_dict as $rd): ?>
                                            <option value="<?php echo (int)$rd->getRoleDictId(); ?>">
                                                <?php echo htmlspecialchars($rd->getRoleName()); ?>
                                            </option>
                                        <?php endforeach; ?>
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
                    <thead><tr>
                        <th><?php echo _("Role Name"); ?></th>
                        <th><?php echo _("Source"); ?></th>
                        <th><?php echo _("Description"); ?></th>
                        <th class="text-center"><?php echo _("Status"); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $r): ?>
                            <tr>
                                <td><strong><?php echo html_entity_decode($r->getRoleName()); ?></strong></td>
                                <td><?php echo $r->getRoleDictId() ? html_entity_decode($r->toArray()['dict_name'] ?? '—') : '<em>' . _("Custom") . '</em>'; ?></td>
                                <td><?php echo html_entity_decode($r->getDescription() ?? ''); ?></td>
                                <td class="text-center">
                                    <?php if ($r->isActive()): ?>
                                        <span class="badge badge-success"><?php echo _("Active"); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-muted text-center"><?php echo _("No roles. Clone one from dictionary above."); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($positions)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><?php echo _("Positions") . ' — ' . html_entity_decode($dept['department_name']); ?></h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr>
                        <th><?php echo _("Code"); ?></th>
                        <th><?php echo _("Team"); ?></th>
                        <th><?php echo _("Role"); ?></th>
                        <th class="text-center"><?php echo _("Status"); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($positions as $p): ?>
                        <tr>
                            <td><strong><?php echo html_entity_decode($p->getPositionCode()); ?></strong></td>
                            <td><?php echo html_entity_decode($p->toArray()['team_code'] ?? '—'); ?></td>
                            <td><?php echo html_entity_decode($p->toArray()['role_name'] ?? ''); ?></td>
                            <td class="text-center">
                                <?php if ($p->isActive()): ?>
                                    <span class="badge badge-success"><?php echo _("Active"); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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
        if (this.value > 0) nameInput.value = selected.text;
    });
}
</script>
