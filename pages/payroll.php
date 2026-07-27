<?php
$path_to_root = "../..";

$sql = "SELECT p.*, c.name AS employee_name
    FROM " . TB_PREF . "ksf_hrm_payroll p
    LEFT JOIN " . TB_PREF . "crm_persons c ON p.person_id = c.id
    ORDER BY p.period_start DESC";
$result = db_query($sql);
?>
<div class="card">
<div class="card-header"><?php echo _("Payroll"); ?></div>
<div class="card-body">
<table class="table table-sm table-striped">
<thead class="thead-dark">
<tr>
    <th><?php echo _("Employee"); ?></th>
    <th><?php echo _("Period Start"); ?></th>
    <th><?php echo _("Period End"); ?></th>
    <th><?php echo _("Gross"); ?></th>
    <th><?php echo _("Deductions"); ?></th>
    <th><?php echo _("Net"); ?></th>
    <th><?php echo _("Status"); ?></th>
</tr>
</thead>
<tbody>
<?php
if ($result && db_num_rows($result)) {
    while ($row = db_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['employee_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['period_start'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['period_end'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['gross'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['deductions'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['net'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? '') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-muted'>" . _("No records found.") . "</td></tr>";
}
?>
</tbody></table>
</div></div>
