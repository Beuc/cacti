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

function api_scheduler_is_time_to_start($schedule) {
	$now   = time();

	switch($schedule['sched_type']) {
		case '1':
			return false;

			break;
		case '2':
			$recur = $schedule['recur_every'] * 86400; // days
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

				db_execute_prepared('UPDATE automation_networks
				SET next_start = ?
				WHERE id = ?',
					array(date('Y-m-d H:i', $target), $schedulework_id));

				return true;

				break;
			}

			return false;

			break;
		case '3':
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

				db_execute_prepared('UPDATE automation_networks
				SET next_start = ?
				WHERE id = ?',
					array(date('Y-m-d H:i', $target), $schedulework_id));

				return true;
			}

			return false;

			break;
		case '4':
		case '5':
			$next = api_scheduler_calculate_next_start($schedule, $now);

			db_execute_prepared('UPDATE automation_networks
			SET next_start = ?
			WHERE id = ?',
				array(date('Y-m-d H:i', $next), $schedulework_id));

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
		case '4':
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
		case '5':
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

	asort($dates);

	$newdates = array();

	foreach ($dates as $date) {
		$ndate = date('Y-m-d', $date) . ' ' . date('H:i:s', strtotime($schedule['start_at']));
		$ntime = strtotime($ndate);

		cacti_log('Start At: ' . $schedule['start_at'] . ', Possible Next Start: ' . $ndate . ' with Timestamp: ' . $ntime, false, POLLER_VERBOSITY_DEBUG);

		if ($ntime > $now) {
			return $ntime;
		}
	}

	return false;
}

