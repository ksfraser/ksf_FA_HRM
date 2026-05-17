<?php

$module = 'ksf_FA_HRM';

$access_levels = array(
    array('HRM', 'ksf_hrm', FA_PERMISSION_READ),
);

extends_hook('install_ksf_fa_hrm', 'Ksf\FA\HRM\Hooks\InstallHook::install');
extends_hook('uninstall_ksf_fa_hrm', 'Ksf\FA\HRM\Hooks\InstallHook::uninstall');

add_extension_options_widget('ksf_FA_HRM', 'Ksf\FA\HRM\Hooks\InstallHook::install');