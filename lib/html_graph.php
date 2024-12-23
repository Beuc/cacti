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

/**
 * Initializes the session variables for real-time data step and window.
 *
 * This function checks if the session variables 'sess_realtime_dsstep' and 'sess_realtime_window'
 * are set. If they are not set, it initializes them with the values from the configuration options
 * 'realtime_interval' and 'realtime_gwindow' respectively.
 *
 * @return void
 */
function initialize_realtime_step_and_window() {
	if (!isset($_SESSION['sess_realtime_dsstep'])) {
		$_SESSION['sess_realtime_dsstep'] = read_config_option('realtime_interval');
	}

	if (!isset($_SESSION['sess_realtime_window'])) {
		$_SESSION['sess_realtime_window'] = read_config_option('realtime_gwindow');
	}
}

/**
 * Sets the default graph action based on user settings and permissions.
 *
 * This function checks if a request variable 'action' is set. If not, it sets up a default action
 * based on the user's settings and permissions. The function prioritizes the following actions:
 * 'tree', 'list', and 'preview', in that order. If none of these actions are allowed it attempts
 * to find the first action that the user has permission to.  If it can not find one of these
 * the user is actually in an area that they do not have permission to, so we raise a message.
 *
 * The function leverages the session sess_graph_view_action to remember the last page that
 * the user visited.  There are only three good values here: tree, preview, and list.
 *
 * @return void
 */
function set_default_graph_action() {
	/* go through the settings and create a default */
	$modes = array(
		'tree'    => array('permission' => 'show_tree',    'id' => '1'),
		'preview' => array('permission' => 'show_preview', 'id' => '3'),
		'list'    => array('permission' => 'show_list',    'id' => '2'),
	);

	if (isset_request_var('action')) {
		$action = get_nfilter_request_var('action');
	} else {
		$action = '';
	}

	if ($action == '') {
		/* setup the default action */
		if (!isset($_SESSION['sess_graph_view_action'])) {

			$user_setting = read_user_setting('default_view_mode');
			$good_mode    = '';

			/* check the defaults */
			foreach($modes as $action => $info) {
				if (is_view_allowed($info['permission']) && $user_setting == $info['id']) {
					$good_mode = $action;
					set_request_var('action', $action);
					$_SESSION['sess_graph_view_action'] = $good_mode;
					break;
				}
			}

			if ($good_mode == '') {
				foreach($modes as $action => $info) {
					if (is_view_allowed($info['permission'])) {
						$good_mode = $action;
						set_request_var('action', $action);
						$_SESSION['sess_graph_view_action'] = $good_mode;
						break;
					}
				}
			}

			if ($good_mode == '') {
				raise_message('no_mode', __('Your User account does not have access to any Graph data'), MESSAGE_LEVEL_ERROR);
			}
		} elseif (in_array($_SESSION['sess_graph_view_action'], array_keys($modes), true)) {
			if (is_view_allowed('show_' . $_SESSION['sess_graph_view_action'])) {
				set_request_var('action', $_SESSION['sess_graph_view_action']);
			}
		}
	} elseif (in_array($action, array('get_node', 'tree_content'), true)) {
		$_SESSION['sess_graph_view_action'] = 'tree';
	} elseif (in_array($action, array_keys($modes), true)) {
		if (is_view_allowed('show_' . $action)) {
			$_SESSION['sess_graph_view_action'] = $action;
		}
	}
}

function create_preview_filter() {
	global $item_rows;

	$all     = array('-1'   => __('All'));
	$any     = array('-1'   => __('Any'));
	$none    = array('0'    => __('None'));

	$sites   = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites   = $any + $sites;

	$locations = array_rekey(
		db_fetch_assoc('SELECT DISTINCT location
			FROM host
			ORDER BY location'),
		'location', 'location'
	);
	$locations = $any + $none + $locations;

	/* unset the ordering if we have a setup that does not support ordering */
	if (isset_request_var('graph_template_id')) {
		if (strpos(get_nfilter_request_var('graph_template_id'), ',') !== false || get_nfilter_request_var('graph_template_id') <= 0) {
			set_request_var('graph_order', '');
			set_request_var('graph_source', '');
		}
	}

	$host_id = get_filter_request_var('host_id');

	if ($host_id > 0) {
		$hostname = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			array($host_id));
	} elseif ($host_id == '') {
		$host_id  = '-1';
		$hostname = __('Any');
	} elseif ($host_id == 0) {
		$host_id  = '0';
		$hostname = __('None');
	} else {
		$host_id  = '-1';
		$hostname = __('Any');
	}

	if ($host_id == 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=0', 'name', '', $total_rows);
	} elseif ($host_id > 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=' . $host_id, 'name', '', $total_rows);
	} else {
		$templates = get_allowed_graph_templates_normalized('', 'name', '', $total_rows);
	}

	$normalized_templates = array();
	if (cacti_sizeof($templates)) {
		foreach($templates as $t) {
			$normalized_templates[$t['id']] = $t['name'];
		}
	}

	$normalized_templates = $all + $none + $normalized_templates;

	$columns = array(
		'1' => __('%d Column', 1),
		'2' => __('%d Columns', 2),
		'3' => __('%d Columns', 3),
		'4' => __('%d Columns', 4),
		'5' => __('%d Columns', 5),
		'6' => __('%d Columns', 6)
	);

	$metrics_array = html_graph_order_filter_array();

	if (isset_request_var('business_hours')) {
		$business_hours = get_nfilter_request_var('business_hours');
	} else {
		$business_hours = read_user_setting('show_business_hours') == 'on' ? 'true':'false';
	}

	if (isset_request_var('thumbnails')) {
		$thumbnails = get_nfilter_request_var('thumbnails');
	} else {
		$thumbnails = read_user_setting('thumbnail_section_preview') == 'on' ? 'true':'false';
	}

	$filters = array(
		'rows' => array(
			array(
				'site_id' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Site'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $sites,
					'value'          => '-1'
				),
				'location' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Location'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => '-1',
					'pageset'        => true,
					'array'         => $locations,
					'value'          => '-1'
				),
				'host_id' => array(
					'method'         => 'drop_callback',
					'friendly_name'  => __('Device'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'request_vars'   => 'location,site_id',
					'sql'            => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'         => 'ajax_hosts',
					'id'             => $host_id,
					'value'          => $hostname,
					'on_change'      => 'applyGraphFilter()'
				),
			),
			array(
				'graph_template_id' => array(
					'method'         => 'drop_multi',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(cg_[0-9]|dq_[0-9]|[\-0-9])')),
					'default'        => '-1',
					'dynamic'        => false,
					'class'          => 'graph-multiselect',
					'pageset'        => true,
					'array'          => $normalized_templates,
					'value'          => get_nfilter_request_var('graph_template_id')
				),
			),
			array(
				'graphs' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Graphs'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => ''
				),
				'columns' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Columns'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => read_user_setting('num_columns', '2'),
					'pageset'       => true,
					'array'         => $columns,
					'value'         => read_user_setting('num_columns', '2')
				),
				'thumbnails' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Thumbnails'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_user_setting('thumbnail_section_preview') == 'on' ? 'true':'false',
					'value'          => $thumbnails
				),
				'business_hours' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Business Hours'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_user_setting('show_business_hours') == 'on' ? 'true':'false',
					'value'          => $business_hours
				)
			),
			array(
				'rfilter' => array(
					'method'        => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				)
			),
			array(
				'timespan' => array(
					'method'         => 'timespan',
					'refresh'        => true,
					'clear'          => true,
					'shifter'        => true,
				),
				'graph_list' => array(
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_IS_NUMERIC_LIST,
					'default'        => ''
				),
				'graph_add' => array(
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_IS_NUMERIC_LIST,
					'default'        => ''
				),
				'graph_remove' => array(
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_IS_NUMERIC_LIST,
					'default'        => ''
				),
				'style' => array(
					'method'         => 'validate',
					'filter'         => FILTER_DEFAULT,
					'default'        => ''
				)
			)
		),
		'buttons' => array(
			'go' => array(
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
				'callback' => 'applyGraphFilter()'
			),
			'clear' => array(
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
				'callback' => 'clearGraphFilter()'
			)
		)
	);

	if (cacti_sizeof($metrics_array)) {
		$filters['rows'][1] += $metrics_array;
	}

	if (is_view_allowed('graph_settings')) {
		$filters['buttons']['save'] = array(
			'method'  => 'button',
			'display' => __('Save'),
			'title'   => __('Save filter to the database'),
			'callback' => 'saveGraphFilter("preview")'
		);
	}

	return $filters;
}

