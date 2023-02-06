<?php

/**
 * Export Posts - a plugin for the MyBB 1.8.x forum software.
 *
 * @package MyBB Plugin
 * @author Laird as a member of the unofficial MyBB Group
 * @copyright 2020 MyBB Group <http://mybb.group>
 * @link <https://github.com/mybbgroup/Export-Posts>
 * @version 1.0.1
 * @license GPL-3.0
 *
 */

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.
 * If not, see <http://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

if (defined('IN_ADMINCP')) {
	$plugins->add_hook('admin_config_plugins_activate_commit', 'expst_hookin__admin_config_plugins_activate_commit');
} else {
	$plugins->add_hook('usercp_menu'                         , 'expst_hookin__usercp_menu'                         );
	$plugins->add_hook('usercp_start'                        , 'expst_hookin__usercp_start'                        );
}

/**
 * Get the information for this plugin.
 *
 * @return Array The plugin's information, indexed by the standard MyBB keys
 *               for this data type.
 */
function exportposts_info() {
	global $plugins_cache, $db, $lang, $admin_session;

	$lang->load('exportposts');

	$desc = $lang->expst_info_desc.PHP_EOL;
	$litems = '';

	if (!empty($plugins_cache) && !empty($plugins_cache['active']) && !empty($plugins_cache['active']['exportposts'])) {
		if (!empty($admin_session['data']['expst_upgrade_success_info'])) {
			$msg_upgrade = $admin_session['data']['expst_upgrade_success_info'];
			$litems .= '<li style="list-style-image: url(styles/default/images/icons/success.png)"><div class="success">'.$msg_upgrade.'</div></li>'.PHP_EOL;
			update_admin_session('expst_upgrade_success_info', '');
		}

		$gid = expst_get_gid();
		if (!empty($gid)) {
			$litems .= '<li style="list-style-image: url(styles/default/images/icons/custom.png)"><a href="index.php?module=config-settings&amp;action=change&amp;gid='.$gid.'">'.$lang->expst_config_settings.'</a></li>'.PHP_EOL;
		}
	}

	if (!empty($litems)) {
		$desc .= '<ul>'.PHP_EOL.$litems.'</ul>'.PHP_EOL;
	}

	$ret = array(
		'name'          => $lang->expst_info_title                         ,
		'description'   => $desc                                           ,
		'website'       => 'https://mybb.group/Thread-Export-Posts'        ,
		'author'        => 'Laird as a member of the unofficial MyBB Group',
		'authorsite'    => 'https://mybb.group/User-Laird'                 ,
		'version'       => '1.0.1'                                         ,
		'guid'          => ''                                              ,
		'codename'      => 'exportposts'                                   ,
		'compatibility' => '18*'                                           ,
	);

	return $ret;
}

/**
 * Performs the tasks required upon installation of this plugin.
 */
function exportposts_install() {
	// We don't do anything here. Given that a plugin cannot be installed
	// without being simultaneously activated, it is sufficient to call
	// expst_install_or_upgrade() from exportposts_activate().
}

/**
 * Performs the tasks required upon uninstallation of this plugin.
 */
function exportposts_uninstall() {
	global $db, $cache;

	expst_remove_settings();

	// Remove this plugin's templategroup and templates.
	$db->delete_query('templates', "title LIKE 'exportposts_%'");
	$db->delete_query('templategroups', "prefix = 'exportposts'");

	// Remove the plugin's entry from the persistent cache.
	$mybbgrp_plugins = $cache->read('mybbgrp_plugins');
	unset($mybbgrp_plugins['exportposts']);
	$cache->update('mybbgrp_plugins', $mybbgrp_plugins);
}

/**
 * Performs the tasks required upon activation of this plugin.
 */
function exportposts_activate() {
	global $lang, $db, $expst_upgrd_msg;

	$info         = exportposts_info();
	$from_version = expst_get_installed_version();
	$to_version   = $info['version'];
	expst_install_or_upgrade($from_version, $to_version);
	if ($from_version !== $to_version) {
		expst_set_installed_version($to_version);
		if ($from_version) {
			$expst_upgrd_msg = $lang->sprintf($lang->expst_upgrade_success_hdr, $lang->expst_info_title, $to_version);
			update_admin_session('expst_upgrade_success_info', $lang->sprintf($lang->expst_upgrade_success_info, $to_version));
		}
	}
}

/**
 * Performs the tasks required upon deactivation of this plugin.
 */
