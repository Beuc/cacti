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
 * duplicate_reports - duplicates a report and all items
 *
 * @param int $_id       - id of the report
 * @param string $_title - title of the new report
 */
function duplicate_reports($_id, $_title) {
	global $fields_reports_edit;
	reports_log(__FUNCTION__ . ', id: ' . $_id, false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

	$report = db_fetch_row_prepared('SELECT *
		FROM reports
		WHERE id = ?',
		array($_id));

	$reports_items = db_fetch_assoc_prepared('SELECT *
		FROM reports_items
		WHERE report_id = ?',
		array($_id));

	$save = array();

	foreach ($fields_reports_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method']) &&
			!preg_match('/^spacer/', $array['method'])) {
			$save[$field] = $report[$field];
		}
	}

	/* duplicate to your id */
	$save['user_id'] = $_SESSION[SESS_USER_ID];

	/* substitute the title variable */
	$save['name'] = str_replace('<name>', $report['name'], $_title);
	/* create new rule */
	$save['enabled'] = '';
	$save['id']      = 0;
	$reports_id      = sql_save($save, 'reports');

	/* create new rule items */
	if (cacti_sizeof($reports_items) > 0) {
		foreach ($reports_items as $reports_item) {
			$save              = $reports_item;
			$save['id']        = 0;
			$save['report_id'] = $reports_id;
			$reports_item_id   = sql_save($save, 'reports_items');
		}
	}
}

function reports_add_devices($report_id, $device_ids, $timespan, $align) {
	$report_user = db_fetch_cell_prepared('SELECT user_id
		FROM reports
		WHERE id = ?',
		array($report_id));

	if ($report_user != $_SESSION[SESS_USER_ID]) {
		raise_message('reports_not_owner');

		return false;
	} else {
		$errors = 0;

		foreach ($device_ids as $device_id) {
			$sequence = db_fetch_cell_prepared('SELECT MAX(sequence)
				FROM reports_items
				WHERE report_id = ?',
				array($report_id)) + 1;

			$exists = db_fetch_cell_prepared('SELECT id
				FROM reports_items
				WHERE host_id = ?
				AND item_type = 5
				AND report_id = ?
				AND timespan = ?',
				array($device_id, $report_id, $timespan));

			$device_exists = db_fetch_cell_prepared('SELECT id
				FROM host
				WHERE id = ?',
				array($device_id));

			$description = db_fetch_cell_prepared('SELECT description
				FROM host
				WHERE id = ?',
				array($device_id));

			if (!$exists) {
				if ($device_exists > 0) {
					$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
						FROM host
						WHERE id = ?',
						array($device_id));

					$site_id = db_fetch_cell_prepared('SELECT site_id
						FROM host
						WHERE id = ?',
						array($device_id));

					// Setup defaults
					$graph_template_id = -1;
					$local_graph_id    = 0;

					db_execute_prepared('INSERT INTO reports_items
						(report_id, item_type, host_template_id, site_id, host_id, graph_template_id, local_graph_id, timespan, align, sequence)
						VALUES (?, 5, ?, ?, ?, ?, ?, ?, ?, ?)',
						array(
							$report_id,
							$host_template_id,
							$site_id,
							$device_id,
							$graph_template_id,
							$local_graph_id,
							$timespan,
							$align,
							$sequence
						)
					);

					raise_message('reports_add_device_' . $device_id, __('Device \'%s\' successfully added to Report.', $description), MESSAGE_LEVEL_INFO);
				} else {
					$errors++;
					raise_message('reports_device_not_found', __('Device not found! Unable to add to Report'), MESSAGE_LEVEL_ERROR);
				}
			} else {
				$errors++;
				raise_message('reports_no_add_device_' . $device_id, __('Device \'%s\' not added to Report as it already exists on report.', $description), MESSAGE_LEVEL_WARN);
			}
		}

		if ($errors) {
			return false;
		} else {
			return true;
		}
	}
}

function reports_add_graphs($report_id, $local_graph_id, $timespan, $align) {
	$report_user = db_fetch_cell_prepared('SELECT user_id
		FROM reports
		WHERE id = ?',
		array($report_id));

	if ($report_user != $_SESSION[SESS_USER_ID]) {
		raise_message('reports_not_owner');

		return false;
	} else {
		$sequence = db_fetch_cell_prepared('SELECT MAX(sequence)
			FROM reports_items
			WHERE report_id = ?',
			array($report_id)) + 1;

		$exists = db_fetch_cell_prepared('SELECT id
			FROM reports_items
			WHERE local_graph_id = ?
			AND report_id = ?
			AND timespan = ?',
			array($local_graph_id, $report_id, $timespan));

		if (!$exists) {
			$gd = db_fetch_row_prepared('SELECT *
				FROM graph_local
				WHERE id = ?',
				array($local_graph_id));

			$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
				FROM host
				WHERE id = ?',
				array($gd['host_id']));

			if (cacti_sizeof($gd)) {
				db_execute_prepared('INSERT INTO reports_items
					(report_id, item_type, host_template_id, host_id, graph_template_id, local_graph_id, timespan, align, sequence)
					VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)',
					array(
						$report_id,
						$host_template_id,
						$gd['host_id'],
						$gd['graph_template_id'],
						$local_graph_id,
						$timespan,
						$align,
						$sequence
					)
				);

				return true;
			} else {
				raise_message('reports_graph_not_found');

				return false;
			}
		} else {
			return true;
		}
	}
}

/**
 * reports_date_time_format - fetches the date/time formatting information for current user
 *
 * @return string - string defining the datetime format specific to this user
 */