function process_sanitize_draw_preview_filter($render = false, $page = '', $action = 'get') {
	$header = __('Graph Preview Filters') . (isset_request_var('style') && get_request_var('style') != '' ? ' ' . __('[ Custom Graph List Applied - Filtering from List ]'):'');

	/* create the page filter */
	$filters    = create_preview_filter();
	$pageFilter = new CactiTableFilter($header, $page, 'form_graph_view', 'sess_pview');
	$pageFilter->rows_label  = __('Graphs');
	$pageFilter->form_method = $action;
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function inject_realtime_form() {
	print "<div class='filterTable'>
		<div id='realtime' class='filterRow' style='display:none;'>
			<div class='filterColumn'>" . __('Window') . "</div>
			<div class='filterColumn'>
				<select name='graph_start' id='graph_start' onChange='realtimeGrapher()' data-defaultLabel='" . __('Window') . "'>";

				foreach ($realtime_window as $interval => $text) {
					printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_window'] ? 'selected="selected"' : '', $text);
				}

				print "</select>
			</div>
			<div class='filterColumn'>" . __('Interval') . "</div>
			<div class='filterColumn'>
				<select name='ds_step' id='ds_step' onChange='realtimeGrapher()' data-defaultLabel='" . __('Interval') . "'>";

				foreach ($realtime_refresh as $interval => $text) {
					printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
				}
				print "</select>
			</div>
			<div class='filterColumn'>
				<input type='button' class='ui-button ui-corner-all ui-widget' id='realtimeoff' value='" . __esc('Stop') . "'>
			</div>
			<div class='filterColumn center'>
				<span id='countdown'></span>
			</div>
			<div class='filterColumn'>
				<input id='future' type='hidden' value='" . read_config_option('allow_graph_dates_in_future') . "'></input>
			</div>
		</div>
	</div>";
}

/**
 * Generates the HTML for the graph preview filter form.
 *
 * This function creates a form that allows users to filter and preview graphs based on various criteria such as site, location, host, template, and time span.
 *
 * @param string $page The current page URL.
 * @param string $action The action to be performed on form submission.
 * @param string $devices_where SQL condition for filtering devices (optional).
 * @param string $templates_where SQL condition for filtering templates (optional).
 *
 * @global array $graphs_per_page Array of graphs per page options.
 * @global array $realtime_window Array of real-time window options.
 * @global array $realtime_refresh Array of real-time refresh interval options.
 * @global array $graph_timeshifts Array of graph time shift options.
 * @global array $graph_timespans Array of graph time span options.
 * @global array $config Configuration settings.
 *
 * @return void
 */
function html_graph_preview_filter($page, $action, $devices_where = '', $templates_where = '') {
	global $graphs_per_page, $realtime_window, $realtime_refresh, $graph_timeshifts, $graph_timespans, $config;

	initialize_realtime_step_and_window();

	process_sanitize_draw_preview_filter(true, $page, $action);

	?>
	<script type='text/javascript'>

   	var refreshIsLogout = false;
	var refreshMSeconds = <?php print read_user_setting('page_refresh') * 1000;?>;
	var graph_start     = <?php print get_current_graph_start();?>;
	var graph_end       = <?php print get_current_graph_end();?>;
	var timeOffset      = <?php print date('Z');?>;
	var pageAction      = '<?php print $action;?>';
	var graphPage       = '<?php print $page;?>';
	var date1Open       = false;
	var date2Open       = false;

	function initPage() {
		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			} else {
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
					$('#date2').datetimepicker('hide');
			} else {
				date2Open = true;
				$('#date2').datetimepicker('show');
			}
		});

		$('#date1').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#date2').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});
	}

	$(function() {
		$.when(initPage()).done(function() {
			initializeGraphs();
		});
	});

	</script>
	<?php

	html_spikekill_js();
}

/**
 * Generates new graphs for a given host and host template.
 *
 * This function processes the selected graphs array and generates the corresponding graphs
 * for the specified host and host template. If no fields are drawn on the form, it saves
 * the graphs without prompting the user.
 *
 * @param string $page The page URL to redirect to after saving the graphs.
 * @param int $host_id The ID of the host for which the graphs are being generated.
 * @param int $host_template_id The ID of the host template used for generating the graphs.
 * @param array $selected_graphs_array An array of selected graphs to be generated.
 */
function html_graph_new_graphs($page, $host_id, $host_template_id, $selected_graphs_array) {
	$snmp_query_id     = 0;
	$num_output_fields = array();
	$output_started    = false;

	foreach ($selected_graphs_array as $form_type => $form_array) {
		foreach ($form_array as $form_id1 => $form_array2) {
			ob_start();

			$count = html_graph_custom_data($host_id, $host_template_id, $snmp_query_id, $form_type, $form_id1, $form_array2);

			if (array_sum($count)) {
				if (!$output_started) {
					$output_started = true;

					top_header();
				}

				ob_end_flush();
			} else {
				ob_end_clean();
			}
		}
	}

	/* no fields were actually drawn on the form; just save without prompting the user */
	if (!$output_started) {
		/* since the user didn't actually click "Create" to POST the data; we have to
		pretend like they did here */
		set_request_var('save_component_new_graphs', '1');
		set_request_var('selected_graphs_array', serialize($selected_graphs_array));

		host_new_graphs_save($host_id);

		header('Location: ' . $page . '?host_id=' . $host_id);

		exit;
	}

	form_hidden_box('host_template_id', $host_template_id, '0');
	form_hidden_box('host_id', $host_id, '0');
	form_hidden_box('save_component_new_graphs', '1', '');
	form_hidden_box('selected_graphs_array', serialize($selected_graphs_array), '');

	if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'graphs_new') === false) {
		set_request_var('returnto', basename($_SERVER['HTTP_REFERER']));
	}

	load_current_session_value('returnto', 'sess_grn_returnto', '');

	form_save_button(get_nfilter_request_var('returnto'));

	bottom_footer();
}

/**
 * Generates custom HTML form data for graph creation based on the provided parameters.
 *
 * @param int    $host_id           The ID of the host.
 * @param int    $host_template_id  The ID of the host template.
 * @param int    $snmp_query_id     The ID of the SNMP query.
 * @param string $form_type         The type of form ('cg' for graph template, 'sg' for SNMP query).
 * @param int    $form_id1          The ID of the form element.
 * @param array  $form_array2       An array of form elements.
 *
 * @return array An array of output fields for the form.
 */
