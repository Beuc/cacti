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

/*
 * Create a consistent responsive filter
 */

class CactiTableFilter {
	/* constructor variables */
	public $form_header      = '';
	public $form_action      = '';
	public $form_id          = '';
	public $session_var      = 'sess_';
	public $action_url       = '';
	public $action_label     = '';
	public $show_columns     = true;
	public $default_filter   = array();
	public $rows_label       = '';
	public $associated_label = '';
	public $js_extra         = '';
	public $dynamic          = true;
	public $def_refresh      = 300;

	/**
	 * Custom hooks for common functionality.
	 * These hooks will reduce the number of
	 * pages that will require a full stack replacement
	 * filter.
	 */
	public $has_graphs     = false;
	public $has_data       = false;
	public $has_save       = false;
	public $has_import     = false;
	public $has_export     = false;
	public $has_purge      = false;
	public $has_named      = false;
	public $has_associated = false;
	public $has_refresh    = false;

	private $sort_array    = array();
	private $button_array  = array();
	private $append_array  = array();
	private $item_rows     = array();
	private $timespans     = array();
	private $timeshifts    = array();
	private $filter_array  = array();
	private $frequencies   = array();

	public function __construct($form_header = '', $form_action = '', $form_id = '',
		$session_var = '', $action_url = '', $action_label = false, $show_columns = true) {

		global $item_rows, $graph_timespans, $graph_timeshifts;

		$this->form_header   = $form_header;
		$this->form_action   = $form_action;
		$this->form_id       = $form_id;
		$this->session_var   = $session_var;
		$this->action_url    = $action_url;
		$this->action_label  = $action_label;
		$this->show_columns  = $show_columns;

		$this->item_rows     = $item_rows;
		$this->timespans     = $graph_timespans;
		$this->timeshifts    = $graph_timeshifts;
		$this->rows_label    = __('Rows');

		$this->frequencies = array(
			5   => __('%d Seconds', 5),
			10  => __('%d Seconds', 10),
			20  => __('%d Seconds', 20),
			30  => __('%d Seconds', 30),
			45  => __('%d Seconds', 45),
			60  => __('%d Minute', 1),
			120 => __('%d Minutes', 2),
			300 => __('%d Minutes', 5)
		);

		if ($session_var == '') {
			$action = get_nfilter_request_var('action');
			$tab    = get_nfilter_request_var('tab');

			if ($action != '') {
				$session_var .= basename(get_current_page(), '.php') . '_' . $action;
			} elseif ($tab != '') {
				$session_var .= basename(get_current_page(), '.php') . '_' . $tab;
			} else {
				$session_var .= basename(get_current_page(), '.php');
			}
		}

		if ($this->action_url != '' && $this->action_label == '') {
			$this->action_label = __('Add');
		}
	}

