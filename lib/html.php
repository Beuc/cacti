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
 * html_start_box - draws the start of an HTML box with an optional title
 *
 * @param string $title - the title of this box ("" for no title)
 * @param string $width - the width of the box in pixels or percent
 * @param bool $div - end with a starting div
 * @param int $cell_padding - the amount of cell padding to use inside of the box
 * @param string $align - the HTML alignment to use for the box (center, left, or right)
 * @param string|array $url_or_buttons - the url to use when the user clicks 'Add'
 *   in the upper-right corner of the box ("" for no 'Add' link)
 *   This function has two method.  This first is for legacy behavior where you
 *   you pass in a href to the function, and an optional label as $add_label
 *   The new format accepts an array of hrefs to add to the start box.  The format
 *   of the array is as follows:
 *
 *   $add_url_or_buttons = array(
 *      array(
 *        'id' => 'uniqueid',
 *        'href' => 'value',
 *        'title' => 'title',
 *        'callback' => true|false,
 *        'class' => 'fa fa-icon'
 *      ),
 *      ...
 *   );
 *
 *   If the callback is true, the Cacti attribute will be added to the href
 *   to present only the contents and not to include both the headers.  If
 *   the link must go off page, simply make sure $callback is false.  There
 *   is a requirement to use fontawesome icon sets for this class, but it
 *   can include other classes.  In addition, the href can be a hash '#' if
 *   your page has a ready function that has it's own javascript.
 * @param bool|string $add_label - used with legacy behavior to add specific text to the link.
 *   This parameter is only used in the legacy behavior.
 * @param bool $showcols - Show the column selector icon
 *
 * @return void
 */
function html_start_box($title, $width, $div, $cell_padding, $align, $add_url_or_buttons, $add_label = false, $showcols = false) {
	global $config;

	static $table_suffix = 1;
	static $help_count   = 0;
	static $mode_count   = 0;
	static $beta_count   = 0;

	if ($add_label === false) {
		$add_label = __('Add');
	}

	if (!is_cacti_release() && $title != '' && $beta_count == 0) {
		$title .= ' [ ' . CACTI_VERSION_BRIEF_FULL . ' ]';

		$beta_count++;
	}

	if ($config['poller_id'] > 1 && $title != '' && $mode_count == 0) {
		$title .= ' [ ' . __('Remote Server') . ': ';

		if ($config['connection'] == 'offline') {
			$title .= '<span class="deviceDown">' . __('Offline') . '</span>';
		} elseif ($config['connection'] == 'recovery') {
			$title .= '<span class="deviceRecovering">' . __('Recovering') . '</span>';
		} else {
			$title .= __('Online');
		}

		$title .= ' ]';

		$mode_count++;
	}

	$table_prefix = basename(get_current_page(), '.php');

	if (!isempty_request_var('action')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('action'));
	} elseif (!isempty_request_var('report')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('report'));
	} elseif (!isempty_request_var('tab')) {
		$table_prefix .= '_' . clean_up_name(get_nfilter_request_var('tab'));
	}

	$table_id = $table_prefix . $table_suffix;

	if ($title != '') {
		print "<div id='$table_id' class='cactiTable' style='width:$width;text-align:$align;'>";
		print '<div class="cactiTableTitleRow">';
		print "<div class='cactiTableTitle'><span>" . ($title != '' ? $title:'') . '</span></div>';
		print "<div class='cactiTableButton'>";

		$page      = get_current_page();
		$help_file = html_help_page($page);

		if ($help_file === false) {
			if (isset_request_var('tab')) {
				$tpage     = $page . ':' . get_nfilter_request_var('tab');
				$help_file = html_help_page($tpage);
			}
		}

		if ($help_file === false) {
			if (isset_request_var('action')) {
				$tpage     = $page . ':' . get_nfilter_request_var('action');
				$help_file = html_help_page($tpage);
			}
		}

		if ($help_file !== false && $help_count == 0 && is_realm_allowed(28)) {
			print "<span class='cactiHelp' title='" . __esc('Get Page Help') . "'><a class='linkOverDark helpPage' data-page='" . html_escape(basename($help_file)) . "' href='#'><i class='ti ti-help actionHelp'></i></a></span>";
			$help_count++;
		}

		if ($showcols) {
			print "<span class='cactiFilterColumns' title='" . __esc('Select Columns for Display') . "'><a id='showColumns' href='#'><i class='ti ti-table-column threeBars'></i></a></span>";
		}

		if ($add_url_or_buttons != '' && !is_array($add_url_or_buttons)) {
			print "<span class='cactiFilterAdd' title='$add_label'><a class='linkOverDark' href='" . html_escape($add_url_or_buttons) . "'><i class='ti ti-plus plusAdd'></i></a></span>";
		} else {
			if (is_array($add_url_or_buttons)) {
				if (cacti_sizeof($add_url_or_buttons)) {
					foreach ($add_url_or_buttons as $icon) {
						if (isset($icon['callback']) && $icon['callback'] === true) {
							$classo = 'linkOverDark';
						} else {
							$classo = '';
						}

						if (isset($icon['class']) && $icon['class'] !== '') {
							$classi = $icon['class'];
						} else {
							$classi = 'ti ti-plus plusAdd';
						}

						if (isset($icon['href'])) {
							$href = html_escape($icon['href']);
						} else {
							$href = '#';
						}

						if (isset($icon['title'])) {
							$title = $icon['title'];
						} else {
							$title = $add_label;
						}

						print "<span class='cactiFilterAdd' title='$title'><a" . (isset($icon['id']) ? " id='" . $icon['id'] . "'":'') . " class='$classo' href='$href'><i class='$classi'></i></a></span>";
					}
				}
			} else {
				print '<span> </span>';
			}
		}
		print '</div></div>';

		if ($div === true) {
			print "<div id='$table_id" . "_child' class='cactiTable'>";
		} else {
			print "<table id='$table_id" . "_child' class='cactiTable' style='padding:" . $cell_padding . "px;'>";
		}
	} else {
		print "<div id='$table_id' class='cactiTable' style='width:$width;text-align:$align;'>";

		if ($div === true) {
			print "<div id='$table_id" . "_child' class='cactiTable'>";
		} else {
			print "<table id='$table_id" . "_child' class='cactiTable' style='padding:" . $cell_padding . "px;'>";
		}
	}

	$table_suffix++;
}

/**
 * Wrapper function for the html_start_box to control filters which presently are displayed
 * as tables in Cacti.  This function will show the three bar show/hide column setting
 */
function html_filter_start_box($title, $add_url_or_buttons = '', $div = false, $showcols = true, $add_label = false, $width = '100%') {
	html_start_box($title, $width, $div, 3, 'center', $add_url_or_buttons, $add_label, $showcols);
}

/**
 * html_sub_tabs - Creates a memory persistent sub-tab interface
 * for a page or pages using a simple method to lay those tabs
 * out.
 *
 * @param array $tabs - An associative array of tab variables and names
 *   Alternatively an array of names that can be converted
 *   using the strtoupper() function to titles.
 * @param string $uri - A string of URL parameters like 'action=edit&id=x'
 * @param string $session_var - An option session variable to use to store
 *   the current tab status.  Defaults to the page
 *   name and the suffix of current_tab
 *
 * @return void  - Output is printed to standard output
 */
function html_sub_tabs($tabs, $uri = '', $session_var = '') {
	/* determine the session variables if not set */
	if ($session_var == '') {
		$session_var = basename(get_current_page(), '.php') . '_current_tab';
	}

	$page_name = get_current_page() . '?' . $uri . ($uri != '' ? '&':'');

	/* set the default settings category */
	if (!isset_request_var('tab')) {
		/* there is no selected tab; select the first one */
		if (isset($_SESSION[$session_var])) {
			$current_tab = $_SESSION[$session_var];
		} else {
			$current_tab = array_keys($tabs)[0];
		}
	} else {
		$current_tab = get_request_var('tab');
	}

	/* Check to see if the tab exists, and if not, use the default */
	if (!isset($tabs[$current_tab])) {
		$current_tab = array_keys($tabs)[0];
	}

	$_SESSION[$session_var] = $current_tab;

	set_request_var('tab', $current_tab);

	/* draw the categories tabs on the top of the page */
	print '<div>';
	print "<div class='tabs' style='float:left;'>
		<nav>
			<ul role='tablist'>";

	if (cacti_sizeof($tabs)) {
		foreach ($tabs as $id => $name) {
			if (is_numeric($id)) {
				print "<li class='subTab'>
					<a class='pic" . ($name == $current_tab ? ' selected' : '') . "'
					href='" . html_escape($page_name . 'tab=' . $name) . "'>" . strtoupper($name) . '</a>
				</li>';
			} else {
				print "<li class='subTab'>
					<a class='pic" . ($id == $current_tab ? ' selected' : '') . "'
					href='" . html_escape($page_name . 'tab=' . $id) . "'>" . $name . '</a>
				</li>';
			}
		}
	}

	print '</ul></nav></div>';
	print '</div>';
}

/**
 * html_end_box - draws the end of an HTML box
 *
 * @param bool $trailing_br - whether to draw a trailing <br> tag after ending
 * @param bool $div - whether type of box is div or table
 *
 * @return void
 */
function html_end_box($trailing_br = true, $div = false) {
	if ($div) {
		print '</div></div>';
	} else {
		print '</table></div>';
	}

	if ($trailing_br == true) {
		print "<div class='break'></div>";
	}
}

function html_filter_end_box() {
	html_end_box(true, true);
}

/**
 * html_graph_area - draws an area the contains full sized graphs
 *
 * @param array $graph_array - the array to contains graph information. for each graph in the
 *   array, the following two keys must exist
 *   $arr[0]["local_graph_id"] // graph id
 *   $arr[0]["title_cache"] // graph title
 * @param string $no_graphs_message - display this message if no graphs are found in $graph_array
 * @param string $extra_url_args - extra arguments to append to the url
 * @param string $header - html to use as a header
 * @param int $columns - the number of columns to present
 * @param int $tree_id - the tree id if this is a tree thumbnail
 * @param int $branch_id - the branch id if this is a tree thumbnail
 *
 * @return void
 */
function html_graph_area(&$graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0, $tree_id = 0, $branch_id = 0) {
	global $config;

	$i = 0;
	$k = 0;
	$j = 0;

	$num_graphs = cacti_sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	if ($('#predefined_timespan').val() == 0) {
		refreshMSeconds = 999999;
	} else {
		refreshMSeconds = <?php print read_user_setting('page_refresh') * 1000;?>;
	}

	refreshPage     = '<?php print get_current_page();?>?action=preview';
	refreshFunction = 'refreshGraphs()';

	var graph_start = <?php print get_current_graph_start();?>;
	var graph_end   = <?php print get_current_graph_end();?>;
	</script>
	<?php

	print '<div class=\'graphPage\'>';

	if ($num_graphs > 0) {
		if ($header != '') {
			print $header;
		}

		foreach ($graph_array as $graph) {
			if (!isset($graph['host_id'])) {
				list($graph['host_id'], $graph['disabled']) = db_fetch_row_prepared('SELECT host_id, disabled
    				FROM graph_local AS gl
	 				LEFT JOIN host AS h
					ON gl.host_id = h.id
     				WHERE gl.id = ?',
					array($graph['local_graph_id']));
			}

			?>
			<div class='graphWrapperOuter cols<?php print $columns;?>' data-disabled='<?php print ($graph['disabled'] == 'on' ? 'true':'false');?>'>
				<div>
					<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print((read_user_setting('custom_fonts') == 'on') ? read_user_setting('title_size') : read_config_option('title_size'));?>'></div>
					<?php if (is_realm_allowed(27)) { ?>
					<div id='dd<?php print $graph['local_graph_id'];?>' class='noprint graphDrillDown'>
						<?php print graph_drilldown_icons($graph['local_graph_id'], 'graph_buttons', $tree_id, $branch_id);?>
					</div>
					<?php } ?>
				</div>
				<?php print (read_user_setting('show_graph_title') == 'on' ? "<div>" . html_escape($graph['title_cache']) . '</div>' : '');?>
			</div>
			<?php
		}
	} else {
		if ($no_graphs_message != '') {
			print "<div class='tableHeaderGraph'><span><em>$no_graphs_message</em></span></div>";
		}
	}

	print '</div>';
}

/**
 * html_graph_thumbnail_area - draws an area the contains thumbnail sized graphs
 *
 * @param array $graph_array - the array to contains graph information. for each graph in the
 *   array, the following two keys must exist
 *   $arr[0]["local_graph_id"] // graph id
 *   $arr[0]["title_cache"] // graph title
 * @param string $no_graphs_message - display this message if no graphs are found in $graph_array
 * @param string $extra_url_args - extra arguments to append to the url
 * @param string $header - html to use as a header
 * @param int $columns - the number of columns to present
 * @param int $tree_id - the tree id if this is a tree thumbnail
 * @param int $branch_id - the branch id if this is a tree thumbnail
 *
 * @return void
 */
