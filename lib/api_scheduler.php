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

function api_scheduler_form() {
	global $sched_types;

	return array(
		'spacer2' => array(
			'method'        => 'spacer',
			'friendly_name' => __('Schedule'),
			'collapsible'   => 'true'
		),
		'sched_type' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('Schedule Type'),
			'description'   => __('Define the collection frequency.'),
			'value'         => '|arg1:sched_type|',
			'array'         => $sched_types,
			'default'       => SCHEDULE_MANUAL
		),
		'start_at' => array(
			'method'        => 'textbox',
			'friendly_name' => __('Starting Date/Time'),
			'description'   => __('What time will this Network discover item start?'),
			'value'         => '|arg1:start_at|',
			'max_length'    => '30',
			'default'       => date('Y-m-d H:i:s'),
			'size'          => 20
		),
		'recur_every' => array(
			'method'        => 'drop_array',
			'friendly_name' => __('Rerun Every'),
			'description'   => __('Rerun discovery for this Network Range every X.'),
			'value'         => '|arg1:recur_every|',
			'default'       => '1',
			'array'         => array(
				1 => '1',
				2 => '2',
				3 => '3',
				4 => '4',
				5 => '5',
				6 => '6',
				7 => '7'
			),
		),
		'day_of_week' => array(
			'method'        => 'drop_multi',
			'friendly_name' => __('Days of Week'),
			'description'   => __('What Day(s) of the week will this Network Range be discovered.'),
			'array'         => array(
				1 => __('Sunday'),
				2 => __('Monday'),
				3 => __('Tuesday'),
				4 => __('Wednesday'),
				5 => __('Thursday'),
				6 => __('Friday'),
				7 => __('Saturday')
			),
			'value' => '|arg1:day_of_week|',
			'class' => 'day_of_week'
		),
		'month' => array(
			'method'        => 'drop_multi',
			'friendly_name' => __('Months of Year'),
			'description'   => __('What Months(s) of the Year will this Network Range be discovered.'),
			'array'         => array(
				1  => __('January'),
				2  => __('February'),
				3  => __('March'),
				4  => __('April'),
				5  => __('May'),
				6  => __('June'),
				7  => __('July'),
				8  => __('August'),
				9  => __('September'),
				10 => __('October'),
				11 => __('November'),
				12 => __('December')
			),
			'value' => '|arg1:month|',
			'class' => 'month'
		),
		'day_of_month' => array(
			'method'        => 'drop_multi',
			'friendly_name' => __('Days of Month'),
			'description'   => __('What Day(s) of the Month will this Network Range be discovered.'),
			'array'         => array(1 => '1', 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32 => __('Last')),
			'value'         => '|arg1:day_of_month|',
			'class'         => 'days_of_month'
		),
		'monthly_week' => array(
			'method'        => 'drop_multi',
			'friendly_name' => __('Week(s) of Month'),
			'description'   => __('What Week(s) of the Month will this Network Range be discovered.'),
			'array'         => array(
				1    => __('First'),
				2    => __('Second'),
				3    => __('Third'),
				'32' => __('Last')
			),
			'value' => '|arg1:monthly_week|',
			'class' => 'monthly_week'
		),
		'monthly_day' => array(
			'method'        => 'drop_multi',
			'friendly_name' => __('Day(s) of Week'),
			'description'   => __('What Day(s) of the week will this Network Range be discovered.'),
			'array'         => array(
				1 => __('Sunday'),
				2 => __('Monday'),
				3 => __('Tuesday'),
				4 => __('Wednesday'),
				5 => __('Thursday'),
				6 => __('Friday'),
				7 => __('Saturday')
			),
			'value' => '|arg1:monthly_day|',
			'class' => 'monthly_day'
		)
	);
}

