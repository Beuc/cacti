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
	public $session_var    = 'sess_default';
	public $default_filter = array();
	public $rows_label     = '';
	public $js_extra       = '';
	public $dynamic        = true;
	public $has_graphs     = false;
	public $has_data       = false;
	public $has_save       = false;
	public $has_import     = false;
	public $has_export     = false;
	public $has_named      = false;

	private $item_rows     = array();
	private $filter_array  = array();

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
		$this->has_graphs    = false;
		$this->has_data      = false;
		$this->has_save      = false;
		$this->has_import    = false;
		$this->has_export    = false;
		$this->has_named     = false;
		$this->rows_label    = __('Rows');

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
		$this->filter_array['sort'] = array(
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

		// Make common adjustements
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

		$this->filter_array['rows'][0] += $this->filter_array['buttons'];

		// Buffer output
		ob_start();

		html_filter_start_box($this->form_header, $this->action_url, true, true, $this->action_label);

		if (isset($this->filter_array['rows'])) {
			print "<form id='" . $this->form_id . "' action='" . $this->form_action . "'>";

			foreach($this->filter_array['rows'] as $index => $row) {
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

				print '</div>' . PHP_EOL;
				print '</div>' . PHP_EOL;
			}

			print '</form>' . PHP_EOL;
		}

		html_filter_end_box();

		return ob_get_clean();
	}

	private function create_javascript() {
		$applyFilter  = "'" . $this->form_action;
		$clearFilter  = $applyFilter;
		$saveFilter   = $applyFilter;
		$importFilter = $applyFilter;
		$exportFilter = $applyFilter;

		if (strpos($applyFilter, '?') === false) {
			$separator = '?';
		} else {
			$separator = '';
		}

		$applyFilter  .= $separator;
		$clearFilter  .= $separator . "clear=true'";
		$saveFilter   .= $separator . "action=savefilter'";
		$importFilter .= $separator . "action=import'";
		$exportFilter .= $separator . "action=export'";
		$changeChain   = '';
		$clickChain    = '';

		if (!$this->has_save) {
			$saveFilter = "'#'";
		}

		if (!$this->has_import) {
			$importFilter = "'#'";
		}

		if (!$this->has_export) {
			$exportFilter = "'#'";
		}

		$filterLength = 0;

		if (isset($this->filter_array['rows'])) {
			foreach($this->filter_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'button':

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
				}
			}

			$applyFilter .= ';';
		}

		return "<script type='text/javascript'>
			function applyFilter() {
				strURL = $applyFilter
				loadUrl({ url: strURL });
			}

			function clearFilter() {
				loadUrl({ url: $clearFilter });
			}

			function saveFilter() {
				loadUrl({ url: $saveFilter });
			}

			function importFilter() {
				loadUrl({ url: $importFilter });
			}

			function exportFilter() {
				document.location = $exportFilter;
				Pace.stop();
			}

			$(function() {
				$('#" . $this->form_id . "').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('" . $changeChain . "').change(function() {
					applyFilter();
				});

				$('" . $clickChain . "').change(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				})

				$('#save').click(function() {
					saveFilter();
				})

				$('#import').click(function() {
					importFilter();
				})

				$('#export').click(function() {
					exportFilter();
				})
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
