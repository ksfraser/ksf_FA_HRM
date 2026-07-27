<?php
$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

if (isset($_POST['save_position'])) {
    insert_position($_POST);
    display_notification(_("Position added successfully"));
    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?view=positions">';
    exit;
}

if (isset($_POST['update_position'])) {
    update_position($_POST);
    display_notification(_("Position updated successfully"));
    echo '<meta http-equiv="refresh" content="0;url=' . $_SERVER['PHP_SELF'] . '?view=positions">';
    exit;
}

$show_form = isset($_GET['add']);
$edit_mode = isset($_GET['edit']);
$edit_row = null;
if ($edit_mode) {
    $edit_id = (int)$_GET['edit'];
    $edit_row = get_position($edit_id);
    $show_form = true;
}
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
                            <label for="position_code"><?php echo _("Code"); ?></label>
                            <input type="text" class="form-control" id="position_code" name="position_code" required maxlength="20"
                                value="<?php echo htmlspecialchars($edit_row['position_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="position_name"><?php echo _("Name"); ?></label>
                            <input type="text" class="form-control" id="position_name" name="position_name" required maxlength="100"
                                value="<?php echo htmlspecialchars($edit_row['position_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="description"><?php echo _("Description"); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="1"><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
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
                <button type="submit" class="btn btn-success btn-sm"><?php echo _("Save"); ?></button>
                <a href="?view=positions" class="btn btn-secondary btn-sm ml-1"><?php echo _("Cancel"); ?></a>
            </form>
            <hr>
        </div>

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th><?php echo _("Description"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                    <th class="text-right"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $result = db_query(
                "SELECT position_id, position_code, position_name, description, is_active
                FROM " . TB_PREF . "fa_positions
                ORDER BY position_name",
                _("Could not query positions")
            );

            if (db_num_rows($result) == 0) {
                echo '<tr><td colspan="5" class="text-center text-muted">' . _("No positions found.") . '</td></tr>';
            } else {
                while ($row = db_fetch_assoc($result)) {
                    $badge = $row['is_active']
                        ? '<span class="badge badge-success">' . _("Active") . '</span>'
                        : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                    echo '<tr>';
                    echo '<td>' . html_entity_decode($row['position_code']) . '</td>';
                    echo '<td>' . html_entity_decode($row['position_name']) . '</td>';
                    echo '<td>' . html_entity_decode($row['description']) . '</td>';
                    echo '<td class="text-center">' . $badge . '</td>';
                    echo '<td class="text-right"><a href="?view=positions&edit=' . (int)$row['position_id'] . '" class="btn btn-outline-secondary btn-sm">' . _("Edit") . '</a></td>';
                    echo '</tr>';
                }
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
