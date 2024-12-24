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

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'purge_execute':
		clear_user_log();
		raise_message('purge_user_log', __('User Log Purged.'), MESSAGE_LEVEL_INFO);
		header('location: user_log.php');

		break;
	case 'purge':
		top_header();
		purge_user_log();
		bottom_footer();

		break;
	default:
		top_header();
		view_user_log();
		bottom_footer();

		break;
}

function view_user_log() {
	global $auth_realms, $item_rows;

	process_sanitize_draw_filter(true);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where  = '';
	$sql_params = array();

	/* filter by username */
	if (get_request_var('user_id') == '-2') {
		$sql_where    = 'WHERE ul.user_id NOT IN (SELECT DISTINCT id FROM user_auth)';
	} elseif (get_request_var('user_id') != '-1') {
		$sql_where    = 'WHERE ul.user_id = ?';
		$sql_params[] = get_request_var('user_id');
	}

	/* filter by result */
	if (get_request_var('result') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ul.result = ?';
		$sql_params[] = get_request_var('result');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (
			ul.username LIKE ? OR ul.time LIKE ? OR ua.full_name LIKE ? OR ul.ip LIKE ?)';

		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username = ul.username
		$sql_where",
		$sql_params);

	$user_log_sql = "SELECT ul.username, ua.full_name, ua.realm,
		ul.time, ul.result, ul.ip
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$user_log = db_fetch_assoc_prepared($user_log_sql, $sql_params);

	$display_text = array(
		'username'  => array(__('User'), 'ASC'),
		'full_name' => array(__('Full Name'), 'ASC'),
		'realm'     => array(__('Authentication Realm'), 'ASC'),
		'time'      => array(__('Date'), 'DESC'),
		'result'    => array(__('Result'), 'DESC'),
		'ip'        => array(__('IP Address'), 'DESC')
	);

	$nav = html_nav_bar('user_log.php?user_id=' . get_request_var('user_id') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('Login Attempts'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'user_log.php');

	$i = 0;

	if (cacti_sizeof($user_log)) {
		foreach ($user_log as $item) {
			form_alternate_row('line' . $i, true);
			?>
			<td class='nowrap'>
				<?php print filter_value($item['username'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php if (isset($item['full_name'])) {
					print filter_value($item['full_name'], get_request_var('filter'));
				} else {
					print __('(User Removed)');
				}
			?>
			</td>
			<td class='nowrap'>
				<?php if (isset($auth_realms[$item['realm']])) {
					print filter_value($auth_realms[$item['realm']], get_request_var('filter'));
				} else {
					print __('N/A');
				}
			?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['time'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php print($item['result'] == 0 ? __('Failed'):($item['result'] == 1 ? __('Success - Password'):($item['result'] == 3 ? __('Success - Password Change'):__('Success - Token'))));?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['ip'], get_request_var('filter'));?>
			</td>
			</tr>
			<?php

			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($user_log)) {
		print $nav;
	}
}

function clear_user_log() {
	$users = db_fetch_assoc('SELECT DISTINCT id, username FROM user_auth');

	if (cacti_sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			// Check how many rows for the current user with a valid token
			foreach (array(1, 2) as $result) {
				$total_rows = db_fetch_cell_prepared('SELECT COUNT(username)
					FROM user_log
					WHERE username = ?
					AND user_id = ?
					AND result = ?',
					array($user['username'], $user['id'], $result));

				if ($total_rows > 1) {
					db_execute_prepared('DELETE
						FROM user_log
						WHERE username = ?
						AND user_id = ?
						AND result = ?
						ORDER BY time LIMIT ' . ($total_rows - 1),
						array($user['username'], $user['id'], $result));
				}
			}

			db_execute_prepared('DELETE
				FROM user_log
				WHERE username = ?
				AND user_id = ?
				AND result = 0',
				array($user['username'], $user['id']));
		}

		/* delete inactive users */
		db_execute('DELETE
			FROM user_log
			WHERE user_id NOT IN (SELECT id FROM user_auth)
			OR username NOT IN (SELECT username FROM user_auth)');
	}
}

function purge_user_log() {
	form_start('user_log.php');

	html_start_box(__('Purge User Log'), '50%', '', '3', 'center', '');

	print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the User Log.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' class='right'>
				<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='" . __esc('Cancel') . "'>&nbsp
				<input type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' value='" . __esc('Continue') . "' title='" . __esc('Purge Log') . "'>
				<script type='text/javascript'>
				$(function() {
					$('#pc').click(function() {
						strURL = location.pathname+'?action=purge_execute';
						loadUrl({url:strURL})
					});

					$('#cancel').click(function() {
						strURL = location.pathname;
						loadUrl({url:strURL})
					});
				});
				</script>
			</td>
		</tr>";

	html_end_box();
}

function create_filter() {
	global $item_rows;

	$all     = array('-1' => __('All'));
	$deleted = array('-2' => __('Deleted/Invalid'));
	$users   = db_fetch_assoc('SELECT DISTINCT id,
		IF(ud.domain_name != "",
			CONCAT(ua.username, " (", ud.domain_name, ")"),
			IF(ua.realm = 0,
				CONCAT(ua.username, " (' . __esc('Local Auth') . ')"),
				CONCAT(ua.username, " (' . __esc('Basic Auth') . ')")
			)
		) AS name
		FROM user_auth AS ua
		LEFT JOIN user_domains AS ud
		ON ua.realm = ud.domain_id+1000
		ORDER BY username, realm');

	if (cacti_sizeof($users)) {
		$users = array_rekey($users, 'id', 'name');
	}

	$users = $all + $deleted + $users;

	$results = array(
		'-1' => __('Any'),
		'1'  => __('Success - Password'),
		'2'  => __('Success - Token'),
		'3'  => __('Success - Password Change'),
		'0'  => __('Failed')
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
				'user_id' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('User'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $users,
					'value'         => '-1'
				),
				'result' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Result'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $results,
					'value'         => '-1'
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
				'title'   => __('Purge User log of all but the last login attempt'),
			)
		),
		'sort' => array(
			'sort_column'    => 'time',
			'sort_direction' => 'DESC'
		)
	);
}

function process_sanitize_draw_filter($render = false) {
	$filters = create_filter();

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('User Login History'), 'user_log.php', 'form_userlog', 'sess_userlog');

	$pageFilter->rows_label = __('Attempts');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