function exportposts_deactivate() {
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
}

/**
 * Determines whether or not this plugin is installed.
 *
 * @return boolean True if installed; false otherwise.
 */
function exportposts_is_installed() {
	return expst_get_installed_version() === false ? false : true;
}

/**
 * Performs all tasks required to install or upgrade this plugin.
 *
 * @param string $from_version The version, as a "PHP-standardized" version
 *                             number string, from which we are upgrading, or
 *                             false if we are installing rather than upgrading.
 * @param string $to_version   The version, as a "PHP-standardized" version
 *                             number string, to which we are upgrading or at
 *                             which we are installing.
 */
function expst_install_or_upgrade($from_version = null, $to_version = null) {
	global $db;
	$prefix = 'exportposts_';

	if (empty($to_version)) {
		$info = exportposts_info();
		$to_version = $info['version'];
	}
	// Save any existing values for this plugin's settings.
	$curr_setting_vals = array();
	$gid = expst_get_gid();
	if (!empty($gid)) {
		$query = $db->simple_select('settings', 'value, name', "gid='{$gid}'");
		while ($setting = $db->fetch_array($query)) {
			$curr_setting_vals[$setting['name']] = $setting['value'];
		}
	}

	// Now delete any existing settings...
	expst_remove_settings();

	// ...and then recreate them, retaining any saved values.
	// We recreate settings so as to refresh any language strings that have
	// been updated since last upgrade (or since installation).
	expst_create_settings($curr_setting_vals);

	// Create/update this plugin's templates.
	expst_insert_or_update_templates($from_version);
}

/**
 * Retrieves from the persistent cache the installed version of this plugin.
 *
 * @return string $version The installed version of this plugin as a "PHP-
 *                         standardized" version number string, or false if the
 *                         plugin is not yet installed (in that case, we are
 *                         presumably in the process of doing so).
 */
function expst_get_installed_version() {
	global $cache;

	$mybbgrp_plugins = $cache->read('mybbgrp_plugins');

	return !empty($mybbgrp_plugins['exportposts']['version'])
	         ? $mybbgrp_plugins['exportposts']['version']
	         : false;
}

/**
 * Sets and stores to the persistent cache the installed version of this plugin.
 *
 * @param string $version This plugin's current version, as a "PHP-standardized"
 *                        version number string.
 */
function expst_set_installed_version($version) {
	global $cache;

	$mybbgrp_plugins = $cache->read('mybbgrp_plugins');
	if (!isset($mybbgrp_plugins['exportposts'])) {
		$mybbgrp_plugins['exportposts'] = array();
	}
	$mybbgrp_plugins['exportposts']['version'] = $version;

	$cache->update('mybbgrp_plugins', $mybbgrp_plugins);
}

/**
 * Inserts or updates this plugin's templates, first, if necessary, creating
 * its template group.
 *
 * @param string $from_version The version, as a "PHP-standardized" version
 *                             number string, from which we are upgrading, or
 *                             false if we are installing rather than upgrading.
 */
