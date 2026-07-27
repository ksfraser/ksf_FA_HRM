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

define('SS_ksf_FA_HRM', 117 << 8);

class hooks_ksf_FA_HRM extends hooks
{
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
             ->addItem('positions',    _("Positions"),        MENU_SETTINGS)
             ->addItem('grades',       _("Grades"),           MENU_SETTINGS)
             ->addItem('payroll',      _("Payroll"),          MENU_ENTRY)
             ->addItem('benefits',     _("Benefits"),         MENU_ENTRY)
             ->addItem('leave',        _("Leave Management"), MENU_ENTRY)
             ->addItem('recruitment',  _("Recruitment"),      MENU_ENTRY)
             ->addItem('reports',      _("Reports"),          MENU_REPORT);

        $menu->registerWithApp($this, 'SA_ksf_FA_HRMVIEW');

        $this->add_extensions();
    }
}
