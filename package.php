<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');
include_once('./lib/export.php');
include_once('./lib/import.php');
include_once('./lib/package.php');
include_once('./lib/xml.php');

/* set default action */
set_default_action();

if (check_get_author_info() === false) {
	exit;
}

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'get_contents':
		$export_type    = get_nfilter_request_var('export_type');
		$export_item_id = get_nfilter_request_var('export_item_id');
		$include_deps   = (get_nfilter_request_var('include_deps') == 'true' ? true:false);

		print get_package_contents($export_type, $export_item_id);

		break;
	default:
		top_header();
		export();
		bottom_footer();

		break;
}

function form_save() {
	global $export_types, $export_errors, $debug, $package_file;

	/* ================= input validation ================= */
	get_filter_request_var('export_item_id');
	/* ==================================================== */

	$export_okay = false;

	$xml_data = get_item_xml(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'), (((isset_request_var('include_deps') ? get_nfilter_request_var('include_deps') : '') == '') ? false : true));

	$info                 = array();
	$info['name']         = get_nfilter_request_var('name');
	$info['author']       = get_nfilter_request_var('author');
	$info['homepage']     = get_nfilter_request_var('homepage');
	$info['email']        = get_nfilter_request_var('email');
	$info['description']  = get_nfilter_request_var('description');
	$info['class']        = get_nfilter_request_var('class');
	$info['tags']         = get_nfilter_request_var('tags');
	$info['installation'] = get_nfilter_request_var('installation');
	$info['version']      = get_nfilter_request_var('version');
	$info['copyright']    = get_nfilter_request_var('copyright');

	// Let's store the Template information for subsequent exports
	$hash = get_export_hash(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'));

	$export_okay = save_packager_metadata($hash, $info);

	$debug = '';

	if ($export_okay) {
		$files = find_dependent_files($xml_data);

		/* search xml files for scripts */
		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				if (strpos($file['file'], '.xml') !== false) {
					$files = array_merge($files, find_dependent_files(file_get_contents($file['file'])));
				}
			}
		}

		$success = package_template($xml_data, $info, $files, $debug);
	} else {
		top_header();
		print __('WARNING: Export Errors Encountered. Refresh Browser Window for Details!') . "\n";
		print $xml_data;
		bottom_footer();
		exit;
	}

	if ($export_errors || !$success) {
		raise_message('package_error', __('There were errors packaging your Templates.  Errors Follow. ') . str_replace("\n", '<br>', $debug), MESSAGE_LEVEL_ERROR);
		header('Location: package.php');
		exit;
	} elseif ($package_file != '') {
		header('Content-Type: application/gzip');
		header('Content-Disposition: attachment; filename="' . basename($package_file) . '"');
		header('Content-Length: ' . filesize($package_file));
		header('Content-Control: no-cache');
		header('Pragma: public');
		header('Expires: 0');
		readfile($package_file);
		unlink($package_file);
		exit;
	}
}

