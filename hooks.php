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
            'install.sql'             => array('fa_departments'),
            'retag_contact_types.sql' => array('ksf_contact_types'),
        );

        $ok = $this->update_databases($company, $updates, $check_only);

        if (!$check_only && $ok) {
            $this->register_contact_types();
        }

        return $ok;
    }

    /**
     * Register the contact types owned by this module (idempotent).
     */
    private function register_contact_types()
    {
        $autoload = dirname(__FILE__) . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (!class_exists('\\ksfraser\\FrontAccounting\\Common\\ContactType\\ContactTypeRegistry')) {
            return;
        }

        \ksfraser\FrontAccounting\Common\ContactType\ContactTypeRegistry::registerTypes(array(
            new \ksfraser\FrontAccounting\Common\ContactType\ContactType(
                'employee', 'Employee', $this->module_name,
                'Organizational employee managed by the HRM module'
            ),
            new \ksfraser\FrontAccounting\Common\ContactType\ContactType(
                'team', 'Team', $this->module_name,
                'Organizational team or group'
            ),
            new \ksfraser\FrontAccounting\Common\ContactType\ContactType(
                'job_applicant', 'Job Applicant', $this->module_name,
                'Applicant for a job posting'
            ),
        ));
    }

    function deactivate_extension($company, $check_only = true)
    {
        if (!$check_only
            && class_exists('\\ksfraser\\FrontAccounting\\Common\\ContactType\\ContactTypeRegistry')) {
            \ksfraser\FrontAccounting\Common\ContactType\ContactTypeRegistry::unregisterModule($this->module_name);
        }

        return true;
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

    // ─── Department UI Hooks ────────────────────────────────────────

    /**
     * Provide active department entities to requesting modules.
     *
     * Consumer: hook_invoke('ksf_FA_HRM', 'getDepartments', $data)
     * $data['active_only'] = true (bool, optional)
     *
     * @see BR-006 (Cross-Module DDL Caching)
     * @see FR-006-005 (Hook Contract)
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Options
     * @return array Department entity arrays (serialized from Department objects)
     */
    function getDepartments(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;

        $service = new \ksfraser\FrontAccounting\HRM\Service\DepartmentService();
        return $service->hookGetDepartments($data, $opts);
    }

    /**
     * Provide pre-rendered department DDL <option> strings to requesting modules.
     *
     * Consumer: hook_invoke('ksf_FA_HRM', 'getDepartmentDDL', $data)
     * $data['active_only'] = true   (bool, optional)
     * $data['blank_label'] = ''     (string, optional)
     * $data['format']      = '{code} - {name}' (string, optional)
     * $data['selected_id'] = 0      (int, optional)
     *
     * @see BR-006 (Cross-Module DDL Caching)
     * @see FR-006-005 (Hook Contract)
     * @see FR-006-006 (Blank Option & Mandatory Validation)
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Options
     * @return array Pre-rendered <option> HTML strings
     */
    function getDepartmentDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;

        $service = new \ksfraser\FrontAccounting\HRM\Service\DepartmentService();
        return $service->hookGetDepartmentDDL($data, $opts);
    }

    /**
     * Provide serializable HtmlOption objects for department DDL.
     *
     * Consumer: hook_invoke('ksf_FA_HRM', 'getDepartmentHtmlOptions', $data)
     * Returns HtmlOption[] that can be serialized, cached, and manipulated
     * before rendering. Consumer calls ->getHtml() on each option.
     *
     * @see BR-006 (Cross-Module DDL Caching)
     * @see FR-006-005 (Hook Contract)
     *
     * @param array $data Hook payload (by reference)
     * @param array|null $opts Options
     * @return \Ksfraser\HTML\Elements\HtmlOption[] Serializable option objects
     */
    function getDepartmentHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;

        $service = new \ksfraser\FrontAccounting\HRM\Service\DepartmentService();
        return $service->hookGetHtmlOptions($data, $opts);
    }

    // ─── Team Hooks ────────────────────────────────────────────────

    function getTeams(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\TeamService();
        return $service->hookGetTeams($data, $opts);
    }

    function getTeamDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\TeamService();
        return $service->hookGetTeamDDL($data, $opts);
    }

    function getTeamHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\TeamService();
        return $service->hookGetTeamHtmlOptions($data, $opts);
    }

    // ─── Role Hooks ────────────────────────────────────────────────

    function getRoles(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleService();
        return $service->hookGetRoles($data, $opts);
    }

    function getRoleDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleService();
        return $service->hookGetRoleDDL($data, $opts);
    }

    function getRoleHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleService();
        return $service->hookGetRoleHtmlOptions($data, $opts);
    }

    // ─── Role Dictionary Hooks ─────────────────────────────────────

    function getRoleDictionary(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleDictionaryService();
        return $service->hookGetRoleDictionary($data, $opts);
    }

    function getRoleDictionaryDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleDictionaryService();
        return $service->hookGetRoleDictionaryDDL($data, $opts);
    }

    function getRoleDictionaryHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\RoleDictionaryService();
        return $service->hookGetRoleDictionaryHtmlOptions($data, $opts);
    }

    // ─── Grade Hooks ───────────────────────────────────────────────

    function getGrades(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\GradeService();
        return $service->hookGetGrades($data, $opts);
    }

    function getGradeDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\GradeService();
        return $service->hookGetGradeDDL($data, $opts);
    }

    function getGradeHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\GradeService();
        return $service->hookGetGradeHtmlOptions($data, $opts);
    }

    // ─── Position Hooks ────────────────────────────────────────────

    function getPositions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\PositionService();
        return $service->hookGetPositions($data, $opts);
    }

    function getPositionDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\PositionService();
        return $service->hookGetPositionDDL($data, $opts);
    }

    function getPositionHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\PositionService();
        return $service->hookGetPositionHtmlOptions($data, $opts);
    }

    // ─── Employment Status Hooks ───────────────────────────────────

    function getEmploymentStatuses(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\EmploymentStatusService();
        return $service->hookGetEmploymentStatuses($data, $opts);
    }

    function getEmploymentStatusDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\EmploymentStatusService();
        return $service->hookGetEmploymentStatusDDL($data, $opts);
    }

    function getEmploymentStatusHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\EmploymentStatusService();
        return $service->hookGetEmploymentStatusHtmlOptions($data, $opts);
    }

    // ─── Benefits Hooks ────────────────────────────────────────────

    function getBenefits(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\BenefitsService();
        return $service->hookGetBenefits($data, $opts);
    }

    function getBenefitDDL(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\BenefitsService();
        return $service->hookGetBenefitDDL($data, $opts);
    }

    function getBenefitHtmlOptions(&$data, $opts = null)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) { return []; }
        require_once $autoload;
        $service = new \ksfraser\FrontAccounting\HRM\Service\BenefitsService();
        return $service->hookGetBenefitHtmlOptions($data, $opts);
    }

    // ─── Inter-Module Capabilities ─────────────────────────────────

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
