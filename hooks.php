<?php
/**
 * ksf_FA_HRM Module Hooks for FrontAccounting
 *
 * HRM adapter: app tab, security areas, DB installation.
 *
 * @package ksf_FA_HRM
 * @version 1.0.0
 */

// Load FAModuleMenu for menu registration
$famodulemenuPath = dirname(__DIR__) . '/ksf_FA_Common/src/Menu/FAModuleMenu.php';
if (file_exists($famodulemenuPath)) {
    require_once $famodulemenuPath;
}

// Load traits for workflow hooks and CRUD operations
use ksfraser\FrontAccounting\Common\Traits\WorkflowHooksTrait;
use ksfraser\FrontAccounting\Common\Traits\CrudOperationsTrait;

define('SS_ksf_FA_HRM', 117 << 8);
define('KSF_HRM_MODULE_NAME', 'ksf_FA_HRM');
define('KSF_HRM_CAPABILITIES', 'employee,department,position,grade,payroll,benefit,leave,recruitment,commission');

class hooks_ksf_FA_HRM extends hooks
{
    use WorkflowHooksTrait;
    use CrudOperationsTrait;
    var $module_name = 'ksf_FA_HRM';
    var $version = '1.0.0';

    function install_tabs($app)
    {
        set_ext_domain('modules/ksf_FA_HRM');
        $app->add_application(new hrm_app());
        set_ext_domain();
    }

    function install_access()
    {
        $security_sections[SS_ksf_FA_HRM] = _("HRM Management");

        $security_areas['SA_ksf_FA_HRMVIEW'] = array(
            SS_ksf_FA_HRM | 1, _("View HRM")
        );
        $security_areas['SA_ksf_FA_HRMMANAGE'] = array(
            SS_ksf_FA_HRM | 2, _("Manage HRM")
        );
        $security_areas['SA_HRM_EMPLOYEE'] = array(
            SS_ksf_FA_HRM | 3, _("Employees")
        );
        $security_areas['SA_HRM_DEPARTMENT'] = array(
            SS_ksf_FA_HRM | 4, _("Departments")
        );
        $security_areas['SA_HRM_PAYROLL'] = array(
            SS_ksf_FA_HRM | 5, _("Payroll")
        );
        $security_areas['SA_HRM_BENEFITS'] = array(
            SS_ksf_FA_HRM | 6, _("Benefits")
        );
        $security_areas['SA_HRM_LEAVE'] = array(
            SS_ksf_FA_HRM | 7, _("Leave Management")
        );
        $security_areas['SA_HRM_RECRUITMENT'] = array(
            SS_ksf_FA_HRM | 8, _("Recruitment")
        );
        $security_areas['SA_HRM_TEAMS'] = array(
            SS_ksf_FA_HRM | 9, _("Teams")
        );
        $security_areas['SA_HRM_ROLES'] = array(
            SS_ksf_FA_HRM | 10, _("Roles")
        );

        return array($security_areas, $security_sections);
    }

    function activate_extension($company, $check_only = true)
    {
        if (!file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            return true;
        }

        $updates = array(
            'install.sql' => array('fa_departments'),
        );

        return $this->update_databases($company, $updates, $check_only);
    }

    function init()
    {
        $this->registerWorkflowType('employee', 'hrm_employee');
        $this->registerWorkflowType('position', 'hrm_position');
        $this->registerWorkflowType('department', 'hrm_department');
        $this->registerWorkflowType('team', 'hrm_team');
        $this->registerWorkflowType('grade', 'hrm_grade');
        $this->registerWorkflowType('payroll', 'hrm_payroll');
        $this->registerWorkflowType('benefit', 'hrm_benefit');
    }

    protected function createRecordInternal(string $recordType, array $data): array
    {
        return $data;
    }

    protected function deleteRecordInternal(string $recordType, array $data): array
    {
        return $data;
    }

    /**
     * Return module constants for inter-module capability discovery.
     *
     * @param array $data Result bucket (by reference)
     * @param array|null $opts Options
     * @return array Module constants
     */
    public function getModuleConstants(&$data, $opts = null)
    {
        $constants = array(
            'KSF_HRM_MODULE_NAME' => KSF_HRM_MODULE_NAME,
            'KSF_HRM_CAPABILITIES' => KSF_HRM_CAPABILITIES,
        );
        $data['constants'] = $constants;
        return $constants;
    }