function reports_date_time_format() {
	$datechar = array(
		GDC_HYPHEN => '-',
		GDC_SLASH  => '/',
		GDC_DOT    => '.'
	);

	$graph_date = '';

	/* setup date format */
	$date_fmt        = read_config_option('default_date_format');
	$dateCharSetting = read_config_option('default_datechar');

	if ($dateCharSetting == '') {
		$dateCharSetting = GDC_SLASH;
	}

	$datecharacter = $datechar[$dateCharSetting];

	switch ($date_fmt) {
		case GD_MO_D_Y:
			$graph_date = 'm' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';

			break;
		case GD_MN_D_Y:
			$graph_date = 'M' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';

			break;
		case GD_D_MO_Y:
			$graph_date = 'd' . $datecharacter . 'm' . $datecharacter . 'Y H:i:s';

			break;
		case GD_D_MN_Y:
			$graph_date = 'd' . $datecharacter . 'M' . $datecharacter . 'Y H:i:s';

			break;
		case GD_Y_MO_D:
			$graph_date = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';

			break;
		case GD_Y_MN_D:
			$graph_date = 'Y' . $datecharacter . 'M' . $datecharacter . 'd H:i:s';

			break;
	}

	reports_log(__FUNCTION__ . ', datefmt: ' . $graph_date, false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

	return $graph_date;
}

/**
 * reports_interval_start - computes the next start time for the given set of parameters
 *
 * @param int $interval  - given interval
 * @param int $count     - given repeat count
 * @param int $offset    - offset in seconds to be added to the new start time
 * @param int $timestamp - current start time for report
 *
 * @return int - new timestamp
 */
function reports_interval_start($interval, $count, $offset, $timestamp) {
	global $reports_interval;
	reports_log(__FUNCTION__ . ', interval: ' . $reports_interval[$interval] . ' count: ' . $count . ' offset: ' . $offset . ' timestamp: ' . date('Y/m/d H:i:s', $timestamp), false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

	switch ($interval) {
		case REPORTS_SCHED_INTVL_MINUTE:
			# add $count minutes to current mailtime
			$ts = utime_add($timestamp, 0, 0, 0, 0, $count, $offset);

			break;
		case REPORTS_SCHED_INTVL_HOUR:
			# add $count hours to current mailtime
			$ts = utime_add($timestamp, 0, 0, 0, $count, 0, $offset);

			break;
		case REPORTS_SCHED_INTVL_DAY:
			# add $count days to current mailtime
			$ts = utime_add($timestamp, 0, 0, $count, 0, 0, $offset);

			break;
		case REPORTS_SCHED_INTVL_WEEK:
			# add $count weeks = 7*$count days to current mailtime
			$ts = utime_add($timestamp, 0, 0, 7 * $count, 0, 0, $offset);

			break;
		case REPORTS_SCHED_INTVL_MONTH_DAY:
			# add $count months to current mailtime
			$ts = utime_add($timestamp, 0, $count, 0, 0, 0, $offset);

			break;
		case REPORTS_SCHED_INTVL_MONTH_WEEKDAY:
			# add $count months to current mailtime, but if this is the nth weekday, it must be the same nth weekday in the new month
			# e.g. if this is currently the 3rd Monday of current month
			# ist must be the 3rd Monday of the new month as well
			$weekday      = date('l', $timestamp);
			$day_of_month = date('j', $timestamp);
			$nth_weekday  = ceil($day_of_month / 7);

			$date_str     = '+' . $count . ' months';
			$month_base   = strtotime($date_str, $timestamp);
			$new_month    = mktime(date('H', $month_base), date('i', $month_base), date('s', $month_base), date('m', $month_base), 1, date('Y', $month_base));

			$date_str     = '+' . ($nth_weekday - 1) . ' week ' . $weekday;
			$base         = strtotime($date_str, $new_month);
			$ts           = mktime(date('H', $month_base), date('i', $month_base), date('s', $month_base), date('m', $base), date('d', $base), date('Y', $base));

			break;
		case REPORTS_SCHED_INTVL_YEAR:
			# add $count years to current mailtime
			$ts = utime_add($timestamp, $count, 0, 0, 0, 0, $offset);

			break;

		default:
			$ts = 0;

			break;
	}

	$now = time();

	if ($ts < $now) {
		$ts = $now;
	}

	return $ts;
}

/**
 * utime_add - add offsets to given timestamp
 *
 * @param int $timestamp- base timestamp
 * @param int $yr		- offset in years
 * @param int $mon		- offset in months
 * @param int $day		- offset in days
 * @param int $hr		- offset in hours
 * @param int $min		- offset in minutes
 * @param int $sec		- offset in seconds
 *
 * @return				- unix time
 */
function utime_add($timestamp, $yr = 0, $mon = 0, $day = 0, $hr = 0, $min = 0, $sec = 0) {
	$dt = localtime($timestamp, true);

	$unixnewtime = mktime(
		$dt['tm_hour'] + $hr,
		$dt['tm_min'] + $min,
		$dt['tm_sec'] + $sec,
		$dt['tm_mon'] + 1 + $mon,
		$dt['tm_mday'] + $day,
		$dt['tm_year'] + 1900 + $yr
	);

	return $unixnewtime;
}

/**
 * reports_log - logs a string to Cacti's log file or optionally to the browser
 *
 * @param string $string  - the string to append to the log file
 * @param bool $output    - whether to output the log line to the browser using pring() or not
 * @param string $environ - tell's from where the script was called from
 * @param mixed $level
 */
function reports_log($string, $output = false, $environ = 'REPORTS', $level = POLLER_VERBOSITY_NONE) {
	# Define REPORTS_DEBUG if not already set
	if (!defined('REPORTS_DEBUG')) {
		if (function_exists('read_config_option')) {
			define('REPORTS_DEBUG', read_config_option('reports_log_verbosity'));
		} else {
			define('REPORTS_DEBUG', 1);
		}
	}

	# if current verbosity >= level of current message, print it
	if (strpos($string, 'STATS') !== false) {
		cacti_log($string, $output, 'SYSTEM');
	} elseif (REPORTS_DEBUG >= $level) {
		cacti_log($string, $output, $environ);
	}
}

/**
 * generate_report - create the complete mail for a single report and send it
 *
 * @param array $schedule_id - the id of the queued up report that is being processed
 * @param array $report      - complete row of reports table for the report to work upon
 * @param bool $force        - when forced, lastsent time will not be entered (e.g. Send Now)
 */
function generate_report($schedule_id, $report, $force = false) {
	global $config, $alignment, $reports_interval, $attach_types;

	include_once(CACTI_PATH_LIBRARY . '/time.php');
	include_once(CACTI_PATH_LIBRARY . '/rrd.php');
	include_once(CACTI_PATH_LIBRARY . '/html_reports.php');

	reports_log(__FUNCTION__ . ', report_id: ' . $report['id'], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

	if (!reports_html_account_exists($report['user_id'])) {
		reports_html_report_disable($report['id']);

		return false;
	}

	if (!isset_request_var('style') || get_request_var('style') == 'true') {
		$theme = 'modern';
	} else {
		$theme = get_selected_theme();
	}

	$body  = reports_generate_html($report['id'], REPORTS_OUTPUT_EMAIL, $theme);

	$start_time  = time();
	$start       = microtime(true);

	# get config option for first-day-of-the-week
	$first_weekdayid = read_user_setting('first_weekdayid', false, false, $report['user_id']);

	$offset      = 0;
	$graphs      = array();
	$attachments = array();

	while (true) {
		$pos = strpos($body, '<GRAPH:', $offset);

		if ($pos) {
			$offset         = $pos + 7;
			$graph          = substr($body, $pos + 7, 10);
			$arr            = explode(':', $graph);
			$arr1           = explode('>', $arr[1]);
			$local_graph_id = $arr[0];
			$timespan       = $arr1[0];

			$graphs[$local_graph_id . ':' . $timespan] = $local_graph_id;
		} else {
			break;
		}
	}

	$user = $report['user_id'];

	$xport_meta = array();

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $key => $local_graph_id) {
			$arr    = explode(':', $key);
			$timesp = $arr[1];

			$timespan = array();
			# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
			get_timespan($timespan, $start_time, $timesp, $first_weekdayid);

			# provide parameters for rrdtool graph
			$graph_data_array = array(
				'graph_start'    => $timespan['begin_now'],
				'graph_end'      => $timespan['end_now'],
				'graph_width'    => $report['graph_width'],
				'graph_height'   => $report['graph_height'],
				'image_format'   => 'png',
				'graph_theme'    => $theme,
				'output_flag'    => RRDTOOL_OUTPUT_STDOUT,
				'disable_cache'  => true
			);

			if ($report['thumbnails'] == 'on') {
				$graph_data_array['graph_nolegend'] = true;
			}

			switch($report['attachment_type']) {
				case REPORTS_TYPE_INLINE_PNG:
					$attachments[] = array(
						'attachment'     => base64_encode(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user)),
						'filename'       => 'graph_' . $local_graph_id . '.png',
						'mime_type'      => 'image/png',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'inline'
					);

					break;
				case REPORTS_TYPE_INLINE_JPG:
					$attachments[] = array(
						'attachment'     => base64_encode(png2jpeg(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user))),
						'filename'       => 'graph_' . $local_graph_id . '.jpg',
						'mime_type'      => 'image/jpg',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'inline'
					);

					break;
				case REPORTS_TYPE_INLINE_GIF:
					$attachments[] = array(
						'attachment'     => base64_encode(png2gif(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user))),
						'filename'       => 'graph_' . $local_graph_id . '.gif',
						'mime_type'      => 'image/gif',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'inline'
					);

					break;
				case REPORTS_TYPE_ATTACH_PNG:
					$attachments[] = array(
						'attachment'     => base64_encode(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user)),
						'filename'       => 'graph_' . $local_graph_id . '.png',
						'mime_type'      => 'image/png',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'attachment'
					);

					break;
				case REPORTS_TYPE_ATTACH_JPG:
					$attachments[] = array(
						'attachment'     => base64_encode(png2jpeg(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user))),
						'filename'       => 'graph_' . $local_graph_id . '.jpg',
						'mime_type'      => 'image/jpg',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'attachment'
					);

					break;
				case REPORTS_TYPE_ATTACH_GIF:
					$attachments[] = array(
						'attachment'     => base64_encode(png2gif(rrdtool_function_graph($local_graph_id, '', $graph_data_array, null, $xport_meta, $user))),
						'filename'       => 'graph_' . $local_graph_id . '.gif',
						'mime_type'      => 'image/gif',
						'local_graph_id' => $local_graph_id,
						'timespan'       => $timesp,
						'inline'         => 'attachment'
					);

					break;
			}
		}
	}

	if ($report['subject'] != '') {
		$subject = $report['subject'];
	} else {
		$subject = $report['name'];
	}

	if (!isset($report['bcc'])) {
		$report['bcc'] = '';
	}

	if (api_plugin_installed('thold') && $report['notify_list'] > 0) {
		$nl_to_emails  = get_notification_emails($report['notify_list'], 'to');
		$nl_bcc_emails = get_notification_emails($report['notify_list'], 'bcc');

		if ($nl_to_emails != '') {
			$report['email'] .= ($report['email'] != '' ? ', ':'') . $nl_to_emails;
		}

		if ($nl_bcc_emails != '') {
			$report['bcc'] .= ($report['bcc'] != '' ? ', ':'') . $nl_bcc_emails;
		}
	}

	$v = CACTI_VERSION;

	$headers['User-Agent'] = 'Cacti-Reports-v' . $v;

	if ($schedule_id > 0) {
		$raw_data = $output_raw = $output_text = '';

		$error = reports_log_and_notify($schedule_id, $start_time, 'html', 'reports', $report['id'], $subject, $raw_data, $output_raw, $body, $output_text, $attachments, $headers);
	} else {
		$error = mailer(
			array($report['from_email'], $report['from_name']),
			$report['email'],
			'',
			$report['bcc'],
			'',
			$subject,
			$body,
			'Cacti Reporting Requires and HTML Email Client',
			$attachments,
			$headers
		);
	}

	$end = microtime(true);

	db_execute_prepared('UPDATE reports
		SET last_started = ?, last_runtime = ?
		WHERE id = ?',
		array(date('Y-m-d H:i:s', $start_time), $end - $start, $report['id']));

	if ($error != '') {
		if (isset_request_var('id')) {
			raise_message('report_message', __esc('Problems sending Report \'%s\' Problem with e-mail Subsystem Error is \'%s\'', $report['name'], $error), MESSAGE_LEVEL_ERROR);

		} else {
			reports_log(__FUNCTION__ . ", Problems sending Report '" . $report['name'] . "'.  Problem with e-mail Subsystem Error is '$error'", false, 'REPORTS', POLLER_VERBOSITY_LOW);
		}

		return false;
	}

	if (isset($_REQUEST)) {
		raise_message('report_message', __esc('Report \'%s\' Sent Successfully', $report['name']), MESSAGE_LEVEL_INFO);
	}

	return true;
}