function html_graph_thumbnail_area(&$graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0, $tree_id = 0, $branch_id = 0) {
	global $config;
	$i = 0;
	$k = 0;
	$j = 0;

	$num_graphs = cacti_sizeof($graph_array);

	if ($columns == 0) {
		$columns = read_user_setting('num_columns');
	}

	?>
	<script type='text/javascript'>
	if ($('#predefined_timespan').val() == 0) {
		refreshMSeconds = 999999;
	} else {
		refreshMSeconds = <?php print read_user_setting('page_refresh') * 1000;?>;
	}

	refreshPage     = '<?php print get_current_page();?>?action=tree';
	refreshFunction = 'refreshGraphs()';

	var graph_start = <?php print get_current_graph_start();?>;
	var graph_end   = <?php print get_current_graph_end();?>;
	</script>
	<?php

	print '<div class=\'graphPage\'>';

	if ($num_graphs > 0) {
		if ($header != '') {
			print $header;
		}

		$start = true;

		foreach ($graph_array as $graph) {
			if (!isset($graph['host_id'])) {
				list($graph['host_id'], $graph['disabled']) = db_fetch_row_prepared('SELECT host_id, disabled
    				FROM graph_local AS gl
	 				LEFT JOIN host AS h
      				ON gl.host_id = h.id
	   				WHERE gl.id = ?',
					array($graph['local_graph_id']));
			}

			if (isset($graph['graph_template_name'])) {
				if (isset($prev_graph_template_name)) {
					if ($prev_graph_template_name != $graph['graph_template_name']) {
						$prev_graph_template_name = $graph['graph_template_name'];
					}
				} else {
					$prev_graph_template_name = $graph['graph_template_name'];
				}
			} elseif (isset($graph['data_query_name'])) {
				if (isset($prev_data_query_name)) {
					if ($prev_data_query_name != $graph['data_query_name']) {
						$print                = true;
						$prev_data_query_name = $graph['data_query_name'];
					} else {
						$print = false;
					}
				} else {
					$print                = true;
					$prev_data_query_name = $graph['data_query_name'];
				}

				if ($print) {
					print "<div class='tableHeaderGraph graphSubHeaderColumn textHeaderDark'><span>" . __('Data Query:') . ' ' . html_escape($graph['data_query_name']) . '</span></div>';
				}
			}

			?>
			<div class='graphWrapperOuter cols<?php print $columns;?>' data-disabled='<?php print ($graph['disabled'] == 'on' ? 'true':'false');?>'>
				<div>
					<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print read_user_setting('default_width');?>' graph_height='<?php print read_user_setting('default_height');?>'></div>
					<?php if (is_realm_allowed(27)) { ?>
					<div id='dd<?php print $graph['local_graph_id'];?>' class='noprint graphDrillDown'>
						<?php print graph_drilldown_icons($graph['local_graph_id'], 'graph_buttons_thumbnails', $tree_id, $branch_id);?>
					</div>
					<?php } ?>
				</div>
				<?php print (read_user_setting('show_graph_title') == 'on' ? "<div class='center'>" . html_escape($graph['title_cache']) . '</div>' : '');?>
			</div>
			<?php
		}
	} else {
		if ($no_graphs_message != '') {
			print "<div class='tableHeaderGraph'><span><em>$no_graphs_message</em></span></div>";
		}
	}

	print '</div>';
}

/**
 * Generates and prints HTML for graph drilldown icons.
 *
 * This function creates a set of icons for various graph-related actions such as
 * viewing graph details, exporting CSV data, viewing time graphs, editing devices,
 * editing graph templates, viewing graphs in real-time, and killing spikes in graphs.
 * It also allows for plugin hooks to add additional icons.
 *
 * @param int $local_graph_id The ID of the local graph.
 * @param string $type The type of icons to generate, default is 'graph_buttons'.
 * @param int $tree_id The ID of the tree, default is 0.
 * @param int $branch_id The ID of the branch, default is 0.
 *
 * @global array $config The global configuration array.
 *
 * @return void
 */
function graph_drilldown_icons($local_graph_id, $type = 'graph_buttons', $tree_id = 0, $branch_id = 0) {
	global $config;

	static $rand = 0;

	$aggregate_url = aggregate_build_children_url($local_graph_id);

	$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	print "<div class='iconWrapper'>";
	print "<a class='iconLink utils' href='#' role='link' id='graph_" . $local_graph_id . "_util'><i class='drillDown ti ti-settings-filled actionCog' title='" . __esc('Graph Details, Zooming and Debugging Utilities') . "'></i></a><br>";
	print "<a class='iconLink csvexport' href='#' role='link' id='graph_" . $local_graph_id . "_csv'><i class='drillDown ti ti-file-type-csv fileCSV' title='" . __esc('CSV Export of Graph Data'). "'></i></a><br>";
	print "<a class='iconLink mrtg' href='#' role='link' id='graph_" . $local_graph_id . "_mrtg'><i class='drillDown ti ti-menu-2 threeBars' title='" . __esc('Time Graph View'). "'></i></a><br>";

	if (is_realm_allowed(3)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			array($local_graph_id));

		if ($host_id > 0) {
			print "<a class='iconLink' href='" . html_escape(CACTI_PATH_URL . "host.php?action=edit&id=$host_id") . "' data-graph='" . $local_graph_id . "' id='graph_" . $local_graph_id . "_de'><i id='de" . $host_id . '_' . $rand . "' class='drillDown ti ti-server editDevice' title='" . __esc('Edit Device') . "'></i></a>";
			print '<br>';
			$rand++;
		}
	}

	if (is_realm_allowed(10) && $graph_template_id > 0) {
		print "<a class='iconLink' role='link' href='" . html_escape(CACTI_PATH_URL . 'graph_templates.php?action=template_edit&id=' . $graph_template_id) . "'><i class='drillDown ti ti-edit editTemplate' title='" . __esc('Edit Graph Template') . "'></i></a>";
		print '<br>';
	}

	if (read_config_option('realtime_enabled') == 'on' && is_realm_allowed(25)) {
		if (read_user_setting('realtime_mode') == '' || read_user_setting('realtime_mode') == '1') {
			print "<a class='iconLink realtime' href='#' role='link' id='graph_" . $local_graph_id . "_realtime'><i class='drillDown ti ti-chart-area-line-filled realTime' title='" . __esc('Click to view just this Graph in Real-time'). "'></i></a><br>";
		} else {
			print "<a class='iconLink' href='#' onclick=\"window.open('" . CACTI_PATH_URL . 'graph_realtime.php?top=0&left=0&local_graph_id=' . $local_graph_id . "', 'popup_" . $local_graph_id . "', 'directories=no,titlebar=no,toolbar=no,location=no,status=no,menubar=no,scrollbars=no,resizable=yes,width=650,height=300');return false\"><i class='drillDown ti ti-chart-area-line-filled realTime' title='" . __esc('Click to view just this Graph in Real-time') . "'></i></a><br>";
		}
	}

	if (is_realm_allowed(1043)) {
		print "<a href='#' class='iconLink spikekill' data-graph='" . $local_graph_id . "' id='graph_" . $local_graph_id . "_sk'><i id='sk" . $local_graph_id . "' class='drillDown ti ti-paint-filled spikeKill' title='" . __esc('Kill Spikes in Graphs') . "'></i></a>";
		print '<br>';
	}

	if ($aggregate_url != '') {
		print $aggregate_url;
	}

	api_plugin_hook($type, array(
		'hook'           => $type,
		'local_graph_id' => $local_graph_id,
		'rra'            => 0,
		'view_type'      => $tree_id > 0 ? 'tree':'preview',
		'tree_id'        => $tree_id,
		'branch_id'      => $branch_id)
	);

	print '</div>';
}

/**
 * Generates an HTML navigation bar for paginated content.
 *
 * @param string $base_url The base URL for the navigation links.
 * @param int $max_pages The maximum number of pages to display in the navigation.
 * @param int $current_page The current page number.
 * @param int $rows_per_page The number of rows to display per page.
 * @param int $total_rows The total number of rows available.
 * @param int $colspan The number of columns to span for the navigation bar (default is 30).
 * @param string $object The name of the object being paginated (default is 'Rows').
 * @param string $page_var The query parameter name for the page number (default is 'page').
 * @param string $return_to The ID of the HTML element to update with the new page content (default is '').
 * @param bool $page_count Whether to display the page count (default is true).
 *
 * @return string The generated HTML for the navigation bar.
 */
function html_nav_bar($base_url, $max_pages, $current_page, $rows_per_page, $total_rows, $colspan=30, $object = '', $page_var = 'page', $return_to = '', $page_count = true) {
	if ($object == '') {
		$object = __('Rows');
	}

	if ($total_rows >= $rows_per_page && $page_count) {
		if (substr_count($base_url, '?') == 0) {
			$base_url = trim($base_url) . '?';
		} else {
			$base_url = trim($base_url) . '&';
		}

		$url_page_select = get_page_list($current_page, $max_pages, $rows_per_page, $total_rows, $base_url, $page_var, $return_to);

		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationPrevious'>
				" . (($current_page > 1) ? "<a href='#' onClick='goto$page_var(" . ($current_page - 1) . ");return false;'><i class='ti ti-chevrons-left previous'></i>" . __('Previous'). '</a>':'') . "
			</div>
			<div class='navBarNavigationCenter'>
				" . __('%d to %d of %s [ %s ]', (($rows_per_page * ($current_page - 1)) + 1), (($total_rows < $rows_per_page) || ($total_rows < ($rows_per_page * $current_page)) ? $total_rows : $rows_per_page * $current_page), $total_rows, $url_page_select) . "
			</div>
			<div class='navBarNavigationNext'>
				" . (($current_page * $rows_per_page) < $total_rows ? "<a href='#' onClick='goto$page_var(" . ($current_page + 1) . ");return false;'>" . __('Next'). "<i class='ti ti-chevrons-right next'></i></a>":'') . '
			</div>
		</div>';
	} elseif ($total_rows > 0) {
		if ($page_count || ($total_rows < $rows_per_page && $current_page == 1)) {
			$nav = "<div class='navBarNavigation'>
				<div class='navBarNavigationNone'>
					" . __('All %d %s', $total_rows, $object) . "
				</div>
			</div>\n";
		} else {
			if (substr_count($base_url, '?') == 0) {
				$base_url = trim($base_url) . '?';
			} else {
				$base_url = trim($base_url) . '&';
			}

			$url_page_select = "<ul class='pagination'>"; //for the same height as write in get_page_list()
			$url_page_select .= "<li>$current_page</a></li>";
			$url_page_select .= '</ul>';

			$nav = "<div class='navBarNavigation'>
				<div class='navBarNavigationPrevious'>
					" . (($current_page > 1) ? "<a href='#' onClick='goto$page_var(" . ($current_page - 1) . ");return false;'><i class='ti ti-chevrons-left previous'></i>" . __('Previous'). '</a>':'') . "
				</div>
				<div class='navBarNavigationCenter'>
					" . __('Current Page: %s', $url_page_select) . "
				</div>
				<div class='navBarNavigationNext'>
					" . ($total_rows >= $rows_per_page ? "<a href='#' onClick='goto$page_var(" . ($current_page + 1) . ");return false;'>" . __('Next'). "<i class='ti ti-chevrons-right next'></i></a>":'') . "
				</div>
			</div>\n";

			if ($return_to == '') {
				$return_to = 'main';
			}

			$url  = $base_url . $page_var;
			$nav .= "<script type='text/javascript'>
			function goto$page_var(pageNo) {
				if (typeof url_graph === 'function') {
					var url_add=url_graph('')
				} else {
					var url_add='';
				};

				strURL = '$url='+pageNo+url_add;

				loadUrl({
					url: strURL,
					elementId: '$return_to',
				});
			}</script>";
		}
	} else {
		$nav = "<div class='navBarNavigation'>
			<div class='navBarNavigationNone'>
				" . __('No %s Found', $object) . '
			</div>
		</div>';
	}

	return $nav;
}

/**
 * html_header_sort - draws a header row suitable for display inside of a box element.  When
 * a user selects a column header, the callback function "filename" will be called to handle
 * the sort the column and display the altered results.
 *
 * @param array $header_items - an array containing a list of column items to display.  The
 *   format is similar to the html_header, with the exception that it has three
 *   dimensions associated with each element (db_column => display_text, default_sort_order)
 *   alternatively (db_column => array('display' = 'blah', 'align' = 'blah', 'sort' = 'blah'))
 * @param string $sort_column - the value of current sort column.
 * @param string $sort_direction - the value the current sort direction.  The actual sort direction
 *   will be opposite this direction if the user selects the same named column.
 * @param int $last_item_colspan - the TD 'colspan' to apply to the last cell in the row
 * @param string $url - a base url to redirect sort actions to
 * @param string $return_to - the id of the object to inject output into as a result of the sort action
 *
 * @return void
 */
function html_header_sort($header_items, $sort_column, $sort_direction, $last_item_colspan = 1, $url = '', $return_to = '') {
	static $page_count = 0;

	$table_id = form_get_table_id();

	$header_items = form_process_visible_display_text($table_id, $header_items);

	/* reverse the sort direction */
	if ($sort_direction == 'ASC') {
		$new_sort_direction = 'DESC';
	} else {
		$new_sort_direction = 'ASC';
	}

	$page = $page_count . '_' . str_replace('.php', '', basename($_SERVER['SCRIPT_NAME']));

	if (isset_request_var('action')) {
		$page .= '_' . get_request_var('action');
	}

	if (isset_request_var('tab')) {
		$page .= '_' . get_request_var('tab');
	}

	if (isset($_SESSION['sort_data'][$page])) {
		$order_data = $_SESSION['sort_data'][$page];
	} else {
		$order_data = array(get_request_var('sort_column') => get_request_var('sort_direction'));
	}

	foreach ($order_data as $key => $direction) {
		$primarySort = $key;

		break;
	}

	$table_visibility = array(
		'table_id' => $table_id,
		'columns'  => $header_items
	);

	print "<thead><tr class='tableHeader' data-columns='" . base64_encode(json_encode($table_visibility)) . "'>";

	$i = 1;

	foreach ($header_items as $db_column => $display_array) {
		/* if the column is not visible, don't display it */
		if (isset($display_array['visible']) && $display_array['visible'] === false) {
			continue;
		}

		$isSort = '';

		if (isset($display_array['nohide'])) {
			$nohide = 'nohide';
		} else {
			$nohide = '';
		}

		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];

			if ($sort_column == $db_column) {
				$icon      = $sort_direction;
				$direction = $new_sort_direction;

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];

					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';

					if (isset($display_array['sort'])) {
						$direction = $display_array['sort'];
					} else {
						$direction = 'ASC';
					}
				}
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			} else {
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			} else {
				$tip = '';
			}
		} else {
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon         = $sort_direction;
				$direction    = $new_sort_direction;
				$display_text = $display_array[0];

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];

					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon      = '';
					$direction = $display_array[1];
				}

				$display_text = $display_array[0];
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'ti ti-caret-up-filled';
		} elseif (strtolower($icon) == 'desc') {
			$icon = 'ti ti-caret-down-filled';
		} else {
			$icon = 'ti ti-caret-up-down-filled';
		}

		if (($db_column == '') || (substr_count($db_column, 'nosort'))) {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='$nohide $align' " . ((($i + 1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . $display_text . '</th>';
		} else {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='sortable $align $nohide $isSort'>";
			print "<div class='sortinfo' sort-return='" . ($return_to == '' ? 'main':$return_to) . "' sort-page='" . ($url == '' ? html_escape(get_current_page(false)):$url) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>";
		}

		$i++;
	}

	print '</tr></thead>';

	$page_count++;
}