function export() {
	global $export_types, $config, $device_classes, $graph_template_classes, $copyrights;

	/* 'graph_template' should be the default */
	if (!isset_request_var('export_type')) {
		set_request_var('export_type', 'host_template');

		$id = db_fetch_cell('SELECT id FROM host_template ORDER BY name LIMIT 1');

		set_request_var('export_item_id', $id);
	}

	unset($export_types['data_template']);
	unset($export_types['data_query']);

	switch (get_nfilter_request_var('export_type')) {
		case 'host_template':
		case 'graph_template':
		case 'data_query':
			break;
		default:
			set_request_var('export_type', 'host_template');
	}

	html_start_box(__('Package Templates'), '100%', '', '3', 'center', '');

	?>
	<tr class='tableRow'>
		<td>
			<table>
				<tr>
					<td><span class='formItemName'><?php print __('What would you like to Package?');?>&nbsp;</span></td>
					<td>
						<select id='export_type'>
							<?php
							foreach($export_types as $key => $array) {
								print "<option value='$key'"; if (get_nfilter_request_var('export_type') == $key) { print ' selected'; } print '>' . html_escape($array['name']) . "</option>";
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box();

	$info = check_get_author_info();
	if ($info === false) {
		exit;
	}

	// Let's get any saved package details from the last time the template was packaged
	$data = array();
	$hash = get_export_hash(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'));

	// Two methods, one with SQLite and one without

	$data = array();
	if (class_exists('SQLite3')) {
		$data = get_packager_metadata($hash);
	}

	// Legacy support, to be deprecated eventually
	if (!cacti_sizeof($data)) {
		$data = read_config_option('package_export_' . $hash);

		if ($data != '') {
			$data = json_decode($data, true);
		}
	}

	// If this template has not been saved before, initialize values
	switch(get_nfilter_request_var('export_type')) {
		case 'host_template':
			$classes = $device_classes;

			if (!isset_request_var('export_item_id')) {
				$detail = db_fetch_row('SELECT *
					FROM host_template
					ORDER BY name
					LIMIT 1');
			} else {
				$detail = db_fetch_row_prepared('SELECT *
					FROM host_template
					WHERE id = ?',
					array(get_filter_request_var('export_item_id')));
			}

			break;
		case 'graph_template':
			$classes = $graph_template_classes;

			if (!isset_request_var('export_item_id')) {
				$detail = db_fetch_row('SELECT *
					FROM graph_templates
					ORDER BY name
					LIMIT 1');
			} else {
				$detail = db_fetch_row_prepared('SELECT *
					FROM graph_templates
					WHERE id = ?',
					array(get_filter_request_var('export_item_id')));
			}

			break;
		case 'data_query':
			$classes = $graph_template_classes;

			if (!isset_request_var('export_item_id')) {
				$detail = db_fetch_row('SELECT id, name
					FROM snmp_query
					ORDER BY name
					LIMIT 1');
			} else {
				$detail = db_fetch_row_prepared('SELECT id, name
					FROM snmp_query
					WHERE id = ?',
					array(get_filter_request_var('export_item_id')));
			}

			break;
	}

	if (cacti_sizeof($detail)) {
		switch(get_nfilter_request_var('export_type')) {
			case 'host_template':
				$data['description'] = __('%s Device Package', $detail['name']);
				break;
			case 'graph_template':
				$data['description'] = __('%s Graph Template Package', $detail['name']);
				break;
			case 'data_query':
				$data['description'] = __('%s Data Query Package', $detail['name']);
				break;
		}

		$meta_columns = array('version', 'class', 'author', 'email', 'tags', 'copyright', 'installation');

		foreach($meta_columns as $m) {
			if (isset($detail[$m]) && $detail[$m] != '') {
				$data[$m] = $detail[$m];
			} else {
				$default = read_config_option("package_$m");

				if (!empty($default)) {
					$data[$m] = $default;
				}
			}
		}

		$data['name'] = $detail['name'];
	}

	if (cacti_sizeof($data)) {
		$info = array_merge($info, $data);
	}

	form_start('package.php', 'form_id');

	html_start_box(__('Available Templates [%s]', $export_types{get_nfilter_request_var('export_type')}['name']), '100%', '', '3', 'center', '');

	$package_form = array(
		'spacer0' => array(
			'method' => 'spacer',
			'friendly_name' => __('Available Templates'),
		),
		'export_item_id' => array(
			'method' => 'drop_sql',
			'friendly_name' => __('%s to Export', $export_types{get_nfilter_request_var('export_type')}['name']),
			'description' => __('Choose the exact items to export in the Package.'),
			'value' => (isset_request_var('export_item_id') ? get_filter_request_var('export_item_id'):'|arg1:export_item_id|'),
			'sql' => $export_types[get_nfilter_request_var('export_type')]['dropdown_sql']
		),
		'include_deps' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Include Dependencies'),
			'description' => __('Some templates rely on other items in Cacti to function properly. It is highly recommended that you select this box or the resulting import may fail.'),
			'value' => 'on',
			'sql' => $export_types[get_nfilter_request_var('export_type')]['dropdown_sql']
		),
		'spacer1' => array(
			'method' => 'spacer',
			'friendly_name' => __('Package Information'),
		),
		'description' => array(
			'method' => 'textbox',
			'friendly_name' => __('Description'),
			'description' => __('The Package Description.'),
			'value' => (isset($info['description']) ? $info['description']:read_config_option('package_description', true)),
			'max_length' => '255',
			'size' => '80'
		),
		'copyright' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Copyright'),
			'description' => __('The license type for this package.'),
			'value' => (isset($info['copyright']) ? $info['copyright']:'GNU General Public License'),
			'array' => array(
				'Apache License 2.0'                 => __('Apache License 2.0'),
				'Creative Commons'                   => __('Creative Commons'),
				'GNU General Public License'         => __('GNU General Public License'),
				'MIT License'                        => __('MIT License'),
				'Eclipse Public License version 2.0' => __('Eclipse Public License version 2.0'),
			),
			'default' => 'GNU General Public License'
		),
		'version' => array(
			'method' => 'textbox',
			'friendly_name' => __('Version'),
			'description' => __('The version number to publish for this Package.'),
			'value' => (isset($info['version']) ? $info['version']:read_config_option('package_version', true)),
			'max_length' => '10',
			'size' => '10'
		),
		'class' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Class'),
			'description' => __('The Classification of the Package.'),
			'value' => (isset($info['class']) ? $info['class']:read_config_option('package_class', true)),
			'array' => $classes,
			'default' => 'unassigned'
		),
		'tags' => array(
			'method' => 'textarea',
			'friendly_name' => __('Tags'),
			'description' => __('Assign various searchable attributes to the Package.'),
			'value' => (isset($info['tags']) ? $info['tags']:read_config_option('package_tags', true)),
			'textarea_rows' => '2',
			'textarea_cols' => '80'
		),
		'installation' => array(
			'method' => 'textarea',
			'friendly_name' => __('Installation Instructions'),
			'description' => __('Some Packages require additional changes outside of Cacti\'s scope such as setting up an SNMP Agent Extension on the Devices to be monitored.  You should add those instructions here..'),
			'value' => (isset($info['installation']) ? $info['installation']:read_config_option('package_installation', true)),
			'textarea_rows' => '5',
			'textarea_cols' => '80'
		),
		'spacer2' => array(
			'method' => 'spacer',
			'friendly_name' => __('Author Information'),
		),
		'author' => array(
			'method' => 'other',
			'friendly_name' => __('Author Name'),
			'description' => __('The Registered Authors Name.'),
			'value' => $info['author'],
			'max_length' => '40',
			'size' => '40'
		),
		'homepage' => array(
			'method' => 'other',
			'friendly_name' => __('Homepage'),
			'description' => __('The Registered Authors Home Page.'),
			'value' => $info['homepage'],
			'max_length' => '60',
			'size' => '60'
		),
		'email' => array(
			'method' => 'other',
			'friendly_name' => __('Email Address'),
			'description' => __('The Registered Authors Email Address.'),
			'value' => $info['email'],
			'max_length' => '60',
			'size' => '60'
		),
		'export_type' => array(
			'method' => 'hidden',
			'value' => get_nfilter_request_var('export_type')
		)
	);

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $package_form
		)
	);

	html_end_box();

	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<input type='hidden' id='name' name='name' value='<?php print $detail['name'];?>'>
				<input class='export' type='submit' value='<?php print __('Package');?>'>
			</td>
		</tr>
	</table>
	</form>
	<script type='text/javascript'>
	var stopTimer = null;

	$(function() {
		$('#export_type').change(function() {
			strURL  = urlPath+'package.php';
			strURL += '?header=false';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&author='+$('#author').val();
			strURL += '&homepage='+escape($('#homepage').val());
			strURL += '&email='+$('#email').val();
			strURL += '&description='+escape($('#description').val());
			strURL += '&version='+escape($('#version').val());
			loadPageNoHeader(strURL);
		});

		$('#export_item_id').change(function() {
			strURL  = urlPath+'package.php';
			strURL += '?header=false';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&export_item_id='+$('#export_item_id').val();
			strURL += '&author='+$('#author').val();
			strURL += '&homepage='+escape($('#homepage').val());
			strURL += '&email='+$('#email').val();
			strURL += '&description='+escape($('#description').val());
			strURL += '&version='+escape($('#version').val());
			loadPageNoHeader(strURL);
		});

		if ($('#details').length) {
			strURL  = urlPath+'package.php';
			strURL += '?action=get_contents';
			strURL += '&export_type='+$('#export_type').val();
			strURL += '&export_item_id='+$('#export_item_id').val();
			strURL += '&include_deps='+$('#include_deps').is(':checked');
			$.get(strURL, function(data) {
				$('#details').html(data);
				$('#name').val($('#export_item_id option:selected').text());
			});
		}

		$('form#form_id').submit(function(event) {
			stopTimer = setTimeout(function() { Pace.stop() }, 1000);
		});

		if ($('#name').val() == '') {
			$('#name').val($('#export_item_id option:selected').text());
		}
	});
	</script>
	<?php

	html_start_box(__('Package Contents Include'), '100%', '', '3', 'center', '');

	if (isset_request_var('export_type') && isset_request_var('export_item_id')) {
		print get_package_contents(get_request_var('export_type'), get_request_var('export_item_id'));
	} else {
		print '<div id="details" style="vertical-align:top">';
		print '</div>';
	}

	html_end_box();
}