/** reports_load_format_file  read the format file from disk and determines it's formatting
 * @param string $format_file		- the file to read from the formats directory
 * @param string $output			- the html and css output from that file
 * @param bool $report_tag_included - a boolean that informs the caller if the report tag is present
 * @param mixed $theme
 * @return bool						- whether or not the format file was processed correctly
 */
function reports_load_format_file($format_file, &$output, &$report_tag_included, &$theme) {
	global $config;

	$contents = array();

	if ($format_file == '') {
		$format_file = 'cacti_group.format';
	}

	$format_file = CACTI_PATH_FORMATS .  '/' . $format_file;

	if (file_exists($format_file) && is_readable($format_file)) {
		$contents = file($format_file);
	}

	$output              = '';
	$report_tag_included = false;

	if (cacti_sizeof($contents)) {
		foreach ($contents as $line) {
			$line = trim($line);

			if (substr_count($line, '<REPORT>')) {
				$report_tag_included = true;
			}

			if (substr($line, 0, 1) != '#') {
				$output .= $line . PHP_EOL;
			} elseif (strstr($line, 'Theme:') !== false) {
				$tparts = explode(':', $line);
				$theme  = trim($tparts[1]);
			}
		}
	} else {
		return false;
	}

	return true;
}

/**
 * determine, if the given tree has graphs; taking permissions into account
 * @param int $tree_id			- tree id
 * @param int $branch_id		- branch id
 * @param int $effective_user	- user id
 * @param string $search_key	- search key
 */
