<?php
/**
 * ksf_FA_HRM Entry Point
 *
 * Routes ?view= to the appropriate page file.
 *
 * @package ksf_FA_HRM
 * @since 1.0.0
 */

$path_to_root = "../..";

$page_security = 'SA_ksf_FA_HRMVIEW';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

$view = isset($_GET['view']) ? $_GET['view'] : 'employees';

$validViews = array(
    'employees'   => array('file' => 'pages/employees.php',   'security' => 'SA_HRM_EMPLOYEE'),
    'departments' => array('file' => 'pages/departments.php', 'security' => 'SA_HRM_DEPARTMENT'),
    'positions'   => array('file' => 'pages/positions.php',   'security' => 'SA_ksf_FA_HRMMANAGE'),
    'grades'      => array('file' => 'pages/grades.php',      'security' => 'SA_ksf_FA_HRMMANAGE'),
    'payroll'     => array('file' => 'pages/payroll.php',     'security' => 'SA_HRM_PAYROLL'),
    'benefits'    => array('file' => 'pages/benefits.php',    'security' => 'SA_HRM_BENEFITS'),
    'leave'       => array('file' => 'pages/leave.php',       'security' => 'SA_HRM_LEAVE'),
    'recruitment'  => array('file' => 'pages/recruitment.php',  'security' => 'SA_HRM_RECRUITMENT'),
    'leave_types'  => array('file' => 'pages/leave_types.php',  'security' => 'SA_HRM_LEAVE'),
    'reports'      => array('file' => 'pages/reports.php',      'security' => 'SA_ksf_FA_HRMVIEW'),
);

if (!isset($validViews[$view])) {
    $view = 'employees';
}

$page_security = $validViews[$view]['security'];
$pageFile = dirname(__FILE__) . '/' . $validViews[$view]['file'];

// Build sub-menu for in-page navigation
$menu = new \ksfraser\FrontAccounting\Common\Menu\FAModuleMenu(
    'index.php',
    'view',
    $view
);

$menu->addItem('employees',    _("&Employees"),       null)
     ->addItem('departments',  _("Departments"),      null)
     ->addItem('positions',    _("Positions"),        null)
     ->addItem('grades',       _("Grades"),           null)
     ->addItem('payroll',      _("Payroll"),          null)
     ->addItem('benefits',     _("Benefits"),         null)
     ->addItem('leave',        _("Leave Management"), null)
     ->addItem('leave_types',  _("Leave Types"),      null)
     ->addItem('recruitment',  _("Recruitment"),      null)
     ->addItem('reports',      _("Reports"),          null);

page(_("HRM"), false, false, '', '');

echo $menu->render();

if (file_exists($pageFile)) {
    include($pageFile);
} else {
    echo "<div class='alert alert-warning'>Page not found: " . htmlspecialchars($view) . "</div>";
}

end_page();