function expst_insert_or_update_templates($from_version) {
	global $db, $lang;

	// First, create this plugin's templategroup if it does not already
	// exist.
	$query = $db->simple_select('templategroups', 'gid', "prefix='exportposts'");
	if (!$db->fetch_field($query, 'gid')) {
		$templateset = array(
			'prefix' => 'exportposts',
			# Replace [Plugin title] with this plugin's title.
			'title' => $lang->expst_info_title,
		);
		$db->insert_query('templategroups', $templateset);
	}

	$templates = array(
		'exportposts_usercp_nav' => array(
			'template' => '<tr>
	<td class="tcat tcat_menu tcat_collapse">
		<div class="expcolimage">
			<img src="{$theme[\'imgdir\']}/collapse{$collapsedimg[\'usercpexportposts\']}.png" id="usercpexportposts_img" class="expander" alt="[-]" title="[-]"/>
		</div>
		<div>
			<span class="smalltext">
				<strong>{$lang->expst_usercp_nav}</strong>
			</span>
		</div>
	</td>
</tr>
<tbody style="{$collapsed[\'usercpexportposts_e\']}" id="usercpexportposts_e">
<tr><td class="trow1 smalltext"><a href="usercp.php?action=exportposts" class="usercp_nav_item" style="background-position: 0 -300px;">{$lang->expst_usercp_exportposts}</a></td></tr>
</tbody>',
			'version'  => '1.0.0'          ,
		),
		'exportposts_export_page_attachcbx' => array(
			'template' => '<input type="checkbox" class="checkbox" id="incattach" name="incattach" checked="checked" value="1" /> <label for="incattach">{$lang->expst_include_attachs}</label>',
			'version'  => '1.0.0'          ,
		),
		'exportposts_export_page' => array(
			'template' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->expst_export_posts}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->expst_export_posts}</strong></td>
</tr>
<tr>
<td class="tcat" colspan="2"><span class="smalltext">{$lang->expst_export_note}</span></td>
</tr>
<tr>
<td class="trow2" valign="top" width="30%"><strong>{$lang->expst_date_limit}</strong></td>
<td class="trow2"><select name="dayway"><option value="older">{$lang->expst_date_limit_older}</option><option value="newer">{$lang->expst_date_limit_newer}</option><option value="disregard">{$lang->expst_date_limit_disregard}</option></select> <input type="text" class="textbox" name="daycut" value="30" size="3" maxlength="4" /> {$lang->expst_date_limit_days}</td>
</tr>
<tr>
<td class="trow1" valign="top" width="30%"><strong>{$lang->expst_attachments}</strong></td>
<td class="trow1">{$attachcbx}</td>
</tr>
<tr>
<td class="trow2" valign="top" width="30%"><strong>{$lang->expst_export_format}</strong></td>
<td class="trow2"><input type="radio" class="radio" name="exporttype" value="html" checked="checked" /> {$lang->expst_export_html}<br /><input type="radio" class="radio" name="exporttype" value="txt" /> {$lang->expst_export_txt}<br /><input type="radio" class="radio" name="exporttype" value="csv" /> {$lang->expst_export_csv}</td>
</tr>
</table>
<br />
<div align="center">
<input type="hidden" name="action" value="do_exportposts" />
<input type="submit" class="button" name="submit" value="{$lang->expst_export_msgs_btn}" />
</div>
</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>',
			'version' => '1.0.0',
		),
		'exportposts_export_html_header' => array(
			'template' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$posts_for}</title>
<style type="text/css">{$css}
* {text-align: left}</style>
</head>
<body>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="1"><span class="largetext"><strong>{$posts_for}</strong></span></td>
</tr>
',
			'version' => '1.0.0',
		),
		'exportposts_export_html_message' => array(
			'template' => '<tr>
<td class="trow1"><strong>{$lang->expst_subject} {$subject}</strong><br /><em>{$lang->expst_sentdatetime} {$senddate}</em></td>
</tr>
<tr>
<td class="trow2">{$message}</td>
</tr>
{$attachments}
<tr>
<td class="tcat" height="3"> </td>
</tr>',
			'version' => '1.0.0',
		),
		'exportposts_export_html_attachment' => array(
			'template' => '<a href="{$aid}-{$att[\'filename\']}">{$att[\'filename\']}</a>',
			'version' => '1.0.0',
		),
		'exportposts_export_html_attachments' => array(
			'template' => '<tr>
<td class="trow1"><em>{$lang->expst_attachments}</em> {$attachmentlist}</td>
</tr>',
			'version' => '1.0.0',
		),
		'exportposts_export_html_footer' => array(
			'template' => '</table>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="trow1" colspan="1">{$exdate}<br /><a href="{$mybb->settings[\'bburl\']}">{$mybb->settings[\'bbname\']}</a></td>
</tr>
</table>
</body>',
			'version' => '1.0.0',
		),
		'exportposts_export_csv_header' => array(
			'template' => '{$lang->expst_sentdatetime_forcss},{$lang->expst_subject_forcss},{$lang->expst_message_forcss}{$csv_attachments_hdr}
',
			'version' => '1.0.0',
		),
		'exportposts_export_csv_message' => array(
			'template' => '{$senddate},"{$subject}","{$message}"{$attachments}
',
			'version' => '1.0.0',
		),
		'exportposts_export_csv_attachment' => array(
			'template' => '{$aid}-{$att[\'filename\']}',
			'version' => '1.0.0',
		),
		'exportposts_export_csv_attachments' => array(
			'template' => '{$attachmentlist}',
			'version' => '1.0.0',
		),
		'exportposts_export_csv_footer' => array(
			'template' => '',
			'version' => '1.0.0',
		),
		'exportposts_export_txt_header' => array(
			'template' => '{$posts_for}
{$exdate}

',
			'version' => '1.0.0',
		),
		'exportposts_export_txt_message' => array(
			'template' => '{$lang->expst_subject} {$subject}
{$lang->expst_sentdatetime} {$senddate}
------------------------------------------------------------------------
{$message}
------------------------------------------------------------------------
{$attachments}

',
			'version' => '1.0.0',
		),
		'exportposts_export_txt_attachment' => array(
			'template' => '{$aid}-{$att[\'filename\']}',
			'version' => '1.0.0',
		),
		'exportposts_export_txt_attachments' => array(
			'template' => '{$lang->expst_attachments} {$attachmentlist}
',
			'version' => '1.0.0',
		),
		'exportposts_export_txt_footer' => array(
			'template' => '',
			'version' => '1.0.0',
		),
		'exportposts_attachment_for_export' => array(
			'template' => '<img src="{$aid}-{$att[\'filename\']}" class="attachment" alt="" title="{$lang->expst_attachment_filename} {$att[\'filename\']}&#13;{$lang->expst_attachment_size} {$att[\'filesize\']}&#13;{$attachdate}" /></a>&nbsp;&nbsp;&nbsp;',
			'version' => '1.0.0',
		),
	);

	foreach ($templates as $template_title => $template_data) {
		// Flag this template if it has been modified in the $templates
		// array since the version of the plugin from which we are
		// upgrading (if any - we skip this flagging on installation,
		// when $from_version is false).
		//
		// This ensures that Find Updated Templates detects them *if*
		// the user has also modified them, and without false positives.
		// The way we flag them is to zero the `version` column of the
		// `templates` table where `sid` is not -2 for this template.
		if (!empty($from_version) && version_compare($template_data['version'], $from_version) > 0) {
			$db->update_query('templates', array('version' => 0), "title='{$template_title}' and sid <> -2");
		}

		// Now insert/update master templates with SID -2.
		$insert_templates = array(
			'title'    => $db->escape_string($template_title),
			'template' => $db->escape_string($template_data['template']),
			'sid'      => '-2',
			'version'  => '1',
			'dateline' => TIME_NOW
		);
		$db->insert_query('templates', $insert_templates);
	}
}

