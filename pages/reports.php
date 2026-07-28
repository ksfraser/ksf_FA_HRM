<?php
$path_to_root = "../..";
$page_security = 'SA_HRM_REPORTS';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();
?>

<div class="card">
    <div class="card-header"><?php echo _("HRM Reports"); ?></div>
    <div class="card-body">
        <p class="text-muted"><?php echo _("HRM Reports - Coming Soon"); ?></p>
        <ul class="text-muted">
            <li><?php echo _("Employee Directory"); ?></li>
            <li><?php echo _("Department Summary"); ?></li>
            <li><?php echo _("Payroll Summary"); ?></li>
            <li><?php echo _("Benefits Summary"); ?></li>
            <li><?php echo _("Leave Balance Report"); ?></li>
        </ul>
    </div>
</div>
