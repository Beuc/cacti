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

$actions = array(
	'1' => __('Delete')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'remove':
		color_remove();

		header('Location: color.php');

		break;
	case 'edit':
		top_header();

		color_edit();

		bottom_footer();

		break;
	case 'export':
		color_export();

		break;
	case 'import':
		top_header();

		color_import();

		bottom_footer();

		break;

	default:
		top_header();

		color();

		bottom_footer();

		break;
}

function form_save() {
	if (isset_request_var('save_component_color')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']        = get_nfilter_request_var('id');

		if (get_nfilter_request_var('read_only') == '') {
			$save['name']      = get_filter_request_var('name', FILTER_SANITIZE_SPECIAL_CHARS);
			$save['hex']       = form_input_validate(get_nfilter_request_var('hex'),  'hex',  '^[a-fA-F0-9]+$' , false, 3);
		} else {
			$save['name']      = get_filter_request_var('hidden_name', FILTER_SANITIZE_SPECIAL_CHARS);
			$save['read_only'] = 'on';
		}

		if (!is_error_message()) {
			$color_id = sql_save($save, 'colors');

			if ($color_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: color.php?action=edit&id=' . (empty($color_id) ? get_nfilter_request_var('id') : $color_id));
		} else {
			header('Location: color.php');
		}
	} elseif (isset_request_var('save_component_import')) {
		if (isset($_FILES['import_file']['tmp_name'])) {
			if (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
				$csv_data   = file($_FILES['import_file']['tmp_name']);
				$debug_data = color_import_processor($csv_data);

				if (cacti_sizeof($debug_data)) {
					$_SESSION['import_debug_info'] = $debug_data;
				}

				header('Location: color.php?action=import');
			}
		} else {
			raise_message(35);

			header('Location: color.php?action=import');
		}
	}

	exit;
}

function form_actions() {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM colors WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: color.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = array();

		/* loop through each of the graphs selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				$color = db_fetch_row_prepared('SELECT name, hex FROM colors WHERE id = ?', array($matches[1]));

				$ilist .= '<li>' . ($color['name'] != '' ? html_escape($color['name']): __('Unnamed Color')) . ' (<span style="background-color:#' . $color['hex'] . '">' . $color['hex'] . '</span>)</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'color.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Delete the following Color.'),
					'pmessage' => __('Click \'Continue\' to Delete following Colors.'),
					'scont'    => __('Delete Color'),
					'pcont'    => __('Delete Colors')
				),
			)
		);

		form_continue_confirmation($form_data);
	}
}

function color_import_processor(&$colors) {
	$i            = 0;
	$hexcol       = 0;
	$return_array = array();

	if (cacti_sizeof($colors)) {
		foreach ($colors as $color_line) {
			/* parse line */
			$line_array     = explode(',', $color_line);
			$insert_columns = array();
			$save_order    = '(';
			$update_suffix = '';

			/* header row */
			if ($i == 0) {
				$j             = 0;
				$first_column  = true;
				$required      = 0;

				if (cacti_sizeof($line_array)) {
					foreach ($line_array as $line_item) {
						$line_item = trim(str_replace("'", '', $line_item));
						$line_item = trim(str_replace('"', '', $line_item));

						switch ($line_item) {
							case 'hex':
								$hexcol = $j;
							case 'name':
								if (!$first_column) {
									$save_order .= ', ';
								}

								$save_order .= $line_item;

								$insert_columns[] = $j;
								$first_column     = false;

								if ($update_suffix != '') {
									$update_suffix .= ", $line_item=VALUES($line_item)";
								} else {
									$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
								}

								$required++;

								break;

							default:
								/* ignore unknown columns */
						}

						$j++;
					}
				}

				$save_order .= ')';

				if ($required >= 2) {
					array_push($return_array, '<b>HEADER LINE PROCESSED OK</b>:  <br>Columns found where: ' . $save_order . '<br>');
				} else {
					array_push($return_array, '<b>HEADER LINE PROCESSING ERROR</b>: Missing required field <br>Columns found where:' . $save_order . '<br>');

					break;
				}
			} else {
				$save_value   = '(';
				$j            = 0;
				$first_column = true;
				$sql_where    = '';

				if (cacti_sizeof($line_array)) {
					foreach ($line_array as $line_item) {
						if (in_array($j, $insert_columns, true)) {
							$line_item = trim(str_replace("'", '', $line_item));
							$line_item = trim(str_replace('"', '', $line_item));

							if (!$first_column) {
								$save_value .= ',';
							} else {
								$first_column = false;
							}

							$save_value .= "'" . $line_item . "'";

							if ($j == $hexcol) {
								$sql_where = "WHERE hex='$line_item'";
							}
						}

						$j++;
					}
				}

				$save_value .= ')';

				if ($j > 0) {
					if (isset_request_var('allow_update')) {
						$sql_execute = 'INSERT INTO colors ' . $save_order .
							' VALUES ' . $save_value . $update_suffix;

						if (db_execute($sql_execute)) {
							array_push($return_array,"INSERT SUCCEEDED: $save_value");
						} else {
							array_push($return_array,"INSERT FAILED: $save_value");
						}
					} else {
						/* perform check to see if the row exists */
						$existing_row = db_fetch_row("SELECT * FROM colors $sql_where");

						if (cacti_sizeof($existing_row)) {
							array_push($return_array,"<strong>INSERT SKIPPED, EXISTING:</strong> $save_value");
						} else {
							$sql_execute = 'INSERT INTO colors ' . $save_order .
								' VALUES ' . $save_value;

							if (db_execute($sql_execute)) {
								array_push($return_array,"INSERT SUCCEEDED: $save_value");
							} else {
								array_push($return_array,"INSERT FAILED: $save_value");
							}
						}
					}
				}
			}

			$i++;
		}
	}

	return $return_array;
}

