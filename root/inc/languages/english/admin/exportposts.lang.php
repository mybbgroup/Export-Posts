<?php

// For this plugin's information pane in the ACP's Plugins page.
$l['expst_info_title'          ] = 'Export Posts';
$l['expst_info_desc'           ] = 'Allows members to export their posts with a similar interface to the core facility to export private messages.';
$l['expst_config_settings'     ] = 'Configure settings';
$l['expst_upgrade_success_hdr' ] = '{1} has been activated successfully and upgraded to version {2}.';
$l['expst_upgrade_success_info'] = 'Successfully upgraded to version {1}.';

// For this plugin's entry in the ACP's Settings page listing.
$l['expst_settings_title'      ] = 'Export Posts Settings';
$l['expst_settings_desc'       ] = 'Settings to customise the Export Posts plugin';
$l['expst_setting_tmpdir_title'] = 'Temporary directory';
$l['expst_setting_tmpdir_desc' ] = 'Set this to the full filesystem path to the temporary directory for this plugin to use when creating downloadable files. The plugin does its best to ensure that (1) files in this directory are deleted immediately after being streamed to the browser, and (2) it is protected from public view, however, you should stipulate a directory outside of your web root, and ensure that any of its contents are deleted regularly. Variables are not supported in this path. If this setting is empty, the plugin will use the "{$mybb->settings[\'uploadspath\']}/exportposts-tmp" directory, creating it if it does not exist.';