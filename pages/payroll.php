<?php
$path_to_root = "../..";
include_once($path_to_root . "/modules/ksf_FA_HRM/includes/employee_db.inc");

$sql = "SELECT p.*, c.name AS employee_name
    FROM " . TB_PREF . "ksf_hrm_payroll p
    LEFT JOIN " . TB_PREF . "crm_persons c ON p.person_id = c.id
    ORDER BY p.pay_period_start DESC";
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
    <th class="text-right"><?php echo _("Gross Pay"); ?></th>
    <th class="text-right"><?php echo _("Deductions"); ?></th>
    <th class="text-right"><?php echo _("Net Pay"); ?></th>
    <th><?php echo _("Status"); ?></th>
</tr>
</thead>
<tbody>
<?php
if ($result && db_num_rows($result)) {
    while ($row = db_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['employee_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['pay_period_start'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['pay_period_end'] ?? '') . "</td>";
        echo "<td class='text-right'>" . price_format($row['gross_pay']) . "</td>";
        echo "<td class='text-right'>" . price_format($row['total_deductions']) . "</td>";
        echo "<td class='text-right'>" . price_format($row['net_pay']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? '') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-muted'>" . _("No records found.") . "</td></tr>";
}
?>
</tbody></table>
</div></div>