function reports_tree_has_graphs($tree_id, $branch_id, $effective_user, $search_key) {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/html_tree.php');

	$sql_where  = '';
	$sql_swhere = '';
	$graphs     = array();
	$new_graphs = array();

	if ($search_key != '') {
		$sql_swhere = " AND gtg.title_cache REGEXP '" . $search_key . "'";
	}

	$device_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_tree_items
		WHERE id = ?
		AND graph_tree_id = ?',
		array($branch_id, $tree_id));

	if ($device_id > 0) {
		$graphs = array_rekey(db_fetch_assoc("SELECT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			WHERE gl.host_id = $device_id
			$sql_swhere"), 'id', 'id');
	} else {
		if ($branch_id > 0) {
			$sql_where .= ' AND gti.parent=' . $branch_id;
		} else {
			$sql_where .= ' AND parent=0';
		}

		$graphs = array_rekey(db_fetch_assoc("SELECT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_tree_items AS gti
			ON gti.local_graph_id=gl.id
			INNER JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gti.local_graph_id
			WHERE gti.local_graph_id>0
			AND graph_tree_id=$tree_id
			$sql_where
			$sql_swhere"), 'id', 'id');

		/* get host graphs first */
		$graphs = array_merge($graphs, array_rekey(db_fetch_assoc("SELECT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_tree_items AS gti
			ON gl.host_id=gti.host_id
			INNER JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			WHERE gti.graph_tree_id=$tree_id
			AND gti.host_id>0
			$sql_where
			$sql_swhere"), 'id', 'id'));
	}

	/* verify permissions */
	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $key => $id) {
			if (!is_graph_allowed($id, $effective_user)) {
				unset($graphs[$key]);
			}
		}
	}

	return cacti_sizeof($graphs);
}

function reports_generate_history_html($history_id, $output = REPORTS_OUTPUT_STDOUT) {
	$data   = db_fetch_row_prepared('SELECT *
		FROM reports_log
		WHERE id = ?',
		array($history_id));

	if (cacti_sizeof($data)) {
		$oreport = db_fetch_row_prepared('SELECT *
			FROM reports
			WHERE id = ?',
			array($data['source_id']));

		/* here is the report html as sent to the user */
		$report = $data['report_html_output'];

		/* strip out the css/format if selected */
		if (get_request_var('style') == 'false') {
			$instyle  = false;
			$pinstyle = null;
			$nreport  = '';
			$lines    = explode("\n", $report);

			foreach($lines as $l) {
				if (strpos($l, '<style') !== false) {
					$instyle = true;
				} elseif (strpos($l, '</style>') !== false) {
					$instyle = false;
				}

				if (($pinstyle === null && !$instyle) || $pinstyle === false) {
					$nreport .= $l . PHP_EOL;
				}

				$pinstyle = $instyle;
			}

			$report = $nreport;
		}

		/* make generic style changes to match Cacti when not showing with style */
		if (get_request_var('style') == 'false') {
			$report = str_replace('report_table', 'cactiTable', $report);
			$report = str_replace('title_row', 'cactiTableTitleRow', $report);
			$report = str_replace('text_row', 'cactiTableTitleRow', $report);
			$report = str_replace('text', 'center', $report);
			$report = str_replace('title', 'center', $report);
			$report = str_replace('image_table', 'cactiTable', $report);
			$report = str_replace('image_row', 'tableRow', $report);
			$report = str_replace('image_column\'', 'image_column\' style=\'text-align:center;\'', $report);
			$report = str_replace('image_column"', 'image_column" style="text-align:center;"', $report);
			$report = str_replace('<table>', '<table class="cactiTable">', $report);
		}

		$graph_data = json_decode(base64_decode($data['report_attachments']), true);

		foreach($graph_data as $index => $graph) {
			$report = str_replace('<GRAPH:' . $graph['local_graph_id'] . ':' . $graph['timespan'] . '>',
				'<img class="graph" src="data:image/png;base64,' . $graph['attachment'] . '">', $report);
		}

		print $report;
	}
}

function reports_remove_history($history_id, $report_id = 0) {
	if ($report_id == 0) {
		$report_id = db_fetch_cell_prepared('SELECT source_id FROM reports_log WHERE id = ?', array($history_id));
	}

	$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', array($report_id));

	if (is_reports_admin() || $report['user_id'] == SESS_USER_ID) {
		db_execute_prepared('DELETE FROM reports_log WHERE id = ?', array($history_id));

		raise_message('remove_message', __('Report \'%s\' History Removed by user \'%s\' or a Report Administrator can remove the report.', $report['name'], get_username($_SESSION[SESS_USER_ID])), MESSAGE_LEVEL_INFO);
	} else {
		raise_message('remove_error', __('Only the owning user \'%s\' or a Report Administrator can remove the report.', get_username($user_id)), MESSAGE_LEVEL_ERROR);
	}
}

/** reports_generate_html  print report to html for online verification
 * @param int $reports_id	- id of report report
 * @param int $output		- type of output
 * @param mixed $theme
 * @return string			- generated html output
 */
function reports_generate_html($reports_id, $output = REPORTS_OUTPUT_STDOUT, &$theme = '') {
	global $config;
	global $alignment;

	include_once(CACTI_PATH_LIBRARY . '/time.php');

	$outstr = '';
	$report = db_fetch_row_prepared('SELECT *
		FROM reports
		WHERE id = ?',
		array($reports_id));

	$reports_items = db_fetch_assoc_prepared('SELECT *
		FROM reports_items
		WHERE report_id = ?
		ORDER BY sequence',
		array($report['id']));

	$format_data = '';
	$report_tag  = false;
	$format_ok   = false;

	if ($theme == '') {
		$theme = 'modern';
	}

	$time = time();
	# get config option for first-day-of-the-week
	$first_weekdayid = read_user_setting('first_weekdayid');

	/* process the format file as applicable */
	if (!isset_request_var('style') || get_request_var('style') == 'true') {
		if ($report['cformat'] == 'on') {
			$format_ok = reports_load_format_file($report['format_file'], $format_data, $report_tag, $theme);
		}

		if ($output == REPORTS_OUTPUT_STDOUT) {
			$format_data = str_replace('<html>', '', $format_data);
			$format_data = str_replace('</html>', '', $format_data);
			$format_data = str_replace('<body>', '', $format_data);
			$format_data = str_replace('</body>', '', $format_data);
			$format_data = preg_replace('#(<head>).*?(</head>)#si', '', $format_data);
			$format_data = str_replace('<head>', '', $format_data);
			$format_data = str_replace('</head>', '', $format_data);
		}
	}

	if (get_request_var('style') == 'false') {
		$format_ok = true;
	}

	if ($format_ok && $report_tag) {
		$include_body = false;
	} else {
		$include_body = true;
	}

	reports_log(__FUNCTION__ . ', items found: ' . cacti_sizeof($reports_items), false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

	if (cacti_sizeof($reports_items)) {
		if ($output == REPORTS_OUTPUT_EMAIL && $include_body) {
			$outstr .= '<body>' . PHP_EOL;
		}

		if ($format_ok) {
			$outstr .= "\t<table class='report_table'>" . PHP_EOL;
		} else {
			$outstr .= "\t<table class='report_table' " . ($output == REPORTS_OUTPUT_STDOUT ? "style='background-color:#F9F9F9;'":'') . '>' . PHP_EOL;
		}

		$outstr .= "\t\t<tr class='title_row'>" . PHP_EOL;

		if ($format_ok) {
			$outstr .= "\t\t\t<td class='title'>" . PHP_EOL;
		} else {
			$outstr .= "\t\t\t<td class='title' style='text-align:" . $alignment[$report['alignment']] . ';font-size:' . $report['font_size'] . "pt;'>" . PHP_EOL;
		}

		$outstr .= "\t\t\t\t<h3>" . html_escape($report['name']) . '</h3>' . PHP_EOL;
		$outstr .= "\t\t\t</td>" . PHP_EOL;
		$outstr .= "\t\t</tr>" . PHP_EOL;
		# this function should be called only at the appropriate targeted time when in batch mode
		# but for preview mode we can't use the targeted time
		# so let's use time()
		$time = time();
		# get config option for first-day-of-the-week
		$first_weekdayid = read_user_setting('first_weekdayid');

		/* don't cache previews */
		$_SESSION['custom'] = 'true';

		$column = 0;

		foreach ($reports_items as $item) {
			reports_log(__FUNCTION__ . ', item_id: ' . $item['id'] . ' local_graph_id: ' . $item['local_graph_id'], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

			if ($item['item_type'] == REPORTS_ITEM_GRAPH) {
				if (is_graph_allowed($item['local_graph_id'], $report['user_id'])) {
					$timespan = array();
					# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
					get_timespan($timespan, $time, $item['timespan'], $first_weekdayid);

					if ($column == 0) {
						$outstr .= "\t\t<tr class='image_row'>" . PHP_EOL;

						if ($format_ok) {
							$outstr .= "\t\t\t<td>" . PHP_EOL;
						} else {
							$outstr .= "\t\t\t<td style='text-align:" . $alignment[$item['align']] . ";'>" . PHP_EOL;
						}

						if ($format_ok) {
							$outstr .= "\t\t\t\t<table class='image_table'>" . PHP_EOL;
						} else {
							$outstr .= "\t\t\t\t<table>" . PHP_EOL;
						}

						$outstr .= "\t\t\t\t\t<tr>" . PHP_EOL;
					}

					if ($format_ok) {
						$outstr .= "\t\t\t\t\t\t<td class='image_column'>" . PHP_EOL;
					} else {
						$outstr .= "\t\t\t\t\t\t<td style='padding:5px;text-align:" . $alignment[$item['align']] . ";'>" . PHP_EOL;
					}

					$outstr .= "\t\t\t\t\t\t\t" . reports_graph_image($report, $item, $timespan, $output, $theme) . PHP_EOL;
					$outstr .= "\t\t\t\t\t\t</td>" . PHP_EOL;

					if ($report['graph_columns'] > 1) {
						$column = ($column + 1) % ($report['graph_columns']);
					}

					if ($column == 0) {
						$outstr .= "\t\t\t\t\t</tr>" . PHP_EOL;
						$outstr .= "\t\t\t\t</table>" . PHP_EOL;
						$outstr .= "\t\t\t</td>" . PHP_EOL;
						$outstr .= "\t\t</tr>" . PHP_EOL;
					}
				}
			} elseif ($item['item_type'] == REPORTS_ITEM_TEXT) {
				$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

				if ($format_ok) {
					$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
				} else {
					$outstr .= "\t\t\t<td style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;' class='text'>" . PHP_EOL;
				}

				$outstr .= "\t\t\t\t" . html_escape($item['item_text']) . PHP_EOL;
				$outstr .= "\t\t\t</td>" . PHP_EOL;
				$outstr .= "\t\t</tr>" . PHP_EOL;

				/* start a new section */
				$column = 0;
			} elseif ($item['item_type'] == REPORTS_ITEM_HOST) {
				if (is_tree_allowed($item['host_id'], $report['user_id'])) {
					$outstr .= reports_expand_device($report, $item, $item['host_id'], $output, $format_ok, $theme);
				}
			} elseif ($item['item_type'] == REPORTS_ITEM_TREE) {
				if (is_tree_allowed($item['tree_id'], $report['user_id'])) {
					if ($item['tree_cascade'] == 'on') {
						$outstr .= expand_branch($report, $item, $item['branch_id'], $output, $format_ok, $theme);
					} elseif (reports_tree_has_graphs($item['tree_id'], $item['branch_id'], $report['user_id'], $item['graph_name_regexp'])) {
						$outstr .= reports_expand_tree($report, $item, $item['branch_id'], $output, $format_ok, $theme, false);
					}
				}
			} else {
				$outstr .= '<tr><td><br><hr><br></td></tr>';
			}
		}

		$outstr .= "\t</table>" . PHP_EOL;

		if ($output == REPORTS_OUTPUT_EMAIL && $include_body) {
			$outstr .= '</body>';
		}
	}

	if (get_request_var('style') == 'false') {
		$outstr = str_replace('report_table', 'cactiTable', $outstr);
		$outstr = str_replace('title_row', 'cactiTableTitleRow', $outstr);
		$outstr = str_replace('text_row', 'cactiTableTitleRow', $outstr);
		$outstr = str_replace('text', 'center', $outstr);
		$outstr = str_replace('title', 'center', $outstr);
		$outstr = str_replace('image_table', 'cactiTable', $outstr);
		$outstr = str_replace('image_row', 'tableRow', $outstr);
		$outstr = str_replace('image_column\'', 'image_column\' style=\'text-align:center;\'', $outstr);
		$outstr = str_replace('image_column"', 'image_column" style="text-align:center;"', $outstr);
		$outstr = str_replace('<table>', '<table class="cactiTable">', $outstr);
	}

	if ($format_ok) {
		if ($report_tag) {
			return str_replace('<REPORT>', $outstr, $format_data);
		} else {
			return $format_data . PHP_EOL . $outstr;
		}
	} else {
		return $outstr;
	}
}

function expand_branch(&$report, &$item, $branch_id, $output, $format_ok, $theme = 'modern') {
	$outstr = '';

	if (reports_tree_has_graphs($item['tree_id'], $branch_id, $report['user_id'], $item['graph_name_regexp'])) {
		$outstr .= reports_expand_tree($report, $item, $branch_id, $output, $format_ok, $theme, true);
	}

	$tree_branches = db_fetch_assoc_prepared('SELECT id
		FROM graph_tree_items
		WHERE parent = ?
		AND host_id = 0
		AND local_graph_id = 0
		AND graph_tree_id = ?
		ORDER BY position', array($branch_id, $item['tree_id']));

	if (cacti_sizeof($tree_branches)) {
		foreach ($tree_branches as $branch) {
			$outstr .= expand_branch($report, $item, $branch['id'], $output, $format_ok, $theme);
		}
	}

	return $outstr;
}

/**
 * return html code for an embedded image
 * @param array $report	- parameters for this report mail report
 * @param $item			- current graph item
 * @param $timespan		- timespan
 * @param $output		- type of output
 * @param mixed $theme
 * @return string		- generated html
 */
function reports_graph_image($report, $item, $timespan, $output, $theme = 'modern') {
	global $config;

	$out = '';

	if ($output == REPORTS_OUTPUT_STDOUT) {
		$out = "<img class='image' alt='' src='" . html_escape(CACTI_PATH_URL . 'graph_image.php' .
			'?graph_width=' . $report['graph_width'] .
			'&graph_height=' . $report['graph_height'] .
			($report['thumbnails'] == 'on' ? '&graph_nolegend=true':'') .
			'&local_graph_id=' . $item['local_graph_id'] .
			'&graph_start=' . $timespan['begin_now'] .
			'&graph_end=' . $timespan['end_now'] .
			'&graph_theme=' . $theme .
			'&image_format=png' .
			'&rra_id=0') . "'>";
	} else {
		$out = '<GRAPH:' . $item['local_graph_id'] . ':' . $item['timespan'] . '>';
	}

	if ($report['graph_linked'] == 'on') {
		if (substr(read_config_option('base_url'), 0, 4) != 'http') {
			if (read_config_option('force_https') == 'on') {
				$prefix = 'https://';
			} else {
				$prefix = 'http://';
			}

			set_config_option('base_url', $prefix . read_config_option('base_url'));
		}

		$out = "<a href='" . html_escape(read_config_option('base_url', true) . '/graph.php?action=view&local_graph_id=' . $item['local_graph_id'] . '&rra_id=0') . "'>" . $out . '</a>';
	}

	return $out . PHP_EOL;
}

/**
 * expand a device for including into report
 *
 * @param array $report		- parameters for this report mail report
 * @param int $item			- current graph item
 * @param int $device_id    - the id of the host to include
 * @param int $output		- type of output
 * @param bool $format_ok	- use css styling
 * @param mixed $theme
 *
 * @return string			- html
 */
function reports_expand_device(&$report, $item, $device_id, $output, $format_ok, $theme = 'modern') {
	global $config, $alignment;

	include(CACTI_PATH_INCLUDE . '/global_arrays.php');
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');
	include_once(CACTI_PATH_LIBRARY . '/html_tree.php');
	include_once(CACTI_PATH_LIBRARY . '/html_utility.php');

	$time = time();

	# get config option for first-day-of-the-week
	$first_weekdayid = read_user_setting('first_weekdayid');

	$user = $report['user_id'];

	$timespan = array();

	# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
	get_timespan($timespan, $time, $item['timespan'], $first_weekdayid);

	$outstr = '';

	$graphs = array();

	$sql_where       = '';
	$title_delimiter = '';
	$search_key      = '';

	$description = db_fetch_cell_prepared('SELECT h.description
		FROM host AS h
		WHERE h.id = ?',
		array($device_id));

	if ($description != '') {
		$title = $title_delimiter . __('Device:') . " " . html_escape($description);
		$title_delimiter = ' > ';
	}

	if ($item['graph_template_id'] == -1) {
		$graph_templates = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT
				gt.id, gt.name
				FROM graph_local AS gl
				INNER JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				INNER JOIN graph_templates_graph AS gtg
				ON gtg.local_graph_id=gl.id
				WHERE gl.host_id = ?
				ORDER BY gt.name',
				array($device_id)),
			'id', 'name'
		);

		/* for graphs without a template */
		array_push($graph_templates,
			array(
				'id'   => '0',
				'name' => __('(No Graph Template)')
			)
		);
	} else {
		$graph_templates = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT
				gt.id, gt.name
				FROM graph_templates AS gt
				WHERE id = ?
				ORDER BY gt.name',
				array($item['graph_template_id'])),
			'id', 'name'
		);
	}

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $id => $name) {
			if (!is_graph_template_allowed($id, $user)) {
				unset($graph_templates[$id]);
			}
		}
	}

	$outgraphs = array();

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $id => $name) {
			if ($item['graph_name_regexp'] != '') {
				$sql_where .= " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'";
			}

			$graphs = db_fetch_assoc_prepared("SELECT
				gtg.local_graph_id, gtg.title_cache
				FROM graph_local AS gl
				INNER JOIN graph_templates_graph AS gtg
				ON gtg.local_graph_id=gl.id
				WHERE gl.graph_template_id = ?
				AND gl.host_id = ?
				$sql_where
				ORDER BY gtg.title_cache",
				array($id, $device_id));

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $key => $graph) {
					if (!is_graph_allowed($graph['local_graph_id'], $user)) {
						unset($graphs[$key]);
					}
				}
			}

			$outgraphs = array_merge($outgraphs, $graphs);
		}

		if (cacti_sizeof($outgraphs)) {
			/* let's sort the graphs naturally */
			usort($outgraphs, 'necturally_sort_graphs');

			/* start graph display */
			if ($title != '') {
				$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

				if ($format_ok) {
					$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
				} else {
					$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
				}

				$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
				$outstr .= "\t\t\t</td>" . PHP_EOL;
				$outstr .= "\t\t</tr>" . PHP_EOL;
			}

			$outstr .= reports_graph_area($outgraphs, $report, $item, $timespan, $output, $format_ok, $theme);
		}
	}

	return $outstr;
}