/**
 * html_header_sort_checkbox - draws a header row with a 'select all' checkbox in the last cell
 * suitable for display inside of a box element.  When a user selects a column header,
 * the callback function "filename" will be called to handle the sort the column and display
 * the altered results.
 *
 * @param array $header_items - an array containing a list of column items to display.  The
 *   fonrmat is similar to the html_header, with the exception that it has three
 *   dimensions associated with each element (db_column => display_text, default_sort_order)
 *   alternatively (db_column => array('display' = 'blah', 'align' = 'blah', 'sort' = 'blah'))
 * @param string $sort_column - the value of current sort column.
 * @param string $sort_direction - the value the current sort direction.  The actual sort direction
 *   will be opposite this direction if the user selects the same named column.
 * @param bool $include_form - whether to include the 'select all' form
 * @param string $form_action - the url to post the 'select all' form to
 * @param string $return_to - the id of the object to inject output into as a result of the sort action
 * @param string $prefix - the prefix to use for the checkbox names
 *
 * @return void
 */
function html_header_sort_checkbox($header_items, $sort_column, $sort_direction, $include_form = true, $form_action = '', $return_to = '', $prefix = 'chk') {
	static $page_count = 0;

	$table_id = form_get_table_id();

	$header_items = form_process_visible_display_text($table_id, $header_items);

	/* reverse the sort direction */
	if ($sort_direction == 'ASC') {
		$new_sort_direction = 'DESC';
	} else {
		$new_sort_direction = 'ASC';
	}

	$page = $page_count . '_' . str_replace('.php', '', basename($_SERVER['SCRIPT_NAME']));

	if (isset_request_var('action')) {
		$page .= '_' . get_request_var('action');
	}

	if (isset_request_var('tab')) {
		$page .= '_' . get_request_var('tab');
	}

	if (isset($_SESSION['sort_data'][$page])) {
		$order_data = $_SESSION['sort_data'][$page];
	} else {
		$order_data = array(get_request_var('sort_column') => get_request_var('sort_direction'));
	}

	foreach ($order_data as $key => $direction) {
		$primarySort = $key;

		break;
	}

	/* default to the 'current' file */
	if ($form_action == '') {
		$form_action = get_current_page();
	}

	$table_visibility = array(
		'table_id' => $table_id,
		'columns'  => $header_items
	);

	print "<thead><tr class='tableHeader' data-columns='" . base64_encode(json_encode($table_visibility)) . "'>";

	foreach ($header_items as $db_column => $display_array) {
		/* if the column is not visible, don't display it */
		if (isset($display_array['visible']) && $display_array['visible'] === false) {
			continue;
		}

		$isSort = '';

		if (isset($display_array['nohide'])) {
			$nohide = 'nohide';
		} else {
			$nohide = '';
		}

		$icon   = '';

		if (array_key_exists('display', $display_array)) {
			$display_text = $display_array['display'];

			if ($sort_column == $db_column) {
				$icon      = $sort_direction;
				$direction = $new_sort_direction;

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];

					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon = '';

					if (isset($display_array['sort'])) {
						$direction = $display_array['sort'];
					} else {
						$direction = 'ASC';
					}
				}
			}

			if (isset($display_array['align'])) {
				$align = $display_array['align'];
			} else {
				$align = 'left';
			}

			if (isset($display_array['tip'])) {
				$tip = $display_array['tip'];
			} else {
				$tip = '';
			}
		} else {
			/* by default, you will always sort ascending, with the exception of an already sorted column */
			if ($sort_column == $db_column) {
				$icon         = $sort_direction;
				$direction    = $new_sort_direction;
				$display_text = $display_array[0];

				if ($db_column == $primarySort) {
					$isSort = 'primarySort';
				} else {
					$isSort = 'secondarySort';
				}
			} else {
				if (isset($order_data[$db_column])) {
					$icon = $order_data[$db_column];

					if ($order_data[$db_column] == 'DESC') {
						$direction = 'ASC';
					} else {
						$direction = 'DESC';
					}

					if ($db_column == $primarySort) {
						$isSort = 'primarySort';
					} else {
						$isSort = 'secondarySort';
					}
				} else {
					$icon      = '';
					$direction = $display_array[1];
				}

				$display_text = $display_array[0];
			}

			$align = 'left';
			$tip   = '';
		}

		if (strtolower($icon) == 'asc') {
			$icon = 'ti ti-caret-up-filled';
		} elseif (strtolower($icon) == 'desc') {
			$icon = 'ti ti-caret-down-filled';
		} else {
			$icon = 'ti ti-caret-up-down-filled';
		}

		if (($db_column == '') || (substr_count($db_column, 'nosort'))) {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='$align $nohide'>" . $display_text . '</th>';
		} else {
			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "'":'') . " class='sortable $align $nohide $isSort'>";
			print "<div class='sortinfo' sort-return='" . ($return_to == '' ? 'main':$return_to) . "' sort-page='" . html_escape($form_action) . "' sort-column='$db_column' sort-direction='$direction'><div class='textSubHeaderDark'>" . $display_text . "<i class='$icon'></i></div></div></th>";
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __esc('Select All Rows'). "' onClick='selectAll(\"$prefix\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows') . "' for='selectall'></label></th>" . ($include_form ? "<th style='display:none;'><form id='$prefix' name='$prefix' method='post' action='$form_action'></th>":'');
	print '</tr></thead>';

	$page_count++;
}

/**
 * html_header - draws a header row suitable for display inside a box element
 *
 * @param array $header_items - an array containing a list of items to be included in the header
 *   alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
 * @param int $last_item_colspan - the TD 'colspan' to apply to the last cell in the row
 *
 * @return void
 */
