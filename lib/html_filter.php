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
	public $form_header    = '';
	public $form_action    = '';
	public $form_id        = '';
	public $action_url     = '';
	public $action_label   = '';
	public $session_var    = 'sess_';
	public $default_filter = array();
	public $rows_label     = '';
	public $js_extra       = '';
	public $dynamic        = true;
	public $def_refresh    = 300;

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
	public $has_refresh    = false;

	private $sort_array    = false;
	private $item_rows     = array();
	private $filter_array  = array();
	private $frequencies   = array();

	public function __construct($form_header = '', $form_action = '', $form_id = '',
		$session_var = '', $action_url = '', $action_label = false) {

		global $item_rows;

		$this->form_header   = $form_header;
		$this->form_action   = $form_action;
		$this->form_id       = $form_id;
		$this->action_url    = $action_url;
		$this->action_label  = $action_label;
		$this->session_var   = $session_var;
		$this->item_rows     = $item_rows;
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

		if ($this->sort_array !== false) {
			$this->filter_array['sort'] = $this->sort_array;
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
				$value = get_request_var('has_graphs');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'has_graphs' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Graphs'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => '',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_data) {
			if (isset_request_var('has_data')) {
				$value = get_request_var('has_data');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'has_data' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Data Sources'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => '',
					'pageset'        => true,
					'value'          => $value
				)
			);
		}

		if ($this->has_named) {
			if (isset_request_var('named')) {
				$value = get_request_var('named');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true':'false';
			}

			$this->filter_array['rows'][0] += array(
				'named' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Named Colors'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => '',
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

		$this->filter_array['rows'][0] += $this->filter_array['buttons'];
		$this->filter_array['rows'][0] += $this->filter_array['buttons'];

		// Buffer output
		ob_start();

		$text_appended = false;

		html_filter_start_box($this->form_header, $this->action_url, true, true, $this->action_label);

		if (isset($this->filter_array['rows'])) {
			print "<form id='" . $this->form_id . "' action='" . $this->form_action . "'>";

			foreach($this->filter_array['rows'] as $index => $row) {
				if ($index > 0 && !$text_appended) {
					print "<div class='filterColumnButton' id='text'></div>";
					$text_appened = true;
				}

				print "<div class='filterTable'>";
				print "<div class='filterRow'>";

				foreach ($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'button':
							print '<div class="filterColumnButton">' . PHP_EOL;
							print '<input type="button" class="ui-button ui-corner-all ui-widget" id="' . $field_name . '" value="' . $field_array['display'] . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '">';
							print '</div>' . PHP_EOL;

							break;
						case 'submit':
							print '<div class="filterColumnButton">' . PHP_EOL;
							print '<input type="submit" class="ui-button ui-corner-all ui-widget" id="' . $field_name . '" value="' . $field_array['display'] . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '">';
							print '</div>' . PHP_EOL;

							break;
						case 'filter_checkbox':
cacti_log('FieldName: ' . $field_array['friendly_name'] . ', Value:'.$field_array['value']);
							print '<div class="filterColumn"><span>' . PHP_EOL;
							print '<input type="checkbox" class="ui-button ui-corner-all ui-widget" id="' . $field_name . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title']:'') . '"' . ($field_array['value'] == 'on' || $field_array['value'] == 'true' ? ' checked':'') . '>';
							print '&nbsp;<label for="' . $field_name . '">' . $field_array['friendly_name'] . '</label>';
							print '</span></div>' . PHP_EOL;

							break;
						case 'timespan':
							print '<div class="filterColumn"><div class="filterFieldName">' . __('Presets') . '</div></div>' . PHP_EOL;

							break;
						default:
							if (isset($field_array['friendly_name'])) {
								print '<div class="filterColumn"><div class="filterFieldName"><label for="' . $field_name . '">' . $field_array['friendly_name'] . '</label></div></div>' . PHP_EOL;
							}

							if (isset_request_var($field_name)) {
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
			$separator = '';
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

		$filterLength    = 0;
		$refreshMSeconds = 9999999;
		$buttonFunctions = '';
		$buttonReady     = '';

		if (isset($this->filter_array['rows'])) {
			foreach($this->filter_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'button':
							switch($field_name) {
								case 'go':
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
						case 'textbox':
						case 'drop_array':
						case 'drop_files':
						case 'drop_sql':
						case 'drop_callback':
						case 'drop_multi':
						case 'drop_color':
						case 'drop_tree':
							if ($field_array['method'] != 'textbox' && $this->dynamic) {
								$changeChain .= ($changeChain != '' ? ', ':'') . '#' . $field_name;
							}

							$applyFilter .= ($filterLength == 0 ? '&':"+'&") . $field_name . "='+$('#" . $field_name . "').val()";
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

			$applyFilter .= ';';
		}

		if ($this->has_refresh && isset_request_var('refresh')) {
			$refreshMSeconds = get_request_var('refresh') * 1000;
		}

		if ($clickChain != '') {
			$clickReady = "$('" . $clickChain . "').click(function() {\n\t\t\t\t" .
				"applyFilter();\n\t\t\t" .
			"});" . PHP_EOL;
		} else {
			$clickReady = '';
		}

		if ($changeChain != '') {
			$changeReady = "$('" . $changeChain . "').change(function() {\n\t\t\t\t" .
				"applyFilter();\n\t\t\t" .
			"});" . PHP_EOL;
		} else {
			$changeReady = '';
		}

		return PHP_EOL . "<script type='text/javascript'>
		function applyFilter() {
			strURL = $applyFilter
			loadUrl({ url: strURL });
		}

		function finishFinalize(options, data) {
			$('#text').text('Finished').fadeOut(2000);
		}
		$buttonFunctions

		$(function() {
			refreshFunction = 'applyFilter()';
			refreshMSeconds = $refreshMSeconds;
			setupPageTimeout();

			$('#" . $this->form_id . "').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
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
