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
 * Draws an edit form based on the provided configuration and fields arrays.
 *
 * @param array $array an array that contains all of the information needed to draw
 *   the html form. see the arrays contained in include/global_settings.php
 *   for the extract syntax of this array
 *
 * @return void
 */
function draw_edit_form($array) {
	if (cacti_sizeof($array)) {
		foreach ($array as $top_branch => $top_children) {
			if ($top_branch == 'config') {
				$config_array = $top_children;
			} elseif ($top_branch == 'fields') {
				$fields_array = $top_children;
			}
		}
	}

	if (cacti_sizeof($fields_array)) {
		if (!isset($config_array['no_form_tag'])) {
			print "<form class='cactiForm' method='post' autocomplete='off' action='" . ((isset($config_array['post_to'])) ? $config_array['post_to'] : get_current_page()) . "'" . ((isset($config_array['form_name'])) ? " name='" . $config_array['form_name'] . "'" : '') . ((isset($config_array['enctype'])) ? " enctype='" . $config_array['enctype'] . "'" : '') . ">";
		}

		$i         = 0;
		$row_class = 'odd';

		foreach ($fields_array as $field_name => $field_array) {
			if ($field_array['method'] == 'hidden') {
				if (!isset($field_array['value'])) {
					cacti_log("WARNING: Cacti Form field '$field_name' does not include a 'value' Column.  Using default.", false);
					cacti_debug_backtrace('form_edit');

					if (isset($field_array['default'])) {
						$field_array['value'] = $field_array['default'];
					} else {
						cacti_log("WARNING: Cacti Form field '$field_name' does not include a 'default' Column.  Using empty string.", false);
						$field_array['value'] = '';
					}
				}

				print '<div class="hidden formRow">';
				form_hidden_box($field_name, $field_array['value'], ((isset($field_array['default'])) ? $field_array['default'] : ''), true);
				print '</div>';
			} elseif ($field_array['method'] == 'hidden_zero') {
				if (!isset($field_array['value'])) {
					cacti_log("WARNING: Cacti Form field '$field_name' does not include a 'value' Column.  Using default.", false);
					cacti_debug_backtrace('form_edit');

					if (isset($field_array['default'])) {
						$field_array['value'] = $field_array['default'];
					} else {
						cacti_log("WARNING: Cacti Form field '$field_name' does not include a 'default' Column.  Using '0'.", false);
						$field_array['value'] = '0';
					}
				}

				print '<div class="hidden formRow">';
				form_hidden_box($field_name, $field_array['value'], '0', true);
				print '</div>';
			} elseif ($field_array['method'] == 'spacer') {
				$collapsible = (isset($field_array['collapsible']) && $field_array['collapsible'] == 'true');

				print "<div class='spacer formHeader" . ($collapsible ? ' collapsible' : '') . "' id='row_$field_name'><div class='formHeaderText'>" . html_escape($field_array['friendly_name']);
				print '<div class="formTooltip">' . (isset($field_array['description']) ? display_tooltip($field_array['description']) : '') . '</div>';
				print($collapsible ? "<div class='formHeaderAnchor'><i class='fa fa-angle-double-up'></i></div>" : '') . '</div></div>';
			} else {
				// Make a row using a div
				if (isset($config_array['force_row_color'])) {
					print "<div id='row_$field_name' class='formRow even-alternate $row_class'>";
				} else {
					print "<div id='row_$field_name' class='formRow $row_class'>";

					if ($row_class == 'even') {
						$row_class = 'odd';
					} else {
						$row_class = 'even';
					}
				}

				// Make a form cell
				print "<div class='formColumnLeft'>";

				print "<div class='formFieldName'>";

				if (isset($field_array['sub_checkbox'])) {
					if (!isset($field_array['sub_checkbox']['value'])) {
						cacti_log("WARNING: Cacti Form field '$field_name' does not include a sub_checkbox 'value' Column.  Using default", false);
						cacti_debug_backtrace('form_edit');

						if (isset($field_array['sub_checkbox']['default'])) {
							$field_array['sub_checkbox']['value'] = $field_array['default'];
						} else {
							cacti_log("WARNING: Cacti Form field '$field_name' does not include a 'default' Column.  Using ''.", false);
							$field_array['sub_checkbox']['value'] = '';
						}
					}

					form_checkbox(
						$field_array['sub_checkbox']['name'],
						$field_array['sub_checkbox']['value'],
						'',
						((isset($field_array['sub_checkbox']['default'])) 	? $field_array['sub_checkbox']['default'] : ''),
						((isset($field_array['sub_checkbox']['form_id'])) 	? $field_array['sub_checkbox']['form_id'] : ''),
						((isset($field_array['sub_checkbox']['class'])) 	? $field_array['sub_checkbox']['class'] : ''),
						((isset($field_array['sub_checkbox']['on_change'])) ? $field_array['sub_checkbox']['on_change'] : ''),
						((isset($field_array['sub_checkbox']['friendly_name'])) ? $field_array['sub_checkbox']['friendly_name'] : '')
					);
				}

				print html_escape($field_array['friendly_name']);

				if (read_config_option('hide_form_description') == 'on') {
					print '<br><span class="formFieldDescription">' . ((isset($field_array['description'])) ? $field_array['description'] : '') . "</span>";
				} else {
					print '<div class="formTooltip">';
					print display_tooltip((isset($field_array['description'])) ? $field_array['description'] : '');
					print '</div>';
				}

				print '</div>';

				// End form cell
				print '</div>';

				// New form column for content
				print '<div class="formColumnRight"><div class="formData">';

				draw_edit_control($field_name, $field_array);

				// End content column
				print '</div></div>';

				// End form row
				print '</div>';
			}

			$i++;
		}

		if (isset($_SESSION[SESS_ERROR_FIELDS]) && !cacti_sizeof($_SESSION[SESS_ERROR_FIELDS])) {
			kill_session_var(SESS_ERROR_FIELDS);
		}
	}
}

/**
 * Draws an edit control based on the specified method and field array.
 *
 * @param string $field_name The name of the control.
 * @param array an array containing data for this control. see include/global_form.php for more specific syntax
 *
 */
