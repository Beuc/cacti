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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');
include_once('./lib/api_aggregate.php');
include_once('./lib/api_automation.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_form_template.php');
include_once('./lib/data_query.php');
include_once('./lib/html_graph.php');
include_once('./lib/html_tree.php');
include_once('./lib/ping.php');
include_once('./lib/poller.php');
include_once('./lib/reports.php');
include_once('./lib/rrd.php');
include_once('./lib/snmp.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$actions = array(
	1 => __('Add Device'),
	2 => __('Delete Device')
);

$os_arr = array_rekey(db_fetch_assoc('SELECT DISTINCT os
	FROM automation_devices
	WHERE os IS NOT NULL AND os!=""'), 'os', 'os');

$status_arr = array(
	__('Down'),
	__('Up')
);

$networks = array_rekey(db_fetch_assoc('SELECT an.id, an.name
	FROM automation_networks AS an
	INNER JOIN automation_devices AS ad
	ON an.id=ad.network_id
	ORDER BY name'), 'id', 'name');

set_default_action();

switch(get_request_var('action')) {
	case 'purge':
		purge_discovery_results();

		break;
	case 'actions':
		form_actions();

		break;
	case 'export':
		export_discovery_results();

		break;

	default:
		display_discovery_page();

		break;
}

function form_actions() {
	global $actions, $availability_options;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* add to cacti */
				$i = 0;

				foreach ($selected_items as $id) {
					$d                        = db_fetch_row_prepared('SELECT * FROM automation_devices WHERE id = ?', array($id));
					$d['poller_id']           = get_filter_request_var('poller_id');
					$d['host_template']       = get_filter_request_var('host_template');
					$d['availability_method'] = get_filter_request_var('availability_method');
					$d['notes']               = __('Added manually through device automation interface.');
					$d['snmp_sysName']        = $d['sysName'];

					// pull ping options from network_id
					$n = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', array($d['network_id']));

					if (cacti_sizeof($n)) {
						$d['ping_method']  = $n['ping_method'];
						$d['ping_port']    = $n['ping_port'];
						$d['ping_timeout'] = $n['ping_timeout'];
						$d['ping_retries'] = $n['ping_retries'];
					}

					$host_id     = automation_add_device($d, true);
					$description = (trim($d['hostname']) != '' ? $d['hostname'] : $d['ip']);

					if ($host_id) {
						raise_message('automation_msg_' . $i, __esc('Device %s Added to Cacti', $description), MESSAGE_LEVEL_INFO);
					} else {
						raise_message('automation_msg_' . $i, __esc('Device %s Not Added to Cacti', $description), MESSAGE_LEVEL_ERROR);
					}

					$i++;
				}
			} elseif (get_nfilter_request_var('drp_action') == 2) { /* remove device */
				foreach ($selected_items as $id) {
					db_execute_prepared('DELETE FROM automation_devices WHERE id = ?', array($id));
				}

				raise_message('automation_remove', __('Devices Removed from Cacti Automation database'), MESSAGE_LEVEL_INFO);
			}
		}

		header('Location: automation_devices.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = array();

		/* default variables */
		$pollers        = array();
		$host_templates = array();
		$poller_id      = 0;

		$availability_method = 0;
		$host_template       = 0;

		/* loop through each of the graphs selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT CONCAT(IF(hostname!="", hostname, "unknown"), " (", ip, ")") FROM automation_devices WHERE id = ?', array($matches[1]))) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray) && get_request_var('drp_action') == '1') { /* add */
			$pollers = array_rekey(
				db_fetch_assoc_prepared('SELECT id, name
					FROM poller
					ORDER BY name'),
				'id', 'name'
			);

			$host_templates = array_rekey(
				db_fetch_assoc_prepared('SELECT id, name
					FROM host_template
					ORDER BY name'),
				'id', 'name'
			);

			$poller_id = db_fetch_cell_prepared('SELECT id FROM poller WHERE disabled = "" LIMIT 1');

			if (empty($poller_id)) {
				$poller_id = $pollers[0]['id'];
			}

			$devices = db_fetch_assoc('SELECT id, sysName, sysDescr
				FROM automation_devices
				WHERE id IN (' . implode(',', $iarray) . ')');

			foreach ($devices as $device) {
				$os = automation_find_os($device['sysDescr'], '', $device['sysName']);

				if (isset($os['host_template']) && $os['host_template'] > 0) {
					if ($host_template == 0) {
						$host_template       = $os['host_template'];
						$availability_method = $os['availability_method'];
					} elseif ($host_template != $os['host_template']) {
						$host_template       = 0;
						$availability_method = 0;

						break;
					}
				} else {
					$host_template       = 0;
					$availability_method = 0;

					break;
				}
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'automation_devices.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Add the following Discovered Device to Cacti.'),
					'pmessage' => __('Click \'Continue\' to Add the following Discovered Devices to Cacti.'),
					'scont'    => __('Add Discovered Device'),
					'pcont'    => __('Add Discovered Devices'),
					'extra'    => array(
						'poller_id' => array(
							'method'  => 'drop_array',
							'title'   => __('Poller'),
							'array'   => $pollers,
							'default' => $poller_id,
							'name'    => 'name',
							'id'      => 'id',
						),
						'host_template' => array(
							'method'  => 'drop_array',
							'title'   => __('Device Template'),
							'array'   => $host_templates,
							'default' => $host_template,
							'name'    => 'name',
							'id'      => 'id',
						),
						'availability_method' => array(
							'method'  => 'drop_array',
							'title'   => __('Availability Method'),
							'array'   => $availability_options,
							'default' => $availability_method,
							'name'    => 'name',
							'id'      => 'id',
						),
					)
				),
				2 => array(
					'smessage' => __('Click \'Continue\' to Remove the following Discovered Device.'),
					'pmessage' => __('Click \'Continue\' to Remove the following Discovered Devices.'),
					'scont'    => __('Remove Discovered Device'),
					'pcont'    => __('Remove Discovered Devices'),
				)
			)
		);

		form_continue_confirmation($form_data);
	}
}

function display_discovery_page() {
	global $item_rows, $os_arr, $status_arr, $networks, $actions;

	top_header();

	process_sanitize_draw_filter(true);

	$total_rows = 0;

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$results = get_discovery_results($total_rows, $rows);

	/* generate page list */
	$nav = html_nav_bar('automation_devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

	form_start('automation_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'host_id' => array(
			'display' => __('Imported Device'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'hostname' => array(
			'display' => __('Device Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'ip' => array(
			'display' => __('IP'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'network_id' => array(
			'display' => __('Network'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'sysName' => array(
			'display' => __('SNMP Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'sysLocation' => array(
			'display' => __('Location'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'sysContact' => array(
			'display' => __('Contact'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'sysDescr' => array(
			'display' => __('Description'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'os' => array(
			'display' => __('OS'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'time' => array(
			'display' => __('Uptime'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'snmp' => array(
			'display' => __('SNMP'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'up' => array(
			'display' => __('Status'),
			'align'   => 'right',
			'sort'    => 'ASC'
		),
		'mytime' => array(
			'display' => __('Last Check'),
			'align'   => 'right',
			'sort'    => 'DESC'
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$snmp_version        = read_config_option('snmp_version');
	$snmp_port           = read_config_option('snmp_port');
	$snmp_timeout        = read_config_option('snmp_timeout');
	$snmp_username       = read_config_option('snmp_username');
	$snmp_password       = read_config_option('snmp_password');
	$max_oids            = read_config_option('max_get_size');
	$ping_method         = read_config_option('ping_method');
	$availability_method = read_config_option('availability_method');

	$status = array("<span class='deviceDown'>" . __('Down') . '</span>',"<span class='deviceUp'>" . __('Up') . '</span>');

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			$description = get_device_description($host['host_id']);
			$network     = get_network_description($host['network_id']);

			form_alternate_row('line' . base64_encode($host['ip']), true);

			if ($host['hostname'] == '') {
				$host['hostname'] = __('Not Detected');
			}

			form_selectable_cell(filter_value($description, ''), $host['id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_selectable_cell(filter_value($host['ip'], get_request_var('filter')), $host['id']);
			form_selectable_cell(filter_value($network, ''), $host['id']);
			form_selectable_cell(filter_value(snmp_data($host['sysName']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysLocation']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysContact']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysDescr']), get_request_var('filter')), $host['id'], '', 'text-align:left;white-space:normal;');
			form_selectable_cell(filter_value(snmp_data($host['os']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(snmp_data(get_uptime($host)), $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['snmp']], $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['up']], $host['id'], '', 'text-align:right');
			form_selectable_cell(substr($host['mytime'],0,16), $host['id'], '', 'text-align:right');
			form_checkbox_cell($host['ip'], $host['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Devices Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($results)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();

	bottom_footer();
}

function get_device_description($id) {
	if ($id > 0) {
		$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($id));

		if (empty($description)) {
			return __('Removed from Cacti');
		} else {
			return $description;
		}
	} else {
		return __('Not In Cacti');
	}
}

function get_network_description($id) {
	if ($id > 0) {
		$description = db_fetch_cell_prepared('SELECT name FROM automation_networks WHERE id = ?', array($id));

		if (empty($description)) {
			return __('Removed from Cacti');
		} else {
			return $description;
		}
	} else {
		return __('Invalid Network');
	}
}

function get_discovery_results(&$total_rows = 0, $rows = 0, $export = false) {
	global $os_arr, $status_arr, $networks, $actions;

	$sql_where  = '';
	$status     = get_request_var('status');
	$network    = get_request_var('network');
	$snmp       = get_request_var('snmp');
	$os         = get_request_var('os');
	$filter     = get_request_var('filter');

	$sql_where  = '';
	$sql_params = array();

	if ($status != '-1') {
		$sql_where   .= 'WHERE up = ?';
		$sql_params[] = $status;
	}

	if ($network > 0) {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'network_id = ?';
		$sql_params[] = $network;
	}

	if ($snmp != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'snmp = ?';
		$sql_params[] = $snmp;
	}

	if ($os != '-1') {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'os= ?';
		$sql_params[] = $network;
	}

	if ($filter != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .
			'(hostname LIKE ? OR ip LIKE ? OR sysName LIKE ? OR sysDescr ? OR sysLocation ? OR sysContact LIKE ?)';

		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
	}

	if ($export) {
		return db_fetch_assoc_prepared("SELECT *
			FROM automation_devices
			$sql_where
			ORDER BY INET_ATON(ip)",
			$sql_params);
	} else {
		$total_rows = db_fetch_cell_prepared("SELECT
			COUNT(*)
			FROM automation_devices
			$sql_where",
			$sql_params);

		$page      = get_request_var('page');
		$sql_order = get_order_string();
		$sql_limit = ' LIMIT ' . ($rows * ($page - 1)) . ',' . $rows;

		$sql_query = "SELECT *,sysUptime snmp_sysUpTimeInstance, FROM_UNIXTIME(time) AS mytime
			FROM automation_devices
			$sql_where
			$sql_order
			$sql_limit";

		return db_fetch_assoc_prepared($sql_query, $sql_params);
	}
}

function create_filter() {
	global $item_rows, $os_arr, $status_arr, $networks;

	$any          = array(-1 => __('Any'));
	$networks_arr = $any + $networks;
	$status_arr   = $any + $status_arr;
	$os_arr       = $any + $os_arr;

	return array(
		'rows' => array(
			array(
				'filter' => array(
					'method'        => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				),
				'network' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Network'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $networks_arr,
					'value'         => '-1'
				)
			),
			array(
				'status' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status_arr,
					'value'         => '-1'
				),
				'os' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('OS'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $os_arr,
					'value'         => '-1'
				),
				'snmp' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('SNMP'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status_arr,
					'value'         => '-1'
				),
				'rows' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Devices'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				)
			)
		),
		'buttons' => array(
			'go' => array(
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			),
			'clear' => array(
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			),
			'export' => array(
				'method'  => 'button',
				'display' => __('Export'),
				'action'  => 'default',
				'title'   => __('Export the Discovered Devices to CSV'),
			),
			'purge' => array(
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge the Discovered Devices from the Database'),
			)
		),
		'sort' => array(
			'sort_column'    => 'hostname',
			'sort_direction' => 'ASC'
		)
	);
}

function process_sanitize_draw_filter($render = false) {
	global $item_rows, $filters, $os_arr, $status_arr, $networks, $actions;

	$filters = create_filter();

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Discovered Devices'), 'automation_devices.php', 'form_devices', 'sess_autom_device');

	$pageFilter->rows_label = __('Devices');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function export_discovery_results() {
	process_sanitize_draw_filter(false);

	$results = get_discovery_results($total_rows, 0, true);

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=discovery_results.csv');
	print "Host,IP,System Name,System Location,System Contact,System Description,OS,Uptime,SNMP,Status\n";

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			if ($host['sysUptime'] != 0) {
				$days   = intval($host['sysUptime'] / 8640000);
				$hours  = intval(($host['sysUptime'] - ($days * 8640000)) / 360000);
				$uptime = $days . ' days ' . $hours . ' hours';
			} else {
				$uptime = '';
			}

			foreach ($host as $h=>$r) {
				$host['$h'] = str_replace(',','',$r);
			}
			print($host['hostname'] == '' ? __('Not Detected'):$host['hostname']) . ',';
			print $host['ip'] . ',';
			print export_data($host['sysName']) . ',';
			print export_data($host['sysLocation']) . ',';
			print export_data($host['sysContact']) . ',';
			print export_data($host['sysDescr']) . ',';
			print export_data($host['os']) . ',';
			print export_data($uptime) . ',';
			print($host['snmp'] == 1 ? __('Up'):__('Down')) . ',';
			print($host['up'] == 1 ? __('Up'):__('Down')) . "\n";
		}
	}
}

function purge_discovery_results() {
	get_filter_request_var('network');

	if (get_request_var('network') > 0) {
		db_execute_prepared('DELETE FROM automation_devices WHERE network_id = ?', array(get_request_var('network')));
	} else {
		db_execute('TRUNCATE TABLE automation_devices');
	}

	header('Location: automation_devices.php');

	exit;
}

function snmp_data($item) {
	if ($item == '') {
		return __('N/A');
	} else {
		return html_escape(str_replace(':',' ', $item));
	}
}

function export_data($item) {
	if ($item == '') {
		return 'N/A';
	} else {
		return $item;
	}
}