/**
 * Gets the gid of this plugin's setting group, if any.
 *
 * @return The gid or false if the setting group does not exist.
 */
function expst_get_gid() {
	global $db;
	$prefix = 'exportposts_';

	$query = $db->simple_select('settinggroups', 'gid', "name = '{$prefix}settings'", array(
		'order_by' => 'gid',
		'order_dir' => 'DESC',
		'limit' => 1
	));

	return $db->fetch_field($query, 'gid');
}

/**
 * Creates this plugin's settings. Assumes that the settings do not already
 * exist, i.e., that they have already been deleted if they were pre-existing.
 *
 * @param Array $curr_setting_vals The values of pre-existing settings, if any,
 *                                 indexed by setting name WITH the `expst_`
 *                                 prefix.
 */
function expst_create_settings($curr_setting_vals = array()) {
	global $db, $lang;
	$prefix = 'exportposts_';

	$lang->load('exportposts');

	$query = $db->simple_select('settinggroups', 'MAX(disporder) as max_disporder');
	$disporder = intval($db->fetch_field($query, 'max_disporder')) + 1;

	// Insert the plugin's settings group into the database.
	$setting_group = array(
		'name'         => $prefix.'settings',
		'title'        => $db->escape_string($lang->expst_settings_title),
		'description'  => $db->escape_string($lang->expst_settings_desc ),
		'disporder'    => $disporder,
		'isdefault'    => 0
	);
	$db->insert_query('settinggroups', $setting_group);
	$gid = $db->insert_id();

	// Define the plugin's settings.
	$settings = array(
		'tmpdir' => array(
			'title'       => $lang->expst_setting_tmpdir_title,
			'description' => $lang->expst_setting_tmpdir_desc ,
			'optionscode' => 'text'                           ,
			'value'       => ''                               ,
		),
	);

	// Insert each of this plugin's settings into the database, restoring
	// pre-existing values where they have been provided.
	$disporder = 1;
	foreach ($settings as $name => $setting) {
		$value = isset($curr_setting_vals[$prefix.$name]) ? $curr_setting_vals[$prefix.$name] : $setting['value'];
		$insert_settings = array(
			'name'        => $db->escape_string($prefix.$name          ),
			'title'       => $db->escape_string($setting['title'      ]),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value'       => $db->escape_string($value                 ),
			'disporder'   => $disporder                                 ,
			'gid'         => $gid                                       ,
			'isdefault'   => 0                                          ,
		);
		$db->insert_query('settings', $insert_settings);
		$disporder++;
	}

	rebuild_settings();
}