/**
 * expand a tree for including into report
 *
 * @param array $report		- parameters for this report mail report
 * @param int $item			- current graph item
 * @param int $output		- type of output
 * @param bool $format_ok	- use css styling
 * @param bool $nested		- nested tree?
 * @param mixed $parent
 * @param mixed $theme
 *
 * @return string			- html
 */
function reports_expand_tree(&$report, $item, $parent, $output, $format_ok, $theme = 'modern', $nested = false) {
	global $config, $alignment;

	include(CACTI_PATH_INCLUDE . '/global_arrays.php');
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');
	include_once(CACTI_PATH_LIBRARY . '/html_tree.php');
	include_once(CACTI_PATH_LIBRARY . '/html_utility.php');

	$tree_id = $item['tree_id'];
	$leaf_id = $item['branch_id'];

	$time = time();
	# get config option for first-day-of-the-week
	$first_weekdayid = read_user_setting('first_weekdayid');

	$user = $report['user_id'];

	$timespan = array();

	# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
	get_timespan($timespan, $time, $item['timespan'], $first_weekdayid);

	if (empty($tree_id)) {
		return;
	}

	$device_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_tree_items
		WHERE id = ?
		AND graph_tree_id = ?',
		array($parent, $tree_id));

	$outstr = '';

	if ($device_id == 0) {
		$leaves = db_fetch_assoc_prepared('SELECT *
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND parent = ?
			ORDER BY position',
			array($tree_id, $parent));
	} elseif (is_device_allowed($device_id, $user)) {
		$leaves = db_fetch_assoc_prepared('SELECT *
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND id = ?
			ORDER BY position',
			array($tree_id, $parent));
	} else {
		$leaves = array();
	}

	$graphs = array();

	if (cacti_sizeof($leaves)) {
		foreach ($leaves as $leaf) {
			$sql_where       = '';
			$title           = '';
			$title_delimiter = '';
			$search_key      = '';
			$host_name       = '';
			$graph_name      = '';
			$leaf_id         = $leaf['id'];

			if (!empty($leaf_id)) {
				if ($leaf['local_graph_id'] == 0 && $leaf['host_id'] == 0) {
					$leaf_type = 'header';
				} elseif ($leaf['host_id'] > 0) {
					$leaf_type = 'host';
				} else {
					$leaf_type = 'graph';
				}
			} else {
				$leaf_type = 'header';
			}

			/* get information for the headers */
			if (!empty($tree_id)) {
				$tree_name = db_fetch_cell_prepared('SELECT name
					FROM graph_tree
					WHERE id = ?',
					array($tree_id));
			}

			if (!empty($parent)) {
				$leaf_name = db_fetch_cell_prepared('SELECT title
					FROM graph_tree_items
					WHERE id = ?',
					array($parent));
			}

			if (!empty($leaf_id)) {
				$host_name = db_fetch_cell_prepared('SELECT h.description
					FROM graph_tree_items AS gti
					INNER JOIN host AS h
					ON h.id = gti.host_id
					WHERE gti.id = ?',
					array($leaf_id));
			}

			if ($leaf_type == 'graph') {
				$graph_name = db_fetch_cell_prepared('SELECT
					gtg.title_cache AS title
					FROM graph_templates_graph AS gtg
					WHERE gtg.local_graph_id = ?',
					array($leaf['local_graph_id']));
			}

			if (!empty($tree_name) && empty($leaf_name) && empty($host_name)) {
				$title           = $title_delimiter . __('Tree:') . " $tree_name";
				$title_delimiter = ' > ';
			}

			if (!empty($leaf_name)) {
				$title .= $title_delimiter . " $leaf_name";
				$title_delimiter = ' > ';
			}

			if (!empty($host_name)) {
				$title .= $title_delimiter . " $host_name";
				$title_delimiter = ' > ';
			}

			if (!empty($graph_name) && !$nested) {
				$title .= $title_delimiter . " $graph_name";
				$title_delimiter = ' > ';
			}

			if ($item['graph_name_regexp'] != '') {
				$sql_where .= " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'";
			}

			if ($leaf_type == 'header' && $nested) {
				$mygraphs = array();

				$graphs = db_fetch_assoc("SELECT DISTINCT
					gti.local_graph_id, gtg.title_cache
					FROM graph_tree_items AS gti
					INNER JOIN graph_local AS gl
					ON gl.id=gti.local_graph_id
					INNER JOIN graph_templates_graph AS gtg
					ON gtg.local_graph_id=gl.id
					WHERE gti.graph_tree_id=$tree_id
					AND gti.parent=$parent
					AND gti.local_graph_id>0
					$sql_where
					ORDER BY gti.position");

				foreach ($graphs as $key => $graph) {
					if (is_graph_allowed($graph['local_graph_id'], $user)) {
						$mygraphs[$graph['local_graph_id']] = $graph;
					}
				}

				if (cacti_sizeof($mygraphs)) {
					/* start graph display */
					if ($title != '') {
						$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

						if ($format_ok) {
							$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
						} else {
							$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
						}
						$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
						$outstr .= "\t\t\t</td>" . PHP_EOL;
						$outstr .= "\t\t</tr>" . PHP_EOL;
					}

					$outstr .= reports_graph_area($mygraphs, $report, $item, $timespan, $output, $format_ok, $theme);
				}
			} elseif ($leaf_type == 'graph' && $nested) {
				$gr_where = '';

				if ($item['graph_name_regexp'] != '') {
					$gr_where .= " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'";
				}

				$graph = db_fetch_row('SELECT local_graph_id, title_cache
					FROM graph_templates_graph
					WHERE local_graph_id=' . $leaf['local_graph_id'] . $gr_where);

				if (cacti_sizeof($graph)) {
					if (is_graph_allowed($graph['local_graph_id'], $user)) {
						$graphs[$graph['local_graph_id']] = $graph;
					}
				}
			} elseif ($leaf_type == 'graph') {
				$gr_where = '';

				if ($item['graph_name_regexp'] != '') {
					$gr_where .= " AND title_cache REGEXP '" . $item['graph_name_regexp'] . "'";
				}

				$graph = db_fetch_cell('SELECT count(*)
					FROM graph_templates_graph
					WHERE local_graph_id=' . $leaf['local_graph_id'] . $gr_where);

				/* start graph display */
				if ($graph > 0) {
					if ($title != '') {
						$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

						if ($format_ok) {
							$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
						} else {
							$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
						}
						$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
						$outstr .= "\t\t\t</td>" . PHP_EOL;
						$outstr .= "\t\t</tr>" . PHP_EOL;
					}
				}

				$graph_list = array(array('local_graph_id' => $leaf['local_graph_id'], 'title_cache' => $graph_name));

				$outstr .= reports_graph_area($graph_list, $report, $item, $timespan, $output, $format_ok, $theme);
			} elseif ($leaf_type == 'host') {
				if ($item['tree_cascade'] == 'on') {
					/* graph template grouping */
					if ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
						$graph_templates = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT
								gt.id, gt.name
								FROM graph_local AS gl
								INNER JOIN graph_templates AS gt
								ON gt.id=gl.graph_template_id
								INNER JOIN graph_templates_graph AS gtg
								ON gtg.local_graph_id=gl.id
								WHERE gl.host_id = ?
								ORDER BY gt.name',
								array($leaf['host_id'])),
							'id', 'name'
						);

						if (cacti_sizeof($graph_templates)) {
							foreach ($graph_templates as $id => $name) {
								if (!is_graph_template_allowed($id, $user)) {
									unset($graph_templates[$id]);
								}
							}
						}

						/* for graphs without a template */
						array_push($graph_templates,
							array(
								'id'   => '0',
								'name' => __('(No Graph Template)')
							)
						);

						$outgraphs = array();

						if (cacti_sizeof($graph_templates)) {
							foreach ($graph_templates as $id => $name) {
								$graphs = db_fetch_assoc('SELECT
									gtg.local_graph_id, gtg.title_cache
									FROM graph_local AS gl
									INNER JOIN graph_templates_graph AS gtg
									ON gtg.local_graph_id=gl.id
									WHERE gl.graph_template_id=' . $id . '
									AND gl.host_id=' . $leaf['host_id'] . "
									$sql_where
									ORDER BY gtg.title_cache");

								if (cacti_sizeof($graphs)) {
									foreach ($graphs as $key => $graph) {
										if (!is_graph_allowed($graph['local_graph_id'], $user)) {
											unset($graphs[$key]);
										}
									}
								}
								$outgraphs = array_merge($outgraphs, $graphs);
							}

							if (cacti_sizeof($outgraphs)) {
								/* let's sort the graphs naturally */
								usort($outgraphs, 'necturally_sort_graphs');

								/* start graph display */
								if ($title != '') {
									$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

									if ($format_ok) {
										$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
									} else {
										$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
									}

									$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
									$outstr .= "\t\t\t</td>" . PHP_EOL;
									$outstr .= "\t\t</tr>" . PHP_EOL;
								}

								$outstr .= reports_graph_area($outgraphs, $report, $item, $timespan, $output, $format_ok, $theme);
							}
						}
					} elseif ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
						/* data query index grouping */
						$data_queries = db_fetch_assoc_prepared('SELECT DISTINCT
							sq.id, sq.name
							FROM graph_local AS gl
							INNER JOIN snmp_query AS sq
							ON gl.snmp_query_id=sq.id
							WHERE gl.host_id = ?
							ORDER BY sq.name',
							array($leaf['host_id']));

						/* for graphs without a data query */
						if (empty($data_query_id)) {
							array_push($data_queries,
								array(
									'id'   => '0',
									'name' => __('(Non Query Based)')
								)
							);
						}

						$i = 0;

						if (cacti_sizeof($data_queries)) {
							foreach ($data_queries as $data_query) {
								/* fetch a list of field names that are sorted by the preferred sort field */
								$sort_field_data = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);

								/* grab a list of all graphs for this host/data query combination */
								$graphs = db_fetch_assoc('SELECT
									gtg.title_cache, gtg.local_graph_id, gl.snmp_index
									FROM graph_local AS gl
									INNER JOIN graph_templates_graph AS gtg
									ON gl.id=gtg.local_graph_id
									WHERE gl.snmp_query_id=' . $data_query['id'] . '
									AND gl.host_id=' . $leaf['host_id'] . "
									$sql_where
									ORDER BY gtg.title_cache");

								if (cacti_sizeof($graphs)) {
									foreach ($graphs as $key => $graph) {
										if (!is_graph_allowed($graph['local_graph_id'], $user)) {
											unset($graphs[$key]);
										}
									}
								}

								/* re-key the results on data query index */
								if (cacti_sizeof($graphs)) {
									if ($i == 0) {
										/* start graph display */
										if ($title != '') {
											$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

											if ($format_ok) {
												$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
											} else {
												$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
											}
											$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
											$outstr .= "\t\t\t</td>" . PHP_EOL;
											$outstr .= "\t\t</tr>" . PHP_EOL;
										}
									}
									$i++;

									$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

									if ($format_ok) {
										$outstr .= "\t\t\t<td class='text'>" . __('Data Query:') . ' ' . $data_query['name'] . PHP_EOL;
										$outstr .= "\t\t\t</td>" . PHP_EOL;
										$outstr .= "\t\t</tr>" . PHP_EOL;
									} else {
										$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . __('Data Query:') . ' ' . $data_query['name'] . PHP_EOL;
										$outstr .= "\t\t\t</td>" . PHP_EOL;
										$outstr .= "\t\t</tr>" . PHP_EOL;
									}

									/* let's sort the graphs naturally */
									usort($graphs, 'necturally_sort_graphs');

									foreach ($graphs as $graph) {
										$snmp_index_to_graph[$graph['snmp_index']][$graph['local_graph_id']] = $graph['title_cache'];
									}
								}

								/* using the sorted data as they key; grab each snmp index from the master list */
								$graph_list = array();

								foreach ($sort_field_data as $snmp_index => $sort_field_value) {
									/* render each graph for the current data query index */
									if (isset($snmp_index_to_graph[$snmp_index])) {
										foreach ($snmp_index_to_graph[$snmp_index] as $local_graph_id => $graph_title) {
											/* reformat the array so it's compatible with the html_graph* area functions */
											array_push($graph_list, array('local_graph_id' => $local_graph_id, 'title_cache' => $graph_title));
										}
									}
								}

								if (cacti_sizeof($graph_list)) {
									$outstr .= reports_graph_area($graph_list, $report, $item, $timespan, $output, $format_ok, $theme);
								}
							}
						}
					}
				} else {
					$graphs = db_fetch_assoc('SELECT
						gtg.local_graph_id, gtg.title_cache
						FROM graph_local AS gl
						INNER JOIN graph_templates_graph AS gtg
						ON gtg.local_graph_id=gl.id
						WHERE gl.host_id=' . $leaf['host_id'] . "
						$sql_where
						ORDER BY gtg.title_cache");

					if (cacti_sizeof($graphs)) {
						foreach ($graphs as $key => $graph) {
							if (!is_graph_allowed($graph['local_graph_id'], $user)) {
								unset($graphs[$key]);
							}
						}
					}

					if (cacti_sizeof($graphs)) {
						/* start graph display */
						if ($title != '') {
							$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

							if ($format_ok) {
								$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
							} else {
								$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
							}
							$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
							$outstr .= "\t\t\t</td>" . PHP_EOL;
							$outstr .= "\t\t</tr>" . PHP_EOL;
						}

						$outstr .= reports_graph_area($graphs, $report, $item, $timespan, $output, $format_ok, $theme);
					}
				}
			}
		}

		if (cacti_sizeof($graphs)) {
			/* start graph display */
			if ($title != '') {
				$outstr .= "\t\t<tr class='text_row'>" . PHP_EOL;

				if ($format_ok) {
					$outstr .= "\t\t\t<td class='text'>" . PHP_EOL;
				} else {
					$outstr .= "\t\t\t<td class='text' style='text-align:" . $alignment[$item['align']] . ';font-size: ' . $item['font_size'] . "pt;'>" . PHP_EOL;
				}
				$outstr .= "\t\t\t\t<h3>$title</h3>" . PHP_EOL;
				$outstr .= "\t\t\t</td>" . PHP_EOL;
				$outstr .= "\t\t</tr>" . PHP_EOL;
			}

			$outstr .= reports_graph_area($graphs, $report, $item, $timespan, $output, $format_ok, $theme);
		}
	}

	return $outstr;
}