function api_scheduler_javascript() {
	?>
	<script type='text/javascript'>
	$(function() {
		$('#day_of_week').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the days(s) of the week'); ?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
		});

		$('#month').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the month(s) of the year'); ?>',
			header: false,
			height: 82,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 380
		});

		$('#day_of_month').multiselect({
			selectedList: 15,
			noneSelectedText: '<?php print __('Select the day(s) of the month'); ?>',
			header: false,
			height: 162,
			groupColumns: true,
			groupColumnsWidth: 50,
			menuWidth: 275
		});

		$('#monthly_week').multiselect({
			selectedList: 4,
			noneSelectedText: '<?php print __('Select the week(s) of the month'); ?>',
			header: false,
			height: 28,
			groupColumns: true,
			groupColumnsWidth: 70,
			menuWidth: 300
		});

		$('#monthly_day').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the day(s) of the week'); ?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
		});

		$('#start_at').datetimepicker({
			minuteGrid: 10,
			stepMinute: 5,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			minDateTime: new Date(<?php print date('Y') . ', ' . (date('m') - 1) . ', ' . date('d, H') . ', ' . date('i', ceil(time() / 300) * 300) . ', 0, 0'; ?>)
		});

		$('#sched_type').change(function() {
			setSchedule();
		});

		setSchedule();
	});

	function setSchedule() {
		var schedType = $('#sched_type').val();

		toggleFields({
			start_at: schedType > 1,
			recur_every: (schedType > 1 && schedType < 4) || schedType == 6,
			day_of_week: schedType == 3,
			month: schedType > 3 && schedType != 6,
			day_of_month: schedType == 4,
			monthly_week: schedType == 3 || schedType == 5,
			monthly_day: schedType == 3 || schedType == 5,
		});

		if (schedType == 2) { // Daily
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Weeks') >= 0) {
					html = html.replace('<?php print __('every X Weeks'); ?>', '<?php print __('every X Days'); ?>');
					html = html.replace('<?php print __('Rerun Every X Weeks'); ?>', '<?php print __('Rerun Every X Days'); ?>');
				} else if (html.indexOf('X Hours') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Hours'); ?>', '<?php print __('Rerun Every X Days'); ?>');
					html = html.replace('<?php print __('every X Hours'); ?>', '<?php print __('every X Days'); ?>');
				} else if (html.indexOf('X Days') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Days'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Days'); ?>');
				}

				$(this).html(html);
			});
		} else if (schedType == 6) { // Hourly
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Weeks') >= 0) {
					html = html.replace('<?php print __('every X Weeks'); ?>', '<?php print __('every X Hours'); ?>');
					html = html.replace('<?php print __('Rerun Every X Weeks'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
				} else if (html.indexOf('X Days') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Days'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
					html = html.replace('<?php print __('every X Days'); ?>', '<?php print __('every X Hours'); ?>');
				} else if (html.indexOf('X Hours') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Hours'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Hours'); ?>');
				}

				$(this).html(html);
			});
		} else if (schedType == 3) { //Weekly
			$('#row_recur_every').find('div:first').each(function() {
				var html = $(this).html();

				if (html.indexOf('X Days') >= 0) {
					html = html.replace('<?php print __('every X Days'); ?>', '<?php print __('every X Weeks'); ?>');
					html = html.replace('<?php print __('Rerun Every X Days'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
				} else if (html.indexOf('X Hours') >= 0) {
					html = html.replace('<?php print __('Rerun Every X Hours'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
					html = html.replace('<?php print __('every X Hours'); ?>', '<?php print __('every X Weeks'); ?>');
				} else if (html.indexOf('X Weeks') < 0) {
					html = html.replace('<?php print __('Rerun Every'); ?>', '<?php print __('Rerun Every X Weeks'); ?>');
					html = html.replace('<?php print __('every X'); ?>', '<?php print __('every X Weeks'); ?>');
				}

				$(this).html(html);
			});
		}
	}
	</script>
	<?php
}

function api_scheduler_is_time_to_start($schedule, $table = 'automation_networks') {
	$now   = time();

	switch($schedule['sched_type']) {
		case SCHEDULE_MANUAL:
			return false;

			break;
		case SCHEDULE_HOURLY:
		case SCHEDULE_DAILY:
			if ($schedule['sched_type'] == SCHEDULE_HOURLY) {
				$recur = $schedule['recur_every'] * 3600; // days
			} else {
				$recur = $schedule['recur_every'] * 86400; // days
			}

			$start = strtotime($schedule['start_at']);
			$next  = strtotime($schedule['next_start']);

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while ($now > $target) {
					$target += $recur;
				}

				db_execute_prepared("UPDATE $table
					SET next_start = ?
					WHERE id = ?",
					array(date('Y-m-d H:i', $target), $schedule['id']));

				return true;

				break;
			}

			return false;

			break;
		case SCHEDULE_WEEKLY:
			$recur = $schedule['recur_every'] * 86400 * 7; // weeks
			$start = strtotime($schedule['start_at']);
			$next  = strtotime($schedule['next_start']);
			$days  = explode(',', $schedule['day_of_week']);
			$day   = 86400;
			$week  = 86400 * 7;

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while (true) {
					$target += $day;
					$cur_day = date('w', $target) + 1;

					$key = array_search($cur_day, $days, false);

					if ($key !== false && $key >= 0) {
						if ($key == 0) {
							$target += $recur - $week;
						}

						break;
					}
				}

				db_execute_prepared("UPDATE $table
					SET next_start = ?
					WHERE id = ?",
					array(date('Y-m-d H:i', $target), $schedule['id']));

				return true;
			}

			return false;

			break;
		case SCHEDULE_MONTHLY:
		case SCHEDULE_MONTHLY_ON_DAY:
			$next = api_scheduler_calculate_next_start($schedule, $now);

			db_execute_prepared("UPDATE $table
				SET next_start = ?
				WHERE id = ?",
				array(date('Y-m-d H:i', $next), $schedule['id']));

			if ($schedule['next_start'] == '0000-00-00 00:00:00') {
				if ($now > strtotime($schedule['start_at'])) {
					return true;
				} else {
					return false;
				}
			} elseif ($now > strtotime($schedule['next_start'])) {
				return true;
			}

			return false;

			break;
	}
}