/**
 * Removes this plugin's settings, including its settings group.
 * Accounts for the possibility that the settings group + settings were
 * accidentally created multiple times.
 */
function expst_remove_settings() {
	global $db;
	$prefix = 'exportposts_';

	$rebuild = false;
	$query = $db->simple_select('settinggroups', 'gid', "name = '{$prefix}settings'");
	while ($gid = $db->fetch_field($query, 'gid')) {
		$db->delete_query('settinggroups', "gid='{$gid}'");
		$db->delete_query('settings', "gid='{$gid}'");
		$rebuild = true;
	}
	if ($rebuild) {
		rebuild_settings();
	}
}

/**
 * Assigns any value of this plugin's "upgrade success" message global variable
 * (conditionally set in exportposts_activate()) to core's global $message
 * variable, which core then displays at the top of the post-activation reload
 * of the ACP Plugins page. With this, we effectively replace the default
 * message "The selected plugin has been activated successfully." with our
 * custom message "[Plugin name] has been activated successfully and upgraded
 * to version [x.y.z]."
 */
function expst_hookin__admin_config_plugins_activate_commit() {
	global $message, $expst_upgrd_msg;

	if (!empty($expst_upgrd_msg)) {
		$message = $expst_upgrd_msg;
	}
}

function expst_hookin__usercp_menu() {
	global $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	$lang->load('exportposts');

	eval('$usercpmenu .= "'.$templates->get('exportposts_usercp_nav').'";');
}