    /**
     * Return module capabilities with descriptions.
     *
     * @param array $data Result bucket (by reference)
     * @param array|null $opts Options
     * @return array Capabilities keyed by capability name
     */
    public function getModuleCapabilities(&$data, $opts = null)
    {
        $capabilities = array(
            'commission' => array(
                'description' => 'Sales commission calculation on imported orders',
                'methods' => array('computeCommission', 'onOrderImported'),
                'events' => array('ORDER_IMPORTED'),
            ),
        );
        $data['capabilities'] = $capabilities;
        return $capabilities;
    }

    /**
     * Check whether the module provides a capability.
     *
     * @param array $data Result bucket (by reference)
     * @param array|null $opts Options (capability)
     * @return bool Capability availability
     */
    public function hasCapability(&$data, $opts = null)
    {
        $capability = isset($opts['capability']) ? $opts['capability'] : (isset($data['capability']) ? $data['capability'] : null);
        if ($capability === null) {
            $data['has_capability'] = false;
            $data['error'] = 'No capability specified';
            return false;
        }
        $capabilities = explode(',', KSF_HRM_CAPABILITIES);
        $hasCapability = in_array($capability, $capabilities);
        $data['has_capability'] = $hasCapability;
        $data['capability_checked'] = $capability;
        return $hasCapability;
    }

    /**
     * Respond to a capability request.
     *
     * Supports 'capabilities', 'constants', and 'has:<capability>'.
     *
     * @param array $data Result bucket (by reference)
     * @param array|null $opts Options (request)
     * @return mixed Result of the requested operation or null
     */
    public function respondToCapabilityRequest(&$data, $opts = null)
    {
        $request = isset($opts['request']) ? $opts['request'] : (isset($data['request']) ? $data['request'] : 'capabilities');
        $data['request'] = $request;
        $data['module'] = $this->module_name;

        if (strpos($request, 'has:') === 0) {
            $capability = substr($request, 4);
            return $this->hasCapability($data, array('capability' => $capability));
        }

        switch ($request) {
            case 'capabilities':
                return $this->getModuleCapabilities($data, $opts);
            case 'constants':
                return $this->getModuleConstants($data, $opts);
            default:
                $data['error'] = 'Unknown request type: ' . $request;
                return null;
        }
    }

    /**
     * order_imported listener: create pending commission entries.
     *
     * Invoked by FA's hook_invoke_all('order_imported', $data) with the
     * payload broadcast by source modules (Square, WooCommerce). The
     * listener is a no-op when the HRM source tree is unavailable.
     *
     * @param array $data Event payload (by reference)
     * @param array|null $opts Options
     * @return void
     */
    public function order_imported(&$data, $opts = null)
    {
        if (!class_exists('ksfraser\FrontAccounting\HRM\Service\CommissionService')) {
            return;
        }
        $service = new \ksfraser\FrontAccounting\HRM\Service\CommissionService();
        $created = $service->onOrderImported($data);
        $data['commissions_created'] = count($created);
    }
}

class hrm_app extends application
{
    function __construct()
    {
        parent::__construct("HRM", _($this->help_context = "&HRM"));

        $this->add_module(_("Human Resources"));

        $menu = new \ksfraser\FrontAccounting\Common\Menu\FAModuleMenu(
            'modules/ksf_FA_HRM/index.php',
            'view',
            ''
        );

        $menu->addItem('employees',    _("&Employees"),       MENU_INQUIRY)
             ->addItem('departments',  _("Departments"),      MENU_INQUIRY)
             ->addItem('teams',        _("Teams"),            MENU_INQUIRY)
             ->addItem('positions',    _("Positions"),        MENU_SETTINGS)
             ->addItem('roles',        _("Roles"),            MENU_SETTINGS)
             ->addItem('grades',       _("Grades"),           MENU_SETTINGS)
             ->addItem('payroll',      _("Payroll"),          MENU_ENTRY)
             ->addItem('benefits',     _("Benefits"),         MENU_ENTRY)
             ->addItem('leave',        _("Leave Management"), MENU_ENTRY)
             ->addItem('leave_types',  _("Leave Types"),      MENU_SETTINGS)
             ->addItem('recruitment',  _("Recruitment"),      MENU_ENTRY)
             ->addItem('reports',      _("Reports"),          MENU_REPORT);

        $menu->registerWithApp($this, 'SA_ksf_FA_HRMVIEW');

        $this->add_extensions();
    }
}
