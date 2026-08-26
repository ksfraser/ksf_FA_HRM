<?php
$path_to_root = "../../..";
$page_security = 'SA_ksf_FA_HRMMANAGE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Team.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Department.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/TeamRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/DepartmentRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/TeamService.php");

use ksfraser\FrontAccounting\HRM\Service\TeamService;

$service = new TeamService();

if (isset($_POST['save_team'])) {
    $service->create($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=teams');
    exit;
}

if (isset($_POST['update_team'])) {
    $service->update((int)$_POST['team_id'], $_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=teams');
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_row = $service->getById((int)$_GET['edit']);
    $show_form = true;
}

$departments = $service->getFormDropdowns()['departments'];

$selected_dept = 0;
if ($show_form) {
    $selected_dept = $edit_mode ? ($edit_row['department_id'] ?? 0) : (int)($_POST['department_id'] ?? 0);
}
$parent_teams = $selected_dept > 0 ? $service->getParentTeams($selected_dept) : [];

$teams = $service->listAll();
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Teams"); ?></h5>
        <a href="?view=teams&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Team"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=teams">
                <?php if ($edit_mode && $edit_row): ?>
                    <input type="hidden" name="update_team" value="1">
                    <input type="hidden" name="team_id" value="<?php echo (int)$edit_row['team_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="save_team" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Department"); ?></label>
                            <select class="form-control" name="department_id" id="dept_select" required>
                                <option value=""><?php echo _("-- Select Department --"); ?></option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d->getDepartmentId(); ?>"
                                    <?php echo ($selected_dept == $d->getDepartmentId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d->getDepartmentCode() . ' - ' . $d->getDepartmentName()); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Parent Team"); ?></label>
                            <select class="form-control" name="parent_team_id">
                                <option value="0"><?php echo _("-- None (Top Level) --"); ?></option>
                            <?php foreach ($parent_teams as $t): ?>
                                <option value="<?php echo (int)$t->getTeamId(); ?>"
                                    <?php echo ($edit_mode && ($edit_row['parent_team_id'] ?? 0) == $t->getTeamId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t->getTeamCode() . ' - ' . $t->getTeamName()); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo _("Team Code"); ?></label>
                            <input type="text" class="form-control" name="team_code" required maxlength="20"
                                value="<?php echo htmlspecialchars($edit_row['team_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo _("Team Name"); ?></label>
                            <input type="text" class="form-control" name="team_name" required maxlength="100"
                                value="<?php echo htmlspecialchars($edit_row['team_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-8">
                        <div class="form-group">
                            <label><?php echo _("Description"); ?></label>
                            <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=teams" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th><?php echo _("Department"); ?></th>
                    <th><?php echo _("Parent Team"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($teams)): ?>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><strong><?php echo html_entity_decode($team->getTeamCode()); ?></strong></td>
                        <td><?php echo html_entity_decode($team->getTeamName()); ?></td>
                        <td><?php echo html_entity_decode($team->department_code ?? ''); ?></td>
                        <td><?php echo html_entity_decode($team->parent_team_name ?? '-'); ?></td>
                        <td class="text-center">
                            <?php if ($team->isActive()): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a href="?view=teams&edit=<?php echo (int)$team->getTeamId(); ?>" class="btn btn-outline-secondary btn-sm"><?php echo _("Edit"); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted"><?php echo _("No teams found."); ?></td></tr>
            <?php endif; ?>
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
        window.location.href = '?view=teams&add=1';
    }
});

var deptSelect = document.getElementById('dept_select');
if (deptSelect) {
    deptSelect.addEventListener('change', function() {
        var deptId = this.value;
        if (deptId > 0) {
            window.location.href = '?view=teams&add=1&dept=' + deptId;
        }
    });
}
</script>
