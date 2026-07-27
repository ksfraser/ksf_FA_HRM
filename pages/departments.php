<?php
$path_to_root = "../../";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

if (isset($_POST['save_dept'])) {
    insert_department($_POST);
    $url = $_SERVER['PHP_SELF'] . '?view=departments';
    header('Location: ' . $url);
    exit;
}

$show_form = isset($_GET['add']);
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Departments"); ?></h5>
        <a href="?view=departments&add=1" class="btn btn-primary btn-sm" id="toggle-add">
            <?php echo _("Add New Department"); ?>
        </a>
    </div>
    <div class="card-body">
        <div id="add-form" class="mb-4" style="display:<?php echo $show_form ? 'block' : 'none'; ?>;">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="hidden" name="save_dept" value="1">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="department_code"><?php echo _("Code"); ?></label>
                            <input type="text" class="form-control" id="department_code" name="department_code" required maxlength="10">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="department_name"><?php echo _("Name"); ?></label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required maxlength="60">
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

        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Code"); ?></th>
                    <th><?php echo _("Name"); ?></th>
                    <th><?php echo _("Description"); ?></th>
                    <th class="text-center"><?php echo _("Status"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $result = db_query(
                "SELECT department_code, department_name, description, is_active
                FROM " . TB_PREF . "fa_departments
                ORDER BY department_code",
                _("Could not query departments")
            );

            if (db_num_rows($result) == 0) {
                echo '<tr><td colspan="4" class="text-center text-muted">' . _("No departments found.") . '</td></tr>';
            } else {
                while ($row = db_fetch_assoc($result)) {
                    $badge = $row['is_active']
                        ? '<span class="badge badge-success">' . _("Active") . '</span>'
                        : '<span class="badge badge-secondary">' . _("Inactive") . '</span>';
                    echo '<tr>';
                    echo '<td>' . html_entity_decode($row['department_code']) . '</td>';
                    echo '<td>' . html_entity_decode($row['department_name']) . '</td>';
                    echo '<td>' . html_entity_decode($row['description']) . '</td>';
                    echo '<td class="text-center">' . $badge . '</td>';
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
