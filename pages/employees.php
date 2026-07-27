<?php
/**
 * HRM - Employees List
 *
 * @package ksf_FA_HRM
 * @since 1.0.0
 */

$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

$result = get_employees();
?>
<div class="card">
<div class="card-header"><?php echo _("Employees"); ?></div>
<div class="card-body">
<table class="table table-sm table-striped">
<thead class="thead-dark">
<tr>
    <th><?php echo _("Code"); ?></th>
    <th><?php echo _("Name"); ?></th>
    <th><?php echo _("Department"); ?></th>
    <th><?php echo _("Position"); ?></th>
    <th><?php echo _("Hire Date"); ?></th>
    <th><?php echo _("Status"); ?></th>
</tr>
</thead>
<tbody>
<?php
if ($result && db_num_rows($result)) {
    while ($row = db_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['employee_code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['person_name'] ?? $row['employee_code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['department_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['position_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['hire_date'] ?? '') . "</td>";
        echo "<td>" . ($row['is_active'] ? _('Active') : _('Inactive')) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-muted'>" . _("No employees found. Check database tables.") . "</td></tr>";
}
?>
</tbody>
</table>
</div></div>