function expst_hookin__usercp_start() {
	global $mybb, $plugins, $theme, $lang, $db, $templates, $parser, $headerinclude, $header, $footer, $usercpnav;
	$prefix = 'exportposts_';

	switch ($mybb->input['action']) {
	case 'exportposts':
		add_breadcrumb($lang->nav_usercp, 'usercp.php');
		add_breadcrumb($lang->expst_breadcrumb, 'usercp.php?action=export');
		if (!$mybb->user['uid']) {
			error($lang->expst_err_notloggedin);
		}
		if (class_exists('ZipArchive')) {
			eval('$attachcbx = "'.$templates->get('exportposts_export_page_attachcbx').'";');
		} else	$attachcbx = $lang->expst_err_nozipsonoattachdl;
		eval('$html = "'.$templates->get('exportposts_export_page').'";');
		output_page($html);
		break;
	case 'do_exportposts':
		if (!$mybb->user['uid']) {
			error($lang->expst_err_notloggedin);
		}
		if ($mybb->request_method == 'post') {
			verify_post_check($mybb->get_input('my_post_key'));

			$plugins->run_hooks('exportposts_do_export_start');

			$exdate = my_date($mybb->settings['dateformat'], TIME_NOW, 0, 0);
			$extime = my_date($mybb->settings['timeformat'], TIME_NOW, 0, 0);
			$exdate = $lang->sprintf($lang->expst_exported_date, $exdate, $extime);

			$sql_conds = '';
			$dayway = $mybb->get_input('dayway');
			if (in_array($dayway, array('older', 'newer'))) {
				$sql_conds = 'dateline ';
				$sql_conds .= $dayway == 'newer' ? '>' : '<';
				$sql_conds .= ' '.(TIME_NOW - 60*60*24*(int)$mybb->get_input('daycut'));
			}

			$incattach = $mybb->get_input('incattach', MyBB::INPUT_INT);

			switch ($mybb->input['exporttype']) {
			case 'html':
				$contenttype = 'text/html';
				// Get the CSS of the global stylesheet for this user's theme
				$tid = $mybb->user['style'];
				if (!$tid) $tid = 1;
				$query = $db->simple_select('themestylesheets', 'stylesheet', "tid = '{$tid}' AND name = 'global.css'", array('limit' => 1));
				$css = $db->fetch_field($query, 'stylesheet');
				$db->free_result($query);
				break;
			case 'csv':
				$contenttype = 'text/csv';
				$csv_attachments_hdr = $incattach ? ",{$lang->expst_attachments_forcss}" : '';
				break;
			default: // 'txt'
				$contenttype = 'text/plain';
			}

			$done = $gotposts = false;
			$last_tid = $last_pid = 0;
			$page = 1;
			$attachs = array();
			$basepath = $mybb->settings[$prefix.'tmpdir'] ? rtrim($mybb->settings[$prefix.'tmpdir'], '/') : "{$mybb->settings['uploadspath']}/exportposts-tmp";
			if (!file_exists($basepath)) {
				mkdir($basepath, 0777, /*$recursive=*/true);
			}

			// Safety first: try to protect the temporary directory
			// from public view in case it is web-accessible.
			$htaccess = "{$basepath}/.htaccess";
			if (!file_exists($htaccess)) {
				file_put_contents($htaccess, "deny from all\nOptions -Indexes");
			}
			$index_file = "{$basepath}/index.html";
			if (!file_exists($index_file)) {
				file_put_contents($index_file, "<html>\n<head>\n<title></title>\n</head>\n<body>\n&nbsp;\n</body>\n</html>");
			}

			$fname_base = 'posts-export-for-'.preg_replace('([^a-z0-9_-])', '', strtolower($mybb->user['username'])).'-'.my_date('Y-m-d-H-i-s');
			$fname = "{$fname_base}.{$mybb->input['exporttype']}";
			$fh = fopen("{$basepath}/{$fname}", 'w+');
			if ($fh === false) {
				error($lang->expst_err_nocrt_tmpdldfile);
			}
			$posts_for = $lang->sprintf($lang->expst_posts_for, htmlspecialchars_uni($mybb->user['username']));
			eval('$hdr = "'.$templates->get('exportposts_export_'.$mybb->input['exporttype'].'_header', 1, 0).'";');
			fwrite($fh, $hdr);
			while (!$done) {
				$per_page = 200;
				if ($sql_conds) $sql_conds .= ' AND ';
				$sql_conds .= "uid = '{$mybb->user['uid']}'";
				$limit = 'LIMIT '.(($page-1) * $per_page).', '.$per_page;
				$sql = 'SELECT * FROM '.TABLE_PREFIX."posts WHERE {$sql_conds} ORDER BY tid ASC, pid ASC {$limit}";
				$query = $db->query($sql);
				if (!$db->num_rows($query)) {
					$done = true;
					$db->free_result($query);
					break;
				} else	$gotposts = true;
				$posts = $cnames = $cgids = array();
				while ($row = $db->fetch_array($query)) {
					// Handle the possibility of posts being
					// posted in between iterations of the
					// main loop: while(!$done).
					if ($row['tid'] <= $last_tid && $row['pid'] <= $last_pid) {
						continue;
					}

					$posts[$row['pid']] = $row;
				}
				$last_tid = $row['tid'];
				$last_pid = $row['pid'];
				$db->free_result($query);

				// Fetch attachments as necessary.
				if ($incattach) {
					$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'attachments WHERE pid in ('.implode(',', array_keys($posts)).')');
					while ($row = $db->fetch_array($query)) {
						if (empty($posts[$row['pid']]['attachs'])) {
							$posts[$row['pid']]['attachs'] = array();
						}
						$posts[$row['pid']]['attachs'][$row['aid']] = $row;
						// Store here for permanence: $posts is reset each run through the main while(!$done) loop.
						$attachs[$row['aid']] = $row;
					}
					$db->free_result($query);
				}

				foreach ($posts as $post) {
					$subject = $post['subject'];
					$message = $post['message'];
					$senddate = my_date($mybb->settings['dateformat'], $post['dateline'], '', false);
					$sendtime = my_date($mybb->settings['timeformat'], $post['dateline'], '', false);
					$senddate .= " {$lang->at} {$sendtime}";
					if ($mybb->input['exporttype'] == 'html') {
						if (empty($parser)) {
							require_once MYBB_ROOT.'inc/class_parser.php';
							$parser = new Postparser;
						}
						$subject = htmlspecialchars_uni($subject);
						$forum = get_forum($post['fid']);
						$parser_options = array(
							'allow_html'      => $forum['allowhtml'     ],
							'allow_mycode'    => $forum['allowmycode'   ],
							'allow_smilies'   => $forum['allowsmilies'  ],
							'allow_imgcode'   => $forum['allowimgcode'  ] && $mybb->user['showimages'] == 1,
							'allow_videocode' => $forum['allowvideocode'] && $mybb->user['showvideos'] == 1,
							'filter_badwords' => 1                       ,
							'me_username'     => $post ['username'      ],
							'allow_smilies'   => !empty($post['smilieoff']),
						);
						$message = $parser->parse_message($message, $parser_options);
					} else {
						$message = str_replace("\r\n", "\n", $message);
						$message = str_replace("\n", "\r\n", $message);
					}
					$attachments = '';
					if ($incattach && !empty($post['attachs'])) {
						$attachmentlist = '';
						foreach ($post['attachs'] as $aid => $att) {
							if ($attachmentlist) $attachmentlist .= $lang->comma;
							if ($mybb->input['exporttype'] == 'html') {
								$att['filename'] = htmlspecialchars_uni($att['filename']);
								// Replace [attachment=id] with image as appropriate.
								if (in_array(get_extension($att['filename']), array('jpeg','gif','bmp','png','jpg')) && stripos($message, "[attachment={$aid}]") !== false) {
									eval('$attach = "'.$templates->get('exportposts_attachment_for_export', 1, 0).'";');
									$message = preg_replace("#\[attachment=".$att['aid']."]#si", $attach, $message);
								}
							}
							eval('$attachmentlist .= "'.$templates->get('exportposts_export_'.$mybb->input['exporttype'].'_attachment', 1, 0).'";');
						}
						eval('$attachments = "'.$templates->get('exportposts_export_'.$mybb->input['exporttype'].'_attachments', 1, 0).'";');
					}
					if ($mybb->input['exporttype'] == 'csv') {
						$message = my_escape_csv($message);
						$subject = my_escape_csv($subject);
						if ($incattach) {
							$attachments = ',"'.my_escape_csv($attachments).'"';
						}
					}
					eval('$post_row = "'.$templates->get('exportposts_export_'.$mybb->input['exporttype'].'_message', 1, 0).'";');
					fwrite($fh, $post_row);
				}
				$page++;
			}
			if (!$gotposts) {
				fclose($fh);
				unlink("{$basepath}/{$fname}");
				error($lang->expst_err_nomsgstoexport);
			}
			eval('$ftr = "'.$templates->get('exportposts_export_'.$mybb->input['exporttype'].'_footer', 1, 0).'";');
			fwrite($fh, $ftr);

			$plugins->run_hooks('exportposts_do_export_end');

			if ($mybb->input['exporttype'] != 'html') {
				fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM
			}

			if ($attachs && class_exists('ZipArchive') && ($zip = new ZipArchive())) {
				if ($zip->open("{$basepath}/{$fname_base}.zip", ZipArchive::CREATE) !== true) {
					unlink("{$basepath}/{$fname}");
					error($lang->expst_err_zipopen);
				}
				fclose($fh);
				if ($zip->addEmptyDir($fname_base) === false) {
					unlink("{$basepath}/{$fname}");
					$zip->close();
					unlink("{$basepath}/{$fname_base}.zip");
					error($lang->expst_err_zipbasedir);
				}
				if ($zip->addFile("{$basepath}/{$fname}", "{$fname_base}/{$fname}") === false) {
					unlink("{$basepath}/{$fname}");
					$zip->close();
					unlink("{$basepath}/{$fname_base}.zip");
					error($lang->sprintf($lang->expst_err_zipaddfile, "{$fname_base}/{$fname}"));
				}
				foreach ($attachs as $aid => $att) {
					if ($zip->addFile("{$mybb->settings['uploadspath']}/{$att['attachname']}", "{$fname_base}/{$aid}-{$att['filename']}") === false) {
						$zip->close();
						unlink("{$basepath}/{$fname_base}.zip");
						error($lang->sprintf($lang->expst_err_zipaddfile, "{$fname_base}/{$aid}-{$att['filename']}"));
					}
				}
				if ($zip->close() === false) {
					unlink("{$basepath}/{$fname}");
					unlink("{$basepath}/{$fname_base}.zip");
					error($lang->expst_err_zipclose);
				}
				header("Content-disposition: filename={$fname_base}.zip");
				header('Content-type: application/zip');
				$fh = fopen("{$basepath}/{$fname_base}.zip", 'r');
				fpassthru($fh);
				fclose($fh);
				unlink("{$basepath}/{$fname_base}.zip");
			} else {
				header("Content-disposition: filename=$fname");
				header("Content-type: ".$contenttype);
				rewind($fh);
				fpassthru($fh);
				fclose($fh);
			}
			unlink("{$basepath}/{$fname}");
		}
		break;
	}
}