function html_header($header_items, $last_item_colspan = 1, $resizable = true) {
	$table_id = form_get_table_id();

	$header_items = form_process_visible_display_text($table_id, $header_items);

	$table_visibility = array(
		'table_id' => $table_id,
		'columns'  => $header_items
	);

	print "<thead><tr class='tableHeader " . ($last_item_colspan > 1 || !$resizable ? 'tableFixed':'') . "' data-columns='" . base64_encode(json_encode($table_visibility)) . "'>";

	$i = 0;

	foreach ($header_items as $item) {
		if (is_array($item)) {
			/* if the column is not visible, don't display it */
			if (isset($display_array['visible']) && $display_array['visible'] === false) {
				continue;
			}

			if (isset($item['nohide'])) {
				$nohide = 'nohide';
			} else {
				$nohide = '';
			}

			if (isset($item['align'])) {
				$align = $item['align'];
			} else {
				$align = 'left';
			}

			if (isset($item['tip'])) {
				$tip = $item['tip'];
			} else {
				$tip = '';
			}

			print '<th ' . ($tip != '' ? "title='" . html_escape($tip) . "' ":'') . "class='$nohide $align' " . ((($i + 1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . html_escape($item['display']) . '</th>';
		} else {
			print '<th ' . ((($i + 1) == cacti_count($header_items)) ? "colspan='$last_item_colspan' " : '') . '>' . html_escape($item) . '</th>';
		}

		$i++;
	}

	print '</tr></thead>';
}

/**
 * html_section_header - draws a header row suitable for display inside a box element
 * but for display as a section title and not as a series of table header columns
 *
 * @param array $header_name - an array of the display name of the header for the section and
 *   optional alignment.
 * @param int $last_item_colspan - the TD 'colspan' to apply to the last cell in the row
 *
 * @return void
 */
function html_section_header($header_item, $last_item_colspan = 1, $resizeable = true) {
	print "<tr class='tableHeader " . ($last_item_colspan > 1 || !$resizable ? 'tableFixed':'') . "'>";

	if (is_array($header_item) && isset($header_item['display'])) {
		print '<th ' . (isset($header_item['align']) ? "style='text-align:" . $header_item['align'] . ";'":'') . " colspan='$last_item_colspan'>" . $header_item['display'] . '</th>';
	} else {
		print "<th colspan='$last_item_colspan'>" . $header_item . '</th>';
	}

	print '</tr>';
}

/**
 * html_header_checkbox - draws a header row with a 'select all' checkbox in the last cell
 * suitable for display inside a box element
 *
 * @param array $header_items - an array containing a list of items to be included in the header
 *   alternatively and array of header names and alignment array('display' = 'blah', 'align' = 'blah')
 * @param bool $include_form - whether to include the 'select all' form
 * @param string $form_action - the url to post the 'select all' form to
 * @param bool $resizable - whether the table is resizable
 * @param string $prefix - the prefix to use for the checkbox names
 *
 * @return void
 */
function html_header_checkbox($header_items, $include_form = true, $form_action = '', $resizable = true, $prefix = 'chk') {
	$table_id = form_get_table_id();

	$header_items = form_process_visible_display_text($table_id, $header_items);

	/* default to the 'current' file */
	if ($form_action == '') {
		$form_action = get_current_page();
	}

	$table_visibility = array(
		'table_id' => $table_id,
		'columns'  => $header_items
	);

	print "<thead><tr class='tableHeader " . (!$resizable ? 'tableFixed':'') . "' data-columns='" . base64_encode(json_encode($table_visibility)) . "'>";

	foreach ($header_items as $item) {
		if (is_array($item)) {
			/* if the column is not visible, don't display it */
			if (isset($display_array['visible']) && $display_array['visible'] === false) {
				continue;
			}

			if (isset($item['nohide'])) {
				$nohide = 'nohide';
			} else {
				$nohide = '';
			}

			if (isset($item['align'])) {
				$align = $item['align'];
			} else {
				$align = 'left';
			}

			if (isset($item['tip'])) {
				$tip = $item['tip'];
			} else {
				$tip = '';
			}

			print '<th ' . ($tip != '' ? " title='" . html_escape($tip) . "' ":'') . "class='$align $nohide'>" . html_escape($item['display']) . '</th>';
		} else {
			print "<th class='left'>" . html_escape($item) . '</th>';
		}
	}

	print "<th class='tableSubHeaderCheckbox'><input id='selectall' class='checkbox' type='checkbox' title='" . __esc('Select All Rows'). "' onClick='selectAll(\"$prefix\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='selectall'></label></th>" . ($include_form ? "<th style='display:none;'><form id='$prefix' name='$prefix' method='post' action='$form_action'></th>":'');
	print '</tr></thead>';
}

/**
 * html_create_list - draws the items for a html dropdown given an array of data
 *
 * @param array $form_data - an array containing data for this dropdown. it can be
 *   formatted in one of three ways:
 *
 *   $dropdown_array = array(
 *     'id'  => 'name1'
 *     'id2' => 'name2',
 *     ...
 *   );
 *
 *   -- or --
 *
 *   $dropdown_array = array(
 *       array(
 *         'id'   => 'id1',
 *         'name' => 'name1',
 *       ),
 *       array(
 *         'id'   => 'id2',
 *         'name' => 'name2'
 *       ),
 *       ...
 *   );
 *
 *   -- or for custom rendering of icons --
 *
 *   $dropdown_array = array(
 *     'server' => array(
 *        'display' => __('Some Value'),
 *        'class'   => 'ti ti-server',
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
 *
 * @return void
 */
function html_create_list($form_data, $column_display, $column_id, $form_previous_value) {
	if (cacti_sizeof($form_data)) {
		foreach ($form_data as $key => $row) {
			if (is_array($row)) {
				if ($column_id != '') {
					print "<option value='" . html_escape($row[$column_id]) . "'";
				} else {
					print "<option value='" . html_escape($key) . "'";
				}
			} else {
				print "<option value='" . html_escape($key) . "'";
			}

			if ($column_id != '' && isset($row[$column_id]) && $form_previous_value == $row[$column_id]) {
				print ' selected';
			} elseif ($column_id == '' && $key == $form_previous_value) {
				print ' selected';
			}

			if (isset($row['class'])) {
				print " data-class='" . $row['class'] . "'";
			}

			if (isset($row['style'])) {
				print " data-style='" . $row['style'] . "'";
			}

			if (!is_array($row)) {
				print '>' . html_escape($row) . '</option>';
			} elseif (isset($row['host_id'])) {
				print '>' . html_escape($row[$column_display]) . '</option>';
			} elseif (isset($row['display'])) {
				print '>' . html_escape($row['display']) . '</option>';
			} else {
				print '>' . html_escape(null_out_substitutions($row[$column_display])) . '</option>';
			}
		}
	}
}

/**
 * html_escape_request_var - sanitizes a request variable for display
 *
 * @param  string $string - string the request variable to escape
 *
 * @return string $new_string - the escaped request variable to be returned.
 */
function html_escape_request_var($string) {
	return html_escape(get_nfilter_request_var($string));
}

/**
 * html_escape - sanitizes a string for display
 *
 * @param  string $string - string the string to escape
 *
 * @return string $new_string - the escaped string to be returned.
 */
function html_escape($string) {
	static $charset;

	if ($charset == '') {
		$charset = ini_get('default_charset');
	}

	if ($charset == '') {
		$charset = 'UTF-8';
	}

	// Grave Accent character can lead to xss
	if ($string !== null && $string != '') {
		$string = str_replace('`', '&#96;', $string);

		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, $charset, false);
	} else {
		return $string;
	}
}

/**
 * html_split_string - takes a string and breaks it into a number of <br> separated segments
 *
 * @param string $string - string to be modified and returned
 * @param int $length - the maximal string length to split to
 * @param int $forgiveness - the maximum number of characters to walk back from to determine
 *   the correct break location.
 *
 * @return string $new_string - the modified string to be returned.
 */
function html_split_string($string, $length = 90, $forgiveness = 10) {
	$new_string = '';
	$j          = 0;
	$done       = false;

	while (!$done) {
		if (mb_strlen($string, 'UTF-8') > $length) {
			for ($i = 0; $i < $forgiveness; $i++) {
				if (substr($string, $length - $i, 1) == ' ') {
					$new_string .= mb_substr($string, 0, $length - $i, 'UTF-8') . '<br>';

					break;
				}
			}

			$string = mb_substr($string, $length - $i, null, 'UTF-8');
		} else {
			$new_string .= $string;
			$done        = true;
		}

		$j++;

		if ($j > 4) {
			break;
		}
	}

	return $new_string;
}

/**
 * draw_graph_items_list - draws a nicely formatted list of graph items for display
 * on an edit form
 *
 * @param array $item_list - an array representing the list of graph items. this array should
 *   come directly from the output of db_fetch_assoc()
 * @param string $filename - the filename to use when referencing any external url
 * @param string $url_data - any extra GET url information to pass on when referencing any
 *   external url
 * @param bool $disable_controls - whether to hide all edit/delete functionality on this form
 *
 * @return void
 */
function draw_graph_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $config;

	include(CACTI_PATH_INCLUDE . '/global_arrays.php');

	$display_text = array(
		array(
			'display' => __('Data Source'),
			'align'   => 'left'
		),
		array(
			'display' => __('Seq#'),
			'align'   => 'center'
		),
		array(
			'display' => __('Type'),
			'align'   => 'left'
		),
		array(
			'display' => __('Consolidation'),
			'align'   => 'left'
		),
		array(
			'display' => __('Legend'),
			'tip'     => __('When exporting or showing the values while hovering over the Graph, what legend to you want displayed?  If empty, it will default to data_source_name (consolidation function).  This does not work for some Cacti items such as TOTAL_ALL_DATA_SOURCES for example.'),
			'align'   => 'left'
		),
		array(
			'display' => __('GPrint'),
			'align'   => 'left'
		),
		array(
			'display' => __('CDEF'),
			'align'   => 'left'
		),
		array(
			'display' => __('VDEF'),
			'align'   => 'left'
		),
		array(
			'display' => __('Primary Color'),
			'align'   => 'left'
		),
		array(
			'display' => __('Gradient Color'),
			'align'   => 'left'
		),
		array(
			'display' => __('Actions'),
			'align'   => 'right'
		)
	);

	html_header($display_text);

	$group_counter    = 0;
	$_graph_type_name = '';
	$i                = 0;

	if (cacti_sizeof($item_list)) {
		foreach ($item_list as $item) {
			$_graph_type_name = $graph_item_types[$item['graph_type_id']];

			/* graph grouping display logic */
			$this_row_style   = '';
			$use_custom_class = false;
			$hard_return      = '';

			if (!preg_match('/(GPRINT|TEXTALIGN|HRULE|VRULE|TICK)/', $_graph_type_name)) {
				$this_row_style      = 'font-weight: bold;';
				$use_custom_class    = true;
				$item['gprint_name'] = '-';

				if ($group_counter % 2 == 0) {
					$customClass = 'graphItem';
				} else {
					$customClass = 'graphItemAlternate';
				}

				$group_counter++;
			}


			/* alternating row color */
			if ($use_custom_class == false) {
				print "<tr id='{$item['id']}' class='tableRow selectable'>";
			} else {
				print "<tr id='{$item['id']}' class='tableRow  selectable $customClass'>";
			}

			if (empty($item['data_source_name'])) {
				$item['data_source_name'] = __('No Source');
			}

			switch (true) {
				case preg_match('/(TEXTALIGN)/', $_graph_type_name):
					$matrix_title = 'TEXTALIGN: ' . ucfirst($item['textalign']);

					break;
				case preg_match('/(TICK)/', $_graph_type_name):
					$matrix_title = $item['data_source_name'] . ': ' . $item['text_format'];

					break;
				case preg_match('/(AREA|STACK|GPRINT|LINE[123])/', $_graph_type_name):
					$matrix_title = $item['data_source_name'] . ': ' . $item['text_format'];

					break;
				case preg_match('/(HRULE)/', $_graph_type_name):
					$matrix_title = 'HRULE: ' . $item['value'];

					break;
				case preg_match('/(VRULE)/', $_graph_type_name):
					$matrix_title = 'VRULE: ' . $item['value'];

					break;
				case preg_match('/(COMMENT)/', $_graph_type_name):
					$matrix_title = 'COMMENT: ' . $item['text_format'];

					break;
			}

			if (preg_match('/(TEXTALIGN)/', $_graph_type_name)) {
				$hard_return = '';
			} elseif ($item['hard_return'] == 'on') {
				$hard_return = "<span style='font-weight:bold;color:#FF0000;'>&lt;HR&gt;</span>";
			}

			if ($disable_controls == false) {
				$display = "<a class='linkEditMain' href='" . html_escape("$filename?action=item_edit&id=" . $item['id'] . "&$url_data") . "'>" . html_escape($matrix_title) . '</a>';
			} else {
				$display = html_escape($matric_title);
			}

			/* data source display */
			print "<td style='$this_row_style'>" . $display . $hard_return . '</td>';

			/* sequence number */
			print "<td class='center' style='$this_row_style'>" . $item['sequence'] . '</td>';

			/* graph item type display */
			print "<td style='$this_row_style'>" . $graph_item_types[$item['graph_type_id']] . '</td>';

			/* consolidation function display */
			if (!preg_match('/(TICK|TEXTALIGN|HRULE|VRULE)/', $_graph_type_name)) {
				print "<td style='$this_row_style'>" . $consolidation_functions[$item['consolidation_function_id']] . '</td>';
			} else {
				print '<td>-</td>';
			}

			/* export/hover legend */
			if ($item['legend'] != '') {
				print "<td style='$this_row_style'>" . html_escape($item['legend']) . '</td>';
			} else {
				print '<td>-</td>';
			}

			/* grpint display */
			print "<td class='prewrap' style='$this_row_style'>";
			if ($item['gprint_name'] != '') {
				print html_escape($item['gprint_name']);
			} else {
				print '-';
			}
			print '</td>';

			/* cdef display */
			print "<td class='prewrap' style='$this_row_style'>";
			if ($item['cdef_name'] != '') {
				print $item['cdef_name'];
			} else {
				print '-';
			}
			print '</td>';

			/* vdef display */
			print "<td class='prewrap' style='$this_row_style'>";
			if ($item['vdef_name'] != '') {
				print $item['vdef_name'];
			} else {
				print '-';
			}
			print '</td>';

			/* color display */
			$blank = '-';

			if (preg_match('/(AREA|STACK|TICK|LINE[123])/', $_graph_type_name)) {
				if (preg_match('/(AREA|STACK)/', $_graph_type_name)) {
					if ($item['hex'] != '') {
						$color1 = $item['hex']  . $item['alpha'];

						if ($item['hex2'] != '') {
							$color2 = $item['hex2'] . ($item['hex2'] != '' ? $item['alpha2']:'');
						} else {
							$color2 = $blank;
						}
					} else {
						$color1 = $color2 = $blank;
					}
				} else {
					if ($item['hex'] != '') {
						$color1 = $item['hex'] . $item['alpha'];
					} else {
						$color1 = $blank;
					}
					$color2 = $blank;
				}
			} else {
				$color1 = $color2 = $blank;
			}

			if (!preg_match('/(TEXTALIGN)/', $_graph_type_name)) {
				if (preg_match('/(AREA|STACK)/', $_graph_type_name)) {
					/* color1 */
					print "<td class='nowrap'>";
					print "<div style='display:table-cell;min-width:16px;background-color:#{$color1}'></div>";
					print "<div style='display:table-cell;padding-left:5px;'>{$color1}</div>";
					print '</td>';

					/* color2 */
					print "<td class='nowrap'>";

					if ($color2 != $blank) {
						print "<div style='display:table-cell;min-width:16px;background-color:#{$color2}'></div>";
						print "<div style='display:table-cell;padding-left:5px;'>{$color2}</div>";
					} else {
						print $color2;
					}
					print '</td>';
				} else {
					/* color 1 */
					print "<td class='nowrap'>";
					if ($color1 != $blank) {
						print "<div style='display:table-cell;min-width:16px;background-color:#{$color1}'></div>";
						print "<div style='display:table-cell;padding-left:5px;'>{$color1}</div>";
					} else {
						print $color1;
					}
					print '</td>';

					/* color2 */
					print "<td>{$color2}</td>";
				}
			} else {
				print '<td></td><td></td>';
			}

			if ($disable_controls == false) {
				print "<td class='right nowrap'>";

				if ($i != cacti_sizeof($item_list) - 1) {
					print "<span><a class='moveArrow ti ti-caret-down-filled' title='" . __esc('Move Down'). "' href='" . html_escape("$filename?action=item_movedown&id=" . $item['id'] . "&$url_data") . "'></a></span>";
				} else {
					print "<span class='moveArrowNone'></span>";
				}

				if ($i > 0) {
					print "<span><a class='moveArrow ti ti-caret-up-filled' title='" . __esc('Move Up') . "' href='" . html_escape("$filename?action=item_moveup&id=" . $item['id'] . "&$url_data") . "'></a></span>";
				} else {
					print "<span class='moveArrowNone'></span>";
				}

				print "<a class='deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='" . html_escape("$filename?action=item_remove&id=" . $item['id'] . "&nostate=true&$url_data") . "'></a>";

				print '</td>';
			}

			print '</tr>';

			$i++;
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . cacti_sizeof($display_text) . "'><em>" . __('No Items') . '</em></td></tr>';
	}
}

/**
 * is_menu_pick_active - determines if current selection is active
 *
 * @param string $menu_url - url of current page
 *
 * @return bool true if active, false if not
*/
function is_menu_pick_active($menu_url) {
	static $url_array, $url_parts;

	$menu_parts = array();

	/* special case for host.php?action=edit&create=true */
	if (strpos($_SERVER['REQUEST_URI'], 'host.php?action=edit&create=true') !== false) {
		if (strpos($menu_url, 'host.php?action=edit&create=true') !== false) {
			return true;
		} else {
			return false;
		}
	} elseif (!is_array($url_array) || (is_array($url_array) && !cacti_sizeof($url_array))) {
		/* break out the URL and variables */
		$url_array = parse_url($_SERVER['REQUEST_URI']);

		if (isset($url_array['query'])) {
			parse_str($url_array['query'], $url_parts);
		} else {
			$url_parts = array();
		}
	}

	// Host requires another check
	if (strpos($menu_url, 'host.php?action=edit&create=true') !== false) {
		return false;
	}

	$menu_array = parse_url($menu_url);

	if ($menu_array === false) {
		return false;
	}

	if (! array_key_exists('path', $menu_array)) {
		return false;
	}

	if (basename($url_array['path']) == basename($menu_array['path'])) {
		if (isset($menu_array['query'])) {
			parse_str($menu_array['query'], $menu_parts);
		} else {
			$menu_parts = array();
		}

		if (isset($menu_parts['id'])) {
			if (isset($url_parts['id'])) {
				if ($menu_parts['id'] == $url_parts['id']) {
					return true;
				}
			}
		} elseif (isset($menu_parts['action'])) {
			if (isset($url_parts['action'])) {
				if ($menu_parts['action'] == $url_parts['action']) {
					return true;
				}
			}
		} else {
			return true;
		}
	}

	return false;
}

/**
 * draw_menu - draws the cacti menu for display in the console
 *
 * @param string $user_menu - the user menu to display
 *
 * @return void
 */
function draw_menu($user_menu = '') {
	global $config, $user_auth_realm_filenames, $menu, $menu_glyphs;

	if (!is_array($user_menu)) {
		$user_menu = $menu;
	}

	print "<div id='menu'><ul id='nav' role='menu'>";

	/* loop through each header */
	$i       = 0;
	$headers = array();

	foreach ($user_menu as $header_name => $header_array) {
		/* pass 1: see if we are allowed to view any children */
		$show_header_items = false;

		foreach ($header_array as $item_url => $item_title) {
			$basename = explode('?', basename($item_url));
			$basename = $basename[0];

			if (preg_match('#link.php\?id=(\d+)#', $item_url, $matches)) {
				if (is_realm_allowed($matches[1] + 10000)) {
					$show_header_items = true;
				} else {
					$show_header_items = false;
				}
			} else {
				$current_realm_id = (isset($user_auth_realm_filenames[basename($item_url)]) ? $user_auth_realm_filenames[basename($item_url)] : 0);

				if (is_realm_allowed($current_realm_id)) {
					$show_header_items = true;
				} elseif (api_user_realm_auth($basename)) {
					$show_header_items = true;
				}
			}
		}

		if ($show_header_items == true) {
			// Let's give our menu li's a unique id
			$id = 'menu_' . strtolower(clean_up_name($header_name));

			if (isset($headers[$id])) {
				$id .= '_' . $i++;
			}
			$headers[$id] = true;

			if (isset($menu_glyphs[$header_name])) {
				$glyph = '<i class="menu_glyph ' . $menu_glyphs[$header_name] . '"></i>';
			} else {
				$glyph = '<i class="menu_glyph ti ti-folder-filled"></i>';
			}

			print "<li class='menuitem' role='menuitem' aria-haspopup='true' id='$id'><a class='menu_parent active' href='#'>$glyph<span>$header_name</span></a>";
			print "<ul role='menu' id='{$id}_div' style='display:block;'>";

			/* pass 2: loop through each top level item and render it */
			foreach ($header_array as $item_url => $item_title) {
				$basename         = explode('?', basename($item_url));
				$basename         = $basename[0];
				$current_realm_id = (isset($user_auth_realm_filenames[$basename]) ? $user_auth_realm_filenames[$basename] : 0);

				/**
				 * if this item is an array, then it contains sub-items. if not, is just
				 * the title string and needs to be displayed
				 */
				if (is_array($item_title)) {
					$i = 0;

					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames[$basename])) {
						/* if the current page exists in the sub-items array, draw each sub-item */
						if (array_key_exists(get_current_page(), $item_title) == true) {
							$draw_sub_items = true;
						} else {
							$draw_sub_items = false;
						}

						foreach ($item_title as $item_sub_url => $item_sub_title) {
							if (substr($item_sub_url, 0, 10) == 'EXTERNAL::') {
								$item_sub_external = true;
								$item_sub_url      = substr($item_sub_url, 10);
							} else {
								$item_sub_external = false;
								$item_sub_url      = CACTI_PATH_URL . $item_sub_url;
							}

							/* always draw the first item (parent), only draw the children if we are viewing a page
							that is contained in the sub-items array */
							if (($i == 0) || ($draw_sub_items)) {
								if (is_menu_pick_active($item_sub_url)) {
									print "<li><a role='menuitem' class='pic selected' href='";
									print html_escape($item_sub_url) . "'";

									if ($item_sub_external) {
										print " target='_blank' rel='noopener'";
									}
									print ">$item_sub_title</a></li>";
								} else {
									print "<li><a role='menuitem' class='pic' href='";
									print html_escape($item_sub_url) . "'";

									if ($item_sub_external) {
										print " target='_blank' rel='noopener'";
									}
									print ">$item_sub_title</a></li>";
								}
							}

							$i++;
						}
					}
				} else {
					if ($current_realm_id == -1 || is_realm_allowed($current_realm_id) || !isset($user_auth_realm_filenames[$basename])) {
						/* draw normal (non sub-item) menu item */
						if (substr($item_url, 0, 10) == 'EXTERNAL::') {
							$item_external = true;
							$item_url      = substr($item_url, 10);
						} else {
							$item_external = false;
							$item_url      = CACTI_PATH_URL . $item_url;
						}

						if (is_menu_pick_active($item_url)) {
							print "<li><a role='menuitem' class='pic selected' href='";
							print html_escape($item_url) . "'";

							if ($item_external) {
								print " target='_blank' rel='noopener'";
							}
							print ">$item_title</a></li>";
						} else {
							print "<li><a role='menuitem' class='pic' href='";
							print html_escape($item_url) . "'";

							if ($item_external) {
								print " target='_blank' rel='noopener'";
							}
							print ">$item_title</a></li>";
						}
					}
				}
			}

			print '</ul></li>';
		}
	}

	print '</ul></div>';
}

