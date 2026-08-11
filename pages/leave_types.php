<?php
$path_to_root = "../../..";
$page_security = 'SA_HRM_LEAVE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/LookupRepository.php");

use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

$lookupRepo = new LookupRepository();

if (isset($_POST['save_leave_type'])) {
    $lookupRepo->saveLeaveType($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=leave_types');
    exit;
}

$show_form = isset($_GET['add']);

$leave_types = $lookupRepo->getLeaveTypes();
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Leave Types"); ?></h5>
        <a href="?view=leave_types&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Leave Type"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?view=leave_types">
                <input type="hidden" name="save_leave_type" value="1">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="type_code"><?php echo _("Code"); ?></label>
                            <input type="text" class="form-control" id="type_code" name="type_code" required maxlength="20">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="type_name"><?php echo _("Name"); ?></label>
                            <input type="text" class="form-control" id="type_name" name="type_name" required maxlength="100">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="default_days"><?php echo _("Default Days"); ?></label>
                            <input type="number" class="form-control" id="default_days" name="default_days" step="0.5" min="0" value="0">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="is_paid" name="is_paid" value="1" checked>
                                <label class="form-check-label" for="is_paid"><?php echo _("Paid"); ?></label>
                            </div>
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
                <a href="?view=leave_types" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th class="text-right"><?php echo _("Default Days"); ?></th>
                    <th class="text-center"><?php echo _("Paid"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($leave_types)): ?>
                <tr><td colspan="5" class="text-center text-muted"><?php echo _("No leave types found."); ?></td></tr>
            <?php else: ?>
                <?php foreach ($leave_types as $lt): ?>
                    <tr>
                        <td><?php echo html_entity_decode($lt['type_code']); ?></td>
                        <td><?php echo html_entity_decode($lt['type_name']); ?></td>
                        <td class="text-right"><?php echo number_format($lt['default_days'], 1); ?></td>
                        <td class="text-center"><?php echo $lt['is_paid'] ? _("Yes") : _("No"); ?></td>
                        <td class="text-center">
                            <?php if ($lt['is_active']): ?>
                                <span class="badge badge-success"><?php echo _("Active"); ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
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
