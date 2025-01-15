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
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/data_query.php');
include_once('./lib/export.php');
include_once('./lib/import.php');
include_once('./lib/package.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');
include_once('./lib/xml.php');

if (!isset_request_var('action') || get_nfilter_request_var('action') == 'templates' || get_nfilter_request_var('action_type') == 'templates') {
	$actions = array(
		1 => __('Delete'),
		2 => __('Duplicate'),
		3 => __('Sync Devices'),
		4 => __('Archive'),
		5 => __('Download')
	);

	if (!file_exists(CACTI_PATH_PKI . '/package.pub')) {
		unset($actions[4]);
		unset($actions[5]);
	}
} elseif (get_nfilter_request_var('action') == 'archives' || get_nfilter_request_var('action_type') == 'archives') {
	$actions = array(
		1 => __('Delete'),
		2 => __('Download'),
	);
}

/* set default action */
set_default_action();

api_plugin_hook('device_template_top');

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'download':
		$ids  = explode(',', get_nfilter_request_var('ids'));
		$type = get_nfilter_request_var('action_type');

		api_device_template_download($type, $ids);

		break;
	case 'item_add_gt':
		template_item_add_gt();

		header('Location: host_templates.php?action=edit&id=' . get_filter_request_var('host_template_id'));

		break;
	case 'item_remove_gt_confirm':
		template_item_remove_gt_confirm();

		break;
	case 'item_remove_gt':
		template_item_remove_gt();

		header('Location: host_templates.php?action=edit&id=' . get_filter_request_var('host_template_id'));

		break;
	case 'item_add_dq':
		template_item_add_dq();

		header('Location: host_templates.php?action=edit&id=' . get_filter_request_var('host_template_id'));

		break;
	case 'item_remove_dq_confirm':
		template_item_remove_dq_confirm();

		break;
	case 'item_remove_dq':
		template_item_remove_dq();

		header('Location: host_templates.php?action=edit&id=' . get_filter_request_var('host_template_id'));

		break;
	case 'edit':
		top_header();

		template_edit();

		bottom_footer();

		break;
	case 'archives':
		top_header();
		device_archives();
		bottom_footer();

		break;
	default:
		top_header();
		device_templates();
		bottom_footer();

		break;
}

function form_save() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	get_filter_request_var('snmp_query_id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	if (isset_request_var('save_component_template')) {
		$save['id']           = get_nfilter_request_var('id');
		$save['hash']         = get_hash_host_template(get_nfilter_request_var('id'));
		$save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['version']      = form_input_validate(get_nfilter_request_var('version'), 'version', '', false, 3);
		$save['class']        = form_input_validate(get_nfilter_request_var('class'), 'class', '', false, 3);
		$save['tags']         = form_input_validate(get_nfilter_request_var('tags'), 'tags', '', true, 3);
		$save['author']       = form_input_validate(get_nfilter_request_var('author'), 'author', '', true, 3);
		$save['email']        = form_input_validate(get_nfilter_request_var('email'), 'email', '', true, 3);
		$save['homepage']     = form_input_validate(get_nfilter_request_var('homepage'), 'homepage', '', true, 3);
		$save['copyright']    = form_input_validate(get_nfilter_request_var('copyright'), 'copyright', '', false, 3);
		$save['installation'] = form_input_validate(get_nfilter_request_var('installation'), 'installation', '', true, 3);

		if (!is_error_message()) {
			$host_template_id = sql_save($save, 'host_template');

			if ($host_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: host_templates.php?action=edit&id=' . (empty($host_template_id) ? get_nfilter_request_var('id') : $host_template_id));
	}
}

function template_item_add_dq() {
	/* ================= input validation ================= */
	get_filter_request_var('host_template_id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	db_execute_prepared(
		'REPLACE INTO host_template_snmp_query
		(host_template_id, snmp_query_id)
		VALUES (?, ?)',
		array(get_request_var('host_template_id'), get_request_var('snmp_query_id'))
	);

	raise_message(41);
}

function template_item_add_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('host_template_id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	db_execute_prepared(
		'REPLACE INTO host_template_graph
		(host_template_id, graph_template_id)
		VALUES (?, ?)',
		array(get_request_var('host_template_id'), get_request_var('graph_template_id'))
	);

	raise_message(41);
}

