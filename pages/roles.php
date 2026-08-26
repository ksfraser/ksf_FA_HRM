<?php
$path_to_root = "../../..";
$page_security = 'SA_ksf_FA_HRMMANAGE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Role.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/RoleDictionary.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Department.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/RoleRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/DepartmentRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/RoleService.php");

use ksfraser\FrontAccounting\HRM\Service\RoleService;

$service = new RoleService();

if (isset($_POST['save_role'])) {
    $service->create($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=roles');
    exit;
}

if (isset($_POST['update_role'])) {
    $service->update((int)$_POST['role_id'], $_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=roles');
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
$departments = $dropdowns['departments'];
$dictionary = $dropdowns['dictionary'];

$selected_dept = 0;
if ($show_form) {
    $selected_dept = $edit_mode ? ($edit_row['department_id'] ?? 0) : (int)($_POST['department_id'] ?? 0);
}

$roles = $service->listAll();
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Roles"); ?></h5>
        <a href="?view=roles&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Role"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=roles">
                <?php if ($edit_mode && $edit_row): ?>
                    <input type="hidden" name="update_role" value="1">
                    <input type="hidden" name="role_id" value="<?php echo (int)$edit_row['role_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="save_role" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Department"); ?></label>
                            <select class="form-control" name="department_id" required>
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
                            <label><?php echo _("Role Type (from Dictionary)"); ?></label>
                            <select class="form-control" name="role_dict_id" id="dict_select">
                                <option value="0"><?php echo _("-- Custom Role --"); ?></option>
                            <?php foreach ($dictionary as $dict): ?>
                                <option value="<?php echo (int)$dict->getRoleDictId(); ?>"
                                    <?php echo ($edit_mode && ($edit_row['role_dict_id'] ?? 0) == $dict->getRoleDictId()) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dict->getRoleName()); ?>
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><?php echo _("Role Name"); ?></label>
                            <input type="text" class="form-control" name="role_name" id="role_name" required maxlength="100"
                                value="<?php echo htmlspecialchars($edit_row['role_name'] ?? ''); ?>">
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
                <a href="?view=roles" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Role Name"); ?></th>
                    <th><?php echo _("Department"); ?></th>
                    <th><?php echo _("Dictionary Type"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($roles)): ?>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?php echo html_entity_decode($role->getRoleName()); ?></strong></td>
                        <td><?php echo html_entity_decode($role->department_code ?? ''); ?></td>
                        <td><?php echo html_entity_decode($role->dict_name ?? '-'); ?></td>
                        <td class="text-center">
                            <?php if ($role->isActive()): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a href="?view=roles&edit=<?php echo (int)$role->getRoleId(); ?>" class="btn btn-outline-secondary btn-sm"><?php echo _("Edit"); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted"><?php echo _("No roles found."); ?></td></tr>
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
        window.location.href = '?view=roles&add=1';
    }
});

var dictSelect = document.getElementById('dict_select');
var roleNameInput = document.getElementById('role_name');
if (dictSelect && roleNameInput) {
    dictSelect.addEventListener('change', function() {
        if (this.value > 0 && !roleNameInput.value) {
            var selected = this.options[this.selectedIndex];
            if (selected.text) {
                roleNameInput.value = selected.text;
            }
        }
    });
}
</script>
