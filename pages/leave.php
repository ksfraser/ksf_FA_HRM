<?php
$path_to_root = "../..";
$page_security = 'SA_HRM_LEAVE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/LookupRepository.php");

use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

$lookupRepo = new LookupRepository();

$sql = "SELECT l.*, c.name AS employee_name, lt.type_name AS leave_type_name
    FROM " . TB_PREF . "leave_balances l
    LEFT JOIN " . TB_PREF . "crm_persons c ON l.person_id = c.id
    LEFT JOIN " . TB_PREF . "leave_types lt ON l.leave_type_id = lt.leave_type_id
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
                    <th><?php echo _("Entitlement"); ?></th>
                    <th><?php echo _("Used"); ?></th>
                    <th><?php echo _("Balance"); ?></th>
                    <th><?php echo _("Year"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($result && db_num_rows($result)) {
                while ($row = db_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['employee_name'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['leave_type_name'] ?? '') . "</td>";
                    echo "<td>" . number_format($row['entitlement'] ?? 0, 1) . "</td>";
                    echo "<td>" . number_format($row['used'] ?? 0, 1) . "</td>";
                    echo "<td>" . number_format($row['balance'] ?? 0, 1) . "</td>";
                    echo "<td>" . htmlspecialchars($row['year'] ?? '') . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='text-muted'>" . _("No records found.") . "</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