/**
 * draw_actions_dropdown - draws a table the allows the user to select an action to perform
 * on one or more data elements
 *
 * @param array $actions_array - an array that contains a list of possible actions. this array should
 *   be compatible with the form_dropdown() function
 * @param int $delete_action - if there is a delete action that should suppress removal of rows
 *   specify it here.  If you don't want any delete actions, set to 0.
 *
 * @return void
 */
function draw_actions_dropdown($actions_array, $delete_action = 1) {
	global $config;

	if ($actions_array === null || cacti_sizeof($actions_array) == 0) {
		return;
	}

	if (!isset($actions_array[0])) {
		$my_actions[0]  = __('Choose an action');
		$my_actions    += $actions_array;
		$actions_array  = $my_actions;
	}

	?>
	<div class='actionsDropdown'>
		<div>
			<span class='actionsDropdownArrow'><img src='<?php print get_theme_paths('%s', 'images/arrow.gif') ?>' alt=''></span>
			<?php form_dropdown('drp_action', $actions_array, '', '', '0', '', '');?>
			<span class='actionsDropdownButton'><input type='submit' class='ui-button ui-corner-all ui-widget' id='submit' value='<?php print __esc('Go');?>' title='<?php print __esc('Execute Action');?>'></span>
		</div>
	</div>
	<input type='hidden' id='action' name='action' value='actions'>
	<script type='text/javascript'>

	function setDisabled() {
		$('tr[id^="line"]').addClass('selectable').prop('disabled', false).removeClass('disabled_row').unbind('click').prop('disabled', false);

		if ($('#drp_action').val() == '<?php print $delete_action;?>') {
			$(':checkbox.disabled').each(function(data) {
				$(this).closest('tr').addClass('disabled_row');
				if ($(this).is(':checked')) {
					$(this).prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
					$(this).closest('tr').removeClass('selected');
				}
				$(this).prop('disabled', true).closest('tr').removeClass('selected');
			});

			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('enable');
				} else {
					$(this).prop('disabled', false);
				}
			});
		} else if ($('#drp_action').val() == 0) {
			$(':checkbox.disabled').each(function(data) {
				$(this).prop('disabled', false);
			});

			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('disable');
				} else {
					$(this).prop('disabled', true);
				}
			});
		} else if ('<?php print $delete_action;?>' != 0) {
			$('#submit').each(function() {
				if ($(this).button === 'function') {
					$(this).button('enable');
				} else {
					$(this).prop('disabled', false);
				}
			});
		}

		$('tr[id^="line"]').filter(':not(.disabled_row)').off('click').on('click', function(event) {
			selectUpdateRow(event, $(this));
		});
	}

	$(function() {
		setDisabled();

		$('#drp_action').change(function() {
			setDisabled();
		});
	});
	</script>
	<?php
}

/*
 * Deprecated functions
 */
/**
 * Draws a matrix header item in an HTML table.
 *
 * @param string $matrix_name The name to be displayed in the matrix header.
 * @param string $matrix_text_color The color of the text in the matrix header.
 * @param int $column_span The number of columns the header item should span. Default is 1.
 *
 * @return void
 */
function DrawMatrixHeaderItem($matrix_name, $matrix_text_color, $column_span = 1) {
	?>
	<th style='height:1px;' colspan='<?php print $column_span;?>'>
		<div class='textSubHeaderDark'><?php print $matrix_name;?></div>
	</th>
	<?php
}

/**
 * Generates an HTML table row with a single cell containing the provided text.
 *
 * This function creates a table row (`<tr>`) with a single table data cell (`<td>`)
 * that contains the provided text. The text is escaped using the `html_escape` function
 * to prevent XSS attacks.
 *
 * @param string $text The text to be displayed inside the table cell.
 *
 * @return void
 */
function form_area($text) {
	?>
	<tr>
		<td class='textArea'>
			<?php print html_escape($text);?>
		</td>
	</tr>
	<?php
}

/**
 * is_console_page - determines if current passed url is considered to be a console page
 *
 * @param string url - url to be checked
 *
 * @return bool true if console page, false if not
 */
function is_console_page($url) {
	global $menu;

	$basename = basename($url);

	if ($basename == 'index.php') {
		return true;
	}

	if ($basename == 'about.php') {
		return true;
	}

	if ($basename == 'rrdcleaner.php') {
		return true;
	}

	if ($basename == 'clog.php') {
		return false;
	}

	if (api_plugin_hook_function('is_console_page', $url) != $url) {
		return true;
	}

	if (cacti_sizeof($menu)) {
		foreach ($menu as $section => $children) {
			if (cacti_sizeof($children)) {
				foreach ($children as $page => $name) {
					if (basename($page) == $basename) {
						return true;
					}
				}
			}
		}
	}

	return false;
}

function html_show_tabs_left() {
	global $config, $tabs_left;

	$realm_allowed     = array();
	$realm_allowed[7]  = is_realm_allowed(7);
	$realm_allowed[8]  = is_realm_allowed(8);
	$realm_allowed[18] = is_realm_allowed(18);
	$realm_allowed[19] = is_realm_allowed(19);
	$realm_allowed[21] = is_realm_allowed(21);
	$realm_allowed[22] = is_realm_allowed(22);

	if ($realm_allowed[8]) {
		$show_console_tab = true;
	} else {
		$show_console_tab = false;
	}

	if ($show_console_tab) {
		$tabs_left[] =
		array(
			'title' => __('Console'),
			'id'	   => 'tab-console',
			'url'   => CACTI_PATH_URL . 'index.php',
		);
	}

	if ($realm_allowed[7]) {
		if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
			// Don't show the graphs tab when offline
		} else {
			$tabs_left[] =
				array(
					'title' => __('Graphs'),
					'id'	   => 'tab-graphs',
					'url'   => CACTI_PATH_URL . 'graph_view.php',
				);
		}
	}

	if ($realm_allowed[21] || $realm_allowed[22]) {
		if ($config['poller_id'] > 1) {
			// Don't show the reports tab on other pollers
		} else {
			$tabs_left[] =
				array(
					'title' => __('Reporting'),
					'id'	   => 'tab-reports',
					'url'   => CACTI_PATH_URL . 'reports.php'
				);
		}
	}

	if ($realm_allowed[18] || $realm_allowed[19]) {
		$tabs_left[] =
			array(
				'title' => __('Logs'),
				'id'	   => 'tab-logs',
				'url'   => CACTI_PATH_URL . ($realm_allowed[18] ? 'clog.php':'clog_user.php'),
			);
	}

	// Get Plugin Text Out of Band
	ob_start();
	api_plugin_hook('top_graph_header_tabs');

	$tab_text = trim(ob_get_clean());
	$tab_text = str_replace('<a', '', $tab_text);
	$tab_text = str_replace('</a>', '|', $tab_text);
	$tab_text = str_replace('<img', '', $tab_text);
	$tab_text = str_replace('<', '', $tab_text);
	$tab_text = str_replace('"', "'", $tab_text);
	$tab_text = str_replace('>', '', $tab_text);
	$elements = explode('|', $tab_text);
	$count    = 0;

	foreach ($elements as $p) {
		$p = trim($p);

		if ($p == '') {
			continue;
		}

		$altpos  = strpos($p, 'alt=');
		$hrefpos = strpos($p, 'href=');
		$idpos   = strpos($p, 'id=');

		if ($altpos !== false) {
			$alt   = substr($p, $altpos + 4);
			$parts = explode("'", $alt);

			if ($parts[0] == '') {
				$alt = $parts[1];
			} else {
				$alt = $parts[0];
			}
		} else {
			$alt = __('Title');
		}

		if ($hrefpos !== false) {
			$href  = substr($p, $hrefpos + 5);
			$parts = explode("'", $href);

			if ($parts[0] == '') {
				$href = $parts[1];
			} else {
				$href = $parts[0];
			}
		} else {
			$href = 'unknown';
		}

		if ($idpos !== false) {
			$id    = substr($p, $idpos + 3);
			$parts = explode("'", $id);

			if ($parts[0] == '') {
				$id = $parts[1];
			} else {
				$id = $parts[0];
			}
		} else {
			$id = 'unknown' . $count;
			$count++;
		}

		$tabs_left[] = array('title' => ucwords($alt), 'id' => 'tab-' . $id, 'url' => $href);
	}

	if ($config['poller_id'] > 1 && $config['connection'] != 'online') {
		// Only show external links when online
	} else {
		$external_links = db_fetch_assoc('SELECT id, title
			FROM external_links
			WHERE style="TAB"
			AND enabled="on"
			ORDER BY sortorder');

		if (cacti_sizeof($external_links)) {
			foreach ($external_links as $tab) {
				if (is_realm_allowed($tab['id'] + 10000)) {
					$tabs_left[] =
						array(
							'title' => $tab['title'],
							'id'    => 'tab-link' . $tab['id'],
							'url'   => CACTI_PATH_URL . 'link.php?id=' . $tab['id']
						);
				}
			}
		}
	}

	$i       = 0;
	$me_base = get_current_page();

	foreach ($tabs_left as $tab) {
		$tab_base = basename($tab['url']);

		if ($tab_base == 'graph_view.php' && ($me_base == 'graph_view.php' || $me_base == 'graph.php')) {
			$tabs_left[$i]['selected'] = true;
		} elseif (isset_request_var('id') && ($tab_base == 'link.php?id=' . get_nfilter_request_var('id')) && $me_base == 'link.php') {
			$tabs_left[$i]['selected'] = true;
		} elseif ($tab_base == 'index.php' && is_console_page($me_base)) {
			$tabs_left[$i]['selected'] = true;
		} elseif ($tab_base == $me_base) {
			$tabs_left[$i]['selected'] = true;
		}

		$i++;
	}

	$i = 0;

	print "<div class='maintabs'><nav><ul role='tablist'>";

	foreach ($tabs_left as $tab) {
		if (isset($tab['id'])) {
			$id = $tab['id'];
		} else {
			$id = 'anchor' . $i;
			$i++;
		}

		print "<li><a id='$id' role='tab' class='lefttab" . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . html_escape($tab['url']) . "'><span class='ti glyph_$id'></span><span class='text_$id'>" . html_escape($tab['title']) . "</span></a><a id='menu-$id' class='maintabs-submenu' href='#'><i class='ti ti-chevron-down'></i></a></li>";
	}

	print "<li class='ellipsis maintabs-submenu-ellipsis'><a id='menu-ellipsis' role='tab' aria-selected='false' class='submenu-ellipsis' href='#'><i class='ti ti-chevron-down'></i></a></li>";

	print '</ul></nav></div>';
}

function html_graph_tabs_right() {
	global $config, $tabs_right;

	$theme = get_selected_theme();

	$tabs_right = array();

	if (is_view_allowed('show_tree')) {
		$tabs_right[] = array(
			'title' => __('Tree View'),
			'image' => get_theme_paths('%s', 'images/tab_tree.gif'),
			'id'    => 'tree',
			'url'   => 'graph_view.php?action=tree',
		);
	}

	if (is_view_allowed('show_list')) {
		$tabs_right[] = array(
			'title' => __('List View'),
			'image' => get_theme_paths('%s', 'images/tab_list.gif'),
			'id'    => 'list',
			'url'   => 'graph_view.php?action=list',
		);
	}

	if (is_view_allowed('show_preview')) {
		$tabs_right[] = array(
			'title' => __('Preview'),
			'image' => get_theme_paths('%s', 'images/tab_preview.gif'),
			'id'    => 'preview',
			'url'   => 'graph_view.php?action=preview',
		);
	}

	$i = 0;

	foreach ($tabs_right as $tab) {
		if ($tab['id'] == 'tree') {
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'tree') {
				$tabs_right[$i]['selected'] = true;
			}
		} elseif ($tab['id'] == 'list') {
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'list') {
				$tabs_right[$i]['selected'] = true;
			}
		} elseif ($tab['id'] == 'preview') {
			if (isset_request_var('action') && get_nfilter_request_var('action') == 'preview') {
				$tabs_right[$i]['selected'] = true;
			}
		} elseif (strstr(get_current_page(false), $tab['url'])) {
			$tabs_right[$i]['selected'] = true;
		}

		$i++;
	}

	print "<div class='tabs' style='float:right;'><nav><ul role='tablist'>";

	foreach ($tabs_right as $tab) {
		switch($tab['id']) {
			case 'tree':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li><a id='treeview' role='tab' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'><img src='" . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li><a role='tab' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}

				break;
			case 'list':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li><a id='listview' role='tab' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'><img src='" . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li><a role='tab' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}

				break;
			case 'preview':
				if (isset($tab['image']) && $tab['image'] != '') {
					print "<li><a role='tab' id='preview' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'><img src='" . $tab['image'] . "' alt='' style='vertical-align:bottom;'></a></li>";
				} else {
					print "<li><a role='tab' title='" . html_escape($tab['title']) . "' class='righttab " . (isset($tab['selected']) ? " selected' aria-selected='true'":"' aria-selected='false'") . " href='" . $tab['url'] . "'>" . $tab['title'] . '</a></li>';
				}

				break;
		}
	}

	print '</ul></nav></div>';
}

function html_transform_graph_template_ids($ids) {
	$return_ids = array();

	if (strpos($ids, ',') !== false) {
		$ids = explode(',', $ids);
	} else {
		$ids = array($ids);
	}

	foreach($ids as $id) {
		if (is_numeric($id)) {
			$return_ids[] = $id;
		} elseif (strpos($id, 'cg_') !== false) {
			$new_id = str_replace('cg_', '', $id);
			$return_ids[] = $new_id;
		} else {
			$id = str_replace('dq_', '', $id);

			$return_ids[] = db_fetch_cell_prepared('SELECT graph_template_id
				FROM snmp_query_graph
				WHERE id = ?',
				array($id));
		}
	}

	return implode(',', $return_ids);
}

