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
include_once('./lib/poller.php');
include_once('./lib/utility.php');

$actions = array(
	1 => __('Delete')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'actions':
		form_actions();

		break;
	default:
		top_header();

		public_keys();

		bottom_footer();
		break;
}

function form_actions() {
	global $actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { // delete
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					package_key_remove($selected_items[$i]);
				}
			}
		}

		header('Location: package_keys.php?header=false');
		exit;
	}

	/* setup some variables */
	$p_list = '';
	$p_array = array();

	/* loop through each of the data queries and process them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$p_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT author FROM package_public_keys WHERE id = ?', array($matches[1]))) . '</li>';
			$p_array[] = $matches[1];
		}
	}

	top_header();

	form_start('package_keys.php');

	html_start_box($actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($p_array) && cacti_sizeof($p_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following .', 'Click \'Continue\' to delete following Package Repositories.', cacti_sizeof($p_array)) . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Public Key', 'Delete Public Keys', cacti_sizeof($p_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: package_keys.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($p_array) ? serialize($p_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function package_key_remove($id) {
	db_execute_prepared('DELETE FROM package_public_keys WHERE id = ?', array($id));
}

function public_keys() {
	global $actions, $item_rows, $types;

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Public Keys'), 'package_keys.php', 'fors', 'sess_package_keys');

	$pageFilter->rows_label = __('Keys');
	$pageFilter->set_sort_array('author', 'ASC');
	$pageFilter->render();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where  = '';
	$sql_params = array();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = ($sql_where != '' ? ' AND ':'WHERE ') .
			'(author LIKE ? OR homepage LIKE ? OR email_address LIKE ?)';

		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM package_public_keys
		$sql_where",
		$sql_params);

	$keys = db_fetch_assoc_prepared("SELECT *
		FROM package_public_keys
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$display_text = array(
		'author' => array(
			'display' => __('Author'),
			'sort'    => 'ASC'
		),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'homepage' => array(
			'display' => __('Home Page'),
			'sort'    => 'ASC'
		),
		'email_address' => array(
			'display' => __('Support EMail Address'),
			'sort'    => 'ASC'
		),
		'nosort' => array(
			'display' => __('Key Type'),
		),
	);

	$nav = html_nav_bar('package_keys.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, sizeof($display_text)+1, __('Package Public Keys'), 'page', 'main');

	form_start('package_keys.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($keys)) {
		foreach ($keys as $key) {
			form_alternate_row('line' . $key['id'], true);

			$pkey = $key['public_key'];

			form_selectable_cell(filter_value($key['author'], get_request_var('filter')), $key['id']);
			form_selectable_cell($key['id'], $key['id']);
			form_selectable_cell(filter_value($key['homepage'], get_request_var('filter')), $key['id']);
			form_selectable_cell(filter_value($key['email_address'], get_request_var('filter')), $key['id']);
			form_selectable_cell(strlen($pkey) < 200 ? 'SHA1':'SHA256', $key['id']);

			form_checkbox_cell($key['author'], $key['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Package Public Keys Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($keys)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