function draw_edit_control($field_name, &$field_array) {
	switch ($field_array['method']) {
		case 'textbox':
			form_text_box(
				$field_name,
				$field_array['value'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				$field_array['max_length'],
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				((isset($field_array['type'])) ? $field_array['type'] : 'text'),
				((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
				((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
			);

			break;
		case 'filepath':
			form_filepath_box(
				$field_name,
				$field_array['value'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				$field_array['max_length'],
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				'text',
				((isset($field_array['form_id'])) ? $field_array['form_id'] : '')
			);

			break;
		case 'dirpath':
			form_dirpath_box(
				$field_name,
				$field_array['value'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				$field_array['max_length'],
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				'text',
				((isset($field_array['form_id'])) ? $field_array['form_id'] : '')
			);

			break;
		case 'textbox_password':
			form_text_box(
				$field_name,
				$field_array['value'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				$field_array['max_length'],
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				'password',
				((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
				'********'
			);

			if (!isset($field_array['noconfirm'])) {
				print '<br>';

				form_text_box(
					$field_name . '_confirm',
					$field_array['value'],
					((isset($field_array['default'])) ? $field_array['default'] : ''),
					$field_array['max_length'],
					((isset($field_array['size'])) ? $field_array['size'] : '40'),
					'password',
					((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
					'********'
				);
			}

			break;
		case 'textarea':
			form_text_area(
				$field_name,
				$field_array['value'],
				$field_array['textarea_rows'],
				$field_array['textarea_cols'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
			);

			break;
		case 'drop_array':
			form_dropdown(
				$field_name,
				$field_array['array'],
				'',
				'',
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				((isset($field_array['friendly_name'])) ? $field_array['friendly_name'] : '')
			);

			break;
		case 'drop_icon':
			form_dropicon(
				$field_name,
				$field_array['array'],
				'',
				'',
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
			);

			break;
		case 'drop_language':
			form_droplanguage(
				$field_name,
				'',
				'',
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
			);

			break;
		case 'drop_files':
			$array_files = array();

			if (isset($field_array['directory'])) {
				$dir = $field_array['directory'];

				if (is_dir($dir) && is_readable($dir)) {
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
							if (is_readable($dir . '/' . $file) && $file != '.' && $file != '..') {
								if (!in_array($file, $field_array['exclusions'], true)) {
									$array_files[basename($file)] = basename($file);
								}
							}
						}
					}
				}
			}

			form_dropdown(
				$field_name,
				$array_files,
				'',
				'',
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				((isset($field_array['friendly_name'])) ? $field_array['friendly_name'] : '')
			);

			break;
		case 'drop_sql':
			form_dropdown(
				$field_name,
				db_fetch_assoc($field_array['sql']),
				'name',
				'id',
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				((isset($field_array['friendly_name'])) ? $field_array['friendly_name'] : '')
			);

			break;
		case 'drop_callback':
			form_callback(
				$field_name,
				$field_array['sql'],
				'name',
				'id',
				$field_array['action'],
				$field_array['id'],
				$field_array['value'],
				((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				((isset($field_array['friendly_name'])) ? $field_array['friendly_name'] : ''),
				((isset($field_array['request_vars'])) ? $field_array['request_vars'] : '')
			);

			break;
		case 'drop_multi':
			form_multi_dropdown(
				$field_name,
				$field_array['array'],
				(isset($field_array['sql']) ? db_fetch_assoc($field_array['sql']) : $field_array['value']),
				'id',
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
			);

			break;
		case 'drop_tree':
			grow_dropdown_tree(
				$field_array['tree_id'],
				'0',
				$field_name,
				$field_array['value']
			);

			break;
		case 'drop_color':
			form_color_dropdown(
				$field_name,
				$field_array['value'],
				__('None'),
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
			);

			break;
		case 'checkbox':
			form_checkbox(
				$field_name,
				$field_array['value'],
				$field_array['friendly_name'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
				$field_array['friendly_name']
			);

			break;
		case 'checkbox_group':
			if (isset($field_array['type']) && $field_array['type'] == 'flex') {
				print "</td></tr><tr><td><div id='{$field_name}_group' class='checkboxgroup1 flexContainer'>" . PHP_EOL;
			} else {
				print "<div id='{$field_name}_group' class='checkboxgroup1'>" . PHP_EOL;
			}

			foreach ($field_array['items'] as $check_name => $check_array) {
				if (isset($field_array['type']) && $field_array['type'] == 'flex') {
					print '<div class="flexChild">';
				}

				form_checkbox(
					$check_name,
					$check_array['value'],
					$check_array['friendly_name'],
					((isset($check_array['default'])) ? $check_array['default'] : ''),
					((isset($check_array['form_id'])) ? $check_array['form_id'] : ''),
					((isset($field_array['class'])) ? $field_array['class'] : ''),
					((isset($check_array['on_change'])) ? $check_array['on_change'] : (((isset($field_array['on_change'])) ? $field_array['on_change'] : ''))),
					$field_array['friendly_name'],
					true
				);

				if (isset($field_array['type']) && $field_array['type'] == 'flex') {
					print '</div>';
				} else {
					print '<br>';
				}
			}

			if (isset($field_array['type']) && $field_array['type'] == 'flex') {
				print '</div>' . PHP_EOL;
			} else {
				print '</div>' . PHP_EOL;
			}

			break;
		case 'radio':
			print "<div style='formRadio'>" . PHP_EOL;

			foreach ($field_array['items'] as $radio_index => $radio_array) {
				form_radio_button(
					$field_name,
					$field_array['value'],
					$radio_array['radio_value'],
					$radio_array['radio_caption'],
					((isset($field_array['default'])) ? $field_array['default'] : ''),
					((isset($field_array['class'])) ? $field_array['class'] : ''),
					((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
				);

				print '<br>';
			}

			print '</div>' . PHP_EOL;

			break;
		case 'custom':
			print $field_array['value'];

			break;
		case 'template_checkbox':
			print '<em>' . html_boolean_friendly($field_array['value']) . '</em>';

			form_hidden_box($field_name, $field_array['value'], '', true);

			break;
		case 'template_drop_array':
			print '<em>' . $field_array['array'][$field_array['value']] . '</em>';

			form_hidden_box($field_name, $field_array['value'], '', true);

			break;
		case 'font':
			form_font_box(
				$field_name,
				$field_array['value'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				$field_array['max_length'],
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				'text',
				((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
				((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
			);

			break;
		case 'file':
			form_file(
				$field_name,
				((isset($field_array['size'])) ? $field_array['size'] : '40'),
				((isset($field_array['accept'])) ? $field_array['accept'] : '')
			);

			break;
		case 'button':
			form_button(
				$field_name,
				((isset($field_array['value'])) ? $field_array['value'] : ''),
				((isset($field_array['title'])) ? $field_array['title'] : ''),
				((isset($field_array['on_click'])) ? $field_array['on_click'] : '')
			);

			break;
		case 'submit':
			form_submit(
				$field_name,
				((isset($field_array['value'])) ? $field_array['value'] : ''),
				((isset($field_array['title'])) ? $field_array['title'] : ''),
				((isset($field_array['on_click'])) ? $field_array['on_click'] : '')
			);

			break;

		default:
			if (isset($field_array['value'])) {
				print '<em>' . html_escape($field_array['value']) . '</em>';

				form_hidden_box($field_name, $field_array['value'], '', true);
			} else {
				cacti_log('ERROR: Field Name: ' . $field_name . ' includes Method: ' . $field_array['method'] . ' does not include a value \'value\' element.', false);
			}

			break;
	}
}

/**
 * form_button - draws a standard button form element
 *
 * @param string $form_name - the name of this form element
 * @param string $value - the display value for the button
 * @param string $title - the hover title for the button
 * @param string $action - the onClick action for the button
 *
 * @return void
 */
function form_button($form_name, $value, $title = '', $action = '') {
	print "<input type='button' class='ui-button ui-corner-all ui-widget' " .
		"id='$form_name' " .
		"name='$form_name' " .
		"value='" . html_escape($value) . "' " .
		($action != '' ? "onClick='$action'" : '') .
		($title != '' ? "title='" . html_escape($title) . "'" : '') . '>';
}

/**
 * Generates an HTML submit button with optional JavaScript action and title.
 *
 * @param string $form_name The name and ID of the form element.
 * @param string $value The display text of the submit button.
 * @param string $title Optional. The title attribute for the submit button.
 * @param string $action Optional. JavaScript code to execute on button click.
 *
 * @return void
 */
function form_submit($form_name, $value, $title = '', $action = '') {
	print "<input type='submit' class='ui-button ui-corner-all ui-widget' " .
		"id='$form_name' " .
		"name='$form_name' " .
		"value='" . html_escape($value) . "' " .
		($action != '' ? "onClick='$action'" : '') .
		($title != '' ? "title='" . html_escape($title) . "'" : '') . '>';
}

/**
 * Draws a standard html file input element
 *
 * @param string $form_name The name and ID of the file input element.
 * @param int $form_size The size attribute of the file input element. Default is 30.
 * @param string $form_accept The accept attribute of the file input element, specifying the types of files that the server accepts. Default is an empty string.
 *
 * @return void
 */
function form_file($form_name, $form_size = 30, $form_accept = '') {
	print "<div>";
	print "<label class='import_label' for='$form_name'>" . __('Select a File') . "</label>";
	print "<input type='file'";

	if (isset($_SESSION[SESS_ERROR_FIELDS]) && !empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
		print " class='import_button ui-state-default ui-corner-all txtErrorTextBox'";
		unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
	} else {
		print " class='import_button ui-state-default ui-corner-all'";
	}

	print " id='$form_name' name='$form_name' size='$form_size'" . ($form_accept != '' ? " accept='$form_accept'" : '') . ">";
	print "<span class='import_text'></span>";
	print "</div>";
}

/**
 * Draws a standard html textbox and provides status of a files existence
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param int $form_max_length - the maximum number of characters that can be entered into this textbox
 * @param int $form_size - the size (width) of the textbox
 * @param string $type - the type of textbox, either 'text' or 'password'
 * @param int $current_id - used to determine if a current value for this form element
 *   exists or not. a $current_id of '0' indicates that no current value exists,
 *   a non-zero value indicates that a current value does exist
 * @param mixed $data - array containing 'text' element for display and if 'error' element present, shows failure
 *
 * @return void
 */

function form_filepath_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $data = false) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	$extra_text  = '';
	$extra_color = '';
	$extra_class = '';
	$error_class = '';

	if (is_array($data)) {
		$extra_text  = $data['text'];
		$extra_class = (isset($data['error']) ? 'fa-times-circle' : 'fa-check-circle');
		$extra_color = (isset($data['error']) ? 'red' : 'green');
		$error_class = (isset($data['error']) ? ' txtErrorTextBox' : '');
	} else {
		if (isset($_SESSION[SESS_FIELD_VALUES])) {
			if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
				$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
			}
		}

		if (isset($_SESSION[SESS_ERROR_FIELDS])) {
			if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
				$error_class = ' txtErrorTextBox';
				unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
			}
		}

		if ($form_previous_value == '') {
			$extra_text  = '';
		} elseif (is_file(trim($form_previous_value))) {
			$extra_class = 'fa-check-circle';
			$extra_color = 'green';
			$extra_text  = __esc('File Found');
		} elseif (is_dir(trim($form_previous_value))) {
			$extra_class = 'fa-times-circle';
			$extra_color = 'red';
			$extra_text  = __esc('Path is a Directory and not a File');
		} else {
			$extra_class = 'fa-times-circle';
			$extra_color = 'red';
			$extra_text  = __esc('File is Not Found');
		}
	}

	$extra_data = '';

	if ($extra_text != '') {
		$extra_data = "<span class='cactiTooltipHint fa $extra_class' style='padding:5px;font-size:16px;color:$extra_color' title='$extra_text'></span>";
	}

	print " class='ui-state-default ui-corner-all$error_class'";

	print " id='$form_name' placeholder='" . __esc('Enter a valid file path') . "' name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
}

/**
 * Draws a standard html textbox and provides status of a directories existence
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param int $form_max_length - the maximum number of characters that can be entered into this textbox
 * @param int $form_size - the size (width) of the textbox
 * @param string $type - the type of textbox, either 'text' or 'password'
 * @param int $current_id - used to determine if a current value for this form element
 *   exists or not. a $current_id of '0' indicates that no current value exists,
 *   a non-zero value indicates that a current value does exist
 *
 * @return void
 */
function form_dirpath_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if (is_dir($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa-solid fa-circle-check' style='padding:5px;font-size:16px;color:green' title='" . __esc('Directory Found') . "'></span>";
	} elseif (is_file($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa-solid fa-circle-x' style='padding:5px;font-size:16px;color:red' title='" . __esc('Path is a File and not a Directory') . '></span>';
	} elseif ($form_previous_value == '') {
		$extra_data = '';
	} else {
		$extra_data = "<span class='cactiTooltipHint fa-solid fa-circle-x' style='padding:5px;font-size:16px;color:red' title='" . __esc('Directory is Not found') . "'></span>";
	}

	print " id='$form_name' name='$form_name' placeholder='" . __esc('Enter a valid directory path') . "' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
}

/**
 * Draws a standard html textbox
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param int $form_max_length - the maximum number of characters that can be entered into this textbox
 * @param int $form_size - the size (width) of the textbox
 * @param string $type - the type of textbox, either 'text' or 'password'
 * @param int $current_id - used to determine if a current value for this form element
 *   exists or not. a $current_id of '0' indicates that no current value exists,
 *   a non-zero value indicates that a current value does exist
 * @param string $placeholder - place a placeholder over an empty field
 * @param string $title - use a title attribute when hovering over the textbox
 *
 * @return void
 */
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $placeholder = '', $title = '') {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type' " . ($type == 'password' || $type == 'password_confirm' ? 'autocomplete="off" readonly onfocus="this.removeAttribute(\'readonly\');"' : '') . ($title != '' ? ' title="' . html_escape($title) . '"' : '');

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	} else {
		print " class='ui-state-default ui-corner-all'";
	}

	if (isset($_SESSION[SESS_FIELD_VALUES]) && isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	print " id='$form_name' " . ($placeholder != '' ? "placeholder='" . html_escape($placeholder) . "'" : '') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>";
}

/**
 * Draws a standard html hidden element
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param bool $form_default_value - the value of this form element to use if there is no current value available
 *
 * @return void
 */
function form_hidden_box($form_name, $form_previous_value, $form_default_value, $in_form = false) {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	print "<div style='display:none;'><input style='height:0px;' type='hidden' id='$form_name' name='$form_name' value='" . html_escape($form_previous_value) . "'></div>";
}

/**
 * Draws a standard html dropdown box
 *
 * @param string $form_name - the name of this form element
 * @param array $form_data - an array containing data for this dropdown. it can be formatted
 *   in one of two ways:
 *   $array["id"] = "value";
 *   -- or --
 *   $array[0]["id"] = 43;
 *   $array[0]["name"] = "Red";
 * @param string $column_display - used to identify the key to be used for display data. this
 *   is only applicable if the array is formatted using the second method above
 * @param string $column_id - used to identify the key to be used for id data. this
 *   is only applicable if the array is formatted using the second method above
 * @param mixed $form_previous_value - the current value of this form element
 * @param string $form_none_entry - the name to use for a default 'none' element in the dropdown
 * @param mixed $form_default_value - the value of this form element to use if there is
 *   no current value available
 * @param string $css_class - any css that needs to be applied to this form element
 * @param string $on_change - onChange modifier
 * @param string $display_name - The display name for this form object
 *
 * @return void
 */
function form_dropdown($form_name, $form_data, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '', $display_name = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($display_name != '') {
		$display = " data-defaultLabel='" . html_escape($display_name) . "'";
	} else {
		$display = '';
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $display . $class . $on_change . '>';

	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? ' selected' : '') . ">$form_none_entry</option>";
	}

	html_create_list($form_data, $column_display, $column_id, html_escape($form_previous_value));

	print '</select>';
}

/**
 * Draws a standard html dropdown box using icon definitions in the form array.
 *
 * @param string $form_name - the name of this form element
 * @param array $form_data - an array containing data for this dropdown. It must contain
 *   the following structure.
 *
 *   $dropdown_array = array(
 *     'server' => array(
 *        'display' => __('Some Value'),
 *        'class'   => 'fa fa-server',
 *        'style'   => 'width:30px;...'
 *     ),
 *     ...
 *   );
 *
 * @param string $column_display - used to identify the key to be used for display data. this
 *   is only applicable if the array is formatted using the second method above
 * @param string $column_id - used to identify the key to be used for id data. this
 *   is only applicable if the array is formatted using the second method above
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_none_entry - the name to use for a default 'none' element in the dropdown
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param string $css_class - any css that needs to be applied to this form element
 * @param string $on_change - onChange modifier
 *
 * @return void
 */
function form_dropicon($form_name, $form_data, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($class != '') {
		$class = " class='drop-icon $class' ";
	} else {
		$class = " class='drop-icon' ";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . $on_change . '>';

	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? ' selected' : '') . ">$form_none_entry</option>";
	}

	html_create_list($form_data, '', '', html_escape($form_previous_value));

	print '</select>';
}

/**
 * Generates a dropdown selection form element for languages.
 *
 * @param string $form_name The name attribute for the select element.
 * @param string $column_display Not used in the function.
 * @param string $column_id Not used in the function.
 * @param string $form_previous_value The previously selected value.
 * @param string $form_none_entry Not used in the function.
 * @param string $form_default_value The default value if no previous value is set.
 * @param string $class Optional. Additional CSS classes for the select element.
 * @param string $on_change Optional. JavaScript code to execute on change event.
 *
 * @return void
 */
function form_droplanguage($form_name, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	$languages = get_installed_locales();

	print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . $on_change . '>';

	foreach ($languages as $key => $value) {
		$selected = '';

		if ($form_previous_value == $key) {
			$selected = ' selected';
		}

		$flags = explode('-', $key);

		if (cacti_count($flags) > 1) {
			$flagName = strtolower($flags[1]);
		} else {
			$flagName = strtolower($flags[0]);
		}

		print '<option value=\'' . $key . '\'' . $selected . ' data-class=\'fi-' . $flagName . '\'><span class="fi fis fi-' . $flagName . '"></span>' . __($value) . '</option>';
	}

	print '</select>';
}

/**
 * Generates a form element based on the provided parameters and configuration.
 *
 * @param string $form_name The name of the form element.
 * @param string $classic_sql The SQL query to fetch data for the form element.
 * @param string $column_display The column name to be displayed in the form element.
 * @param string $column_id The column name to be used as the value in the form element.
 * @param string $action The action to be performed on form element change.
 * @param string $previous_id The previous ID value of the form element.
 * @param string $previous_value The previous value of the form element.
 * @param string $none_entry The text to display for a "none" entry.
 * @param string $default_value The default value for the form element.
 * @param string $class Optional. Additional CSS classes for the form element.
 * @param string $on_change Optional. JavaScript function to call on form element change.
 * @param string $display_name Optional. The display name for the column.
 * @param string $request_vars Optional. The the request variables to include in the action
 *
 * @return void
 */
function form_callback($form_name, $classic_sql, $column_display, $column_id, $action, $previous_id, $previous_value, $none_entry, $default_value, $class = '', $on_change = '', $display_name = '', $request_vars = '') {
	if ($previous_value == '') {
		$previous_value = $default_value;
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	$theme = get_selected_theme();

	if (read_config_option('autocomplete') > 0) {
		print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . '>';

		if (!empty($none_entry)) {
			print "<option value='0'" . (empty($previous_value) ? ' selected' : '') . ">$none_entry</option>";
		}

		$form_data = db_fetch_assoc($classic_sql);

		html_create_list($form_data, $column_display, $column_id, html_escape($previous_id));

		print '</select>';
	} else {
		if (empty($previous_id) && $previous_value == '') {
			$previous_value = $none_entry;
		}

		print "<input id='$form_name' name='$form_name' type='text' class='drop-callback ui-state-default ui-corner-all' data-action='$action' data-variables='$request_vars' data-callback='$on_change' data-value='" . html_escape($previous_value) . "' value='" . html_escape($previous_id) . "'>";
	}
}

/**
 * Draws a standard html checkbox
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_caption - the text to display to the right of the checkbox
 * @param string $form_default_value - the value of this form element to use if there is
 *   no current value available
 * @param int $current_id - used to determine if a current value for this form element
 *   exists or not. a $current_id of '0' indicates that no current value exists,
 *   a non-zero value indicates that a current value does exist
 * @param string $class - specify a css class
 * @param string $on_change - specify a javascript onchange action
 * @param string $title - specify a title for the checkbox on hover
 * @param boolean $show_label - show the form caption in the checkbox
 *
 * @return void
 */

function form_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0, $class = '', $on_change = '', $title = '', $show_label = false) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($class != '') {
		$class = ' ' . trim($class);
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change'";
	}

	if ($form_previous_value == 'on') {
		$checked = " checked aria-checked='true'";
	} else {
		$checked = " aria-checked='false'";
	}

	$labelClass = '';

	if ($show_label) {
		$labelClass = ' checkboxLabelWanted';
	}

	print "<span class='nowrap'>";
	print "<label class='checkboxSwitch' " . ($title != '' ? " title='" . html_escape($title) . "'" : '') . '><input ' . ($title != '' ? " title='" . html_escape($title) . "'" : '') . " class='formCheckbox$class' type='checkbox' id='$form_name' name='$form_name'" . $on_change . $checked . "><span class='checkboxSlider checkboxRound'></span></label>";
	print "<label class='checkboxLabel$labelClass' for='$form_name'>" . html_escape($form_caption) . '</label>';
	print '</span>';
}

/**
 * Draws a standard html radio button
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element (selected or not)
 * @param string $form_current_value - the current value of this form element (element id)
 * @param string $form_caption - the text to display to the right of the checkbox
 * @param string $form_default_value - the value of this form element to use if there is
 * @param string $class - The object class for customization
 * @param string $on_change - An onChange event to attach to the form object no current value available
 *
 * @return void
 */
function form_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($class != '') {
		$class = " $class";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	if ($form_previous_value == $form_current_value) {
		$checked = " checked aria-checked='true'";
	} else {
		$checked = " aria-checked='false'";
	}

	$css_id = $form_name . '_' . $form_current_value;

	print "<span class='nowrap'>";
	print "<label class='radioSwitch'><input value='" . html_escape($form_current_value) .
		"' class='formCheckbox$class' type='radio' id='$css_id' name='$form_name'" .
		$on_change . $checked . "><span class='radioSlider radioRound'></span></label>";
	print "<label class='radioLabelWanted' for='$css_id'>" . html_escape($form_caption) . '</label>';
	print '</span>';
}

/**
 * Draws a standard html text area box
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element (selected or not)
 * @param int $form_rows - the number of rows in the text area box
 * @param int $form_columns - the number of columns in the text area box
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param string $class Optional. Additional CSS classes to apply to the textarea element. Default is an empty string.
 * @param string $on_change Optional. JavaScript code to execute when the textarea value changes. Default is an empty string.
 * @param string $placeholder Optional. Placeholder text for the textarea element. Default is an empty string.
 *
 * @return void
 */
function form_text_area($form_name, $form_previous_value, $form_rows, $form_columns, $form_default_value, $class = '', $on_change = '', $placeholder = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	if ($placeholder != '') {
		$placeholder = " placeholder='" . html_escape($placeholder) . "'";
	}

	print "<textarea class='$class ui-state-default ui-corner-all' aria-multiline='true' cols='$form_columns' rows='$form_rows' id='$form_name' name='$form_name'" . $on_change . $placeholder . '>' . html_escape($form_previous_value) . '</textarea>';
}

/**
 * Draws a standard html multiple select dropdown
 *
 * @param string $form_name - the name of this form element
 * @param array $array_display - an array containing display values for this dropdown. it must
 *   be formatted like:
 *   $array[id] = display;
 * @param array $sql_previous_values - an array containing keys that should be marked as selected.
 *   it must be formatted like:
 *   $array[0][$column_id] = key
 * @param string $column_id - the name of the key used to reference the keys above
 * @param string $class Optional. Additional CSS classes to apply to the select element.
 * @param string $on_change Optional. JavaScript code to execute when the selection changes.
 *
 * @return void
 */
function form_multi_dropdown($form_name, $array_display, $sql_previous_values, $column_id, $class = '', $on_change = '') {
	if (!is_array($sql_previous_values) && $sql_previous_values != '') {
		$values              = explode(',', $sql_previous_values);
		$sql_previous_values = array();

		foreach ($values as $value) {
			$sql_previous_values[][$column_id] = $value;
		}
	} elseif ($sql_previous_values == '') {
		$values = db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', array($form_name));

		if ($values != '') {
			$values = explode(',', $values);

			foreach ($values as $value) {
				$sql_previous_values[][$column_id] = $value;
			}
		}
	}

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			$class .= ($class != '' ? ' ' : '') . 'txtErrorTextBox';
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		}
	}

	$class = "multiselect $class";

	if ($class != '') {
		$class .= " $class";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	print "<select style='height:20px;' size='1' class='$class' id='$form_name' name='$form_name" . "[]' multiple>";

	foreach (array_keys($array_display) as $id) {
		print "<option value='" . $id . "'";

		if (is_array($sql_previous_values) && cacti_sizeof($sql_previous_values)) {
			for ($i = 0; ($i < cacti_count($sql_previous_values)); $i++) {
				if ($sql_previous_values[$i][$column_id] == $id) {
					print ' selected';
				}
			}
		}

		print '>' . html_escape($array_display[$id]);
		print '</option>';
	}

	print '</select>';
}

/**
 * Draws a dropdown containing a list of colors that uses a bit
 *   of css magic to make the dropdown item background color represent each color in
 *   the list
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_none_entry - the name to use for a default 'none' element in the dropdown
 * @param string $form_default_value - the value of this form element to use if there is no current value available
 * @param string $class Optional. Additional CSS classes for the dropdown.
 * @param string $on_change Optional. JavaScript code to execute on change event.
 *
 * @return void
 */
function form_color_dropdown($form_name, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if ($class != '') {
		$class = " class='colordropdown $class' ";
	} else {
		$class = " class='colordropdown'";
	}

	$current_color = db_fetch_cell_prepared(
		'SELECT hex
		FROM colors
		WHERE id = ?',
		array($form_previous_value)
	);

	if ($on_change != '') {
		$on_change = ' ' . $on_change . ';';
	}

	$on_change = " onChange='this.style.backgroundColor=this.options[this.selectedIndex].style.backgroundColor;$on_change'";

	$colors_sql = 'SELECT *
		FROM colors
		ORDER BY
			SUBSTRING(hex,0,2) ASC,
			SUBSTRING(hex,2,2) ASC,
			SUBSTRING(hex,4,2) ASC';

	$colors_list = db_fetch_assoc($colors_sql);

	print "<select style='background-color: #$current_color;' id='$form_name' name='$form_name'" . $class . $on_change . '>';

	if ($form_none_entry != '') {
		print "<option value='0'>$form_none_entry</option>";
	}

	if (cacti_sizeof($colors_list)) {
		foreach ($colors_list as $color) {
			if ($color['name'] == '') {
				$display = __('Cacti Color (%s)', $color['hex']);
			} else {
				$display = $color['name'] . ' (' . $color['hex'] . ')';
			}

			print "<option data-color='" . $color['hex'] . "' style='background-color: #" . $color['hex'] . ";' value='" . $color['id'] . "'";

			if ($form_previous_value == $color['id']) {
				print ' selected';
			}

			print '>' . html_escape($display) . '</option>';
		}
	}

	print '</select>';
}

/**
 * Draws a standard html textbox and provides status of a fonts existence
 *
 * @param string $form_name - the name of this form element
 * @param string $form_previous_value - the current value of this form element
 * @param string $form_default_value - the value of this form element to use if there is
 *   no current value available
 * @param int $form_max_length - the maximum number of characters that can be entered
 *   into this textbox
 * @param int $form_size - the size (width) of the textbox
 * @param string $type - the type of textbox, either 'text' or 'password'
 * @param int $current_id - used to determine if a current value for this form element
 *   exists or not. a $current_id of '0' indicates that no current value exists,
 *   a non-zero value indicates that a current value does exist
 * @param string $placeholder The placeholder text for the input element. Default is an empty string.
 *
 * @return void
 */
function form_font_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $placeholder = '') {
	global $config;

	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	if (isset($_SESSION[SESS_ERROR_FIELDS])) {
		if (!empty($_SESSION[SESS_ERROR_FIELDS][$form_name])) {
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION[SESS_ERROR_FIELDS][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	}

	if (isset($_SESSION[SESS_FIELD_VALUES])) {
		if (!empty($_SESSION[SESS_FIELD_VALUES][$form_name])) {
			$form_previous_value = $_SESSION[SESS_FIELD_VALUES][$form_name];
		}
	}

	if ($form_previous_value == '') { # no data: defaults are used; everything is fine
		$extra_data = '';
	} else {
		/* verifying all possible pango font params is too complex to be tested here
		 * so we only escape the font
		 */
		$extra_data = "<span style='color:green'><br>[" . __('NO FONT VERIFICATION POSSIBLE') . ']</span>';
	}

	print " id='$form_name' " . ($placeholder != '' ? "placeholder='" . html_escape($placeholder) . "'" : '') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
}

/**
 * Given as set of form options in the form of an array
 *   generate a continuation form confirm dialog for the user.
 *
 * @param array $form_data - Options to present to the users depending on the drop action
 *
 * The options array has two sections 'general' and 'options'.  The 'general' option
 *   must includes the following 4 variables:
 *   - page: The page that is being rendered or returnedd to in case of a cancel
 *   - actions: An array of legal actions that we can construct a title for
 *   - eaction: An action to add to the form save when there are more than one on a page
 *   - optvar: A request variable to pull the selected option from.  Normally 'drp_action'
 *   - item_array: An array of selected items that have been pre-processed
 *   - item_list: An string of list items "<li>Title</li>" that have been pre-processed
 *   - header: A paragraph that is placed before the options and after the message text
 *   - footer: A paragraph that is placed after the options and just before the Continue button
 *
 * The 'options' array should have a matching value array for each of the approved
 *   actions.  For each action, you need one of the following formats variables:
 *   - scont - Singular continuation string
 *   - pcont - Plural continuation string
 *   - cont  - Generic continuation string
 *   - smessage - Singular confirmation message to the user.
 *   - pmessage - Plural confirmation message to the user.
 *   - message  - Generic confirmation message to the user.
 *   - extra    - An array of general form input.  The supported methods include:
 *      textbox, other, drop_array, checkbox, radio_button
 *      additional options include: title, default, width, options for radio_button, and array for drop_array
 *
 * An example might look like the following:
 *
 * $form_data = array(
 *	'general' => array(
 *		'page'       => 'user_domains.php',
 *		'actions'    => $actions,
 *		'eaction'    => 'action_variable', // Extra Action
 *		'optvar'     => 'drp_action'
 *		'header'     => 'some header text',
 *		'item_array' => $d_array,
 *		'item_list'  => $d_list,
 *		'footer'     => 'some footer text'
 *	),
 *	'options' => array(
 *		1 => array(
 *			'header'     => 'some header text',
 *			'smessage' => __('Click \'Continue\' to Delete the following User Domain.'),
 *			'pmessage' => __('Click \'Continue\' to Delete following User Domains.'),
 *			'scont'    => __('Delete User Domain'),
 *			'pcont'    => __('Delete User Domains')
 *			'footer'     => 'some footer text'
 *		),
 *		2 => array(
 *			'header'     => 'some header text',
 *			'smessage' => __('Click \'Continue\' to Disable the following User Domain.'),
 *			'pmessage' => __('Click \'Continue\' to Disable following User Domains.'),
 *			'scont'    => __('Disable User Domain'),
 *			'pcont'    => __('Disable User Domains'),
 *			'extra'    => array(
 *				'group_prefix' => array(
 *					'method'  => 'textbox',
 *					'title'   => __('Group Prefix:'),
 *					'default' => __('New Group'),
 *					'width'   => 25,
 *					'size'    => 25
 *				)
 *			),
 *			'footer'     => 'some footer text'
 *		)
 *	);
 *
 * @param string $plugin_hook - The plugin hook to call for the continuation
 * @param array $save - An array of data to save for the continuation
 *
 * @return void - Data is streamed through stdout
 */
function form_continue_confirmation($form_data, $plugin_hook = '', $save = array()) {
	$page      = $form_data['general']['page'];
	$actions   = $form_data['general']['actions'];
	$drpvar    = $form_data['general']['optvar'];
	$iarray    = $form_data['general']['item_array'];
	$ilist     = $form_data['general']['item_list'];
	$drpval    = get_nfilter_request_var($drpvar);
	$poutput   = '';
	$form_name = 'form';

	if (!isset($form_data['options'][$drpval]) && $plugin_hook != '' && cacti_sizeof($iarray)) {
		$title = __('Proceed with action');

		if (!cacti_sizeof($save)) {
			/* Legacy plugin form confirmation logic */
			$save['drp_action'] = $drpval;

			if ($ilist != '') {
				$save['ds_list']  = $ilist;
			}

			if (cacti_sizeof($iarray)) {
				$save['ds_array'] = $iarray;
			}
		}

		// Trap the output
		ob_start();

		api_plugin_hook_function($plugin_hook, $save);

		$poutput = ob_get_clean();
	} elseif (cacti_sizeof($iarray)) {
		$data = $form_data['options'][$drpval];

		if (isset($data['return']) && $data['return'] == true) {
			raise_message('form_return', $data['rmessage'], MESSAGE_LEVEL_ERROR);
			header('Location: ' . $page);

			exit;
		} elseif (cacti_sizeof($iarray) > 1) {
			if (isset($data['pmessage'])) {
				$message = $data['pmessage'];
			} elseif (isset($data['message'])) {
				$message = $data['message'];
			}

			if (isset($data['pcont'])) {
				$title = $data['pcont'];
			} elseif (isset($data['cont'])) {
				$title = $data['cont'];
			}
		} else {
			if (isset($data['smessage'])) {
				$message = $data['smessage'];
			} elseif (isset($data['message'])) {
				$message = $data['message'];
			}

			if (isset($data['scont'])) {
				$title = $data['scont'];
			} elseif (isset($data['cont'])) {
				$title = $data['cont'];
			}
		}
	} else {
		raise_message(40);
		header('Location: ' . $page);

		exit;
	}

	top_header();

	form_start($page, 'action_confirm');

	html_start_box($actions[$drpval], '60%', true, '3', 'center', '');

	if (isset($message)) {
		print "<div class='left'><p>$message</p></div>";
	}

	if (isset($form_data['general']['header'])) {
		print "<div class='textArea left' colspan='3'><p>";
		print $form_data['general']['header'];
		print '</p></div>';
	}

	if (isset($data['header'])) {
		print "<div class='textArea left' colspan='3'><p>";
		print $data['header'];
		print '</p></div>';
	}

	if ($ilist != '' && $poutput == '') {
		print "<div class='textArea left' colspan='3'>";
		print "<div class='itemlist'><ul>$ilist</ul></div>";
		print '</div>';
	}

	if (isset($data['flist'])) {
		if (cacti_sizeof($iarray) > 1) {
			if (isset($data['pfmessage'])) {
				$message = $data['pfmessage'];
			} elseif (isset($data['fmessage'])) {
				$message = $data['fmessage'];
			}
		} else {
			if (isset($data['sfmessage'])) {
				$message = $data['sfmessage'];
			} elseif (isset($data['fmessage'])) {
				$message = $data['fmessage'];
			}
		}

		print "<div class='textArea left' colspan='3'>";
		print "<p>$message</p>";
		print '</div>';
		print "<div class='textArea left' colspan='3'>";
		print "<div class='itemlist'><ul>{$data['flist']}</ul></div>";
		print '</div>';
	}

	if (isset($data['extra'])) {
		/* prepend checkboxes for this form */
		$form_array = array();

		foreach($data['extra'] as $field_name => $field_array) {
			$form_array += array($field_name => $field_array);

			$form_array[$field_name]['value'] = '';

			/* two form overrides */
			if (isset($field_array['title'])) {
				$form_array[$field_name]['friendly_name'] = $field_array['title'];
			}

			if (isset($field_array['width'])) {
				$form_array[$field_name]['max_length']    = $field_array['width'];
			}

			if (read_config_option('hide_form_description') == 'on') {
				$form_array[$field_name]['description'] = '';
			}

			$form_array[$field_name]['form_id'] = 0;

			if (isset($field_array['confirm'])) {
				$form_array[$field_name]['sub_checkbox'] = array(
					'name'          => 't_' . $field_name,
					'friendly_name' => __('Update this Field'),
					'class'         => 'ui-state-disabled',
					'value'         => ''
				);
			}
		}

		print "<div class='confirm_actions'>";

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);

		print '</div>';
	}

	if (isset($data['footer'])) {
		print "<div class='textArea left' colspan='3'><p>";
		print $data['footer'];
		print '</p></div>';
	}

	if (isset($form_data['general']['footer'])) {
		print "<div class='textArea left' colspan='3'><p>";
		print $form_data['general']['footer'];
		print '</p></div>';
	}

	if ($poutput != '') {
		print $poutput;
	}

	print "<div class='saveRow'>";
	print "<input type='hidden' name='action' value='actions'>";

	if (isset($form_data['general']['eaction'])) {
		if (!isset($form_data['general']['eactionid'])) {
			$form_data['eactionid'] = 1;
		}

		print "<input type='hidden' name='{$form_data['general']['eaction']}' value='{$form_data['general']['eactionid']}'>";
	}

	if (isset($data['eaction'])) {
		if (!isset($data['eactionid'])) {
			$data['eactionid'] = 1;
		}

		print "<input type='hidden' name='{$data['eaction']}' value='{$data['eactionid']}'>";
	}

	print "<input type='hidden' name='selected_items' value='" . (isset($iarray) ? serialize($iarray) : '') . "'>";
	print "<input type='hidden' name='drp_action' value='" . html_escape($drpval) . "'>";
	print "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo(\"$page\")' title='" . __('Return to previous page'). "'>&nbsp;";
	print "<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='$title'>";
	print '</div>';

	html_end_box(false, true);

	form_end();

	bottom_footer();
}

/**
 * Draws a table presenting the user with some choice and allowing
 *   them to either proceed (delete) or cancel
 *
 * @param string $body_text - the text to prompt the user with on this form
 * @param string $cancel_url - the url to go to when the user clicks 'cancel'
 * @param string $action_url - the url to go to when the user clicks 'delete'
 *
 * @return void
 */
function form_confirm($title_text, $body_text, $cancel_url, $action_url) { ?>
	<br>
	<table style='width:60%;'>
		<tr>
			<td class='even' colspan='10'>
				<table>
					<tr class='cactiTableTitle'>
						<td class='textHeaderDark'><?php print $title_text; ?></td>
					</tr>
					<?php
					form_area($body_text);
					form_confirm_buttons($action_url, $cancel_url);
					?>
				</table>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Draws a cancel and delete button suitable for display
 *   on a confirmation form
 *
 * @param string $cancel_url - the url to go to when the user clicks 'cancel'
 * @param string $action_url - the url to go to when the user clicks 'delete'
 *
 * @return void
 */
function form_confirm_buttons($action_url, $cancel_url) {
	global $config;
	?>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo("<?php print html_escape(CACTI_PATH_URL . $cancel_url); ?>")' value='<?php print __esc('Cancel'); ?>'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo("<?php print html_escape(CACTI_PATH_URL . $action_url . '&confirm=true'); ?>")' value='<?php print __esc('Delete'); ?>'>
		</td>
	</tr>
	<?php
}

/**
 * Draws a (save|create) and cancel button at the bottom of
 *   an html edit form
 *
 * @param string $cancel_url - the url to go to when the user clicks 'cancel'
 * @param string $force_type - if specified, will force the 'action' button to be either
 *   'save' or 'create'. otherwise this field should be properly auto-detected
 * @param string $key_field - the name of the key field in the form
 * @param boolean $ajax - whether or not to use ajax for the return
 *
 * @return void
 */
function form_save_button($cancel_url, $force_type = '', $key_field = 'id', $ajax = true) {
	$calt = __('Cancel');

	if (empty($force_type) || $force_type == 'return') {
		if (isempty_request_var($key_field)) {
			$alt = __esc('Create');
		} else {
			$alt = __esc('Save');

			if ($force_type != '') {
				$calt   = __esc('Return');
			} else {
				$calt   = __esc('Cancel');
			}
		}
	} elseif ($force_type == 'save') {
		$alt = __esc('Save');
	} elseif ($force_type == 'create') {
		$alt = __esc('Create');
	} elseif ($force_type == 'close') {
		$alt = __esc('Close');
	} elseif ($force_type == 'import') {
		$alt = __esc('Import');
	} elseif ($force_type == 'export') {
		$alt = __esc('Export');
	}

	if ($force_type != 'import' && $force_type != 'export' && $force_type != 'save' && $force_type != 'close' && $cancel_url != '') {
		$cancel_action = "<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo(\"" . html_escape(sanitize_uri($cancel_url)) . "\")' value='" . $calt . "'>";
	} else {
		$cancel_action = '';
	}

	?>
	<div class='cactiTable saveRowParent'>
		<div class='formRow'>
			<div class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<?php print $cancel_action; ?>
				<input type='submit' class='<?php print $force_type; ?> ui-button ui-corner-all ui-widget' id='submit' value='<?php print $alt; ?>'>
			</div>
		</div>
	</div>
	<?php

	form_end($ajax);
}

/**
 * Draws a set of buttons at the end of an edit form.
 *
 * This function constructs a set of form buttons using the following
 * array structure:
 *
 * id     = An id for the button or submit object
 * type   = Either 'button' or 'submit'.  If unset 'button'
 * value  = A human readable button name
 * method = As set of methods for form actions
 *
 * methods include:
 *
 * return - Return from whence you came
 * get    - Submit with a get action
 * post   - Submit with a post action
 * url    - A URL to get, post, or cancel to
 * data   - A JSON encoded structure of post or get data
 *
 * @param array $buttons - an array of 'id', 'value', 'method', 'type'
 * @param string $cancel_url - A url to return to when the user hit's cancel
 * @param string $force_type - For the type of the button to this
 * @param string $key_field  - The id of the key field.
 * @param bool $ajax     - handle the return with ajax or a page load
 *
 * @return void
 */
function form_save_buttons($buttons, $cancel_url = '', $force_type = '', $key_field = 'id', $ajax = true) {
	$calt = __('Cancel');

	if ($cancel_url == '') {
		if (isset($_SERVER['HTTP_REFERER'])) {
			$url_components = parse_url($_SERVER['HTTP_REFERER']);
			$cancel_url = $url_components['path'];
		} else {
			$cancel_url = '';
		}
	}

	if (empty($force_type) || $force_type == 'return') {
		if (isempty_request_var($key_field)) {
			$alt = __esc('Create');
		} else {
			$alt = __esc('Save');
			if ($force_type != '') {
				$calt   = __esc('Return');
			} else {
				$calt   = __esc('Cancel');
			}
		}
	} elseif ($force_type == 'save') {
		$alt = __esc('Save');
	} elseif ($force_type == 'create') {
		$alt = __esc('Create');
	} elseif ($force_type == 'close') {
		$alt = __esc('Close');
	} elseif ($force_type == 'import') {
		$alt = __esc('Import');
	} elseif ($force_type == 'export') {
		$alt = __esc('Export');
	}
	if ($force_type != 'import' && $force_type != 'export' && $force_type != 'save' && $force_type != 'close' && $cancel_url != '') {
		$cancel_action = "<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo(\"" . html_escape($cancel_url, ENT_QUOTES) . "\")' value='" . $calt . "'>";
	} else {
		$cancel_action = '';
	}

	?>
	<div class='cactiTable saveRowParent'>
		<div class='formRow'>
			<div class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<?php foreach ($buttons as $b) {
					$type = 'button';

					if (isset($b['type']) && $b['type'] == 'submit') {
						$type = 'submit';
					}

					print "<input type='$type' class='ui-button ui-corner-all ui-widget' id='" . $b['id'] . "' value='" . html_escape($b['value']) . "'";

					$onclick = '';

					if (!empty($b['method'])) {
						$url  = empty($b['url']) ? '' : html_escape($b['url'], ENT_QUOTES);
						$data = empty($b['data']) ? '{}' : $b['data'];

						switch ($b['method']) {
							case 'return':
								if ($url == '') {
									$url = $cancel_url;
								}

								if ($ajax) {
									$onclick = 'cactiReturnTo("' . $url . '")';
								} else {
									$onclick = "document.location ='$url'";
								}

								break;
							case 'post':
								$onclick = "var pv=$data;postUrl({ url: '$url' }, pv); return false;";

								break;
							case 'get':
								$onclick = "var pv=$data;loadUrl({ url: '$url' }, pv); return false;";

								break;
						}
					}

					if (!empty($onclick)) {
						print " onclick='" . html_escape($onclick, ENT_QUOTES) . "'";
					}

					print '>' . PHP_EOL;
				} ?>
			</div>
		</div>
	</div>
	<?php

	form_end(true);
}

/**
 * Draws post form start. To be combined with form_end()
 *
 * @param string $action - a mandatory php file URI
 * @param string $id     - an optional id, if empty, one will be generated
 * @param bool $multipart - whether or not to use multipart encoding
 *
 * @return void
 */
function form_start($action, $id = '', $multipart = false) {
	global $form_id, $form_action;
	static $counter = 1;

	if ($id == '') {
		$form_id = 'form' . $counter;
		$counter++;
	} else {
		$form_id = trim($id);
	}

	$form_action = $action;

	print "<form class='cactiFormStart' id='$form_id' name='$form_id' action='$form_action' autocomplete='off' method='post'" . ($multipart ? " enctype='multipart/form-data'" : '') . ">";
}

/**
 * Draws post form end. To be combined with form_start()
 *
 * This function prints the closing </form> tag and, if the $ajax parameter is true,
 * includes JavaScript to handle unsaved form data warnings and form validation.
 *
 * @param bool $ajax Optional. Whether to include AJAX handling JavaScript. Default true.
 *
 * @return void
 */
function form_end($ajax = true) {
	global $form_id, $form_action;

	print '</form>' . PHP_EOL;

	if ($ajax) {
		if ($form_id == null) {
			cacti_log('WARNING: Function: form_end() called without a form_start() called first', false);
			cacti_debug_backtrace('FORM', false, true);
			$form_id = 'empty';
		}
		?>
		<script type='text/javascript'>
			var formArray = [];
			var changed = false;

			function warningMessage(href, type, scroll_or_id) {
				title = '<?php print __esc('Warning Unsaved Form Data'); ?>';
				returnStr = '<div id="messageContainer" style="display:none">' +
					'<h4><?php print __('Unsaved Changes Detected'); ?></h4>' +
					'<p style="display:table-cell;overflow:auto"><?php print __('You have unsaved changes on this form.  If you press &#39;Continue&#39; these changes will be discarded.  Press &#39;Cancel&#39; to continue editing the form.'); ?></p>' +
					'</div>';

				$('#messageContainer').remove();
				$('body').append(returnStr);

				var messageButtons = {
					'Cancel': {
						text: sessionMessageCancel,
						id: 'messageCancel',
						click: function() {
							$(this).dialog('close');
							$('#messageContainer').remove();
						}
					},
					'Continue': {
						text: sessionMessageContinue,
						id: 'messageContinue',
						click: function() {
							$('#messageContainer').remove();

							if (type == 'noheader') {
								loadUrl({
									url: href,
									scroll: scroll_or_id,
									force: true
								})
							} else if (type == 'toptab') {
								loadUrl({
									url: href,
									scroll: scroll_or_id,
									force: true,
									loadType: 'toptab'
								});
							} else {
								loadUrl({
									url: href,
									force: true
								});
							}
						}
					}
				};

				messageWidth = $(window).width();
				if (messageWidth > 600) {
					messageWidth = 600;
				} else {
					messageWidth -= 50;
				}

				$('#messageContainer').dialog({
					draggable: true,
					resizable: false,
					height: 'auto',
					minWidth: messageWidth,
					maxWidth: 800,
					maxHeight: 600,
					title: title,
					buttons: messageButtons
				});
			}

			$(function() {
				formValidate('#<?php print $form_id; ?>', '<?php print $form_action; ?>');
			});
		</script>
		<?php
	}
}