	private function create_default() {
		/* default filter */
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
					'rows' => array(
						'method'        => 'drop_array',
						'friendly_name' => $this->rows_label,
						'filter'        => FILTER_VALIDATE_INT,
						'default'       => '-1',
						'pageset'       => true,
						'array'         => $this->item_rows,
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
				)
			),
			'sort' => array(
				'sort_column'    => 'name',
				'sort_direction' => 'ASC'
			)
		);
	}

	public function __destruct() {
		return true;
	}

	public function set_filter_row($array, $index = false) {
		if ($index === false) {
			$this->filter_array['rows'][] = $array;
		} else {
			$this->filter_array['rows'][$index] = $array;
		}
	}

	public function get_filter_row($index) {
		if ($index === false) {
			return false;
		}

		if (array_key_exists($index, $this->filter_array['rows'])) {
			return $this->filter_array['rows'][$index];
		} else {
			return false;
		}
	}

	public function set_filter_array($array) {
		$this->filter_array = $array;
	}

	public function get_filter() {
		return $this->filter_array;
	}

	public function set_sort_array($sort_column, $sort_direction) {
		$this->sort_array = array(
			'sort_column'    => $sort_column,
			'sort_direction' => $sort_direction
		);
	}

	public function add_button($id, $button) {
		$this->button_array[$id] = $button;
	}

	public function add_row_element($row, $id, $filter) {
		$this->append_array[$row][$id] = $filter;
	}

	public function render() {
		/* create the filter for the page */
		$filter = $this->create_filter();

		/* validate filter variables */
		$this->sanitize_filter_variables();

		/* if validation succeeds, print output the data */
		print $filter;

		/* create javascript to operate of the filter */
		print $this->create_javascript();

		return true;
	}

	public function sanitize() {
		/* create the filter for the page */
		$filter = $this->create_filter();

		/* validate filter variables */
		$this->sanitize_filter_variables();
	}

	private function create_filter() {
		if (!cacti_sizeof($this->filter_array)) {
			$this->filter_array = $this->create_default();
		}

		if (cacti_sizeof($this->sort_array)) {
			$this->filter_array['sort'] = $this->sort_array;
		}

		if (cacti_sizeof($this->button_array)) {
			$this->filter_array['buttons'] += $this->button_array;
		}

		if (cacti_sizeof($this->append_array)) {
			foreach($this->append_array as $row => $data) {
				foreach($data as $id => $filter) {
					$this->filter_array['rows'][$row][$id] = $filter;
				}
			}
		}

		// Make common adjustements
		if ($this->has_refresh) {
			if (isset_request_var('refresh')) {
				$value = get_nfilter_request_var('refresh');
			} else {
				$value = $this->def_refresh;
			}

			$this->filter_array['rows'][0] += array(
				'refresh' => array(
					'method'        => 'drop_array',
					'friendly_name' => __('Refresh'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => $this->def_refresh,
					'array'         => $this->frequencies,
					'value'         => $value
				)
			);
		}

		if ($this->has_graphs) {
			if (isset_request_var('has_graphs')) {
				$value = get_nfilter_request_var('has_graphs');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'has_graphs' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Graphs'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_config_option('default_has') == 'on' ? 'true':'false',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_data) {
			if (isset_request_var('has_data')) {
				$value = get_nfilter_request_var('has_data');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'has_data' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Data Sources'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_config_option('default_has') == 'on' ? 'true':'false',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_named) {
			if (isset_request_var('named')) {
				$value = get_nfilter_request_var('named');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'named' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Named Colors'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_config_option('default_has') == 'on' ? 'true':'false',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_associated) {
			if (isset_request_var('associated')) {
				$value = get_nfilter_request_var('associated');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'associated' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => ($this->associated_label != '' ? $this->associated_label : __('Associated')),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => read_config_option('default_has') == 'on' ? 'true':'false',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_save) {
			$this->filter_array['buttons']['save'] = array(
				'method'  => 'button',
				'display' => __('Save'),
				'title'   => __('Save Filter Defaults'),
				'status'  => __('Saving Filter')
			);
		}

		if ($this->has_import) {
			$this->filter_array['buttons']['import'] = array(
				'method'  => 'button',
				'display' => __('Import'),
				'title'   => __('Import Data'),
			);
		}

		if ($this->has_export) {
			$this->filter_array['buttons']['export'] = array(
				'method'  => 'button',
				'display' => __('Export'),
				'title'   => __('Export Data'),
			);
		}

		if ($this->has_purge) {
			$this->filter_array['buttons']['purge'] = array(
				'method'  => 'button',
				'display' => __('Purge'),
				'title'   => __('Purge Data'),
				'status'  => __('Purging Data')
			);
		}

		if (isset($this->filter_array['buttons']) && cacti_sizeof($this->filter_array['buttons'])) {
			$this->filter_array['rows'][0] += $this->filter_array['buttons'];
		}

		// Buffer output
		ob_start();

		$text_appended = false;

		if (isset($this->filter_array['links']) && cacti_sizeof($this->filter_array['links'])) {
			$linkButtons = array();

			if ($this->action_url != '') {
				$linkButtons[] = array(
					'id'       => 'add',
					'href'     => $this->action_url,
					'title'    => $this->action_label,
					'callback' => true,
					'class'    => 'fa fa-plus'
				);
			}

			foreach($this->filter_array['links'] as $index => $link) {
				$linkButtons[] = array(
					'id'       => 'dynamic' . $index,
					'href'     => $link['url'],
					'title'    => $link['display'],
					'callback' => true,
					'class'    => $link['class']
				);
			}

			html_filter_start_box($this->form_header, $linkButtons, true, $this->show_columns, $this->action_label);
		} else {
			html_filter_start_box($this->form_header, $this->action_url, true, $this->show_columns, $this->action_label);
		}

		if (isset($this->filter_array['rows'])) {
			print "<form id='" . $this->form_id . "' action='" . $this->form_action . "'>";

			foreach($this->filter_array['rows'] as $index => $row) {
				if ($index > 0 && !$text_appended) {
					print "<div class='filterColumnButton' id='text'></div>";
					$text_appened = true;
				}

				print "<div class='filterTable even'>";
				print "<div class='filterRow'>";

				foreach ($row as $field_name => $field_array) {
					if (isset($field_array['class'])) {
						$class = ' ' . $field_array['class'];
					} else {
						$class = '';
					}

					if (!isset($field_array['value']) &&
						$field_array['method'] != 'validate' &&
						$field_array['method'] != 'submit' &&
						$field_array['method'] != 'button' &&
						$field_array['method'] != 'timespan') {
						cacti_log("WARNING: The Filter Class value field $field_name is missing");

						$field_array['value'] = '';
					}

					switch($field_array['method']) {
						case 'content':
							print '<div class="filterColumn">' . $field_array['content'] . '</div>';

							break;
						case 'validate':
							// Just for validating other request variables

							break;
						case 'button':
							print '<div class="filterColumnButton">' . PHP_EOL;

							if (isset($field_array['display'])) {
								print '<input type="button" class="ui-button ui-corner-all ui-widget' . $class . '" id="' . $field_name . '" value="' . $field_array['display'] . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '">';
							} else {
								print '<button type="button" class="ui-button ui-corner-all ui-widget' . $class . '" id="' . $field_name . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '"><i class="' . $field_array['class'] . '"></i></button>';
							}

							print '</div>' . PHP_EOL;

							break;
						case 'submit':
							print '<div class="filterColumnButton">' . PHP_EOL;
							print '<input type="submit" class="ui-button ui-corner-all ui-widget' . $class . '" id="' . $field_name . '" value="' . $field_array['display'] . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '">';
							print '</div>' . PHP_EOL;

							break;
						case 'filter_checkbox':
							print '<div class="filterColumn"><span>' . PHP_EOL;
							print '<input type="checkbox" class="ui-button ui-corner-all ui-widget' . $class . '" id="' . $field_name . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '"' . ($field_array['value'] == 'on' || $field_array['value'] == 'true' ? ' checked':'') . '>';
							print '&nbsp;<label for="' . $field_name . '">' . $field_array['friendly_name'] . '</label>';
							print '</span></div>' . PHP_EOL;

							break;
						case 'timespan':
							print '<div class="filterColumn"><div class="filterFieldName">' . __('Presets') . '</div></div>' . PHP_EOL;

							print '<div class="filterColumn">';
							print '<select id="predefined_timespan" class="' . $class . '">';

							$this->timespans = array_merge(array(GT_CUSTOM => __('Custom')), $this->timespans);

							$start_val = 0;
							$end_val   = cacti_sizeof($this->timespans);

							if (cacti_sizeof($this->timespans)) {
								foreach ($this->timespans as $value => $text) {
									print "<option value='$value'" . ($_SESSION['sess_current_timespan'] == $value ? ' selected':'') .  '>' . html_escape($text) . '</option>';
								}
							}
							print '</select>';
							print '</div>';

							// From data
							print '<div class="filterColumn">';
							print __('From');
							print '</div>';
							print '<div class="filterColumn">';
							print '<span>';
							print '<input type="text" class="ui-state-default ui-corner-all' . $class . '" id="date1" size="18" value="' . (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '') . '">';
							print '<i id="startDate" class="calendar fa fa-calendar-alt" title="' . __esc('Start Date Selector') . '"></i>';
							print '</span>';
							print '</div>';

							// To Data
							print '<div class="filterColumn">';
							print __('From');
							print '</div>';
							print '<div class="filterColumn">';
							print '<span>';
							print '<input type="text" class="ui-state-default ui-corner-all' . $class . '" id="date2" size="18" value="' . (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '') . '">';
							print '<i id="endDate" class="calendar fa fa-calendar-alt" title="' . __esc('End Date Selector') . '"></i>';
							print '</span>';
							print '</div>';

							if (isset($field_array['shifter']) && $field_array['shifter'] === true) {
								print '<div class="filterColumn">';
								print '<span>';

								print '<i id="shift_left" class="shiftArrow fa fa-backward" title="' . __esc('Shift Time Backward') . '"></i>';
								print '<select id="predefined_timeshift" title="' . __esc('Define Shifting Interval') . '" class="' . $class . '">';

								$start_val = 1;
								$end_val    = cacti_sizeof($this->timeshifts) + 1;

								if (cacti_sizeof($this->timeshifts)) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'" . ($_SESSION['sess_current_timeshift'] == $shift_value ? ' selected':'') . '>' . html_escape($this->timeshifts[$shift_value]) . '</option>';
									}
								}

								print '</select>';
								print '<i id="shift_right" class="shiftArrow fa fa-forward" title="' . __esc('Shift Time Forward') . '"></i>';

								print '</span>';
								print '</div>';
							}

							if ((isset($field_array['refresh']) && $field_array['refresh'] === true) || (isset($field_array['clear']) && $field_array['clear'] === true)) {
								print '<div class="filterColumn">';
								print '<span>';

								if (isset($field_array['refresh'])) {
									print '<input type="button" class="ui-button ui-corner-all ui-widget' . $class . '" id="tsrefresh" value="' . __esc('Refresh') . '" title="' . __esc('Refresh Selected Timespan') . '">';
								}

								if (isset($field_array['clear'])) {
									print '<input type="button" class="ui-button ui-corner-all ui-widget' . $class . '" id="tsclear" value="' . __esc('Clear') . '" title="' . __esc('Clear Selected Timespan') . '">';
								}

								print '</span>';
								print '</div>';
							}

							break;
						default:
							if (isset($field_array['friendly_name'])) {
								print '<div class="filterColumn"><div class="filterFieldName"><label for="' . $field_name . '">' . $field_array['friendly_name'] . '</label></div></div>' . PHP_EOL;
							}

							if (isset_request_var($field_name) && strpos($field_array['method'], 'callback') === false) {
								$field_array['value'] = get_nfilter_request_var($field_name);
							}

							print '<div class="filterColumn">' . PHP_EOL;

							draw_edit_control($field_name, $field_array);

							print '</div>' . PHP_EOL;
					}
				}

				if ($index == 0) {
					print "<div class='filterColumnButton' id='text'></div>";
				}

				print '</div>' . PHP_EOL;
				print '</div>' . PHP_EOL;
			}

			print '</form>' . PHP_EOL;
		}

		html_filter_end_box();

		return ob_get_clean();
	}

	private function make_function($buttonId, $buttonArray, $buttonAction) {
		$func_nl = "\n\t\t\t";
		$func_el = "\n\t\t";
		$buttonFunction = '';

		if (isset($buttonArray['url'])) {
			if (!isset($buttonArray['status'])) {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					"loadUrl({ url: '{$buttonArray['url']}' });" . $func_el .
				"};" . PHP_EOL;
			} else {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					"$('#text').text('{$field_array['status']}');" . $func_nl .
					"pulsate('#text');" . $func_nl .
					"loadUrl({ url: '{$buttonArray['url']}', funcEnd: 'finishFinalize' });" . $func_el .
				"};" . PHP_EOL;
			}
		} else {
			if (!isset($buttonArray['status'])) {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					"loadUrl({ url: $buttonAction });" . $func_el .
				"};" . PHP_EOL;
			} else {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					"$('#text').text('{$field_array['status']}');" . $func_nl .
					"pulsate('#text');" . $func_nl .
					"loadUrl({ url: $buttonAction, funcEnd: 'finishFinalize' });" . $func_el .
					"};" . PHP_EOL;
			}
		}

		return $buttonFunction;
	}

	private function create_javascript() {
		$applyFilter   = "'" . $this->form_action;
		$clearFilter   = $applyFilter;
		$defaultFilter = $applyFilter;

		if (strpos($applyFilter, '?') === false) {
			$separator = '?';
		} else {
			$separator = '&';
		}

		$applyFilter   .= $separator;

		$clearFilter   .= $separator . "clear=true'";
		$defaultFilter .= $separator . "action=noaction'";

		$changeChain   = '';
		$clickChain    = '';

		if (!$this->has_save) {
			$saveFilter = "'#'";
		}

		if (!$this->has_import) {
			$importFilter = "'#'";
		}

		if (!$this->has_export && !isset($this->filter_array['buttons']['export'])) {
			$exportFilter = "'#'";
		}

		if (!$this->has_purge && !isset($this->filter_array['buttons']['purge'])) {
			$purgeFilter = "'#'";
		}

		if (isset($this->filter_array['options']['change_function'])) {
			$changeFunction = $this->filter_array['options']['change_function'];
		} else {
			$changeFunction = 'applyFilter()';
		}

		if (isset($this->filter_array['options']['clear_function'])) {
			$clearFunction = $this->filter_array['options']['clear_function'];
		} else {
			$clearFunction = 'clearFilter()';
		}

		$filterLength    = 0;
		$refreshMSeconds = 9999999;
		$buttonFunctions = '';
		$buttonReady     = '';
		$readyAdd        = '';
		$globalAdd       = '';

		if (isset($this->filter_array['rows'])) {
			foreach($this->filter_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'content':
						case 'validate':
							// Just for validating other request variables

							break;
						case 'button':
							switch($field_name) {
								case 'go':
								case 'clear':
									break;
								default:
									$buttonAction = str_replace('noaction', $field_name, $defaultFilter);

									$buttonFunctions .= $this->make_function($field_name, $field_array, $buttonAction);

									$buttonReady .= PHP_EOL . "\t\t\t$('#{$field_name}').click(function() { {$field_name}Function(); });";
							}

							break;
						case 'filter_checkbox':
							if ($this->dynamic) {
								$clickChain .= ($clickChain != '' ? ', ':'') . '#' . $field_name;
							}

							$applyFilter .= ($filterLength == 0 ? '&':"+'&") . $field_name . "='+$('#" . $field_name . "').is(':checked')";
							$filterLength++;

							break;
						case 'timespan':
							if (!isset($field_array['span_function'])) {
								$readyAdd .= "$('#predefined_timespan').change( function() { applyGraphTimespan(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#predefined_timespan').change( function() { " . $field_array['span_function'] . "; });" . PHP_EOL;
							}

							if (isset($field_array['shifter']) && $field_array['shifter'] === true) {
								if (!isset($field_array['lshift_function'])) {
									$readyAdd .= "$('#shift_left').click( function() { timeshiftGraphFilterLeft(); });" . PHP_EOL;
								} else {
									$readyAdd .= "$('#shift_left').click( function() { " . $field_array['lshift_function'] . "; });" . PHP_EOL;
								}

								if (!isset($field_array['rshift_function'])) {
									$readyAdd .= "$('#shift_right').click( function() { timeshiftGraphFilterRight(); });" . PHP_EOL;
								} else {
									$readyAdd .= "$('#shift_right').click( function() { " . $field_array['rshift_function'] . "; });" . PHP_EOL;
								}
							}

							if (!isset($field_array['refresh_function'])) {
								$readyAdd .= "$('#tsrefresh').click( function() { refreshGraphTimespanFilter(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#tsrefresh').click( function() { " . $field_array['refresh_function'] . "; });" . PHP_EOL;
							}

							if (!isset($field_array['clear_function'])) {
								$readyAdd .= "$('#tsclear').click( function() { clearGraphTimespanFilter(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#tsclear').click( function() { " . $field_array['clear_function'] . "; });" . PHP_EOL;
							}

							break;
						case 'textbox':
						case 'drop_array':
						case 'drop_files':
						case 'drop_sql':
						case 'drop_callback':
						case 'drop_multi':
						case 'drop_color':
						case 'drop_tree':
							if ($field_array['method'] != 'textbox' && $this->dynamic) {
								if (!isset($field_array['dynamic']) || $field_array['dynamic'] === true) {
									$changeChain .= ($changeChain != '' ? ', ':'') . '#' . $field_name;
								}
							}

							if ($field_name != 'rfilter') {
								$applyFilter .= ($filterLength == 0 ? '&':"+'&") . $field_name . "='+$('#" . $field_name . "').val()";
							} else {
								$applyFilter .= ($filterLength == 0 ? '&':"+'&") . $field_name . "='+atob($('#" . $field_name . "').val())";
							}
							$filterLength++;

							break;
						case 'submit':
							break;

						default:
							break;
					}

					if ($this->has_refresh && $field_name == 'refresh') {
						$refreshMSeconds = $field_array['value'] * 1000;
					}
				}
			}

			if ($filterLength == 0) {
				$applyFilter .= "';";
			} else {
				$applyFilter .= ";";
			}
		}

		if (isset($this->filter_array['javascript']['ready']) && $this->filter_array['javascript']['ready'] != '') {
			$readyAdd .= "\t\t" . trim($this->filter_array['javascript']['ready']) . PHP_EOL;;
		}

		if (isset($this->filter_array['javascript']['global']) && $this->filter_array['javascript']['global'] != '') {
			$globalAdd .= "\t\t" . trim($this->filter_array['javascript']['global']) . PHP_EOL;
		}

		if ($this->has_refresh || isset_request_var('refresh')) {
			$refreshMSeconds = get_request_var('refresh') * 1000;
		}

		if ($clickChain != '') {
			$clickReady = "$('" . $clickChain . "').click(function() {\n\t\t\t\t" .
				"$changeFunction;\n\t\t\t" .
			"});" . PHP_EOL;
		} else {
			$clickReady = '';
		}

		if ($changeChain != '') {
			$changeReady = "$('" . $changeChain . "').change(function() {\n\t\t\t\t" .
				"$changeFunction;\n\t\t\t" .
			"});" . PHP_EOL;
		} else {
			$changeReady = '';
		}

		return PHP_EOL . "<script type='text/javascript'>
		$globalAdd
		function applyFilter() {
			strURL = $applyFilter
			loadUrl({ url: strURL });
		}

		function clearFilter() {
			strURL = $clearFilter
			loadUrl({ url: strURL });
		}

		function finishFinalize(options, data) {
			$('#text').text('Finished').fadeOut(2000);
		}
		$buttonFunctions

		$(function() {
			refreshFunction = '$changeFunction';
			refreshMSeconds = $refreshMSeconds;
			setupPageTimeout();

			$('#" . $this->form_id . "').submit(function(event) {
				event.preventDefault();
				$changeFunction;
			});

			$('#clear').click(function() {
				$clearFunction;
			});

			$readyAdd
			$changeReady
			$clickReady
			$buttonReady
		});
	</script>" . PHP_EOL;
	}

	private function sanitize_filter_variables() {
		$filters = array();

		if (isset($this->filter_array['rows'])) {
			foreach($this->filter_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'timespan':
						case 'button':
						case 'submit':

							break;
						default:
							$filters[$field_name]['filter'] = $field_array['filter'];

							if (isset($field_array['filter_options'])) {
								$filters[$field_name]['options'] = $field_array['filter_options'];
							}

							if (isset($field_array['pageset'])) {
								$filters[$field_name]['pageset'] = $field_array['pageset'];
							}

							if (isset($field_array['default'])) {
								$filters[$field_name]['default'] = $field_array['default'];
							} else {
								$filters[$field_name]['default'] = '';
							}

							break;
					}
				}
			}
		}

		$filters['page']['filter']  = FILTER_VALIDATE_INT;
		$filters['page']['default'] = 1;

		if (!isset_request_var('page')) {
			set_request_var('page', 1);
		}

		if (!isset_request_var('rows')) {
			set_request_var('rows', read_config_option('num_rows_table'));
		}

		if (isset($this->filter_array['sort'])) {
			$filters['sort_column']['filter']     = FILTER_CALLBACK;
			$filters['sort_column']['options']    = array('options' => 'sanitize_search_string');
			$filters['sort_column']['default']    = $this->filter_array['sort']['sort_column'];

			$filters['sort_direction']['filter']  = FILTER_CALLBACK;
			$filters['sort_direction']['options'] = array('options' => 'sanitize_search_string');
			$filters['sort_direction']['default'] = $this->filter_array['sort']['sort_direction'];
		}

		validate_store_request_vars($filters, $this->session_var);
	}
}