/**
 * natural sort function
 * @param $a
 * @param $b
 * @return string
 */
function necturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}

/**
 * draw graph area
 * @param array $graphs		- array of graphs
 * @param array $report		- report parameters
 * @param int $item			- current item
 * @param int $timespan		- requested timespan
 * @param int $output		- type of output
 * @param bool $format_ok	- use css styling
 * @param mixed $theme
 * @return string
 */
function reports_graph_area($graphs, &$report, $item, $timespan, $output, $format_ok, $theme = 'modern') {
	global $alignment;

	$outstr = '';
	$column = 0;

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$item['local_graph_id'] = $graph['local_graph_id'];

			if ($column == 0) {
				$outstr .= "\t\t<tr class='image_row'>" . PHP_EOL;

				if ($format_ok) {
					$outstr .= "\t\t\t<td>" . PHP_EOL;
					$outstr .= "\t\t\t\t<table>". PHP_EOL;
				} else {
					$outstr .= "\t\t\t<td style='text-align:" . $alignment[$item['align']] . ";'>" . PHP_EOL;
					$outstr .= "\t\t\t\t<table style='width:100%;'>" . PHP_EOL;
				}

				$outstr .= "\t\t\t\t\t<tr>" . PHP_EOL;
			}

			if ($format_ok) {
				$outstr .= "\t\t\t\t\t\t<td class='image_column'>" . PHP_EOL;
			} else {
				$outstr .= "\t\t\t\t\t\t<td style='padding:5px;text-align='" . $alignment[$item['align']] . ";'>" . PHP_EOL;
			}

			$outstr .= "\t\t\t\t\t\t\t" . reports_graph_image($report, $item, $timespan, $output, $theme) . PHP_EOL;
			$outstr .= "\t\t\t\t\t\t</td>" . PHP_EOL;

			if ($report['graph_columns'] > 1) {
				if (($column + 1) % $report['graph_columns'] == 0) {
					$column = 0;
				} else {
					$column++;
				}
			}

			if ($column == 0) {
				$outstr .= "\t\t\t\t\t</tr>" . PHP_EOL;
				$outstr .= "\t\t\t\t</table>" . PHP_EOL;
				$outstr .= "\t\t\t</td>" . PHP_EOL;
				$outstr .= "\t\t</tr>" . PHP_EOL;
			}
		}
	}

	if ($column > 0) {
		$outstr .= "\t\t\t\t\t</tr>" . PHP_EOL;
		$outstr .= "\t\t\t\t</table>" . PHP_EOL;
		$outstr .= "\t\t\t</td>" . PHP_EOL;
		$outstr .= "\t\t</tr>" . PHP_EOL;
	}

	return $outstr;
}