function html_graph_custom_data($host_id, $host_template_id, $snmp_query_id, $form_type, $form_id1, $form_array2) {
	/* ================= input validation ================= */
	input_validate_input_number($form_id1, 'form_id1');
	/* ==================================================== */

	$num_output_fields = array();
	$display           = false;

	if ($form_type == 'cg') {
		$graph_template_id   = $form_id1;
		$graph_template_name = db_fetch_cell_prepared('SELECT name
			FROM graph_templates
			WHERE id = ?',
			array($graph_template_id));

		if (graph_template_has_override($graph_template_id)) {
			$display = true;
			$header  = __('Create Graph from %s', html_escape($graph_template_name));
		}
	} elseif ($form_type == 'sg') {
		foreach ($form_array2 as $form_id2 => $form_array3) {
			/* ================= input validation ================= */
			input_validate_input_number($snmp_query_id, 'snmp_query_id');
			input_validate_input_number($form_id2, 'form_id2');
			/* ==================================================== */

			$snmp_query_id       = $form_id1;
			$snmp_query_graph_id = $form_id2;
			$num_graphs          = cacti_sizeof($form_array3);

			$snmp_query = db_fetch_cell_prepared('SELECT name
				FROM snmp_query
				WHERE id = ?',
				array($snmp_query_id));

			$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
				FROM snmp_query_graph
				WHERE id = ?',
				array($snmp_query_graph_id));
		}

		if (graph_template_has_override($graph_template_id)) {
			$display = true;

			if ($num_graphs > 1) {
				$header = __('Create %s Graphs from %s', $num_graphs, html_escape($snmp_query));
			} else {
				$header = __('Create Graph from %s', html_escape($snmp_query));
			}
		}
	}

	if ($display) {
		form_start('graphs_new.php', 'new_graphs');

		html_start_box($header, '100%', '', '3', 'center', '');
	}

	/* ================= input validation ================= */
	input_validate_input_number($graph_template_id, 'graph_template_id');
	/* ==================================================== */

	$data_templates = db_fetch_assoc_prepared('SELECT
		data_template.name AS data_template_name,
		data_template_rrd.data_source_name,
		data_template_data.*
		FROM (data_template, data_template_rrd, data_template_data, graph_templates_item)
		WHERE graph_templates_item.task_item_id = data_template_rrd.id
		AND data_template_rrd.data_template_id = data_template.id
		AND data_template_data.data_template_id = data_template.id
		AND data_template_rrd.local_data_id = 0
		AND data_template_data.local_data_id = 0
		AND graph_templates_item.local_graph_id = 0
		AND graph_templates_item.graph_template_id = ?
		GROUP BY data_template.id
		ORDER BY data_template.name',
		array($graph_template_id));

	$graph_template = db_fetch_row_prepared('SELECT gt.name AS graph_template_name, gtg.*
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gt.id = gtg.graph_template_id
		WHERE gt.id = ?
		AND gtg.local_graph_id = 0',
		array($graph_template_id));

	array_push($num_output_fields, draw_nontemplated_fields_graph($graph_template_id, $graph_template, "g_$snmp_query_id" . '_' . $graph_template_id . '_|field|', __('Graph [Template: %s]', html_escape($graph_template['graph_template_name'])), true, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

	array_push($num_output_fields, draw_nontemplated_fields_graph_item($graph_template_id, 0, 'gi_' . $snmp_query_id . '_' . $graph_template_id . '_|id|_|field|', __('Graph Items [Template: %s]', html_escape($graph_template['graph_template_name'])), true));

	/* DRAW: Data Sources */
	if (cacti_sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			array_push($num_output_fields, draw_nontemplated_fields_data_source($data_template['data_template_id'], 0, $data_template, 'd_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|field|', __('Data Source [Template: %s]', html_escape($data_template['data_template_name'])), true, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

			$data_template_items = db_fetch_assoc_prepared('SELECT
				data_template_rrd.*
				FROM data_template_rrd
				WHERE data_template_rrd.data_template_id = ?
				AND local_data_id = 0',
				array($data_template['data_template_id']));

			array_push($num_output_fields, draw_nontemplated_fields_data_source_item($data_template['data_template_id'], $data_template_items, 'di_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|_|field|', '', true, false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
			array_push($num_output_fields, draw_nontemplated_fields_custom_data($data_template['id'], 'c_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|', __('Custom Data [Template: %s]', html_escape($data_template['data_template_name'])), true, false, $snmp_query_id));
		}
	}

	if ($display) {
		html_end_box(false);
	}

	return $num_output_fields;
}

function html_save_graph_settings() {
	if (is_view_allowed('graph_settings')) {
		get_filter_request_var('columns');
		get_filter_request_var('predefined_timespan');
		get_filter_request_var('predefined_timeshift');
		get_filter_request_var('graphs');
		get_filter_request_var('thumbnails', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '(true|false)')));
		get_filter_request_var('business_hours', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '(true|false)')));

		if (isset_request_var('predefined_timespan')) {
			set_user_setting('default_timespan', get_request_var('predefined_timespan'));
		}

		if (isset_request_var('predefined_timeshift')) {
			set_user_setting('default_timeshift', get_request_var('predefined_timeshift'));
		}

		if (isset_request_var('business_hours')) {
			set_user_setting('show_business_hours', get_request_var('business_hours') == 'true' ? 'on':'');
		}

		if (isset_request_var('section') && get_nfilter_request_var('section') == 'preview') {
			if (isset_request_var('columns')) {
				set_user_setting('num_columns', get_request_var('columns'));
			}

			if (isset_request_var('graphs')) {
				if (get_request_var('graphs') != '-1') {
					set_user_setting('preview_graphs_per_page', get_request_var('graphs'));
				}
			}

			if (isset_request_var('thumbnails')) {
				set_user_setting('thumbnail_section_preview', get_nfilter_request_var('thumbnails') == 'true' ? 'on':'');
			}
		} else {
			if (isset_request_var('columns')) {
				set_user_setting('num_columns_tree', get_request_var('columns'));
			}

			if (isset_request_var('graphs')) {
				if (get_request_var('graphs') != '-1') {
					set_user_setting('treeview_graphs_per_page', get_request_var('graphs'));
				}
			}

			if (isset_request_var('thumbnails')) {
				set_user_setting('thumbnail_section_tree', get_request_var('thumbnails') == 'true' ? 'on':'');
			}
		}
	}
}