function color_import() {
	form_start('color.php?action=import', '', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box('Import Results', '100%', '', '3', 'center', '');

		print "<tr class='even'><td>
			<p class='textArea'>" . __('Cacti has imported the following items:') . "</p>
		</td></tr>\n";

		if (cacti_sizeof($_SESSION['import_debug_info'])) {
			foreach ($_SESSION['import_debug_info'] as $import_result) {
				print "<tr class='even'><td>" . $import_result . "</td></tr>\n";
			}
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Colors'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'><?php print __('Import Colors from Local File'); ?></font><br>
			<?php print __('Please specify the location of the CSV file containing your Color information.');?>
		</td>
		<td class='left'>
			<div>
				<label class='import_label' for='import_file'><?php print __('Select a File'); ?></label>
				<input class='ui-state-default ui-corner-all import_button' type='file' id='import_file' name='import_file'>
				<span class='import_text'></span>
			</div>
		</td>
	</tr><?php
	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'><?php print __('Overwrite Existing Data?');?></font><br>
			<?php print __('Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old rows, only update duplicate rows.');?>
		</td>
		<td class='left'>
			<input type='checkbox' name='allow_update' id='allow_update'><?php print __('Allow Existing Rows to be Updated?');?>
		</td><?php

	html_end_box(false);

	html_start_box(__('Required File Format Notes'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td><strong><?php print __('The file must contain a header row with the following column headings.');?></strong>
			<br><br>
				<?php print __('<strong>name</strong> - The Color Name');?><br>
				<?php print __('<strong>hex</strong> - The Hex Value');?><br>
			<br>
		</td>
	</tr><?php

	form_hidden_box('save_component_import','1','');

	html_end_box();

	form_save_button('color.php', 'import');
}

function color_edit() {
	global $fields_color_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$color        = db_fetch_row_prepared('SELECT * FROM colors WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('Colors [edit: %s]', $color['hex']);
	} else {
		$header_label = __('Colors [new]');
	}

	form_start('color.php', 'color');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_color_edit, (isset($color) ? $color : array()))
		)
	);

	html_end_box(true, true);

	form_save_button('color.php');

	?>
	<script type='text/javascript'>
	$(function() {
		checkReadonly();

		$('#hex').colorpicker().css({'width':'60px'});
		$('#read_only').click(function() {
			checkReadonly();
		});

		$('#name').keyup(function() {
			$('#hidden_name').val($(this).val());
		});

		function checkReadonly() {
			if ($('#read_only').is(':checked') || $('#read_only').val() == 'on') {
				$('#name').prop('disabled', true);
				$('#hex').prop('disabled', true);
			} else {
				$('#name').prop('disabled', false);
				$('#hex').prop('disabled', false);
			}
		}
	});
	</script>
	<?php
}

function process_sanitize_render_filter($render = false) {
	$pageFilter = new CactiTableFilter(__('Colors'), 'color.php', 'form_color', 'sess_color', 'color.php?action=edit');

	$pageFilter->rows_label = __('Colors');
	$pageFilter->has_graphs = true;
	$pageFilter->has_import = true;
	$pageFilter->has_export = true;
	$pageFilter->has_named  = true;

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function color() {
	global $actions, $item_rows;

	process_sanitize_render_filter(true);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hex LIKE ' . db_qstr('%' .  get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('named') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . " read_only='on'";
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' graphs > 0';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM colors
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$colors = db_fetch_assoc("SELECT *
		FROM colors
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('color.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Colors'), 'page', 'main');

	form_start('color.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'hex' => array(
			'display' => __('Hex'),
			'align' => 'left',
			'sort' => 'DESC',
			'tip' => __('The Hex Value for this Color.')
		),
		'name' => array(
			'display' => __('Color Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name of this Color definition.')
		),
		'read_only' => array(
			'display' => __('Named Color'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('Is this color a named color which are read only.')
		),
		'nosort1' => array(
			'display' => __('Color'),
			'align' => 'center',
			'sort' => 'DESC',
			'tip' => __('The Color as shown on the screen.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align' => 'right',
			'sort' => '',
			'tip' => __('Colors in use cannot be Deleted.  In use is defined as being referenced either by a Graph or a Graph Template.')
		),
		'graphs' => array(
			'display' => __('Graphs Using'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The number of Graph using this Color.')
		),
		'templates' => array(
			'display' => __('Templates Using'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The number of Graph Templates using this Color.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($colors)) {
		foreach ($colors as $color) {
			if ($color['graphs'] == 0 && $color['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			if ($color['name'] == '') {
				$color['name'] = 'Unnamed #'. $color['hex'];
			}

			form_alternate_row('line' . $color['id'], false, $disabled);

			$url = 'color.php?action=edit&id=' . $color['id'];

			form_selectable_cell(filter_value($color['hex'], '', $url), $color['id']);
			form_selectable_cell(filter_value($color['name'], get_request_var('filter')), $color['id']);
			form_selectable_cell($color['read_only'] == 'on' ? __('Yes'):__('No'), $color['id']);
			form_selectable_cell('', $color['id'], '', 'text-align:right;background-color:#' . $color['hex'] . ';min-width:30%');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $color['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($color['graphs'], '-1'), $color['id'], '', 'right');
			form_selectable_cell(number_format_i18n($color['templates'], '-1'), $color['id'], '', 'right');
			form_checkbox_cell($color['name'], $color['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='7'><em>" . __('No Colors Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($colors)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions, 1);

	form_end();
}

function color_export() {
	process_sanitize_render_filter(false);

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hex LIKE ' . db_qstr('%' .  get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('named') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . " read_only='on'";
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs>0 OR templates>0';
	} else {
		$sql_having = '';
	}

	$colors = db_fetch_assoc("SELECT *,
        SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs,
        SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates
        FROM (
			SELECT c.*, local_graph_id
			FROM colors AS c
			LEFT JOIN (
				SELECT color_id, graph_template_id, local_graph_id
				FROM graph_templates_item
				WHERE color_id>0
			) AS gti
			ON c.id=gti.color_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having");

	if (cacti_sizeof($colors)) {
		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename=colors.csv');

		print '"name","hex"' . "\n";

		foreach ($colors as $color) {
			print '"' . $color['name'] . '","' . $color['hex'] . '"' . "\n";
		}
	}
}
