<?php
$path_to_root = "../..";
$page_security = 'SA_HRM_BENEFITS';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/Benefit.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Entity/EmployeeBenefit.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/FatRepositoryTrait.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Repository/BenefitRepository.php");
require_once($path_to_root . "/modules/ksf_FA_HRM/src/Service/BenefitsService.php");

use ksfraser\FrontAccounting\HRM\Service\BenefitsService;

$service = new BenefitsService();

$selected_id = isset($_POST['selected_id']) ? $_POST['selected_id'] : (isset($_GET['selected_id']) ? $_GET['selected_id'] : '');
$View = isset($_GET['view']) ? $_GET['view'] : (isset($_POST['view']) ? $_POST['view'] : '');

if (isset($_POST['save_benefit'])) {
    $service->create($_POST);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=benefits');
    exit;
}

$addNew = isset($_GET['addNew']);

$benefits = $service->listAll(false);
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo _("Benefits Management"); ?></h5>
        <a href="benefits.php?view=benefits&addNew=1" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> <?php echo _("Add New Benefit"); ?>
        </a>
    </div>
    <div class="card-body">

<?php if ($addNew): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><?php echo _("Add New Benefit"); ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="benefits.php?view=benefits">
                    <input type="hidden" name="save_benefit" value="1" />
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="benefit_code"><?php echo _("Benefit Code"); ?></label>
                                <input type="text" class="form-control" id="benefit_code" name="benefit_code" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="benefit_name"><?php echo _("Benefit Name"); ?></label>
                                <input type="text" class="form-control" id="benefit_name" name="benefit_name" required />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="benefit_type"><?php echo _("Benefit Type"); ?></label>
                                <select class="form-control" id="benefit_type" name="benefit_type" required>
                                    <option value=""><?php echo _("Select Type"); ?></option>
                                    <option value="Medical"><?php echo _("Medical"); ?></option>
                                    <option value="Dental"><?php echo _("Dental"); ?></option>
                                    <option value="Vision"><?php echo _("Vision"); ?></option>
                                    <option value="Life Insurance"><?php echo _("Life Insurance"); ?></option>
                                    <option value="Pension"><?php echo _("Pension"); ?></option>
                                    <option value="Other"><?php echo _("Other"); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employer_rate"><?php echo _("Employer Rate"); ?></label>
                                <input type="number" class="form-control" id="employer_rate" name="employer_rate" step="0.01" min="0" required />
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employee_rate"><?php echo _("Employee Rate"); ?></label>
                                <input type="number" class="form-control" id="employee_rate" name="employee_rate" step="0.01" min="0" required />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gl_account_code"><?php echo _("GL Account Code"); ?></label>
                                <input type="text" class="form-control" id="gl_account_code" name="gl_code_expense" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="is_active">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked />
                                    <label class="form-check-label" for="is_active"><?php echo _("Active"); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr />
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><?php echo _("Save Benefit"); ?></button>
                        <a href="benefits.php?view=benefits" class="btn btn-secondary"><?php echo _("Cancel"); ?></a>
                    </div>
                </form>
            </div>
        </div>
<?php endif; ?>

<?php if (empty($benefits)): ?>
    <div class="text-center text-muted"><?php echo _("No benefits found."); ?></div>
<?php else: ?>
        <table class="table table-sm table-striped">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo _("Benefit Code"); ?></th>
                    <th><?php echo _("Benefit Name"); ?></th>
                    <th><?php echo _("Benefit Type"); ?></th>
                    <th><?php echo _("Employer Contribution"); ?></th>
                    <th><?php echo _("Employee Contribution"); ?></th>
                    <th><?php echo _("Status"); ?></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($benefits as $benefit): ?>
                <tr>
                    <td><?php echo display_heading($benefit->getBenefitCode()); ?></td>
                    <td><?php echo $benefit->getBenefitName(); ?></td>
                    <td><?php echo $benefit->getBenefitType(); ?></td>
                    <td><?php echo price_format($benefit->getEmployerRate()); ?></td>
                    <td><?php echo price_format($benefit->getEmployeeRate()); ?></td>
                    <td>
                        <?php if ($benefit->isActive()): ?>
                            <span class="badge badge-success"><?php echo _("Active"); ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?php echo _("Inactive"); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </div>
</div>