function html_make_device_where() {
	$sql_where = '';

	if (isset_request_var('site_id') && get_filter_request_var('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':' (') . 'h.site_id = ' . get_request_var('site_id');
	}

	if (isset_request_var('location') && get_nfilter_request_var('location') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':' (') . 'h.location = ' . db_qstr(get_nfilter_request_var('location'));
	}

	if (isset_request_var('host_template_id') && get_filter_request_var('host_template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':' (') . 'h.location = ' . get_request_var('host_template_id');
	}

	if (isset_request_var('external_id') && get_nfilter_filter_request_var('external_id') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':' (') . 'h.external_id = ' . db_qstr(get_nfilter_request_var('external_id'));
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

	return $sql_where;
}

function html_graph_order_filter_array() {
	$return  = array();

	if (read_config_option('dsstats_enable') == '') {
		$data_sources = array('-1' => __('Enable Data Source Statistics to Sort'));

		$return['graph_source'] = array(
			'method'         => 'drop_array',
			'friendly_name'  => __('Source'),
			'filter'         => FILTER_CALLBACK,
			'filter_options' => array('options' => 'sanitize_search_string'),
			'array'          => $data_sources,
			'value'          => ''
		);
	} else {
		$mode = read_config_option('dsstats_mode'); // 0 - Peak/Average only, 1 - Kitchen Sink
		$peak = read_config_option('dsstats_peak'); // '' - Average CF Only, 'on' - Average and Max CF's

		if (isset_request_var('graph_template_id')) {
			$graph_templates = html_transform_graph_template_ids(get_nfilter_request_var('graph_template_id'));

			if (strpos($graph_templates, ',') !== false || $graph_templates == '' || $graph_templates <= 0) {
				$show_sort    = false;

				$data_sources = array('-1' => __('Select a Single Template'));
			} else {
				$show_sort    = true;

				$data_sources = array_rekey(
					db_fetch_assoc_prepared("SELECT DISTINCT data_source_name AS graph_source
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON dtr.id = gti.task_item_id
						WHERE graph_template_id = ?
						AND local_graph_id = 0
						ORDER BY data_source_name",
						array($graph_templates)),
					'graph_source', 'graph_source'
				);

				if (get_nfilter_request_var('graph_source') == '' ||
					get_nfilter_request_var('graph_source') == '-1' ||
					!in_array(get_nfilter_request_var('graph_source'), $data_sources)) {
					if (cacti_sizeof($data_sources)) {
						set_request_var('graph_source', array_keys($data_sources)[0]);
						set_request_var('graph_order', 'desc');
					}
				}
			}

			$return['graph_source'] = array(
				'method'         => 'drop_array',
				'friendly_name'  => __('Source'),
				'filter'         => FILTER_CALLBACK,
				'filter_options' => array('options' => 'sanitize_search_string'),
				'array'          => $data_sources,
				'value'          => ''
			);

			if ($show_sort) {
				$options = array(
					'asc'  => __('Ascending'),
					'desc' => __('Descending')
				);

				$return['graph_order'] = array(
					'method'         => 'drop_array',
					'friendly_name'  => __('Order'),
					'filter'         => FILTER_CALLBACK,
					'filter_options' => array('options' => 'sanitize_search_string'),
					'default'        => 'desc',
					'array'          => $options,
					'value'          => 'desc'
				);

				if ($peak == 'on') {
					$options = array(
						'0' => __esc('Average'),
						'1' => __esc('Maximum')
					);

					$return['cf'] = array(
						'method'         => 'drop_array',
						'friendly_name'  => __('CF'),
						'filter'         => FILTER_VALIDATE_INT,
						'default'        => '0',
						'array'          => $options,
						'value'          => get_nfilter_request_var('cf')
					);
				}

				if ($mode == 0) {
					$options = array(
						'average' => __esc('Average'),
						'peak'    => __esc('Maximum')
					);

					$return['measure'] = array(
						'method'         => 'drop_array',
						'friendly_name'  => __('Measure'),
						'filter'         => FILTER_CALLBACK,
						'filter_options' => array('options' => 'sanitize_search_string'),
						'default'        => 'average',
						'array'          => $options,
						'value'          => get_nfilter_request_var('measure')
					);
				} else {
					$options = array(
						'average' => __esc('Average'),
						'peak'    => __esc('Maximum'),
						'p25n'    => __esc('25th Percentile'),
						'p50n'    => __esc('50th Percentile (Median)'),
						'p75n'    => __esc('75th Percentile'),
						'p90n'    => __esc('90th Percentile'),
						'p95n'    => __esc('95th Percentile'),
						'sum'     => __esc('Total/Sum/Bandwidth')
					);

					$return['measure'] = array(
						'method'         => 'drop_array',
						'friendly_name'  => __('Measure'),
						'filter'         => FILTER_CALLBACK,
						'filter_options' => array('options' => 'sanitize_search_string'),
						'default'        => 'average',
						'array'          => $options,
						'value'          => get_nfilter_request_var('measure')
					);
				}
			}
		} else {
			$data_sources = array('-1' => __('Select a Single Template'));

			$return['graph_source'] = array(
				'method'         => 'drop_array',
				'friendly_name'  => __('Source'),
				'filter'         => FILTER_CALLBACK,
				'filter_options' => array('options' => 'sanitize_search_string'),
				'array'          => $data_sources,
				'value'          => ''
			);
		}
	}

	return $return;
}

function html_thumbnails_filter($callBack = 'applyGraphFilter') {
	$output  = "<input id='thumbnails' type='checkbox' onClick='$callBack()' " . (get_request_var('thumbnails') == 'true' ? 'checked':'') . ">";
	$output .= "<label for='thumbnails'>" . __('Thumbnails') . "</label>";

	return $output;
}

function html_business_hours_filter($callBack = 'applyGraphFilter') {
	if (read_config_option('business_hours_enable') == 'on') {
		$output  = "<input id='business_hours' type='checkbox' onClick='$callBack()' " . (get_request_var('business_hours') == 'true' ? 'checked':'') . ">";
		$output .= "<label for='business_hours'>" . __('Business Hours') . "</label>";

		return $output;
	}
}

/**
 * Generates an HTML host filter dropdown or input field based on configuration.
 *
 * @param int|string $host_id The ID of the host to be selected by default. Defaults to '-1'.
 * @param string $call_back The JavaScript function to call when the selection changes. Defaults to 'applyFilter'.
 * @param string $sql_where Additional SQL WHERE clause to filter the devices. Defaults to an empty string.
 * @param bool $noany Whether to exclude the 'Any' option from the dropdown. Defaults to false.
 * @param bool $nonone Whether to exclude the 'None' option from the dropdown. Defaults to false.
 *
 * @return void
 */
function html_host_filter($host_id = '-1', $call_back = 'applyFilter', $sql_where = '', $noany = false, $nonone = false) {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	if ($host_id == '-1' && isset_request_var('host_id')) {
		$host_id = get_filter_request_var('host_id');
	}

	if (!read_config_option('autocomplete_enabled')) {
		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<select id='host_id' name='host_id' onChange='<?php print $call_back;?>' data-defaultLabel='<?php print __('Device');?>'>
				<?php if (!$noany) {?><option value='-1'<?php if ($host_id == '-1') {?> selected<?php }?>><?php print __('Any');?></option><?php }?>
				<?php if (!$nonone) {?><option value='0'<?php if ($host_id == '0') {?> selected<?php }?>><?php print __('None');?></option><?php }?>
				<?php

				$devices = get_allowed_devices($sql_where);

				if (cacti_sizeof($devices)) {
					foreach ($devices as $device) {
						print "<option value='{$device['id']}'" . ($host_id == $device['id'] ? ' selected':'') . '>' . html_escape(strip_domain($device['description'])) . '</option>';
					}
				}
				?>
			</select>
		</td>
		<?php
	} else {
		if ($host_id > 0) {
			$hostname = db_fetch_cell_prepared('SELECT description
				FROM host
				WHERE id = ?',
				array($host_id));
		} elseif ($host_id == 0) {
			$hostname = __('None');
		} else {
			$hostname = __('Any');
		}

		?>
		<td>
			<?php print __('Device');?>
		</td>
		<td>
			<?php print "<input id='host_id' name='host_id' type='text' class='drop-callback ui-state-default ui-corner-all' data-action='ajax_hosts' data-callback='$call_back' data-callback-id='host_id' data-value='" . html_escape($hostname) . "' value='" . html_escape($host_id) . "'>";?>
		</td>
	<?php
	}
}

/**
 * Generates an HTML dropdown filter for selecting a site.
 *
 * @param int|string $site_id The ID of the site to be selected by default. Defaults to '-1'.
 * @param string $call_back The JavaScript function to call when the selection changes. Defaults to 'applyFilter'.
 * @param string $sql_where Additional SQL WHERE clause to filter the sites. Defaults to an empty string.
 * @param bool $noany Whether to exclude the 'Any' option from the dropdown. Defaults to false.
 * @param bool $nonone Whether to exclude the 'None' option from the dropdown. Defaults to false.
 *
 * @return void
 */
function html_site_filter($site_id = '-1', $call_back = 'applyFilter', $sql_where = '', $noany = false, $nonone = false) {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	if ($site_id == '-1' && isset_request_var('site_id')) {
		$site_id = get_filter_request_var('site_id');
	}

	?>
	<td>
		<?php print __('Site');?>
	</td>
	<td>
		<select id='site_id' onChange='<?php print $call_back;?>' data-defaultLabel='<?php print __('Site');?>'>
			<?php if (!$noany) {?><option value='-1'<?php if ($site_id == '-1') {?> selected<?php }?>><?php print __('Any');?></option><?php }?>
			<?php if (!$nonone) {?><option value='0'<?php if ($site_id == '0') {?> selected<?php }?>><?php print __('None');?></option><?php }?>
			<?php

			$sites = get_allowed_sites($sql_where);

			if (cacti_sizeof($sites)) {
				foreach ($sites as $site) {
					print "<option value='" . $site['id'] . "'" . ($site_id == $site['id'] ? ' selected':'') . '>' . html_escape($site['name']) . '</option>';
				}
			}
			?>
		</select>
	</td>
	<?php
}

/**
 * Generates an HTML dropdown filter for selecting a location.
 *
 * @param string $location The currently selected location value. Default is an empty string.
 * @param string $call_back The JavaScript function to call when the selection changes. Default is 'applyFilter'.
 * @param string $sql_where Additional SQL WHERE clause to filter the locations. Default is an empty string.
 * @param bool $noany If true, the "Any" option will not be included in the dropdown. Default is false.
 * @param bool $nonone If true, the "None" option will not be included in the dropdown. Default is false.
 *
 * @return void
 */
function html_location_filter($location = '', $call_back = 'applyFilter', $sql_where = '', $noany = false, $nonone = false) {
	$theme = get_selected_theme();

	if (strpos($call_back, '()') === false) {
		$call_back .= '()';
	}

	?>
	<td>
		<?php print __('Location');?>
	</td>
	<td>
		<select id='location' onChange='<?php print $call_back;?>'>
			<?php if (!$noany) {?><option value='-1'<?php if ($location == '-1') {?> selected<?php }?>><?php print __('Any');?></option><?php }?>
			<?php if (!$nonone) {?><option value='0'<?php if ($location == '0') {?> selected<?php }?>><?php print __('None');?></option><?php }?>
			<?php

			if ($sql_where != '' && strpos($sql_where, 'WHERE') === false) {
				$sql_where = 'WHERE ' . $sql_where;
			}

			$locations = array_rekey(
				db_fetch_assoc("SELECT DISTINCT location
					FROM host AS h
					$sql_where
					ORDER BY location ASC"),
				'location', 'location'
			);

			if (cacti_sizeof($locations)) {
				foreach ($locations as $l) {
					if ($l == '') {
						continue;
					}

					print "<option value='" . html_escape($l) . "'" . ($location == $l ? ' selected':'') . '>' . html_escape($l) . '</option>';
				}
			}
			?>
		</select>
	</td>
	<?php
}

/**
 * Generates the HTML for spike kill actions.
 *
 * This function is responsible for creating the HTML elements
 * necessary for spike kill actions within the application.
 *
 * @return void
 */
function html_spikekill_actions() {
	switch(get_nfilter_request_var('action')) {
		case 'spikemenu':
			html_spikekill_menu(get_filter_request_var('local_graph_id'));

			break;
		case 'spikesave':
			switch(get_nfilter_request_var('setting')) {
				case 'ravgnan':
					$id = get_nfilter_request_var('id');

					switch($id) {
						case 'avg':
						case 'last':
						case 'nan':
							set_user_setting('spikekill_avgnan', $id);

							break;
					}

					break;
				case 'rstddev':
					set_user_setting('spikekill_deviations', get_filter_request_var('id'));

					break;
				case 'rvarout':
					set_user_setting('spikekill_outliers', get_filter_request_var('id'));

					break;
				case 'rvarpct':
					set_user_setting('spikekill_percent', get_filter_request_var('id'));

					break;
				case 'rkills':
					set_user_setting('spikekill_number', get_filter_request_var('id'));

					break;
				case 'rabsmax':
					set_user_setting('spikekill_absmax', get_filter_request_var('id'));

					break;
			}

			break;
	}
}

/**
 * Retrieves the spike kill setting for a given name.
 *
 * This function reads a user-specific setting for the provided name. If the user-specific
 * setting is not available, it falls back to the default configuration option.
 *
 * @param string $name The name of the setting to retrieve.
 * @return mixed The value of the spike kill setting.
 */
function html_spikekill_setting($name) {
	return read_user_setting($name, read_config_option($name), true);
}

/**
 * Generates an HTML list item for a spike kill menu.
 *
 * @param string $text The text content of the menu item.
 * @param string $icon (Optional) The icon class for the menu item.
 * @param string $class (Optional) Additional CSS classes for the menu item.
 * @param string $id (Optional) The ID attribute for the menu item.
 * @param string $data_graph (Optional) The data-graph attribute for the menu item.
 * @param string $subitem (Optional) Submenu items in HTML format.
 *
 * @return string The generated HTML for the menu item.
 */
function html_spikekill_menu_item($text, $icon = '', $class = '', $id = '', $data_graph = '', $subitem = '') {
	$output = '<li ';

	if (!empty($id)) {
		$output .= "id='$id' ";
	}

	if (!empty($data_graph)) {
		$output .= "data-graph='$data_graph' ";
	}

	$output .= 'class=\'' . (empty($class)?'': " $class") . '\'>';
	$output .= '<span class=\'spikeKillMenuItem\'>';

	if (!empty($icon)) {
		$output .= "<i class='$icon'></i>";
	}

	$output .= "$text</span>";

	if (!empty($subitem)) {
		$output .= "<ul>$subitem</ul>";
	}

	$output .= '</li>';

	return $output;
}

/**
 * Generates the HTML for the SpikeKill menu.
 *
 * This function creates a menu for configuring the SpikeKill settings in Cacti.
 * It includes options for various replacement methods, standard deviations, variance percentages,
 * variance outliers, kills per RRA, and absolute maximum values.
 *
 * @param int $local_graph_id The ID of the local graph.
 *
 * @global array $settings The global settings array containing SpikeKill configuration options.
 *
 * @return void
 */
function html_spikekill_menu($local_graph_id) {
	global $settings;
	$ravgnan1 = html_spikekill_menu_item(__('Average'), html_spikekill_setting('spikekill_avgnan') == 'avg' ? 'ti ti-check':'fa', 'skmethod', 'method_avg');
	$ravgnan2 = html_spikekill_menu_item(__('NaN\'s'), html_spikekill_setting('spikekill_avgnan') == 'nan' ? 'ti ti-check':'fa', 'skmethod', 'method_nan');
	$ravgnan3 = html_spikekill_menu_item(__('Last Known Good'), html_spikekill_setting('spikekill_avgnan') == 'last' ? 'ti ti-check':'fa', 'skmethod', 'method_last');

	$ravgnan = html_spikekill_menu_item(__('Replacement Method'), '', '', '', '', $ravgnan1 . $ravgnan2 . $ravgnan3);

	$rstddev = '';

	foreach ($settings['spikes']['spikekill_deviations']['array'] as $key => $value) {
		$rstddev .= html_spikekill_menu_item($value, html_spikekill_setting('spikekill_deviations') == $key ? 'ti ti-check':'fa', 'skstddev', 'stddev_' . $key);
	}
	$rstddev  = html_spikekill_menu_item(__('Standard Deviations'), '', '', '', '', $rstddev);

	$rvarpct = '';

	foreach ($settings['spikes']['spikekill_percent']['array'] as $key => $value) {
		$rvarpct .= html_spikekill_menu_item($value, html_spikekill_setting('spikekill_percent') == $key ? 'ti ti-check':'fa', 'skvarpct', 'varpct_' . $key);
	}
	$rvarpct = html_spikekill_menu_item(__('Variance Percentage'), '', '', '', '', $rvarpct);

	$rvarout  = '';

	foreach ($settings['spikes']['spikekill_outliers']['array'] as $key => $value) {
		$rvarout .= html_spikekill_menu_item($value, html_spikekill_setting('spikekill_outliers') == $key ? 'ti ti-check':'fa', 'skvarout', 'varout_' . $key);
	}
	$rvarout  = html_spikekill_menu_item(__('Variance Outliers'), '', '', '', '', $rvarout);

	$rkills  = '';

	foreach ($settings['spikes']['spikekill_number']['array'] as $key => $value) {
		$rkills .= html_spikekill_menu_item($value,html_spikekill_setting('spikekill_number') == $key ? 'ti ti-check':'fa', 'skills', 'kills_' . $key);
	}
	$rkills  = html_spikekill_menu_item(__('Kills Per RRA'), '', '', '', '', $rkills);

	$rabsmax  = '';

	foreach ($settings['spikes']['spikekill_absmax']['array'] as $key => $value) {
		$rabsmax .= html_spikekill_menu_item($value, html_spikekill_setting('spikekill_absmax') == $key ? 'ti ti-check':'fa', 'skabsmax', 'absmax_' . $key);
	}
	$rabsmax = html_spikekill_menu_item(__('Absolute Max Value'), '', '', '', '', $rabsmax);

	?>
	<div class='spikekillParent' style='display:none;z-index:20;position:absolute;text-align:left;white-space:nowrap;padding-right:2px;'>
	<ul class='spikekillMenu' style='font-size:1em;'>
	<?php
	print html_spikekill_menu_item(__('Remove StdDev'), 'deviceUp ti ti-lifebuoy-filled', 'rstddev', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Remove Variance'), 'deviceRecovering ti ti-lifebuoy-filled', 'rvariance', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Gap Fill Range'), 'deviceUnknown ti ti-lifebuoy-filled', 'routlier', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Float Range'), 'deviceDown ti ti-lifebuoy-filled', 'rrangefill', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Absolute Maximum'), 'deviceError ti ti-lifebuoy-filled', 'rabsolute', '',  $local_graph_id);

	print html_spikekill_menu_item(__('Dry Run StdDev'), 'deviceUp ti ti-check', 'dstddev', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Variance'), 'deviceRecovering ti ti-check', 'dvariance', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Gap Fill Range'), 'deviceUnknown ti ti-check', 'doutlier', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Float Range'), 'deviceDown ti ti-check', 'drangefill', '',  $local_graph_id);
	print html_spikekill_menu_item(__('Dry Run Absolute Maximum'), 'deviceError ti ti-check', 'dabsolute', '',  $local_graph_id);

	print html_spikekill_menu_item(__('Settings'), 'ti ti-settings-filled', '', '', '', $ravgnan . $rstddev . $rvarpct . $rvarout . $rkills . $rabsmax);
}

function html_spikekill_js() {
	?>
	<script type='text/javascript'>
	var spikeKillOpen = false;

	$(function() {
		$(document).click(function() {
			spikeKillClose();
		});

		$('a.spikekill').children().contextmenu(function() {
			return false;
		});

		$('a.spikekill').unbind().click(function() {
			if (spikeKillOpen == false) {
				local_graph_id = $(this).attr('data-graph');

				$.get('?action=spikemenu&local_graph_id='+local_graph_id)
					.done(function(data) {
						$('#sk'+local_graph_id).after(data);

						menuAnchor = $('#sk'+local_graph_id).offset().left;
						pageWidth  = $(document).width();

						if (pageWidth - menuAnchor < 180) {
							$('.spikekillMenu').css({ position: 'absolute', top: 0, left: -180 });
						}

						$('.spikekillMenu').menu({
							select: function(event, ui) {
								$(this).menu('focus', event, ui.item);
							},
							delay: 1000
						});

						$('.spikekillParent').show();

						spikeKillActions();

						spikeKillOpen = true;
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});

			} else {
				spikeKillClose();
			}
		});
	});

	function spikeKillClose() {
		if (spikeKillOpen) {
			$(document).find('.spikekillMenu').menu('destroy').parent().remove();
			spikeKillOpen = false;
		}
	}

	function spikeKillActions() {
		$('.rstddev').unbind().click(function() {
			removeSpikesStdDev($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.dstddev').unbind().click(function() {
			dryRunStdDev($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.rvariance').unbind().click(function() {
			removeSpikesVariance($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.dvariance').unbind().click(function() {
			dryRunVariance($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.routlier').unbind().click(function() {
			removeSpikesInRange($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.doutlier').unbind().click(function() {
			dryRunSpikesInRange($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.rrangefill').unbind().click(function() {
			removeRangeFill($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.drangefill').unbind().click(function() {
			dryRunRangeFill($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.rabsolute').unbind().click(function() {
			removeSpikesAbsolute($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.dabsolute').unbind().click(function() {
			dryRunAbsolute($(this).attr('data-graph'));
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();
		});

		$('.skmethod').unbind().click(function() {
			$('.skmethod').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=ravgnan&id='+$(this).attr('id').replace('method_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.skills').unbind().click(function() {
			$('.skills').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rkills&id='+$(this).attr('id').replace('kills_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.skstddev').unbind().click(function() {
			$('.skstddev').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rstddev&id='+$(this).attr('id').replace('stddev_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.skvarpct').unbind().click(function() {
			$('.skvarpct').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rvarpct&id='+$(this).attr('id').replace('varpct_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.skvarout').unbind().click(function() {
			$('.skvarout').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rvarout&id='+$(this).attr('id').replace('varout_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.skabsmax').unbind().click(function() {
			$('.skabsmax').find('i').removeClass('ti ti-check');
			$(this).find('i:first').addClass('ti ti-check');
			$(this).find('.spikekillMenu').menu('destroy').parent().remove();

			strURL = '?action=spikesave&setting=rabsmax&id='+$(this).attr('id').replace('absmax_','');
			$.get(strURL)
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		});
	}
	</script>
	<?php
}

/**
 * html_common_header - prints a common set of header, css and javascript links
 *
 * @param string title - the title of the page to place in the browser
 * @param string selectedTheme - optionally sets a specific theme over the current one
 *
 * @return void
 */
function html_common_header($title, $selectedTheme = '') {
	global $path2calendar, $path2timepicker, $path2colorpicker, $path2ms, $path2msfilter;

	if ($selectedTheme == '') {
		$selectedTheme = get_selected_theme();
	}

	print "<meta content='width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5' name='viewport'>" . PHP_EOL;

	$script_policy = read_config_option('content_security_policy_script');

	if ($script_policy == 'unsafe-eval') {
		$script_policy = "'$script_policy'";
	} else {
		$script_policy = '';
	}
	$alternates = html_escape(read_config_option('content_security_alternate_sources'));

	?>
	<meta http-equiv='X-UA-Compatible' content='IE=Edge,chrome=1'>
	<meta name='apple-mobile-web-app-capable' content='yes'>
	<meta name='description' content='Monitoringauth tool of the Internet'>
	<meta name='mobile-web-app-capable' content='yes'>
	<meta name="theme-color" content="#161616"/>
	<meta http-equiv="Content-Security-Policy" content="default-src *; img-src 'self' https://api.qrserver.com <?php print $alternates;?> data: blob:; style-src 'self' 'unsafe-inline' <?php print $alternates;?>; script-src 'self' <?php print html_escape($script_policy);?> 'unsafe-inline' <?php print $alternates;?>; worker-src 'self' <?php print $alternates;?>;">
	<meta name='robots' content='noindex,nofollow'>
	<title><?php print $title; ?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<script type='text/javascript'>
		var urlPath='<?php print CACTI_PATH_URL;?>';
		var aboutCacti = '<?php print __esc('About Cacti');?>';
		var cactiCharts = '<?php print __esc('Charts');?>';
		var cactiClient = '<?php print __esc('Client');?>';
		var cactiCommunityForum = '<?php print __esc('User Community');?>';
		var cactiConsole = '<?php print __esc('Console');?>';
		var cactiConsoleAllowed=<?php print(is_realm_allowed(8) ? 'true':'false');?>;
		var cactiContributeTo = '<?php print __esc('Contribute to the Cacti Project');?>';
		var cactiDashboards = '<?php print __esc('Panels');?>';
		var cactiDevHelp = '<?php print __esc('Help in Developing');?>';
		var cactiDocumentation = '<?php print __esc('Documentation');?>';
		var cactiDonate = '<?php print __esc('Donation & Sponsoring');?>';
		var cactiGeneral = '<?php print __esc('General');?>';
		var cactiGraphsAllowed=<?php print(is_realm_allowed(7) ? 'true':'false');?>;
		var cactiHome = '<?php print __esc('Cacti Home');?>';
		var cactiKeyboard = '<?php print __esc('Keyboard');?>';
		var cactiMisc = '<?php print __esc('Miscellaneous');?>';
		var cactiProfile = '<?php print __esc('Profile');?>';
		var cactiProjectPage = '<?php print __esc('Cacti Project Page');?>';
		var cactiRRDProxy = '<?php print __esc('RRDProxy');?>';
		var cactiShortcuts = '<?php print __esc('Shortcuts');?>';
		var cactiSpine = '<?php print __esc('Spine');?>';
		var cactiTheme = '<?php print __esc('Theme');?>';
		var cactiTranslate = '<?php print __esc('Help in Translating');?>';
		var cactiUser = '<?php print __esc('User');?>';
		var changePassword = '<?php print __esc('Change Password');?>';
		var clearFilterTitle = '<?php print __esc('Clear Current Filter');?>';
		var clipboard = '<?php print __esc('Clipboard');?>';
		var clipboardCopyFailed = '<?php print __esc('Failed to find data to copy!');?>';
		var clipboardID = '<?php print __esc('Clipboard ID');?>';
		var clipboardNotAvailable = '<?php print __esc('Copy operation is unavailable at this time');?>';
		var clipboardNotUpdated = '<?php print __esc('Sorry, your clipboard could not be updated at this time');?>';
		var clipboardUpdated = '<?php print __esc('Clipboard has been updated');?>';
		var compactGraphicalUserInterface = '<?php print __esc('Compact Mode');?>';
		var keyup_delay = <?php print get_keyup_delay(); ?>;
		var darkColorMode = '<?php print __esc('Dark Color Mode');?>';
		var defaultSNMPAuthProtocol = '<?php print read_config_option('snmp_auth_protocol');?>';
		var defaultSNMPPrivProtocol = '<?php print read_config_option('snmp_priv_protocol');?>';
		var defaultSNMPSecurityLevel = '<?php print read_config_option('snmp_security_level');?>';
		var editProfile = '<?php print __esc('Edit Profile');?>';
		var errorNumberPrefix = '<?php print __esc('Error:');?>';
		var errorOnPage = '<?php print __esc('Sorry, we could not process your last action.');?>';
		var errorReasonPrefix = '<?php print __esc('Reason:');?>';
		var errorReasonTitle = '<?php print __esc('Action failed');?>';
		var errorReasonUnexpected = '<?php print __esc('The response to the last action was unexpected.');?>';
		var filterSettingsSaved = '<?php print __esc('Filter Settings Saved');?>';
		var hScroll=<?php print read_user_setting('enable_hscroll', '') == 'on' ? 'true':'false';?>;
		var help = '<?php print __esc('Help');?>';
		var ignorePreferredColorTheme = '<?php print __esc('Ignore System Color');?>';
		var justCacti = '<?php print __esc('Cacti');?>';
		var lightColorMode = '<?php print __esc('Light Color Mode');?>';
		var listView = '<?php print __esc('List View');?>';
		var logout = '<?php print __esc('Logout');?>';
		var mixedOnPage = '<?php print __esc('Note, we could not process all your actions.  Details are below.');?>';
		var mixedReasonTitle = '<?php print __esc('Some Actions failed');?>';
		var noFileSelected = '<?php print __esc('No file selected');?>';
		var passwordMinChars = <?php print read_config_option('secpass_minlen');?>;
		var passwordMatch = '<?php print __esc('Passphrases match');?>';
		var passwordMatchTooShort = '<?php print __esc('Passphrase matches but too short');?>';
		var passwordNotMatch = '<?php print __esc('Passphrases do not match');?>';
		var passwordNotMatchTooShort = '<?php print __esc('Passphrase too short and not matching');?>';
		var passwordPass = '<?php print __esc('Passphrase length meets 8 character minimum');?>';
		var passwordTooShort = '<?php print __esc('Passphrase too short');?>';
		var passwordValid = '<?php print __esc('Password Validation Passes');?>';
		var previewView = '<?php print __esc('Preview View');?>';
		var realtimeClickOff = '<?php print __esc('Click again to take this Graph out of Realtime');?>';
		var realtimeClickOn = '<?php print __esc('Click to view just this Graph in Realtime');?>';
		var reportABug = '<?php print __esc('Report a bug');?>';
		var searchFilter = '<?php print __esc('Enter a search term');?>';
		var searchRFilter = '<?php print __esc('Enter a regular expression');?>';
		var searchSelect = '<?php print __esc('Select to Search');?>';
		var searchPlaceholder = '<?php print __esc('Search');?>';
		var searchEnterKeyword = '<?php print __esc('Enter keyword');?>';
		var sessionMessageCancel = '<?php print __esc('Cancel');?>';
		var sessionMessageContinue = '<?php print __esc('Continue');?>';
		var sessionMessageOk = '<?php print __esc('Ok');?>';
		var sessionMessagePause = '<?php print __esc('Pause');?>';
		var sessionMessageSave = '<?php print __esc('The Operation was successful.  Details are below.');?>';
		var sessionMessageTitle = '<?php print __esc('Operation successful');?>';
		var showHideFilter = '<?php print __esc('Click to Show/Hide Filter');?>';
		var spikeKillResults = '<?php print __esc('SpikeKill Results');?>';
		var standardGraphicalUserInterface = '<?php print __esc('Standard Mode');?>';
		var tableConstraints = '<?php print __esc('Allow or limit the table columns to extend beyond the current windows limits.');?>';
		var testFailed = '<?php print __esc('Connection Failed');?>';
		var testSuccessful = '<?php print __esc('Connection Successful');?>';
		var theme = '<?php print $selectedTheme;?>';
		var timeGraphView = '<?php print __esc('Time Graph View');?>';
		var treeView = '<?php print __esc('Tree View');?>';
		var usePreferredColorTheme = '<?php print __esc('Use System Color');?>';
		var userSettings=<?php print is_view_allowed('graph_settings') ? 'true':'false';?>;
		var utilityView = '<?php print __esc('Utility View');?>';
		var allSelectedText = '<?php print __('All Graph Templates');?>';
		var templatesSelected = '<?php print __esc('Templates Selected');;?>';
		var notTemplated = '<?php print __esc('Not Templated');?>';
		var allText = '<?php __esc('All');?>';
		var noneText = '<?php print __esc('None');?>';
		var zoom_i18n_3rd_button = '<?php print __esc('3rd Mouse Button');?>';
		var zoom_i18n_advanced = '<?php print __esc('Advanced');?>';
		var zoom_i18n_auto = '<?php print __esc('Auto');?>';
		var zoom_i18n_begin = '<?php print __esc('Begin with');?>';
		var zoom_i18n_center = '<?php print __esc('Center');?>';
		var zoom_i18n_close = '<?php print __esc('Close');?>';
		var zoom_i18n_copy_graph = '<?php print __esc('Copy graph');?>';
		var zoom_i18n_copy_graph_link = '<?php print __esc('Copy graph link');?>';
		var zoom_i18n_disabled = '<?php print __esc('Disabled');?>';
		var zoom_i18n_end = '<?php print __esc('End with');?>';
		var zoom_i18n_graph = '<?php print __esc('Graph');?>';
		var zoom_i18n_mode = '<?php print __esc('Zoom Mode');?>';
		var zoom_i18n_newTab = '<?php print __esc('Open in new tab');?>';
		var zoom_i18n_off = '<?php print __esc('Always Off');?>';
		var zoom_i18n_on = '<?php print __esc('Always On');?>';
		var zoom_i18n_quick = '<?php print __esc('Quick');?>';
		var zoom_i18n_save_graph = '<?php print __esc('Save graph');?>';
		var zoom_i18n_settings = '<?php print __esc('Settings');?>';
		var zoom_i18n_timestamps = '<?php print __esc('Timestamps');?>';
		var zoom_i18n_zoom_2 = '<?php print __esc('2x');?>';
		var zoom_i18n_zoom_4 = '<?php print __esc('4x');?>';
		var zoom_i18n_zoom_8 = '<?php print __esc('8x');?>';
		var zoom_i18n_zoom_16 = '<?php print __esc('16x');?>';
		var zoom_i18n_zoom_32 = '<?php print __esc('32x');?>';
		var zoom_i18n_zoom_in = '<?php print __esc('Zoom In');?>';
		var zoom_i18n_zoom_out = '<?php print __esc('Zoom Out');?>';
		var zoom_i18n_zoom_out_factor = '<?php print __esc('Zoom Out Factor');?>';
		var zoom_i18n_zoom_out_positioning = '<?php print __esc('Zoom Out Positioning');?>';
		var zoom_outOfRangeTitle='<?php print __esc('Zoom Window Out of Range');?>';
		var zoom_outOfRangeMessage='<?php print __esc('Zoom Dates before January 1, 1993 are not supported in Cacti!  Pick a more recent date.');?>';
	</script>
	<?php
	/* Global icons */
	print get_md5_include_icon('images', theme: $selectedTheme, file: 'favicon.ico', rel: 'shortcut icon');
	print get_md5_include_icon('images', theme: $selectedTheme, file: 'cacti_logo.gif', rel: 'icon', sizes: '96x96');

	/* Theme-based styles */
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.zoom.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery-ui.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'default/style.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.multiselect.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.multiselect.filter.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.timepicker.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.colorpicker.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'pace.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'Diff.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'jquery.toast.css');
	print get_md5_include_css('include/css/', theme: $selectedTheme, file: 'main.css');

	/* Global styles */
	print get_md5_include_css('include/css/billboard.css');
	print get_md5_include_css('include/fa/css/all.css');
    print get_md5_include_css('include/tabler/webfont/tabler-icons.css');
	print get_md5_include_css('include/vendor/flag-icons/css/flag-icons.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/main.css');

	/* Global scripts */
	print get_md5_include_js('include/js/screenfull.js', true);
	print get_md5_include_js('include/js/jquery.js');
	print get_md5_include_js('include/js/jquery-ui.js');
	print get_md5_include_js('include/js/jquery.ui.touch.punch.js', true);
	print get_md5_include_js('include/js/jquery.cookie.js');
	print get_md5_include_js('include/js/js.storage.js');
	print get_md5_include_js('include/js/jstree.js');
	print get_md5_include_js('include/js/jquery.toast.js');
	print get_md5_include_js('include/js/jquery.hotkeys.js', true);
	print get_md5_include_js('include/js/jquery.tablednd.js');
	print get_md5_include_js('include/js/jquery.zoom.js', true);
	print get_md5_include_js('include/js/jquery.multiselect.js');
	print get_md5_include_js('include/js/jquery.multiselect.filter.js');
	print get_md5_include_js('include/js/jquery.timepicker.js');
	print get_md5_include_js('include/js/jquery.colorpicker.js', true);
	print get_md5_include_js('include/js/jquery.tablesorter.js');
	print get_md5_include_js('include/js/jquery.tablesorter.widgets.js', true);
	print get_md5_include_js('include/js/jquery.tablesorter.pager.js', true);
	print get_md5_include_js('include/js/jquery.validate/jquery.validate.js', true);
	print get_md5_include_js('include/js/Chart.js', true);
	print get_md5_include_js('include/js/d3.js');
	print get_md5_include_js('include/js/billboard.js');
	print get_md5_include_js('include/layout.js');
	print get_md5_include_js('include/js/pace.js');
	print get_md5_include_js('include/js/purify.js');
	print get_md5_include_js('include/realtime.js');
	print get_md5_include_js('include/js/ui-notices.js');
    print get_md5_include_js('include/js/lzjs.js');
    print get_md5_include_js('include/js/big.js');

	/* Main theme based scripts (included last to allow overrides) */
	print get_md5_include_js('include/css/', theme: $selectedTheme, file: 'main.js');

	/* Language based scripts */
	if (isset($path2calendar) && file_exists($path2calendar)) {
		print get_md5_include_js($path2calendar);
	}

	if (isset($path2timepicker) && file_exists($path2timepicker)) {
		print get_md5_include_js($path2timepicker);
	}

	if (isset($path2colorpicker) && file_exists($path2colorpicker)) {
		print get_md5_include_js($path2colorpicker);
	}

	if (isset($path2ms) && file_exists($path2ms)) {
		print get_md5_include_js($path2ms);
	}

	if (isset($path2msfilter) && file_exists($path2msfilter)) {
		print get_md5_include_js($path2msfilter);
	}

	if (file_exists('include/css/custom.css')) {
		print get_md5_include_css('include/css/custom.css');
	}

	api_plugin_hook('page_head');
}

/**
 * Generates the URL for the help page corresponding to the given page.
 *
 * This function maps a given page to its corresponding help documentation URL.
 * It uses a predefined array of page-to-help mappings and allows for plugin
 * hooks to modify or extend these mappings.
 *
 * @param string $page The page for which the help URL is to be generated.
 *
 * @return string|false The URL to the help documentation if the page is found, false otherwise.
 */
function html_help_page($page) {
	global $config, $help;

	$help = array(
		'aggregates.php'              => 'Aggregates.html',
		'aggregate_templates.php'     => 'Aggregate-Templates.html',
		'automation_networks.php'     => 'Automation-Networks.html',
		'cdef.php'                    => 'CDEFs.html',
		'color_templates.php'         => 'Color-Templates.html',
		'color.php'                   => 'Colors.html',
		'pollers.php'                 => 'Data-Collectors.html',
		'data_debug.php'              => 'Data-Debug.html',
		'data_input.php'              => 'Data-Input-Methods.html',
		'data_source_profiles.php'    => 'Data-Profiles.html',
		'data_queries.php'            => 'Data-Queries.html',
		'data_templates.php'          => 'Data-Source-Templates.html',
		'data_sources.php'            => 'Data-Sources.html',
		'host.php'                    => 'Devices.html',
		'automation_templates.php'    => 'Device-Rules.html',
		'host_templates.php'          => 'Device-Templates.html',
		'automation_devices.php'      => 'Discovered-Devices.html',
		'templates_export.php'        => 'Export-Template.html',
		'links.php'                   => 'External-Links.html',
		'gprint_presets.php'          => 'GPRINTs.html',
		'graphs_new.php'              => 'Graph-a-Single-SNMP-OID.html',
		'graph_view.php'              => 'Graph-Overview.html',
		'automation_graph_rules.php'  => 'Graph-Rules.html',
		'graph_templates.php'         => 'Graph-Templates.html',
		'graphs.php'                  => 'Graphs.html',
		'templates_import.php'        => 'Import-Template.html',
		'plugins.php'                 => 'Plugins.html',
		'automation_snmp.php'         => 'SNMP-Options.html',
		'settings.php:authentication' => 'Settings-Auth.html',
		'settings.php:data'           => 'Settings-Data.html',
		'settings.php:snmp'           => 'Settings-Device-Defaults.html',
		'settings.php:general'        => 'Settings-General.html',
		'settings.php:mail'           => 'Settings-Mail-Reporting-DNS.html',
		'settings.php:path'           => 'Settings-Paths.html',
		'settings.php:boost'          => 'Settings-Performance.html',
		'settings.php:poller'         => 'Settings-Poller.html',
		'settings.php:spikes'         => 'Settings-Spikes.html',
		'settings.php:visual'         => 'Settings-Visual.html',
		'sites.php'                   => 'Sites.html',
		'automation_tree_rules.php'   => 'Tree-Rules.html',
		'tree.php'                    => 'Trees.html',
		'user_domains.php'            => 'User-Domains.html',
		'user_group_admin.php'        => 'User-Group-Management.html',
		'user_admin.php'              => 'User-Management.html',
		'vdef.php'                    => 'VDEFs.html',
		'reports.php'                 => 'Reports-Admin.html',
		'reports.php:details'         => 'Reports-Admin.html',
		'reports.php:items'           => 'Reports-Items.html',
		'reports.php:preview'         => 'Reports-Preview.html',
		'reports.php:events'          => 'Reports-Events.html',
		'clog.php'                    => 'Cacti-Log.html',
		'clog_user.php'               => 'Cacti-Log.html',
	);

	$help = api_plugin_hook_function('help_page', $help);

	if (isset($help[$page])) {
		return CACTI_PATH_URL . 'docs/' . $help[$page];
	}

	return false;
}

/**
 * Generates the HTML authentication header.
 *
 * This function outputs the HTML structure for the authentication header, including
 * the DOCTYPE declaration, HTML head, and body content. It also integrates plugin hooks
 * for customization.
 *
 * @param string $section The section identifier for the authentication header.
 * @param string $browser_title The title to be displayed in the browser's title bar.
 * @param string $legend The legend text to be displayed in the authentication area.
 * @param string $title The title text to be displayed in the authentication form.
 * @param array $hook_args Optional. Additional arguments to be passed to plugin hooks. Default is an empty array.
 *
 * @return void
 */
function html_auth_header($section, $browser_title, $legend, $title, $hook_args = array()) {
	global $themes;

	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<!-- <?php print "{$section}_title"; ?> -->
	<?php html_common_header(api_plugin_hook_function("{$section}_title", $browser_title));?>
</head>
<body>
<div class='cactiAuthBody'>
	<div class='cactiAuthCenter'>
		<div class='cactiAuthArea'>
			<legend><?php print $legend;?></legend><hr />
			<form id='auth' name='auth' method='post' action='<?php print get_current_page();?>'>
				<input type='hidden' name='action' value='<?php print $section; ?>'>
				<?php api_plugin_hook_function("{$section}_before", $hook_args);	?>
				<div class='cactiAuthTitle'>
					<table class='cactiAuthTable'>
						<tr><td><?php print $title; ?></td></tr>
					</table>
				</div>
				<div class='cactiAuth'>
					<table class='cactiAuthTable'>
					<?php
}

/**
 * Renders the footer section of the authentication HTML page.
 *
 * This function outputs the closing HTML tags for the authentication page,
 * including any error messages, version information, and additional HTML content.
 * It also triggers a plugin hook and includes the global session file.
 *
 * @param string $section The section identifier used for the plugin hook.
 * @param string $error Optional. The error message to display. Default is an empty string.
 * @param string $html Optional. Additional HTML content to include. Default is an empty string.
 *
 * @return void
 */
function html_auth_footer($section, $error = '', $html = '') {
	?>
					</table>
				</div>
				<?php api_plugin_hook("{$section}_after"); ?>
			</form>
			<hr />
			<div class='cactiAuthErrors'>
				<?php print $error; ?>
			</div>
			<div class='versionInfo'>
				<?php print __('Version %s | %s', CACTI_VERSION_BRIEF, COPYRIGHT_YEARS_SHORT);?>
			</div>
		</div>
		<div class='cactiAuthLogo'></div>
	</div>
	<?php
	print $html;
	include_once(__DIR__ . '/../include/global_session.php');
	?>
</div>
</body>
</html>
<?php
}

