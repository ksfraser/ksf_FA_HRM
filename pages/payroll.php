<?php
$path_to_root = "../../..";
$page_security = 'SA_HRM_PAYROLL';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Payroll.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/PayrollEntry.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/PayrollRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/PayrollService.php");

use ksfraser\FrontAccounting\HRM\Service\PayrollService;

$service = new PayrollService();

$payrolls = $service->listAll();
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
            <?php if (!empty($payrolls)): ?>
                <?php foreach ($payrolls as $payroll): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payroll->getEmployeeName() ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($payroll->getPayPeriodStart()); ?></td>
                        <td><?php echo htmlspecialchars($payroll->getPayPeriodEnd()); ?></td>
                        <td class="text-right"><?php echo price_format($payroll->getGrossPay()); ?></td>
                        <td class="text-right"><?php echo price_format($payroll->getTotalDeductions()); ?></td>
                        <td class="text-right"><?php echo price_format($payroll->getNetPay()); ?></td>
                        <td><?php echo htmlspecialchars($payroll->getStatus()); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-muted"><?php echo _("No records found."); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