function api_scheduler_calculate_next_start($schedule) {
	$now    = time();
	$dates  = array();

	switch($schedule['sched_type']) {
		case SCHEDULE_MANUAL:

			break;
		case SCHEDULE_MONTHLY:
			$months = explode(',', $schedule['month']);
			$days   = explode(',', $schedule['day_of_month']);

			foreach ($months as $month) {
				foreach ($days as $day) {
					switch($month) {
						case '1':
							$smonth = 'January';

							break;
						case '2':
							$smonth = 'February';

							break;
						case '3':
							$smonth = 'March';

							break;
						case '4':
							$smonth = 'April';

							break;
						case '5':
							$smonth = 'May';

							break;
						case '6':
							$smonth = 'June';

							break;
						case '7':
							$smonth = 'July';

							break;
						case '8':
							$smonth = 'August';

							break;
						case '9':
							$smonth = 'September';

							break;
						case '10':
							$smonth = 'October';

							break;
						case '11':
							$smonth = 'November';

							break;
						case '12':
							$smonth = 'December';

							break;
					}

					if ($day == '32') {
						$dates[] = strtotime('last day of ' . $smonth);
					} else {
						$dates[] = strtotime("$smonth $day");
					}
				}
			}

			break;
		case SCHEDULE_MONTHLY_ON_DAY:
			$months = explode(',', $schedule['month']);
			$weeks  = explode(',', $schedule['monthly_week']);
			$days   = explode(',', $schedule['monthly_day']);
			$now    = time();
			$dates  = array();

			foreach ($months as $month) {
				foreach ($weeks as $week) {
					foreach ($days as $day) {
						switch($month) {
							case '1':
								$smonth = 'January';

								break;
							case '2':
								$smonth = 'February';

								break;
							case '3':
								$smonth = 'March';

								break;
							case '4':
								$smonth = 'April';

								break;
							case '5':
								$smonth = 'May';

								break;
							case '6':
								$smonth = 'June';

								break;
							case '7':
								$smonth = 'July';

								break;
							case '8':
								$smonth = 'August';

								break;
							case '9':
								$smonth = 'September';

								break;
							case '10':
								$smonth = 'October';

								break;
							case '11':
								$smonth = 'November';

								break;
							case '12':
								$smonth = 'December';

								break;
						}

						switch($week) {
							case '1':
								$sweek = 'first';

								break;
							case '2':
								$sweek = 'second';

								break;
							case '3':
								$sweek = 'third';

								break;
							case '4':
								$sweek = 'forth';

								break;
							case '32':
								$sweek = 'last';

								break;
						}

						switch($day) {
							case '1':
								$sday = 'Sunday';

								break;
							case '2':
								$sday = 'Monday';

								break;
							case '3':
								$sday = 'Tuesday';

								break;
							case '4':
								$sday = 'Wednesday';

								break;
							case '5':
								$sday = 'Thursday';

								break;
							case '6':
								$sday = 'Friday';

								break;
							case '7':
								$sday = 'Saturday';

								break;
						}

						$dates[] = strtotime("$sweek $sday of $smonth", strtotime($schedule['start_at']));
					}
				}
			}

			break;
	}

	if ($schedule['sched_type'] !== SCHEDULE_MANUAL) {
		asort($dates);

		$newdates = array();

		foreach ($dates as $date) {
			$ndate = date('Y-m-d', $date) . ' ' . date('H:i:s', strtotime($schedule['start_at']));
			$ntime = strtotime($ndate);

			cacti_log('Start At: ' . $schedule['start_at'] . ', Possible Next Start: ' . $ndate . ' with Timestamp: ' . $ntime, false, 'SCHEDULER', POLLER_VERBOSITY_DEBUG);

			if ($ntime > $now) {
				return $ntime;
			}
		}
	}

	return false;
}

