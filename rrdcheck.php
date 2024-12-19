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

include_once('./include/auth.php');
include_once(CACTI_PATH_LIBRARY . '/functions.php');

$rra_path = CACTI_PATH_RRA . '/';

top_header();

set_default_action();

if (read_config_option('rrdcheck_enable') != 'on') {
	html_start_box(__('RRD check'), '100%', '', '3', 'center', '');
	print __('RRD check is disabled, please enable in Configuration -> Settings -> Data');
	html_end_box();
}

switch(get_request_var('action')) {
	case 'purge':
		rrdcheck_purge();

	default:
		rrdcheck_display_problems();
}

bottom_footer();

function rrdcheck_purge() {
	db_execute('TRUNCATE TABLE rrdcheck');
}

/*
 * Display all rrdcheck entries
 */
function rrdcheck_display_problems() {
	global $config, $item_rows;

	/* suppress warnings */
	error_reporting(0);

	process_sanitize_draw_filter(true);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where  = '';
	$sql_params = array();

	$secsback = get_request_var('age');

	if (get_request_var('age') == 0) {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'test_date >= ?';
		$sql_params[] = date('Y-m-d H:i:s', time() - (7200));
	} else {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'test_date <= ?';
		$sql_params[] = date('Y-m-d H:i:s', (time() - $secsback));
	}

	if (get_request_var('filter') != '') {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') .
			'(message LIKE ? OR h.description LIKE ? OR dtd.name_cache LIKE ? OR dtd.local_data_id LIKE ?)';

		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(rc.local_data_id)
		FROM rrdcheck AS rc
		LEFT JOIN data_local AS dl
		ON rc.local_data_id = dl.id
		LEFT JOIN data_template_data AS dtd
		ON rc.local_data_id = dtd.local_data_id
		LEFT JOIN host AS h
		ON dl.host_id = h.id
		$sql_where",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$problems = db_fetch_assoc_prepared("SELECT h.description, dtd.name_cache,
		rc.local_data_id, rc.test_date, rc.message
		FROM rrdcheck AS rc
		LEFT JOIN data_local AS dl
		ON rc.local_data_id = dl.id
		LEFT JOIN data_template_data AS dtd
		ON rc.local_data_id = dtd.local_data_id
		LEFT JOIN host AS h
		ON dl.host_id = h.id
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar(CACTI_PATH_URL . 'rrdcheck.php?filter'. get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('RRDcheck Problems'), 'page', 'main');

	form_start('rrdcheck.php');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description' => array(
			'display' => __('Host Description'),
			'sort'    => 'ASC'
		),
		'name_cache' => array(
			'display' => __('Data Source'),
			'sort'    => 'ASC'
		),
		'local_data_id' => array(
			'display' => __('Local Data ID'),
			'align'   => 'center',
			'sort'    => 'ASC'
		),
		'message' => array(
			'display' => __('Message'),
			'sort'    => 'ASC'
		),
		'test_date' => array(
			'display' => __('Date'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($problems)) {
		foreach ($problems as $p) {
			form_alternate_row('line' . $p['local_data_id'], true);

			if ($p['description'] == '') {
				$p['description'] = __('Deleted');
			}

			if ($p['name_cache'] == '') {
				$p['name_cache'] = __('Deleted');
			}

			form_selectable_cell(filter_value($p['description'], get_request_var('filter')), $p['local_data_id']);
			form_selectable_cell(filter_value($p['name_cache'], get_request_var('filter')), $p['local_data_id']);
			form_selectable_cell(filter_value($p['local_data_id'], get_request_var('filter')), $p['local_data_id'], '', 'center');
			form_selectable_cell(filter_value($p['message'], get_request_var('filter')), $p['local_data_id']);
			form_selectable_cell($p['test_date'], $p['local_data_id'], '', 'right');

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="5"><em>' . __('No RRDcheck Problems Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($problems)) {
		print $nav;
	}

	form_end();

	/* restore original error handler */
	restore_error_handler();
}

function create_filter() {
	global $item_rows, $page_refresh_interval;

	$all     = array('-1' => __('All'));
	$any     = array('-1' => __('Any'));
	$none    = array('0'  => __('None'));

	$ages = array(
		'0'      => '&lt; ' . __('%d hours', 2),
		'14400'  => '&gt; ' . __('%d hours', 4),
		'43200'  => '&gt; ' . __('%d hours',12),
		'86400'  => '&gt; ' . __('%d day', 1),
		'259200' => '&gt; ' . __('%d days', 3),
		'604800' => '&gt; ' . __('%d days', 5)
	);

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
				'age' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Age'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '0',
					'pageset'       => true,
					'array'         => $ages,
					'value'         => '0'
				),
				'rows' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Attempts'),
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
			'purge' => array(
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge Data Source Checks from the Database'),
			)
		),
		'sort' => array(
			'sort_column'    => 'test_date',
			'sort_direction' => 'DESC'
		)
	);
}

function process_sanitize_draw_filter($render = false) {
	$filters = create_filter();

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('RRDfile Checker'), 'rrdcheck.php', 'form_rrdcheck', 'sess_rrdc');

	$pageFilter->rows_label = __('Data Sources');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