function html_graph_preview_view() {
	global $is_request_ajax;

	if (!is_view_allowed('show_preview')) {
		header('Location: permission_denied.php');

		exit;
	}

	if (isset_request_var('external_id')) {
		$host_id = db_fetch_cell_prepared('SELECT id FROM host WHERE external_id = ?', array(get_nfilter_request_var('external_id')));

		if (!empty($host_id)) {
			set_request_var('host_id', $host_id);
			set_request_var('reset',true);
		}
	}

	top_graph_header();

	html_graph_preview_filter('graph_view.php', 'preview');

	api_plugin_hook_function('graph_tree_page_buttons',
		array(
			'mode'      => 'preview',
			'timespan'  => $_SESSION['sess_current_timespan'],
			'starttime' => get_current_graph_start(),
			'endtime'   => get_current_graph_end()
		)
	);

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	$sql_or = '';

	if (isset_request_var('style')) {
		if (get_request_var('style') == 'selective') {
			$graph_list = array();

			/* process selected graphs */
			if (!isempty_request_var('graph_list')) {
				foreach (explode(',', get_request_var('graph_list')) as $item) {
					if (is_numeric($item)) {
						$graph_list[$item] = 1;
					}
				}
			}

			if (!isempty_request_var('graph_add')) {
				foreach (explode(',', get_request_var('graph_add')) as $item) {
					if (is_numeric($item)) {
						$graph_list[$item] = 1;
					}
				}
			}

			/* remove items */
			if (!isempty_request_var('graph_remove')) {
				foreach (explode(',', get_request_var('graph_remove')) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;

			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (cacti_sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = array_to_sql_or($graph_array, 'gtg.local_graph_id');
			}
		}
	}

	$total_graphs = 0;

	/* create filter for sql */
	$sql_where  = '';

	if (!isempty_request_var('rfilter')) {
		$sql_where .= " gtg.title_cache RLIKE '" . get_request_var('rfilter') . "'";
	}

	$sql_where .= ($sql_or != '' && $sql_where != '' ? ' AND ':'') . $sql_or;

	if (!isempty_request_var('site_id') && get_request_var('site_id') > 0) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.site_id=' . get_request_var('site_id');
	} elseif (isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.site_id=0';
	}

	if (!isempty_request_var('host_id') && get_request_var('host_id') > 0) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=' . get_request_var('host_id');
	} elseif (isempty_request_var('host_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=0';
	}

	if (get_request_var('location') != '' && get_request_var('location') != '-1' && get_request_var('location') != '0') {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.location = ' . db_qstr(get_request_var('location'));
	} elseif (get_request_var('location') == '0') {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.location = ""';
	}

	if (!isempty_request_var('graph_template_id') && get_request_var('graph_template_id') != '-1' && get_request_var('graph_template_id') != '0') {
		$graph_templates = html_transform_graph_template_ids(get_request_var('graph_template_id'));

		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . $graph_templates . '))';
	} elseif (get_request_var('graph_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	}

	if (get_request_var('graphs') == '-1') {
		$graph_rows = read_user_setting('preview_graphs_per_page', read_config_option('preview_graphs_per_page', 20));
	} else {
		$graph_rows = get_request_var('graphs');
	}

	$sql_limit = ($graph_rows * (get_request_var('page') - 1)) . ',' . $graph_rows;

	if (read_config_option('dsstats_enable') == 'on' && get_request_var('graph_source') != '' && get_request_var('graph_order') != '') {
		$sql_order = array(
			'data_source' => get_request_var('graph_source'),
			'order'       => get_request_var('graph_order'),
			'start_time'  => get_current_graph_start(),
			'end_time'    => get_current_graph_end(),
			'cf'          => get_request_var('cf'),
			'measure'     => get_request_var('measure')
		);
	} else {
		$sql_order  = 'gtg.title_cache';
	}

	$graphs = get_allowed_graphs($sql_where, $sql_order, $sql_limit, $total_graphs);

	$nav = html_nav_bar('graph_view.php', MAX_DISPLAY_PAGES, get_request_var('page'), $graph_rows, $total_graphs, get_request_var('columns'), __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (get_request_var('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	} else {
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}

	html_end_box();

	if ($total_graphs) {
		print $nav;
	}

	if (!$is_request_ajax) {
		bottom_footer();
	}
}

function html_graph_list_view() {
	global $graph_timespans, $alignment, $graph_sources, $item_rows;

	if (!is_view_allowed('show_list')) {
		header('Location: permission_denied.php');

		exit;
	}

	/* reset the graph list on a new viewing */
	if (!isset_request_var('page')) {
		set_request_var('graph_list', '');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
		),
		'graph_template_id' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => '-1'
		),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'location' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
		),
		'graph_add' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'graph_list' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'graph_remove' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		)
	);

	validate_store_request_vars($filters, 'sess_gl');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* check to see if site_id and location are mismatched */
	if (get_request_var('site_id') >= 0) {
		if (get_request_var('location') != '0' && get_request_var('location') != '-1') {
			$exists = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM host
				WHERE site_id = ?
				AND location = ?',
				array(get_request_var('site_id'), get_request_var('location')));

			if (!$exists) {
				set_request_var('location', '-1');
			}
		}
	}

	$graph_list = array();

	/* save selected graphs into url */
	if (!isempty_request_var('graph_list')) {
		foreach (explode(',', get_request_var('graph_list')) as $item) {
			if (is_numeric($item)) {
				$graph_list[$item] = 1;
			}
		}
	}

	if (!isempty_request_var('graph_add')) {
		foreach (explode(',', get_request_var('graph_add')) as $item) {
			if (is_numeric($item)) {
				$graph_list[$item] = 1;
			}
		}
	}

	/* remove items */
	if (!isempty_request_var('graph_remove')) {
		foreach (explode(',', get_request_var('graph_remove')) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* update the revised graph list session variable */
	if (cacti_sizeof($graph_list)) {
		set_request_var('graph_list', implode(',', array_keys($graph_list)));
	}
	load_current_session_value('graph_list', 'sess_gl_graph_list', '');

	$reports = db_fetch_assoc_prepared('SELECT *
		FROM reports
		WHERE user_id = ?',
		array($_SESSION[SESS_USER_ID]));

	top_graph_header();

	form_start('graph_view.php', 'chk');

	/* display graph view filter selector */
	html_start_box(__('Graph List View Filters') . (isset_request_var('style') && get_request_var('style') != '' ? ' ' . __('[ Custom Graph List Applied - Filter FROM List ]'):''), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='55' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<?php html_host_filter(get_request_var('host_id'));?>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>' onClick='clearFilter()'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'>
							<?php if (cacti_sizeof($reports)) {?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='addreport' value='<?php print __esc('Report');?>' title='<?php print __esc('Add to a Report');?>'>
							<?php } ?>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<?php html_site_filter(get_request_var('site_id'));?>
					<?php

					if (get_request_var('site_id') >= 0) {
						$loc_where = 'WHERE site_id = ' . db_qstr(get_request_var('site_id'));
					} else {
						$loc_where = '';
					}

					html_location_filter(get_request_var('location'), 'applyFilter', $loc_where);
					?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' class='multi-select' data-defaultLabel='<?php print __('Template');?>'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('Not Templated');?></option>
							<?php

							// suppress total rows collection
							$total_rows = -1;

							$graph_templates = get_allowed_graph_templates('', 'name', '', $total_rows);

							if (cacti_sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));

								foreach ($graph_templates as $gt) {
									if ($gt['id'] != 0) {
										$found = db_fetch_cell_prepared('SELECT id
											FROM graph_local
											WHERE graph_template_id = ? LIMIT 1',
											array($gt['id']));

										if ($found) {
											print "<option value='" . $gt['id'] . "'";

											if (cacti_sizeof($selected)) {
												if (in_array($gt['id'], $selected, true)) {
													print ' selected';
												}
											}
											print '>';
											print html_escape($gt['name']) . "</option>\n";
										}
									}
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()' data-defaultLabel='<?php print __('Graphs');?>'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' id='style' value='selective'>
			<input type='hidden' id='action' value='list'>
			<input type='hidden' id='graph_add' value=''>
			<input type='hidden' id='graph_remove' value=''>
			<input type='hidden' id='graph_list' value='<?php print get_request_var('graph_list');?>'>
		</td>
	</tr>
	<?php
	html_end_box();

	/* create filter for sql */
	$sql_where  = '';

	if (!isempty_request_var('rfilter')) {
		$sql_where .= " gtg.title_cache RLIKE '" . get_request_var('rfilter') . "'";
	}

	if (!isempty_request_var('site_id') && get_request_var('site_id') > 0) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.site_id=' . get_request_var('site_id');
	} elseif (isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.site_id=0';
	}

	if (!isempty_request_var('host_id') && get_request_var('host_id') > 0) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=' . get_request_var('host_id');
	} elseif (isempty_request_var('host_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=0';
	}

	if (get_request_var('location') != '' && get_request_var('location') != '-1' && get_request_var('location') != '0') {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.location = ' . db_qstr(get_request_var('location'));
	} elseif (get_request_var('location') == '0') {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' h.location = ""';
	}

	if (!isempty_request_var('graph_template_id') && get_request_var('graph_template_id') != '-1' && get_request_var('graph_template_id') != '0') {
		$graph_templates = html_transform_graph_template_ids(get_request_var('graph_template_id'));

		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . $graph_templates . '))';
	} elseif (get_request_var('graph_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	}

	$total_rows = 0;
	$sql_limit  = ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', $sql_limit, $total_rows);

	$nav = html_nav_bar('graph_view.php?action=list', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (is_realm_allowed(10)) {
		$display_text = array(
			'title_cache' => array(
				'display' => __('Graph Name'),
				'align'   => 'left',
				'tip'     => __('The Title of this Graph.  Generally programmatically generated from the Graph Template definition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.')
			),
			'site_name' => array(
				'display' => __('Site Name'),
				'align'   => 'left',
				'tip'     => __('The Site Name for this Graph.')
			),
			'location' => array(
				'display' => __('Site Location'),
				'align'   => 'left',
				'tip'     => __('The Site Location for this Graph.')
			),
			'local_graph_id' => array(
				'display' => __('Device'),
				'align'   => 'left',
				'tip'     => __('The device for this Graph.')
			),
			'source' => array(
				'display' => __('Source Type'),
				'align'   => 'right',
				'tip'     => __('The underlying source that this Graph was based upon.')
			),
			'name' => array(
				'display' => __('Source Name'),
				'align'   => 'left',
				'tip'     => __('The Graph Template or Data Query that this Graph was based upon.')
			),
			'height' => array(
				'display' => __('Size'),
				'align'   => 'left',
				'tip'     => __('The size of this Graph when not in Preview mode.')
			)
		);
	} else {
		$display_text = array(
			'title_cache' => array(
				'display' => __('Graph Name'),
				'align'   => 'left',
				'tip'     => __('The Title of this Graph.  Generally programmatically generated from the Graph Template definition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.')
			),
			'height' => array(
				'display' => __('Size'),
				'align'   => 'left',
				'tip'     => __('The size of this Graph when not in Preview mode.')
			)
		);
	}

	html_header_checkbox($display_text, false);

	$i = 0;

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_details = get_graph_template_details($graph['local_graph_id']);

			if ($graph['graph_source'] == '0') { //Not Templated, customize graph source and template details.
				$template_details = api_plugin_hook_function('customize_template_details', $template_details);
				$graph            = api_plugin_hook_function('customize_graph', $graph);
			}

			if (isset($template_details['graph_name'])) {
				$graph['name'] = $template_details['graph_name'];
			}

			if (isset($template_details['graph_description'])) {
				$graph['description'] = $template_details['graph_description'];
			}

			$current_page = get_current_page();

			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell(filter_value($graph['title_cache'], get_request_var('rfilter'), $current_page . '?action=view&local_graph_id=' . $graph['local_graph_id'] . '&rra_id=0'), $graph['local_graph_id']);

			if (is_realm_allowed(10)) {
				if ($graph['site_name'] != '') {
					form_selectable_ecell($graph['site_name'], $graph['local_graph_id']);
				} else {
					form_selectable_ecell('-', $graph['local_graph_id']);
				}

				if ($graph['location'] != '') {
					form_selectable_ecell($graph['location'], $graph['local_graph_id']);
				} else {
					form_selectable_ecell('-', $graph['local_graph_id']);
				}

				form_selectable_ecell($graph['description'], $graph['local_graph_id']);
				form_selectable_cell(filter_value($graph_sources[$template_details['source']], get_request_var('rfilter')), $graph['local_graph_id'], '', 'right');
				form_selectable_cell(filter_value($template_details['name'], get_request_var('rfilter'), $template_details['url']), $graph['local_graph_id'], '', 'left');
			}

			form_selectable_ecell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($graphs)) {
		print $nav;
	}

	form_end();

	$report_text = '';

	if (cacti_sizeof($reports)) {
		$report_text = '<div id="addGraphs" style="display:none;">
		<p>' . __('Select the Report to add the selected Graphs to.') . '</p>
		<table class="cactiTable">';

		$report_text .= '<tr><td>' . __('Report Name') . '</td>';
		$report_text .= '<td><select id="report_id">';

		foreach ($reports as $report) {
			$report_text .= '<option value="' . $report['id'] . '">' . html_escape($report['name']) . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '<tr><td>' . __('Timespan') . '</td>';
		$report_text .= '<td><select id="timespan">';

		foreach ($graph_timespans as $key => $value) {
			$report_text .= '<option value="' . $key . '"' . ($key == read_user_setting('default_timespan') ? ' selected':'') . '>' . $value . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '<tr><td>' . __('Align') . '</td>';
		$report_text .= '<td><select id="align">';

		foreach ($alignment as $key => $value) {
			$report_text .= '<option value="' . $key . '"' . ($key == REPORTS_ALIGN_CENTER ? ' selected':'') . '>' . $value . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '</table></div>';
	}

	?>
	<div class='break'></div>
	<div class='cactiTable'>
		<div style='float:left'><img src='images/arrow.gif' alt=''>&nbsp;</div>
		<div style='float:right'><input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'></div>
	</div>
	<?php print $report_text;?>
	<script type='text/javascript'>
		refreshMSeconds=999999999;
		refreshFunction = 'refreshGraphs()';

		var graph_list_array = new Array(<?php print get_request_var('graph_list');?>);

		function clearFilter() {
			strURL = 'graph_view.php?action=list&clear=1';
			loadUrl({url:strURL})
		}

		function applyFilter() {
			strURL = 'graph_view.php?action=list&page=1';
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&location=' + $('#location').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&graph_template_id=' + $('#graph_template_id').val();
			strURL += '&rfilter=' + base64_encode($('#rfilter').val());
			strURL += url_graph('');
			loadUrl({url:strURL})
		}

		function initializeChecks() {
			for (var i = 0; i < graph_list_array.length; i++) {
				$('#line'+graph_list_array[i]).addClass('selected');
				$('#chk_'+graph_list_array[i]).prop('checked', true);
				$('#chk_'+graph_list_array[i]).parent().addClass('selected');
			}
		}

		function viewGraphs() {
			graphList = $('#graph_list').val();
			$('input[id^=chk_]').each(function(data) {
				graphID = $(this).attr('id').replace('chk_','');
				if ($(this).is(':checked')) {
					graphList += (graphList.length > 0 ? ',':'') + graphID;
				}
			});
			$('#graph_list').val(graphList);

			strURL = urlPath+'graph_view.php?action=preview';
			$('#chk').find('select, input').each(function() {
				switch($(this).attr('id')) {
					case 'rfilter':
						strURL += '&' + $(this).attr('id') + '=' + base64_encode($(this).val());
						break;
					case 'graph_template_id':
					case 'host_id':
					case 'graph_add':
					case 'graph_remove':
					case 'graph_list':
					case 'style':
					case 'csrf_magic':
						strURL += '&' + $(this).attr('id') + '=' + $(this).val();
						break;
					default:
						break;
				}
			});

			strURL += '&reset=true';

			loadUrl({url:strURL})

			$('#breadcrumbs').empty().html('<li><a href="graph_view.php?action=preview"><?php print __('Preview Mode');?></a></li>');
			$('#listview').removeClass('selected');
			$('#preview').addClass('selected');
		}

		function url_graph(strNavURL) {
			if ($('#action').val() == 'list') {
				var strURL = '';
				var strAdd = '';
				var strDel = '';
				$('input[id^=chk_]').each(function(data) {
					graphID = $(this).attr('id').replace('chk_','');
					if ($(this).is(':checked')) {
						strAdd += (strAdd.length > 0 ? ',':'') + graphID;
					} else if (graphChecked(graphID)) {
						strDel += (strDel.length > 0 ? ',':'') + graphID;
					}
				});

				strURL = '&demon=1&graph_list=<?php print get_request_var('graph_list');?>&graph_add=' + strAdd + '&graph_remove=' + strDel;

				return strNavURL + strURL;
			} else {
				return strNavURL;
			}
		}

		function graphChecked(graph_id) {
			for(var i = 0; i < graph_list_array.length; i++) {
				if (graph_list_array[i] == graph_id) {
					return true;
				}
			}

			return false;
		}

		function addReport() {
			$('#addGraphs').dialog({
				title: '<?php print __('Add Selected Graphs to Report');?>',
				minHeight: 80,
				minWidth: 400,
				modal: true,
				resizable: false,
				draggable: false,
				buttons: [
					{
						text: '<?php print __('Cancel');?>',
						click: function() {
							$(this).dialog('close');
						}
					},
					{
						text: '<?php print __('Ok');?>',
						click: function() {
							graphList = $('#graph_list').val();
							$('input[id^=chk_]').each(function(data) {
								graphID = $(this).attr('id').replace('chk_','');
								if ($(this).is(':checked')) {
									graphList += (graphList.length > 0 ? ',':'') + graphID;
								}
							});
							$('#graph_list').val(graphList);

							$(this).dialog('close');

							strURL = 'graph_view.php?action=ajax_reports' +
								'&header=false' +
								'&report_id='   + $('#report_id').val()   +
								'&timespan='    + $('#timespan').val()    +
								'&align='       + $('#align').val()       +
								'&graph_list='  + $('#graph_list').val();

							loadUrl({url:strURL});
						}
					}
				],
				open: function() {
					$('.ui-dialog').css('z-index', 99);
					$('.ui-widget-overlay').css('z-index', 98);
				},
				close: function() {
					$('[title]').each(function() {
						if ($(this).tooltip('instance')) {
							$(this).tooltip('close');
						}
					});
				}
			});
		}

		$(function() {
			pageAction = 'list';

			initializeChecks();

			$('#addreport').click(function() {
				addReport();
			});

			$('#chk').unbind().on('submit', function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	</script>
	<?php

	bottom_footer();
}

function html_graph_update_timespan() {
	if (isset_request_var('date1')) {
		$_SESSION['sess_current_date1'] = get_request_var('date1');
	}

	if (isset_request_var('date2')) {
		$_SESSION['sess_current_date2'] = get_request_var('date2');
	}

	if (isset_request_var('predefined_timespan')) {
		$return = array(
			'date1'      => $_SESSION['sess_current_date1'],
			'date2'      => $_SESSION['sess_current_date2'],
			'timestamp1' => $_SESSION['sess_current_timespan_begin_now'],
			'timestamp2' => $_SESSION['sess_current_timespan_end_now'],
		);

		print json_encode($return);
	}
}

function html_graph_get_reports() {
	// Add to a report
	get_filter_request_var('report_id');
	get_filter_request_var('timespan');
	get_filter_request_var('align');

	if (isset_request_var('graph_list')) {
		$items = explode(',', get_request_var('graph_list'));

		if (cacti_sizeof($items)) {
			$good = true;

			foreach ($items as $item) {
				if (!reports_add_graphs(get_filter_request_var('report_id'), $item, get_request_var('timespan'), get_request_var('align'))) {
					raise_message('reports_add_error');
					$good = false;

					break;
				}
			}

			if ($good) {
				raise_message('reports_graphs_added');
			}
		}
	} else {
		raise_message('reports_no_graph');
	}

	header('Location: graph_view.php?action=list');
}

function html_graph_single_validate() {
	/* ================= input validation ================= */
	get_filter_request_var('rra_id', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([0-9]+|all)$/')));
	get_filter_request_var('local_graph_id');
	get_filter_request_var('graph_end');
	get_filter_request_var('graph_start');
	get_filter_request_var('view_type', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9]+)$/')));
	get_filter_request_var('business_hours', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9]+)$/')));
	/* ==================================================== */

	if (isset_request_var('business_hours')) {
		$_SESSION['sess_business_hours'] = get_request_var('business_hours');
	} elseif (isset($_SESSION['sess_business_hours'])) {
		set_request_var('business_hours', $_SESSION['sess_business_hours']);
	}

	if (!isset_request_var('rra_id')) {
		set_request_var('rra_id', 'all');
	}
}

function html_graph_check_access() {
	$exists = db_fetch_cell_prepared('SELECT local_graph_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array(get_request_var('local_graph_id'))
	);

	/* make sure the graph requested exists (sanity) */
	if (!$exists) {
		print '<strong><font class="txtErrorTextBox">' . __('GRAPH DOES NOT EXIST') . '</font></strong>';
		bottom_footer();

		exit;
	}

	/* take graph permissions into account here */
	if (!is_graph_allowed(get_request_var('local_graph_id'))) {
		header('Location: permission_denied.php');

		exit;
	}
}

function html_graph_get_info() {
	$rras        = array();
	$graph_title = null;

	if (get_request_var('rra_id') == 'all' || isempty_request_var('rra_id')) {
		$sql_where = ' AND dspr.id IS NOT NULL';
	} else {
		$sql_where = ' AND dspr.id=' . get_request_var('rra_id');
	}

	$rras        = get_associated_rras(get_request_var('local_graph_id'), $sql_where);
	$graph_title = get_graph_title(get_request_var('local_graph_id'));

	return array('rras' => $rras, 'title' => $graph_title);
}

function html_graph_single_view() {
	global $config;

	html_graph_single_validate();

	html_graph_check_access();

	$info = html_graph_get_info();
	$rras = $info['rras'];
	$graph_title = $info['title'];

	$current_page = get_current_page();
	if ($current_page == 'graph.php') {
		top_header();
	} elseif ($current_page == 'graph_view.php') {
		top_graph_header();
	} else {
		top_general_header();
	}

	print "<div class='cactiTable'>";

	html_start_box(__esc('Graph Utility View for Graph: %s', $graph_title), '100%', true, '3', 'center', '');

	api_plugin_hook_function(
		'page_buttons',
		array(
			'lgid'   => get_request_var('local_graph_id'),
			'leafid' => '', //$leaf_id,
			'mode'   => 'mrtg',
			'rraid'  => get_request_var('rra_id')
		)
	);

	$graph = db_fetch_row_prepared('SELECT gtg.local_graph_id, width, height, title_cache, gtg.graph_template_id, h.id AS host_id, h.disabled
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id = gl.id
		LEFT JOIN host AS h
		ON gl.host_id = h.id
		WHERE gtg.local_graph_id = ?',
		array(get_request_var('local_graph_id')));

	$graph_template_id = $graph['graph_template_id'];

	$i = 0;

	if (cacti_sizeof($rras)) {
		$graph_end   = time() - 30;

		print '<div class=\'graphPage\'>';

		foreach ($rras as $rra) {
			if (!empty($rra['timespan'])) {
				$graph_start = $graph_end - $rra['timespan'];
			} else {
				$graph_start = $graph_end - ($rra['step'] * $rra['rows'] * $rra['steps']);
			}

			$aggregate_url = aggregate_build_children_url(get_request_var('local_graph_id'), $graph_start, $graph_end, $rra['id']);

			?>
			<div class='graphWrapperOuter cols1' data-disabled='<?php print ($graph['disabled'] == 'on' ? 'true':'false');?>'>
				<div>
					<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id'] ?>' graph_id='<?php print $graph['local_graph_id']; ?>' rra_id='<?php print $rra['id']; ?>' graph_width='<?php print $graph['width']; ?>' graph_height='<?php print $graph['height']; ?>' graph_start='<?php print $graph_start; ?>' graph_end='<?php print $graph_end; ?>' title_font_size='<?php print((read_user_setting('custom_fonts') == 'on') ? read_user_setting('title_size') : read_config_option('title_size')); ?>'></div>
					<?php if (is_realm_allowed(27)) { ?>
					<div id='dd<?php print get_request_var('local_graph_id'); ?>' style='vertical-align:top;' class='graphDrillDown noprint'>
						<a class='iconLink utils' href='#' id='graph_<?php print get_request_var('local_graph_id'); ?>_util' graph_start='<?php print $graph_start; ?>' graph_end='<?php print $graph_end; ?>' rra_id='<?php print $rra['id']; ?>'><img class='drillDown' src='<?php print CACTI_PATH_URL . 'images/cog.png'; ?>' alt='' title='<?php print __esc('Graph Details, Zooming and Debugging Utilities'); ?>'></a><br>
						<a id='graph_<?php print $rra['id']; ?>_csv' class='iconLink csv' href='<?php print html_escape(CACTI_PATH_URL . 'graph_xport.php?local_graph_id=' . get_request_var('local_graph_id') . '&rra_id=' . $rra['id'] . '&view_type=' . get_request_var('view_type') .  '&graph_start=' . $graph_start . '&graph_end=' . $graph_end); ?>'><img src='<?php print CACTI_PATH_URL . 'images/table_go.png'; ?>' alt='' title='<?php print __esc('CSV Export'); ?>'></a><br>

						<?php
						if (is_realm_allowed(10) && $graph_template_id > 0) {
							print "<a class='iconLink' role='link' title='" . __esc('Edit Graph Template') . "' href='" . html_escape(CACTI_PATH_URL . 'graph_templates.php?action=template_edit&id=' . $graph_template_id) . "'><img src='" . html_escape(CACTI_PATH_URL . 'images/template_edit.png') . "'></img></a>";
							print '<br/>';
						}

						if (read_config_option('realtime_enabled') == 'on' || is_realm_allowed(25)) {
							print "<a class='iconLink' href='#' onclick=\"window.open('" . CACTI_PATH_URL . 'graph_realtime.php?top=0&left=0&local_graph_id=' . get_request_var('local_graph_id') . "', 'popup_" . get_request_var('local_graph_id') . "', 'directories=no,toolbar=no,menubar=no,resizable=yes,location=no,scrollbars=no,status=no,titlebar=no,width=650,height=300');return false\"><img src='" . CACTI_PATH_URL . "images/chart_curve_go.png' alt='' title='" . __esc('Click to view just this Graph in Real-time') . "'></a><br/>\n";
						}

						print($aggregate_url != '' ? $aggregate_url : '');

						api_plugin_hook('graph_buttons', array('hook' => 'view', 'local_graph_id' => get_request_var('local_graph_id'), 'rra' => $rra['id'], 'view_type' => get_request_var('view_type')));

						?>
					</div>
					<?php } ?>
				</div>
				<div><?php print html_escape($rra['name']); ?></div>
				<div><input type='hidden' id='thumbnails' value='<?php print html_escape(get_request_var('thumbnails')); ?>'></input></div>
			</div>
			<?php
			$i++;
		}

		print '</div>';

		api_plugin_hook_function('tree_view_page_end');
	}

	?>
	<script type='text/javascript'>
		var originalWidth = null;
		var refreshTime = <?php print read_user_setting('page_refresh') * 1000; ?>;
		var graphTimeout = null;

		function initializeGraph() {
			$('a.iconLink').tooltip();

			$('.graphWrapper').each(function() {
				var itemWrapper = $(this);
				var itemGraph = $(this).find('.graphimage');

				if (itemGraph.length != 1) {
					itemGraph = itemWrapper;
				}

				var graph_id     = itemGraph.attr('graph_id');
				var rra_id       = itemGraph.attr('rra_id');
				var graph_height = itemGraph.attr('graph_height');
				var graph_width  = itemGraph.attr('graph_width');
				var graph_start  = itemGraph.attr('graph_start');
				var graph_end    = itemGraph.attr('graph_end');

				$.getJSON(urlPath + 'graph_json.php?' +
						'local_graph_id=' + graph_id +
						'&graph_height=' + graph_height +
						'&graph_start=' + graph_start +
						'&graph_end=' + graph_end +
						'&rra_id=' + rra_id +
						'&graph_width=' + graph_width +
						'&disable_cache=true' +
						($('#thumbnails').val() == 'true' ? '&graph_nolegend=true' : ''))
					.done(function(data) {
						wrapper = $('#wrapper_' + data.local_graph_id + '[rra_id=\'' + data.rra_id + '\']');
						wrapper.html(
							"<img class='graphimage' id='graph_" + data.local_graph_id +
							"' src='data:image/" + data.type + ";base64," + data.image +
							"' rra_id='" + data.rra_id +
							"' graph_type='" + data.type +
							"' graph_id='" + data.local_graph_id +
							"' graph_start='" + data.graph_start +
							"' graph_end='" + data.graph_end +
							"' graph_left='" + data.graph_left +
							"' graph_top='" + data.graph_top +
							"' graph_width='" + data.graph_width +
							"' graph_height='" + data.graph_height +
							"' image_width='" + data.image_width +
							"' image_height='" + data.image_height +
							"' canvas_left='" + data.graph_left +
							"' canvas_top='" + data.graph_top +
							"' canvas_width='" + data.graph_width +
							"' canvas_height='" + data.graph_height +
							"' width='" + data.image_width +
							"' height='" + data.image_height +
							"' value_min='" + data.value_min +
							"' value_max='" + data.value_max + "'>"
						);

						$('#graph_start').val(data.graph_start);
						$('#graph_end').val(data.graph_end);

						var gr_location = '#graph_' + data.local_graph_id;
						if (data.rra_id > 0) {
							gr_location += '[rra_id=\'' + data.rra_id + '\']';
						}

						$(gr_location).zoom({
							inputfieldStartTime: 'date1',
							inputfieldEndTime: 'date2',
							serverTimeOffset: <?php print date('Z'); ?>
						});

						responsiveResizeGraphs(true);
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});
			});

			$('a[id$="_util"]').off('click').on('click', function() {
				graph_id = $(this).attr('id').replace('graph_', '').replace('_util', '');
				rra_id = $(this).attr('rra_id');
				graph_start = $(this).attr('graph_start');
				graph_end = $(this).attr('graph_end');
				var tree = $('#navigation').length ? true:false;

				loadUrl({
					url: pageName + '?' +
						'action=zoom' + (tree ? '-tree':'-preview') +
						'&local_graph_id=' + graph_id +
						'&rra_id=' + rra_id +
						'&graph_start=' + graph_start +
						'&graph_end=' + graph_end
				});
			});

			$('a[id$="_csv"]').each(function() {
				$(this).off('click').on('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					document.location = $(this).attr('href');
					Pace.stop();
				});
			});

			graphTimeout = setTimeout(initializeGraph, refreshTime);
		}

		$(function() {
			pageAction = 'graph';

			if (graphTimeout !== null) {
				clearTimeout(graphTimeout);
			}

			initializeGraph();
			$('#navigation').show();
			$('#navigation_right').show();
		});
	</script>
	<?php

	html_end_box(false, true);

	print '</div>';

	bottom_footer();
}

function html_graph_zoom() {
	global $config;

	html_graph_single_validate();

	html_graph_check_access();

	$info = html_graph_get_info();
	$rras = $info['rras'];
	$graph_title = $info['title'];

	/* find the maximum time span a graph can show */
	$max_timespan = 1;

	if (cacti_sizeof($rras)) {
		foreach ($rras as $rra) {
			if ($rra['steps'] * $rra['rows'] * $rra['rrd_step'] > $max_timespan) {
				$max_timespan = $rra['steps'] * $rra['rows'] * $rra['rrd_step'];
			}
		}
	}

	/* fetch information for the current RRA */
	if (isset_request_var('rra_id') && get_request_var('rra_id') > 0) {
		$rra = db_fetch_row_prepared('SELECT dspr.id, step, steps, dspr.name, `rows`
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dsp.id=dspr.data_source_profile_id
			WHERE dspr.id = ?', array(get_request_var('rra_id')));

		$rra['timespan'] = $rra['steps'] * $rra['step'] * $rra['rows'];
	} else {
		$rra = db_fetch_row_prepared('SELECT dspr.id, step, steps, dspr.name, `rows`
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dsp.id=dspr.data_source_profile_id
			WHERE dspr.id = ?', array($rras[0]['id']));

		$rra['timespan'] = $rra['steps'] * $rra['step'] * $rra['rows'];
	}

	/* define the time span, which decides which rra to use */
	$timespan = - ($rra['timespan']);

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell_prepared('SELECT
		data_template_data.rrd_step
		FROM (data_template_data, data_template_rrd, graph_templates_item)
		WHERE graph_templates_item.task_item_id = data_template_rrd.id
		AND data_template_rrd.local_data_id = data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		LIMIT 0,1', array(get_request_var('local_graph_id')));
	$ds_step                       = empty($ds_step) ? 300 : $ds_step;
	$seconds_between_graph_updates = ($ds_step * $rra['steps']);

	$now = time();

	if (isset_request_var('graph_end') && (get_request_var('graph_end') <= $now - $seconds_between_graph_updates)) {
		$graph_end = get_request_var('graph_end');
	} else {
		$graph_end = $now - $seconds_between_graph_updates;
	}

	if (isset_request_var('graph_start')) {
		if (($graph_end - get_request_var('graph_start')) > $max_timespan) {
			$graph_start = $now - $max_timespan;
		} else {
			$graph_start = get_request_var('graph_start');
		}
	} else {
		$graph_start = $now + $timespan;
	}

	/* required for zoom out function */
	if ($graph_start == $graph_end) {
		$graph_start--;
	}

	$graph = db_fetch_row_prepared('SELECT gtg.local_graph_id, width, height, title_cache, gtg.graph_template_id, h.id AS host_id, h.disabled
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id = gl.id
		LEFT JOIN host AS h
		ON gl.host_id = h.id
		WHERE gtg.local_graph_id = ?',
		array(get_request_var('local_graph_id')));

	$graph_height      = $graph['height'];
	$graph_width       = $graph['width'];
	$graph_template_id = $graph['graph_template_id'];

	if (read_user_setting('custom_fonts') == 'on' && read_user_setting('title_size') != '') {
		$title_font_size = read_user_setting('title_size');
	} elseif (read_config_option('title_size') != '') {
		$title_font_size = read_config_option('title_size');
	} else {
		$title_font_size = 10;
	}

	$current_page = get_current_page();
	if ($current_page == 'graph.php') {
		top_header();
	} elseif ($current_page == 'graph_view.php') {
		top_graph_header();
	} else {
		top_general_header();
	}

	print "<div class='cactiTable'>";

	html_start_box(__esc('Graph Utility View for Graph: %s', $graph_title), '100%', true, '3', 'center', '');
	?>
	<div class='graphPage'>
		<div class='graphWrapperOuter cols1' data-disabled='<?php print ($graph['disabled'] == 'on' ? 'true':'false');?>'>
			<div>
				<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id'] ?>' graph_id='<?php print $graph['local_graph_id']; ?>' rra_id='<?php print $rra['id']; ?>' graph_width='<?= $graph_width ?>' graph_height='<?= $graph_height ?>' title_font_size='<?= $title_font_size ?>'></div>
				<?php if (is_realm_allowed(27)) { ?>
				<div id='dd<?php print $graph['local_graph_id']; ?>' style='vertical-align:top;' class='graphDrillDown noprint'>
					<a href='#' id='graph_<?php print $graph['local_graph_id']; ?>_properties' class='iconLink properties'>
						<img class='drillDown' src='<?php print CACTI_PATH_URL . 'images/graph_properties.gif'; ?>' alt='' title='<?php print __esc('Graph Source/Properties'); ?>'>
					</a>
					<br>
					<a href='#' id='graph_<?php print $graph['local_graph_id']; ?>_csv' class='iconLink properties'>
						<img class='drillDown' src='<?php print CACTI_PATH_URL . 'images/table_go.png'; ?>' alt='' title='<?php print __esc('Graph Data'); ?>'>
					</a>
					<br>
					<?php
					if (is_realm_allowed(10) && $graph_template_id > 0) {
						print "<a class='iconLink' role='link' title='" . __esc('Edit Graph Template') . "' href='" . html_escape(CACTI_PATH_URL . 'graph_templates.php?action=template_edit&id=' . $graph_template_id) . "'><img src='" . html_escape(CACTI_PATH_URL . 'images/template_edit.png') . "'></img></a>";
						print '<br/>';
					}

					api_plugin_hook('graph_buttons', array('hook' => 'zoom', 'local_graph_id' => get_request_var('local_graph_id'), 'rra' =>  get_request_var('rra_id'), 'view_type' => get_request_var('view_type'))); ?>
				</div>
				<?php print(read_user_setting('show_graph_title') == 'on' ? '<div>' . html_escape($graph['title_cache']) . '</div>' : ''); ?>
				<?php } ?>
			</div>
		</div>
		<div>
			<input type='hidden' id='date1' value=''>
			<input type='hidden' id='date2' value=''>
			<input type='hidden' id='graph_start' value='<?php print $graph_start; ?>'>
			<input type='hidden' id='graph_end' value='<?php print $graph_end; ?>'>
			<input type='hidden' id='thumbnails' value='<?php print html_escape(get_request_var('thumbnails')); ?>'></input>
			<input type='hidden' id='business_hours' value='<?php print html_escape(get_request_var('business_hours')); ?>'></input>
		</div>
	</div>
	<?php

	html_end_box(false, true);

	?>
	<div class='cactiTable'><div id='data'></div></div>
	<script type='text/javascript'>
		var graph_id = <?php print get_request_var('local_graph_id') . ";\n"; ?>
		var rra_id = <?php print get_request_var('rra_id') . ";\n"; ?>
		var graph_start = 0;
		var graph_end = 0;
		var graph_height = 0;
		var graph_width = 0;
		var props_on = false;
		var graph_data_on = true;

		/* turn off the page refresh */
		var refreshMSeconds = 9999999;

		function graphProperties() {
			loadUrl({
				url: urlPath + pageName + '?action=properties' +
					'&local_graph_id=' + graph_id +
					'&rra_id=<?php print get_request_var('rra_id'); ?>' +
					'&view_type=<?php print get_request_var('view_type'); ?>' +
					'&graph_start=' + $('#graph_start').val() +
					'&graph_end=' + $('#graph_end').val(),
				noState: true,
				elementId: 'data',
			});

			props_on = true;
			graph_data_on = false;
		}

		function graphXport() {
			loadUrl({
				url: urlPath + 'graph_xport.php?local_graph_id=' + graph_id +
					'&rra_id=0' +
					'&format=table' +
					'&graph_start=' + $('#graph_start').val() +
					'&graph_end=' + $('#graph_end').val(),
				noState: true,
				elementId: 'data',
				funcEnd: 'graphXportFinalize',
			});

			props_on = false;
			graph_data_on = true;
		}

		function graphXportFinalize(options, data) {
			if (typeof resizeWrapper !== 'undefined') {
				resizeWrapper();
			}

			$('.download').click(function(event) {
				event.preventDefault;
				graph_id = $(this).attr('id').replace('graph_', '');
				document.location = urlPath + 'graph_xport.php?local_graph_id=' + graph_id + '&rra_id=0&view_type=tree&graph_start=' + $('#graph_start').val() + '&graph_end=' + $('#graph_end').val();
				Pace.stop();
			});
		}

		function initializeGraph() {
			$('.graphWrapper').each(function() {
				graph_id = $(this).attr('id').replace('wrapper_', '');
				rra_id = $(this).attr('rra_id');
				graph_height = $(this).attr('graph_height');
				graph_width = $(this).attr('graph_width');

				if (!(rra_id > 0)) {
					rra_id = 0;
				}

				$.getJSON(urlPath + 'graph_json.php?rra_id=' + rra_id +
						'&local_graph_id=' + graph_id +
						'&graph_start=' + $('#graph_start').val() +
						'&graph_end=' + $('#graph_end').val() +
						'&graph_height=' + graph_height +
						'&graph_width=' + graph_width +
						'&disable_cache=true' +
						'&business_hours=' + ($('#business_hours').val() == 'true' ? 'true' : 'false') +
						($('#thumbnails').val() == 'true' ? '&graph_nolegend=true' : ''))
					.done(function(data) {
						$('#wrapper_' + data.local_graph_id).html(
							"<img class='graphimage' id='graph_" + data.local_graph_id +
							"' src='data:image/" + data.type + ";base64," + data.image +
							"' rra_id='" + data.rra_id +
							"' graph_type='" + data.type +
							"' graph_id='" + data.local_graph_id +
							"' graph_start='" + data.graph_start +
							"' graph_end='" + data.graph_end +
							"' graph_left='" + data.graph_left +
							"' graph_top='" + data.graph_top +
							"' graph_width='" + data.graph_width +
							"' graph_height='" + data.graph_height +
							"' image_width='" + data.image_width +
							"' image_height='" + data.image_height +
							"' canvas_left='" + data.graph_left +
							"' canvas_top='" + data.graph_top +
							"' canvas_width='" + data.graph_width +
							"' canvas_height='" + data.graph_height +
							"' width='" + data.image_width +
							"' height='" + data.image_height +
							"' value_min='" + data.value_min +
							"' value_max='" + data.value_max + "'>"
						);

						$('#graph_start').val(data.graph_start);
						$('#graph_end').val(data.graph_end);

						var gr_location = '#graph_' + data.local_graph_id;
						if (data.rra_id > 0) {
							gr_location += '[rra_id=\'' + data.rra_id + '\']';
						}

						$(gr_location).zoom({
							inputfieldStartTime: 'date1',
							inputfieldEndTime: 'date2',
							serverTimeOffset: <?php print date('Z'); ?>
						});

						if (graph_data_on) {
							graphXport();
						} else if (props_on) {
							graphProperties();
						}

						responsiveResizeGraphs(true);
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});
			});

			$('a[id$="_properties"]').unbind('click').click(function() {
				graph_id = $(this).attr('id').replace('graph_', '').replace('_properties', '');
				graphProperties();
			});

			$('a[id$="_csv"]').unbind('click').click(function() {
				graph_id = $(this).attr('id').replace('graph_', '').replace('_csv', '');
				graphXport();
			});
		}

		$(function() {
			pageAction = 'graph';
			initializeGraph();
			$('#navigation').show();
			$('#navigation_right').show();
			$('a.iconLink').tooltip();
		});
	</script>
	<?php

	print '</div>';

	bottom_footer();
}

function html_graph_properties() {
	global $config;

	html_graph_single_validate();

	html_graph_check_access();

	$current_page = get_current_page();
	if ($current_page == 'graph.php') {
		top_header();
	} elseif ($current_page == 'graph_view.php') {
		top_graph_header();
	} else {
		top_general_header();
	}

	$graph_data_array['print_source'] = true;

	/* override: graph start time (unix time) */
	if (!isempty_request_var('graph_start')) {
		$graph_data_array['graph_start'] = get_request_var('graph_start');
	}

	/* override: graph end time (unix time) */
	if (!isempty_request_var('graph_end')) {
		$graph_data_array['graph_end'] = get_request_var('graph_end');
	}

	$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_STDERR;
	$graph_data_array['print_source'] = 1;
	?>
	<br>
	<div class='cactiTable'>
		<div id="data" class='cactiTable'>
			<div class='cactiTable'>
				<span class='cactiTableTitleRow'><?php print __('RRDtool Command:'); ?></span>
				<?php
				$null_param = array();
				print @rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, null, $null_param, $_SESSION[SESS_USER_ID]);
				unset($graph_data_array['print_source']);
				?>
				<br>
				<span class='cactiTableTitleRow'><?php print __('RRDtool Says:'); ?></span>
				<span class='left'>
					<?php
					if ($config['poller_id'] == 1) {
						print @rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, null, $null_param, $_SESSION[SESS_USER_ID]);
					} else {
						print __esc('Not Checked');
					}
					?>
				</span>
			</div>
		</div>
	</div>
	<?php

	bottom_footer();
}

