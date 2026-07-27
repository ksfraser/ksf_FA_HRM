<?php
$path_to_root = "../..";

$sql = "SELECT l.*, c.name AS employee_name
    FROM " . TB_PREF . "ksf_hrm_leave_balances l
    LEFT JOIN " . TB_PREF . "crm_persons c ON l.person_id = c.id
    ORDER BY c.name ASC";
$result = db_query($sql);
?>
<div class="card">
<div class="card-header"><?php echo _("Leave Balances"); ?></div>
<div class="card-body">
<table class="table table-sm table-striped">
<thead class="thead-dark">
<tr>
    <th><?php echo _("Employee"); ?></th>
    <th><?php echo _("Leave Type"); ?></th>
    <th><?php echo _("Total Days"); ?></th>
    <th><?php echo _("Used Days"); ?></th>
    <th><?php echo _("Year"); ?></th>
</tr>
</thead>
<tbody>
<?php
if ($result && db_num_rows($result)) {
    while ($row = db_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['employee_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['leave_type'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['total_days'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['used_days'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['year'] ?? '') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-muted'>" . _("No records found.") . "</td></tr>";
}
?>
</tbody></table>
</div></div>