/**
 * convert png images stream to jpeg using php-gd
 *
 * @param string $png_data	- the png image as a stream
 * @return string			- the jpeg image as a stream
 */
function png2jpeg($png_data) {
	global $config;

	if ($png_data != '') {
		$fn = '/tmp/' . time() . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);

		if (!$im) {								/* check for errors */
			$im  = imagecreate(150, 30);		/* create an empty image */
			$bgc = imagecolorallocate($im, 255, 255, 255);
			$tc  = imagecolorallocate($im, 0, 0, 0);
			imagefilledrectangle($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			imagestring($im, 1, 5, 5, "Error while opening: $fn", $tc);
		}

		ob_start(); // start a new output buffer to capture jpeg image stream
		imagejpeg($im);	// output to buffer
		$ImageData       = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}

	return $ImageData;
}

/**
 * convert png images stream to gif using php-gd
 *
 * @param string $png_data	- the png image as a stream
 * @return string			- the gif image as a stream
 */
function png2gif($png_data) {
	global $config;

	if ($png_data != '') {
		$fn = '/tmp/' . time() . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);

		if (!$im) {								/* check for errors */
			$im  = imagecreate(150, 30);		/* create an empty image */
			$bgc = imagecolorallocate($im, 255, 255, 255);
			$tc  = imagecolorallocate($im, 0, 0, 0);
			imagefilledrectangle($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			imagestring($im, 1, 5, 5, "Error while opening: $fn", $tc);
		}

		ob_start(); // start a new output buffer to capture gif image stream
		imagegif($im);	// output to buffer
		$ImageData       = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}

	return $ImageData;
}

/**
 * get available format files for cacti reporting
 * @return array	- available format files
 */
function reports_get_format_files() {
	global $config;

	$formats = array();
	$dir     = CACTI_PATH_FORMATS .  '';

	if (is_dir($dir)) {
		if (function_exists('scandir')) {
			$files = scandir($dir);
		} elseif ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				$files[] = $file;
			}

			closedir($dh);
		}

		if (cacti_sizeof($files)) {
			foreach ($files as $file) {
				if (substr_count($file, '.format')) {
					$contents = file($dir . '/' . $file);

					if (cacti_sizeof($contents)) {
						foreach ($contents as $line) {
							$line = trim($line);

							if (substr_count($line, 'Description:') && substr($line, 0, 1) == '#') {
								$arr            = explode(':', $line);
								$formats[$file] = trim($arr[1]) . ' (' . $file . ')';
							}
						}
					}
				}
			}
		}
	}

	return $formats;
}

/**
 * define the reports code that will be processed at the end of each polling event
 */
function reports_poller_bottom() {
	global $config;
	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	$command_string = read_config_option('path_php_binary');
	$extra_args     = '-q ' . CACTI_PATH_BASE . '/poller_reports.php';
	exec_background($command_string, $extra_args);
}

/**
 * Setup the new dropdown action for Graph Management
 * @arg $action		actions to be performed from dropdown
 * @param mixed $action
 */
function reports_graphs_action_array($action) {
	$action['reports'] = __('Add to Report');

	return $action;
}

/**
 * reports_graphs_action_prepare - perform reports_graph prepare action
 * @param array $save - drp_action: selected action from dropdown
 *              graph_array: graphs titles selected from graph management's list
 *              graph_list: graphs selected from graph management's list
 * returns array $save				-
 *  */
function reports_graphs_action_prepare($save) {
	global $config, $graph_timespans, $alignment;

	if ($save['drp_action'] == 'reports') { /* report */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Choose the Report to associate these graphs with.  The defaults for alignment will be used for each graph in the list below.') . '</p>
				<p>' . $save['graph_list'] . '</p>
				<p>' . __('Report:') . '<br>';

		form_dropdown('reports_id', db_fetch_assoc_prepared('SELECT reports.id, reports.name
					FROM reports
					WHERE user_id = ?
					ORDER by name',
			array($_SESSION[SESS_USER_ID])), 'name', 'id', '', '', '0');

		print '<br><p>' . __('Graph Timespan:') . '<br>';
		form_dropdown('timespan', $graph_timespans, '', '', '0', '', '', '');

		print '<br><p>' . __('Graph Alignment:') . '<br>';
		form_dropdown('alignment', $alignment, '', '', '0', '', '', '');

		print '</p>
			</td>
		</tr>';
	} else {
		return $save;
	}
}

/**
 * reports_graphs_action_execute - perform reports_graph execute action
 * @param string $action - action to be performed
 * return -
 *  */
function reports_graphs_action_execute($action) {
	global $config;

	if ($action == 'reports') { /* report */
		$message = '';

		/* loop through each of the graph_items selected on the previous page for skipped items */
		if (isset_request_var('selected_items')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				$reports_id      = get_filter_request_var('reports_id');

				input_validate_input_number($reports_id, 'reports_id');
				get_filter_request_var('timespan');
				get_filter_request_var('alignment');

				$report = db_fetch_row_prepared('SELECT *
					FROM reports
					WHERE id = ?',
					array($reports_id));

				foreach ($selected_items as $local_graph_id) {
					/* see if the graph is already added */
					$exists = db_fetch_cell_prepared('SELECT id
						FROM reports_items
						WHERE local_graph_id = ?
						AND report_id = ?
						AND timespan = ?',
						array($local_graph_id, $reports_id, get_nfilter_request_var('timespan')));

					if (!$exists) {
						$sequence = db_fetch_cell_prepared('SELECT MAX(sequence)
							FROM reports_items
							WHERE report_id = ?',
							array($reports_id));

						$sequence++;

						$graph_data = db_fetch_row_prepared('SELECT *
							FROM graph_local
							WHERE id = ?',
							array($local_graph_id));

						if ($graph_data['host_id']) {
							$host_template = db_fetch_cell_prepared('SELECT host_template_id
								FROM host
								WHERE id = ?',
								array($graph_data['host_id']));
						} else {
							$host_template = 0;
						}

						$save['id']                = 0;
						$save['report_id']         = $reports_id;
						$save['item_type']         = REPORTS_ITEM_GRAPH;
						$save['tree_id']           = 0;
						$save['branch_id']         = 0;
						$save['tree_cascade']      = '';
						$save['graph_name_regexp'] = '';
						$save['host_template_id']  = $host_template;
						$save['host_id']           = $graph_data['host_id'];
						$save['graph_template_id'] = $graph_data['graph_template_id'];
						$save['local_graph_id']    = $local_graph_id;
						$save['timespan']          = get_nfilter_request_var('timespan');
						$save['align']             = get_nfilter_request_var('alignment');
						$save['item_text']         = '';
						$save['font_size']         = $report['font_size'];
						$save['sequence']          = $sequence;

						$id = sql_save($save, 'reports_items');

						if ($id) {
							$message .= __('Created Report Graph Item \'<i>%s</i>\'', get_graph_title($local_graph_id)) . '<br>';
						} else {
							$message .= __('Failed Adding Report Graph Item \'<i>%s</i>\' Already Exists', get_graph_title($local_graph_id)) . '<br>';
						}
					} else {
						$message .= __('Skipped Report Graph Item \'<i>%s</i>\' Already Exists', get_graph_title($local_graph_id)) . '<br>';
					}
				}
			}
		}

		if ($message != '') {
			raise_message('report_send', $message, MESSAGE_LEVEL_INFO);
		}
	} else {
		return $action;
	}
}
/**
 * reports_log_and_notify - This function will perform the notification functions for Cacti reports.
 * It is being written to have a common platform to send notifications which can broaden to use
 * mechanisms outside of Email.  We are designing it first to work with existing plugins and core
 * cacti functionality and will extend it from there are required.
 *
 * The parameters include:
 *
 * @param  int       $id           - The report queue unique id number.
 * @param  timestamp $start_time   - The time that the report process started for logging
 * @param  string    $report_type  - The output type of the report.  To be used by the plugin for re-rendering
 * @param  string    $source       - The plugin defined report source name.  For example: 'reports', 'reportit', 'flowview'
 * @param  int       $source_id    - The id as it is known to the source
 * @param  string    $subject      - The notification subject
 * #param  mixed     $raw_data     - Data required to reproduce the report output at a future date
 * @param  string    $oput_raw     - The Raw HTML output without CSS
 * @param  string    $oput_html    - The Report Output with CSS
 * @param  string    $oput_text    - The text output if any
 * @param  array     $attachements - The attachments required to reproduce the report
 * @param  array     $headers      - Any report specific Email headers
 */
function reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject,
	&$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = array(), $headers = false) {

	$report = db_fetch_row_prepared('SELECT *
		FROM reports_queued
		WHERE id = ?',
		array($id));

	if ($oput_text == null) {
		$oput_text = '';
	}

	$fromemail = read_config_option('settings_from_email');
	if ($fromemail == '') {
		$fromemail = 'cacti@cacti.net';
	}

	$fromname = read_config_option('settings_from_name');
	if ($fromname == '') {
		$fromname = __('Cacti %s', ucfirst($source));
	}

	$from['email'] = $fromemail;
	$from['name']  = $fromname;

	if (cacti_sizeof($report)) {
		if ($report['notification'] != '') {
			$notifications = json_decode($report['notification'], true);

			foreach($notifications as $type => $data) {
				switch($type) {
					case 'email':
						if (!isset($data['to_email'])) {
							cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Missing 'to_email' attribute in request", $report['name']), false, 'REPORTS');
							break;
						} else {
							$to_emails = $data['to_email'];
						}

						if (isset($data['cc_email'])) {
							$cc_emails = $data['cc_email'];
						} else {
							$cc_emails = '';
						}

						if (isset($data['bcc_email'])) {
							$bcc_emails = $data['bcc_email'];
						} else {
							$bcc_emails = '';
						}

						if (isset($data['reply_to'])) {
							$reply_to = $data['reply_to'];
						} else {
							$reply_to = '';
						}

						if (isset($data['from']) && ((is_array($data['from']) && cacti_sizeof($data['from'])) || $data['from'] != '')) {
							$from = $data['from'];
						}

						mailer($from, $to_emails, $cc_emails, $bcc_emails, $reply_to, $subject, $oput_html, $oput_text, $attachments, $headers);

						break;
					case 'notification_list':
						if (!isset($data['id'])) {
							cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Missing notification list 'id' attribute in request", $report['name']), false, 'REPORTS');
							break;
						} else {
							if (isset($data['from']) && ((is_array($data['from']) && cacti_sizeof($data['from'])) || $data['from'] != '')) {
								$from = $data['from'];
							}

							$list = db_fetch_row_prepared('SELECT *
								FROM plugin_notification_lists
								WHERE id = ?',
								array($data['id']));

							if (cacti_sizeof($list)) {
								/* process the format file */
								$report_tag  = '';
								$theme       = 'modern';
								$output_html = '';
								$format      = $list['format_file'] != '' ? $list['format_file']:'default';

								$format_ok = reports_load_format_file($format, $output, $report_tag, $theme);

								if ($format_ok) {
									if ($report_tag) {
										$oput_html = str_replace('<REPORT>', $oput_html, $output);
									} else {
										$oput_html = $output . PHP_EOL . $oput_html;
									}
								}

								$to_emails  = $list['emails'];
								$cc_emails  = isset($list['cc_emails']) ? $list['cc_emails']:'';
								$bcc_emails = $list['bcc_emails'];
								$reply_to   = isset($list['reply_to'])  ? $list['reply_to']:'';

								if (isset($data['reply_to']) && $reply_to == '') {
									$reply_to = $data['reply_to'];
								} else {
									$reply_to = '';
								}

								mailer($from, $to_emails, $cc_emails, $bcc_emails, $reply_to, $subject, $oput_html, $oput_text, $attachments, $headers);
							} else {
								cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Unable to locate notification list '%s'", $report['name'], $id), false, 'REPORTS');
							}
						}

						break;
					default:
						cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Unknown notification type '%s' attribute in request", $report['name'], $type), false, 'REPORTS');

						break;
				}
			}
		}

		/* prepare attachments for storage */
		if (cacti_sizeof($attachments)) {
			foreach($attachments as $index => $a) {
				if ($a['inline'] == 'attachment') {
					if (file_exists($a['attachment'])) {
						$attachments[$index]['attachment'] = file_get_contents($a['attachment']);
						$attachments[$index]['inline']     = 'inline';
					}
				}
			}
		}

		$end_time = microtime(true);

		$save = array();

		$save['id']                 = 0;
		$save['name']               = $report['name'];
		$save['source']             = $source;
		$save['source_id']          = $source_id;
		$save['report_output_type'] = $report_type;
		$save['report_raw_data']    = json_encode($raw_data);
		$save['report_raw_output']  = $oput_raw;
		$save['report_html_output'] = $oput_html;
		$save['report_txt_output']  = $oput_text;
		$save['report_attachments'] = base64_encode(json_encode($attachments));
		$save['send_type']          = $report['request_type'];
		$save['send_time']          = date('Y-m-d H:i:s');
		$save['run_time']           = $end_time - $start_time;
		$save['sent_by']            = $report['requested_by'];
		$save['sent_id']            = $report['requested_id'];
		$save['notification']       = $report['notification'];

		sql_save($save, 'reports_log');
	}
}

function reports_queue($name, $request_type, $source, $source_id, $command, $notification) {
	if (isset($_SESSION['sess_user_id'])) {
		$requested_id = $_SESSION['sess_user_id'];
		$requested_by = db_fetch_cell_prepared('SELECT username
			FROM user_auth
			WHERE id = ?',
			array($requested_id));

		if ($requested_by == '') {
			$requested_by = 'unknown';
		}
	} else {
		$requested_id = -1;
		$requested_by = 'system';
	}

	$save = array();

	$save['id']             = 0;
	$save['name']           = $name;
	$save['request_type']   = $request_type;
	$save['source']         = $source;
	$save['source_id']      = $source_id;
	$save['status']         = 'pending';
	$save['run_command']    = $command;
	$save['scheduled_time'] = date('Y-m-d H:i:s');
	$save['notification']   = json_encode($notification);
	$save['requested_by']   = $requested_by;
	$save['requested_id']   = $requested_id;

	$id = sql_save($save, 'reports_queued');

	if ($id > 0) {
		if ($requested_id > 0) {
			raise_message('report_scheduled', __esc("The Report '%s' from source %s with id %s is scheduled to run!", $name, $source, $source_id, 'flowview'), MESSAGE_LEVEL_INFO);
		} else {
			cacti_log(sprintf("The Report '%s' from source %s with id %s is scheduled to run!", $name, $source, $source_id), false, 'REPORTS', POLLER_VERBOSITY_MEDIUM);
		}
	} else {
		if ($requested_id > 0) {
			raise_message('report_not_scheduled', __("The Report '%s' from source %s with id %s was not scheduled to run due to an error!", $name, $source, $source_id, 'flowview'), MESSAGE_LEVEL_ERROR);
		} else {
			cacti_log(sprintf("FATAL: The Report '%s' from source %s with id %s was not scheduled to run due to an error!", $name, $source, $source_id), false, 'REPORTS');
		}
	}

	return $id;
}

function reports_run($id) {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	$report = db_fetch_row_prepared('SELECT *
		FROM reports_queued
		WHERE id = ?',
		array($id));

	if (cacti_sizeof($report)) {
		db_execute_prepared('UPDATE reports_queued
			SET status = ?, start_time = ?
			WHERE id = ?',
			array('running', date('Y-m-d H:i:s'), $id));
	} else {
		return false;
	}

	$start = microtime(true);

	$return_code = 0;
	$output      = array();
	$command     = $report['run_command'] . ' --report-id=' . $report['source_id'] . ' --queue-id=' . $id;
	$timeout     = $report['run_timeout'];
	$source      = strtoupper($report['source']);

	if ($timeout == 0) {
		$timeout = read_config_option('scheduler_timeout');
	}

	cacti_log("NOTE: Report:{$report['name']} Command:$command", false, 'REPORTS', POLLER_VERBOSITY_MEDIUM);

	$last_line = exec_with_timeout($command, $output, $return_code, $timeout);

	$end  = microtime(true);

	$stats = sprintf("$source STATS: Time:%0.2f Report:'%s' Id:'%s'", $end-$start, $report['name'], $report['source_id']);

	cacti_log($stats, false, 'SYSTEM');

	db_execute_prepared('DELETE FROM reports_queued WHERE id = ?', array($id));
}

function get_notification_emails($id = '', $recipient = 'to') {
	if ($id == '' || $id === null) {
		return '';
	}

	if (!thold_notification_list_enabled($id)) {
		return '';
	}

	if (!empty($id)) {
		if ($recipient == 'to') {
			return trim(db_fetch_cell_prepared('SELECT emails
				FROM plugin_notification_lists
				WHERE id = ?',
				array($id)));
		} else {
			return trim(db_fetch_cell_prepared('SELECT bcc_emails
				FROM plugin_notification_lists
				WHERE id = ?',
				array($id)));
		}
	} else {
		return '';
	}
}