function form_actions() {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		if (get_nfilter_request_var('action_type') == 'templates') {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					db_execute('DELETE FROM host_template WHERE ' . array_to_sql_or($selected_items, 'id'));
					db_execute('DELETE FROM host_template_snmp_query WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));
					db_execute('DELETE FROM host_template_graph WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));

					/* "undo" any device that is currently using this template */
					db_execute('UPDATE host SET host_template_id = 0 WHERE deleted = "" AND ' . array_to_sql_or($selected_items, 'host_template_id'));
				} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
					foreach($selected_items as $id) {
						api_duplicate_device_template($id, get_nfilter_request_var('title_format'));
					}
				} elseif (get_nfilter_request_var('drp_action') == '3') { // sync
					foreach($selected_items as $id) {
						api_device_template_sync_template($id);
					}
				} elseif (get_nfilter_request_var('drp_action') == '4') { // archive template
					$archive_note = get_request_var('archive_note');

					foreach($selected_items as $id) {
						api_device_template_archive($id, $archive_note);
					}
				} elseif (get_nfilter_request_var('drp_action') == '5') { // download package
					top_header();

					print '<script text="text/javascript">
						function DownloadStart(url) {
							document.getElementById("download_iframe").src = url;
							setTimeout(function() {
								loadUrl({ url: "host_templates.php?action_type=templates" });
								Pace.stop();
							}, 2000);
						}

						$(function() {
							DownloadStart(\'host_templates.php?action=download&action_type=templates&ids=' . implode(',', $selected_items) . '\');
						});
					</script>
					<iframe id="download_iframe" style="display:none;"></iframe>';

					bottom_footer();
					exit(0);
				}
			}

			header('Location: host_templates.php?action=templates&action=templates');
		} else {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					foreach($selected_items as $id) {
						$name = db_fetch_cell_prepared('SELECT name
							FROM host_template_archive
							WHERE id = ?', array($id));

						db_execute_prepared('DELETE FROM host_template_archive WHERE id = ?', array($id));

						raise_message('archives_removed_' . $id, __('The Device Template Archive %s has been removed.', $name), MESSAGE_LEVEL_INFO);
					}
				} elseif (get_nfilter_request_var('drp_action') == 2) {
					top_header();

					print '<script text="text/javascript">
						function DownloadStart(url) {
							document.getElementById("download_iframe").src = url;
							setTimeout(function() {
								loadUrl({ url: "host_templates.php?action_type=archives" });
								Pace.stop();
							}, 1000);
						}

						$(function() {
							DownloadStart(\'host_templates.php?action=download&action_type=archives&ids=' . implode(',', $selected_items) . '\');
						});
					</script>
					<iframe id="download_iframe" style="display:none;"></iframe>';

					bottom_footer();
					exit(0);
				}
			}

			header('Location: host_templates.php?action=archives');
		}

		exit;
	} else {
		$ilist  = '';
		$iarray = array();

		/* loop through each of the host templates selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				if (get_nfilter_request_var('action_type') == 'templates') {
					$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM host_template WHERE id = ?', array($matches[1]))) . '</li>';
				} else {
					$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM host_template_archive WHERE id = ?', array($matches[1]))) . '</li>';
				}

				$iarray[] = $matches[1];
			}
		}

		if (get_nfilter_request_var('action_type') == 'templates') {
			$form_data = array(
				'general' => array(
					'page'       => 'host_templates.php?action_type=templates',
					'actions'    => $actions,
					'optvar'     => 'drp_action',
					'item_array' => $iarray,
					'item_list'  => $ilist
				),
				'options' => array(
					1 => array(
						'smessage' => __('Click \'Continue\' to Delete the following Device Template.'),
						'pmessage' => __('Click \'Continue\' to Delete following Device Templates.'),
						'scont'    => __('Delete Device Template'),
						'pcont'    => __('Delete Device Templates')
					),
					2 => array(
						'smessage' => __('Click \'Continue\' to Duplicate the following Device Template.'),
						'pmessage' => __('Click \'Continue\' to Duplicate following Device Templates.'),
						'scont'    => __('Duplicate Device Template'),
						'pcont'    => __('Duplicate Device Templates'),
						'extra'    => array(
							'title_format' => array(
								'method'  => 'textbox',
								'title'   => __('Title Format'),
								'default' => '<template_title> (1)',
								'width'   => 25
							)
						)
					),
					3 => array(
						'smessage' => __('Click \'Continue\' to Sync Devices to the following Device Template.'),
						'pmessage' => __('Click \'Continue\' to Sync Devices to the following Device Templates.'),
						'scont'    => __('Sync Device Template'),
						'pcont'    => __('Sync Device Templates')
					),
					4 => array(
						'smessage' => __('Click \'Continue\' to Archive the following Device Template.'),
						'pmessage' => __('Click \'Continue\' to Archive the following Device Templates.'),
						'scont'    => __('Archive Device Template'),
						'pcont'    => __('Archive Device Templates'),
						'extra'    => array(
							'archive_note' => array(
								'method'  => 'textarea',
								'title'   => __('Archive Note'),
								'default' => '',
								'textarea_rows' => 4,
								'textarea_cols' => 80
							)
						)
					),
					5 => array(
						'smessage' => __('Click \'Continue\' to Download the following Device Template.'),
						'pmessage' => __('Click \'Continue\' to Download the following Device Templates.'),
						'scont'    => __('Download Device Template'),
						'pcont'    => __('Download Device Templates')
					),
				)
			);
		} else {
			$form_data = array(
				'general' => array(
					'page'       => 'host_templates.php?action_type=archive',
					'actions'    => $actions,
					'optvar'     => 'drp_action',
					'item_array' => $iarray,
					'item_list'  => $ilist
				),
				'options' => array(
					1 => array(
						'smessage' => __('Click \'Continue\' to Delete the following Device Template Archive.'),
						'pmessage' => __('Click \'Continue\' to Delete following Device Template Archives.'),
						'scont'    => __('Delete Device Template Archive'),
						'pcont'    => __('Delete Device Templates Archives')
					),
					2 => array(
						'smessage' => __('Click \'Continue\' to Download the following Device Template Archive.'),
						'pmessage' => __('Click \'Continue\' to Download following Device Template Archives.'),
						'scont'    => __('Download Device Template Archive'),
						'pcont'    => __('Download Device Templates Archives')
					)
				)
			);
		}

		form_continue_confirmation($form_data);
	}
}

function template_item_remove_gt_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	form_start('host_templates.php?action=edit&id' . get_request_var('host_template_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$template = db_fetch_row_prepared('SELECT *
		FROM graph_templates
		WHERE id = ?',
		array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Graph Template will be disassociated from the Device Template.'); ?></p>
			<p><?php print __('Graph Template Name: %s', html_escape($template['name'])); ?>'<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel'); ?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue'); ?>' name='continue' title='<?php print __esc('Remove Graph Template'); ?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
		$('#continue').click(function(data) {
			var options = {
				url: 'host_templates.php?action=item_remove_gt',
				noState: true,
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				host_template_id: <?php print get_request_var('host_template_id'); ?>,
				id: <?php print get_request_var('id'); ?>
			}

			postUrl(options, data);
		});
	</script>
	<?php
}

function template_item_remove_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_graph
		WHERE graph_template_id = ?
		AND host_template_id = ?',
		array(get_request_var('id'), get_request_var('host_template_id'))
	);

	raise_message(41);
}

function template_item_remove_dq_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	form_start('host_templates.php?action=edit&id' . get_request_var('host_template_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$query = db_fetch_row_prepared('SELECT * FROM snmp_query WHERE id = ?', array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Queries will be disassociated from the Device Template.'); ?></p>
			<p><?php print __('Data Query Name: %s', html_escape($query['name'])); ?>'<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel'); ?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue'); ?>' name='continue' title='<?php print __esc('Remove Data Query'); ?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
		$('#continue').click(function(data) {
			var options = {
				url: 'host_templates.php?action=item_remove_dq',
				noState: true,
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				host_template_id: <?php print get_request_var('host_template_id'); ?>,
				id: <?php print get_request_var('id'); ?>
			}

			postUrl(options, data);
		});
	</script>
	<?php
}

function template_item_remove_dq() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_snmp_query
		WHERE snmp_query_id = ?
		AND host_template_id = ?',
		array(get_request_var('id'), get_request_var('host_template_id'))
	);

	raise_message(41);
}

function template_edit() {
	global $fields_host_template_edit, $copyrights;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$host_template = db_fetch_row_prepared('SELECT *
			FROM host_template
			WHERE id = ?',
			array(get_request_var('id'))
		);

		$header_label = __esc('Device Templates [edit: %s]', $host_template['name']);
	} else {
		$header_label = __('Device Templates [new]');
		set_request_var('id', 0);
	}

	if (!cacti_sizeof($host_template) || $host_template['version'] == '') {
		$fields_host_template_edit['version']['default'] = CACTI_VERSION;
	}

	if (!cacti_sizeof($host_template) || $host_template['author'] == '') {
		$fields_host_template_edit['author']['default'] = read_config_option('packaging_author');
	}

	if (!cacti_sizeof($host_template) || $host_template['email'] == '') {
		$fields_host_template_edit['email']['default'] = read_config_option('packaging_email');
	}

	if (!cacti_sizeof($host_template) || $host_template['copyright'] == '') {
		$fields_host_template_edit['copyright']['default'] = read_config_option('packaging_copyright');
	}

	form_start('host_templates.php', 'form_network');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => 'true'),
			'fields' => inject_form_variables($fields_host_template_edit, (isset($host_template) ? $host_template : array()))
		)
	);

	/* we have to hide this button to make a form change in the main form trigger the correct
	 * submit action */
	print "<div style='display:none;'><input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Default Submit Button') . "'></div>";

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		html_start_box(__('Associated Graph Templates'), '100%', '', '3', 'center', '');

		$selected_graph_templates = db_fetch_assoc_prepared('SELECT graph_templates.id, graph_templates.name
			FROM (graph_templates,host_template_graph)
			WHERE graph_templates.id = host_template_graph.graph_template_id
			AND host_template_graph.host_template_id = ?
			ORDER BY graph_templates.name', array(get_request_var('id')));

		$i = 0;

		if (cacti_sizeof($selected_graph_templates)) {
			foreach ($selected_graph_templates as $item) {
				form_alternate_row("gt$i", true);
				?>
				<td class='left'>
					<strong><?php print $i; ?>)</strong> <?php print html_escape($item['name']); ?>
				</td>
				<td class='right'>
					<a class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete'); ?>' href='<?php print html_escape('host_templates.php?action=item_remove_gt_confirm&id=' . $item['id'] . '&host_template_id=' . get_request_var('id')); ?>'></a>
				</td>
				<?php
				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="2"><em>' . __('No Associated Graph Templates.') . '</em></td></tr>';
		}

		?>
		<tr class='odd'>
			<td colspan='2'>
				<table>
					<tr style='line-height:10px'>
						<td class='nowrap templateAdd'>
							<?php print __('Add Graph Template'); ?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('graph_template_id', db_fetch_assoc_prepared('SELECT gt.id, gt.name
								FROM graph_templates AS gt
								LEFT JOIN host_template_graph AS htg
								ON gt.id=htg.graph_template_id
								AND htg.host_template_id = ?
								WHERE htg.host_template_id IS NULL
								AND gt.id NOT IN (SELECT graph_template_id FROM snmp_query_graph)
								ORDER BY gt.name', array(get_request_var('id'))), 'name', 'id', '', '', ''); ?>
						</td>
						<td class='noHide'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add'); ?>' id='add_gt' title='<?php print __esc('Add Graph Template to Device Template'); ?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box(__('Associated Data Queries'), '100%', '', '3', 'center', '');

		$selected_data_queries = db_fetch_assoc_prepared('SELECT snmp_query.id, snmp_query.name
			FROM (snmp_query, host_template_snmp_query)
			WHERE snmp_query.id = host_template_snmp_query.snmp_query_id
			AND host_template_snmp_query.host_template_id = ?
			ORDER BY snmp_query.name', array(get_request_var('id')));

		$i = 0;

		if (cacti_sizeof($selected_data_queries)) {
			foreach ($selected_data_queries as $item) {
				form_alternate_row("dq$i", true);
				?>
				<td class='left'>
					<strong><?php print $i; ?>)</strong> <?php print html_escape($item['name']); ?>
				</td>
				<td class='right'>
					<a class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete'); ?>' href='<?php print html_escape('host_templates.php?action=item_remove_dq_confirm&id=' . $item['id'] . '&host_template_id=' . get_request_var('id')); ?>'></a>
				</td>
				<?php
				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="2"><em>' . __('No Associated Data Queries.') . '</em></td></tr>';
		}

		?>
		<tr class='odd'>
			<td colspan='2'>
				<table>
					<tr style='line-height:10px;'>
						<td class='nowrap queryAdd'>
							<?php print __('Add Data Query'); ?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('snmp_query_id', db_fetch_assoc_prepared('SELECT snmp_query.id, snmp_query.name
								FROM snmp_query LEFT JOIN host_template_snmp_query
								ON (snmp_query.id = host_template_snmp_query.snmp_query_id AND host_template_snmp_query.host_template_id = ?)
								WHERE host_template_snmp_query.host_template_id is null
								ORDER BY snmp_query.name', array(get_request_var('id'))), 'name', 'id', '', '', ''); ?>
						</td>
						<td class='noHide'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add'); ?>' id='add_dq' title='<?php print __esc('Add Data Query to Device Template'); ?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php

		html_end_box();

		api_plugin_hook('device_template_edit');
	}

	form_save_button('host_templates.php', 'return');

	?>
	<script type='text/javascript'>
		function deleteFinalize(options, data) {
			$('#cdialog').dialog({
				title: '<?php print __('Delete Item from Device Template'); ?>',
				close: function() {
					$('.delete').blur();
					$('.selectable').removeClass('selected');
				},
				minHeight: 80,
				minWidth: 500
			});
		}

		$(function() {
			$('#cdialog').remove();
			$('#main').append("<div id='cdialog' class='cdialog'></div>");

			$('.delete').click(function(event) {
				event.preventDefault();

				var options = {
					url: $(this).attr('href'),
					noState: true,
					funcEnd: 'deleteFinalize',
					elementId: 'cdialog',
				};

				loadUrl(options);
			}).css('cursor', 'pointer');

			$('#add_dq').click(function() {
				var options = {
					url: 'host_templates.php?action=item_add_dq',
					noState: true,
				}

				var data = {
					host_template_id: $('#id').val(),
					snmp_query_id: $('#snmp_query_id').val(),
					reindex_method: $('#reindex_method').val(),
					__csrf_magic: csrfMagicToken
				}

				postUrl(options, data);
			});

			$('#add_gt').click(function() {
				var options = {
					url: 'host_templates.php?action=item_add_gt',
					noState: true,
				}

				var data = {
					host_template_id: $('#id').val(),
					graph_template_id: $('#graph_template_id').val(),
					__csrf_magic: csrfMagicToken
				}

				postUrl(options, data);
			});
		});
	</script>
	<?php
}

function create_template_filter() {
	global $item_rows, $device_classes;

	$all  = array('-1' => __('All'));

	$device_classes = $all + $device_classes;

	$action_arr = array(
		'templates' => __('Templates'),
		'archives'  => __('Archives')
	);

	if (isset_request_var('has_hosts')) {
		$value = get_nfilter_request_var('has_hosts');
	} else {
		$value = read_config_option('default_has') == 'on' ? 'true':'false';
	}

	if (get_request_var('class') == '-1' || isempty_request_var('class')) {
		$graph_templates = db_fetch_assoc('SELECT DISTINCT rs.id, rs.name
			FROM (
				SELECT gt.id, gt.name
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT gt.id, gt.name
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
			ORDER BY name');
	} else {
		$graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT rs.id, rs.name
			FROM (
				SELECT gt.id, gt.name, htg.host_template_id
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT gt.id, gt.name, htsq.host_template_id
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
			INNER JOIN host_template AS ht
			ON rs.host_template_id = ht.id
			WHERE ht.class = ?
			ORDER BY name',
			array(get_request_var('class')));
	}

	$graph_templates = array_rekey($graph_templates, 'id', 'name');

	$graph_templates = $all + $graph_templates;

	return array(
		'rows' => array(
			array(
				'class' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Class'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $device_classes,
					'value'          => '-1'
				),
				'graph_template' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Graph Templates'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'description'    => __('Search for Device Templates that use this specific Graph Template'),
					'pageset'        => true,
					'array'          => $graph_templates,
					'value'          => '-1'
				),
				'action' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('View'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => 'templates',
					'pageset'        => true,
					'array'          => $action_arr,
					'value'          => 'templates'
				),
				'has_hosts' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Devices'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => '',
					'pageset'        => true,
					'value'          => $value
				)
			),
			array(
				'filter' => array(
					'method'         => 'textbox',
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
					'method'         => 'drop_array',
					'friendly_name'  => __('Templates'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				)
			)
		),
		'buttons' => array(
			'go' => array(
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			),
			'clear' => array(
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			)
		),
		'sort' => array(
			'sort_column'    => 'name',
			'sort_direction' => 'DESC'
		)
	);
}

function process_sanitize_draw_template_filter($render = false) {
	$filters = create_template_filter();

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Device Templates'), 'host_templates.php', 'form_template', 'sess_ht', 'host_templates.php?action=edit');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function device_templates() {
	global $actions, $item_rows, $device_classes;

	process_sanitize_draw_template_filter(true);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	$sql_where  = '';
	$sql_params = array();
	$sql_join   = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'ht.name LIKE ?';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	if (get_request_var('class') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'ht.class = ?';
		$sql_params[] = get_request_var('class');
	}

	if (get_request_var('graph_template') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gt_id = ?';
		$sql_params[] = get_request_var('graph_template');

		$sql_join   = "INNER JOIN (
			SELECT DISTINCT host_template_id, id AS gt_id
			FROM (
				SELECT htg.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT htsq.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
		) AS htdata
		ON htdata.host_template_id = ht.id";
	}

	if (get_request_var('has_hosts') == 'true') {
		$sql_having = 'HAVING hosts > 0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(`rows`)
		FROM (
			SELECT
			COUNT(ht.id) AS `rows`, COUNT(DISTINCT host.id) AS hosts
			FROM host_template AS ht
			$sql_join
			LEFT JOIN host
			ON host.host_template_id = ht.id
			LEFT JOIN host_template_archive AS hta
			ON hta.host_template_id = ht.id
			$sql_where
			GROUP BY ht.id
			$sql_having
		) AS rs",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$template_list = db_fetch_assoc_prepared("SELECT
		ht.id, ht.name, ht.class, ht.version, ht.author, ht.copyright, COUNT(DISTINCT host.id) AS hosts, COUNT(DISTINCT hta.id) AS `archives`
		FROM host_template AS ht
		$sql_join
		LEFT JOIN host
		ON host.host_template_id=ht.id
		LEFT JOIN host_template_archive AS hta
		ON hta.host_template_id = ht.id
		$sql_where
		GROUP BY ht.id
		$sql_having
		$sql_order
		$sql_limit",
		$sql_params);

	$display_text = array(
		'name' => array(
			'display' => __('Device Template Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Device Template.')
		),
		'ht.class' => array(
			'display' => __('Device Class'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Class of this Device Template.  The Class Name should be representative of it\'s function.')
		),
		'ht.version' => array(
			'display' => __('Version'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The version of this Device Template.')
		),
		'archives' => array(
			'display' => __('Archives'),
			'align'   => 'center',
			'sort'    => 'DESC',
			'tip'     => __('The number of Archived versions of this Device Template on disk.')
		),
		'ht.author' => array(
			'display' => __('Author'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The author of this Device Template.')
		),
		'ht.copyright' => array(
			'display' => __('Copyright'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The copyright of this Device Template.')
		),
		'ht.id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Device Template.  Useful when performing automation or debugging.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'sort'    => '',
			'tip'     => __('Device Templates in use cannot be Deleted.  In use is defined as being referenced by a Device.')
		),
		'hosts' => array(
			'display' => __('Devices Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Devices using this Device Template.')
		)
	);

	$nav = html_nav_bar('host_templates.php?action=templates', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Device Templates'), 'page', 'main');

	form_start('host_templates.php?action=templates', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'host_templates.php?action=template');

	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['hosts'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $template['id'], true, $disabled);

			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'host_templates.php?action=edit&id=' . $template['id']), $template['id']);

			if ($template['class'] != '') {
				form_selectable_cell($device_classes[$template['class']], $template['id']);
			} else {
				form_selectable_cell(__('Unassigned'), $template['id']);
			}

			$archive_url = 'host_templates.php?action=archives&host_template=' . $template['id'];

			form_selectable_ecell($template['version'], $template['id'], '', 'center');
			form_selectable_cell(filter_value($template['archives'], '', $archive_url), $template['id'], '', 'center');
			form_selectable_ecell($template['author'], $template['id'], '', 'left');
			form_selectable_ecell($template['copyright'], $template['copyright'], '', 'left');

			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell($disabled ? __('No') : __('Yes'), $template['id'], '', 'right');

			$url = 'host.php?reset=true&host_template_id=' . $template['id'];

			form_selectable_cell(filter_value(number_format_i18n($template['hosts'], '-1'), '', $url), $template['id'], '', 'right');

			form_checkbox_cell($template['name'], $template['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Device Templates Found') . "</em></td></tr>";
	}

	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	form_hidden_box('action_type', 'templates', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

function create_archive_filter() {
	global $item_rows, $device_classes;

	$all  = array('-1' => __('All'));

	$device_classes = $all + $device_classes;

	if (isset_request_var('has_hosts')) {
		$value = get_nfilter_request_var('has_hosts');
	} else {
		$value = read_config_option('default_has') == 'on' ? 'true':'false';
	}

	$action_arr = array(
		'templates' => __('Templates'),
		'archives'  => __('Archives')
	);

	if (get_request_var('class') == '-1' || isempty_request_var('class')) {
		$graph_templates = db_fetch_assoc('SELECT DISTINCT rs.id, rs.name
			FROM (
				SELECT gt.id, gt.name
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT gt.id, gt.name
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
			ORDER BY name');
	} else {
		$graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT rs.id, rs.name
			FROM (
				SELECT gt.id, gt.name, htg.host_template_id
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT gt.id, gt.name, htsq.host_template_id
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
			INNER JOIN host_template AS ht
			ON rs.host_template_id = ht.id
			WHERE ht.class = ?
			ORDER BY name',
			array(get_request_var('class')));
	}

	$graph_templates = array_rekey($graph_templates, 'id', 'name');

	$graph_templates = $all + $graph_templates;

	if (!isempty_request_var('class') && get_request_var('class') != '-1') {
		$sql_where    = 'WHERE ht.class = ?';
		$sql_params[] = get_request_var('class');
	} else {
		$sql_where    = '';
		$sql_params   = array();
	}

	$device_templates = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT ht.id, ht.name
			FROM host_template AS ht
			INNER JOIN host_template_archive AS hta
			ON ht.id = hta.host_template_id
			$sql_where
			ORDER BY ht.name ASC",
			$sql_params),
		'id', 'name'
	);

	$device_templates = $all + $device_templates;

	return array(
		'rows' => array(
			array(
				'class' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Class'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $device_classes,
					'value'          => '-1'
				),
				'host_template' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Device Template'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'description'    => __('Search for Device Templates that use this specific Graph Template'),
					'pageset'        => true,
					'array'          => $device_templates,
					'value'          => '-1'
				),
				'graph_template' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Graph Template'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'description'    => __('Search for Device Templates that use this specific Graph Template'),
					'pageset'        => true,
					'array'          => $graph_templates,
					'value'          => '-1'
				),
				'action' => array(
					'method'         => 'drop_array',
					'friendly_name'  => __('View'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => 'templates',
					'pageset'        => true,
					'array'          => $action_arr,
					'value'          => 'templates'
				),
				'has_hosts' => array(
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Devices'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => array('options' => array('regexp' => '(true|false)')),
					'default'        => '',
					'pageset'        => true,
					'value'          => $value
				)
			),
			array(
				'filter' => array(
					'method'         => 'textbox',
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
					'method'         => 'drop_array',
					'friendly_name'  => __('Archives'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				)
			)
		),
		'buttons' => array(
			'go' => array(
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			),
			'clear' => array(
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			)
		),
		'sort' => array(
			'sort_column'    => 'name',
			'sort_direction' => 'DESC'
		)
	);
}

function process_sanitize_draw_archive_filter($render = false) {
	$filters = create_archive_filter();

	/* create the page filter */
	$pageFilter = new CactiTableFilter(__('Device Templates Archives'), 'host_templates.php', 'form_template', 'sess_hta');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function device_archives() {
	global $actions, $item_rows, $device_classes;

	process_sanitize_draw_archive_filter(true);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	$sql_where  = '';
	$sql_params = array();
	$sql_join   = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'ht.name LIKE ? OR ht.archive_note LIKE ?';

		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	if (get_request_var('class') != '-1') {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'ht.class = ?';
		$sql_params[] = get_request_var('class');
	}

	if (get_request_var('host_template') != '-1') {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'ht.host_template_id = ?';
		$sql_params[] = get_request_var('host_template');
	}

	if (get_request_var('graph_template') != '-1') {
		$sql_where   .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gt_id = ?';
		$sql_params[] = get_request_var('graph_template');

		$sql_join   = "INNER JOIN (
			SELECT DISTINCT host_template_id, id AS gt_id
			FROM (
				SELECT htg.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN host_template_graph AS htg
				ON htg.graph_template_id = gt.id
				UNION
				SELECT htsq.host_template_id, gt.id
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				INNER JOIN host_template_snmp_query AS htsq
				ON sqg.snmp_query_id = htsq.snmp_query_id
			) AS rs
		) AS htdata
		ON htdata.host_template_id = ht.id";
	}

	if (get_request_var('has_hosts') == 'true') {
		$sql_having = 'HAVING hosts > 0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(`rows`)
		FROM (
			SELECT
			COUNT(ht.id) AS `rows`, COUNT(DISTINCT host.id) AS hosts
			FROM host_template_archive AS ht
			$sql_join
			LEFT JOIN host
			ON host.host_template_id = ht.id
			$sql_where
			GROUP BY ht.id
			$sql_having
		) AS rs",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$archives = db_fetch_assoc_prepared("SELECT
		ht.id, ht.name, ht.class, ht.version, ht.author, ht.copyright, archive_note, archive_date, LENGTH(archive) AS size, COUNT(DISTINCT host.id) AS hosts
		FROM host_template_archive AS ht
		$sql_join
		LEFT JOIN host
		ON host.host_template_id=ht.id
		$sql_where
		GROUP BY ht.id
		$sql_having
		$sql_order
		$sql_limit",
		$sql_params);

	$display_text = array(
		'name' => array(
			'display' => __('Device Template Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Device Template.')
		),
		'ht.class' => array(
			'display' => __('Device Class'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Class of this Device Template.  The Class Name should be representative of it\'s function.')
		),
		'ht.version' => array(
			'display' => __('Version'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The version of this Device Template.')
		),
		'ht.author' => array(
			'display' => __('Author'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The author of this Device Template.')
		),
		'ht.copyright' => array(
			'display' => __('Copyright'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The copyright of this Device Template.')
		),
		'ht.archive_note' => array(
			'display' => __('Archive Notes'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Hover over the column to see the archive notes.')
		),
		'ht.id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Device Template.  Useful when performing automation or debugging.')
		),
		'size' => array(
			'display' => __('Size'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The size of the Device Template Archive in the Database.')
		),
		'ht.archive_date' => array(
			'display' => __('Archive Date'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Device Template.  Useful when performing automation or debugging.')
		)
	);

	$nav = html_nav_bar('host_templates.php?action=archives', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Device Templates'), 'page', 'main');

	form_start('host_templates.php?action=archives', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'host_templates.php?action=archives');

	if (cacti_sizeof($archives)) {
		foreach ($archives as $a) {
			form_alternate_row('line' . $a['id'], true);

			form_selectable_cell(filter_value($a['name'], get_request_var('filter')), $a['id']);

			if ($a['class'] != '') {
				form_selectable_cell($device_classes[$a['class']], $a['id']);
			} else {
				form_selectable_cell(__('Unassigned'), $a['id']);
			}

			form_selectable_ecell($a['version'], $a['id'], '', 'center');
			form_selectable_ecell($a['author'], $a['id'], '', 'left');
			form_selectable_ecell($a['copyright'], $a['id'], '', 'left');

			form_selectable_cell(filter_value(__('Notes'), '', '#', $a['archive_note']), $a['id'], '', 'left');

			form_selectable_cell($a['id'], $a['id'], '', 'right');

			form_selectable_cell(number_format_i18n($a['size'], 2, 1000), $a['id'], '', 'right');
			form_selectable_cell($a['archive_date'], $a['id'], '', 'right');

			form_checkbox_cell($a['name'], $a['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Device Template Archives Found') . "</em></td></tr>";
	}

	html_end_box(false);

	if (cacti_sizeof($archives)) {
		print $nav;
	}

	form_hidden_box('action_type', 'archives', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

