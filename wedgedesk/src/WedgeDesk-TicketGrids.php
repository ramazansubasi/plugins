<?php
/**
 * WedgeDesk
 *
 * This file serves as the display code for the general ticket listings.
 *
 * @package wedgedesk
 * @copyright 2011 Peter Spicer, portions SimpleDesk 2010-11 used under BSD licence
 * @license http://wedgedesk.com/index.php?action=license
 *
 * @since 1.0
 * @version 1.0
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 *	Display the main front page, showing tickets waiting for staff, waiting for user feedback and so on.
 *
 *	This function sets up multiple blocks to be shown to users, defines what columns these blocks should have and states
 *	the rules to be used in getting the data.
 *
 *	Each block has multiple parameters, and is stated in $context['ticket_blocks']:
 *	<ul>
 *	<li>block_icon: which image to use in $plugindir/images/ for denoting the type of block; filename plus extension</li>
 *	<li>title: the text string to use as the block's heading</li>
 *	<li>where: an SQL clause denoting the rule for obtaining the items in this block</li>
 *	<li>display: whether the block should be processed and prepared for display</li>
 *	<li>count: the number of items in this block, for pagination; generally should be a call to {@link shd_count_helpdesk_tickets()}</li>
 *	<li>columns: an array of columns to display in this block, in the order they should be displayed, using the following options, derived from {@link shd_get_block_columns()}:
 *		<ul>
 *			<li>ticket_id: the ticket's read status, privacy icon, and id</li>
 *			<li>ticket_name: name/link to the ticket</li>
 *			<li>starting_user: profile link to the user who opened the ticket</li>
 *			<li>replies: number of (visible) replies in the ticket</li>
 *			<li>allreplies: number of (all) replies in the ticket (includes deleted replies, which 'replies' does not)</li>
 *			<li>last_reply: the user who last replied</li>
 *			<li>status: the current ticket's status</li>
 *			<li>assigned: link to the profile of the user the ticket is assigned to, or 'Unassigned' if not assigned</li>
 *			<li>urgency: the current ticket's urgency</li>
 *			<li>updated: time of the last reply in the ticket; states Never if no replies</li>
 *			<li>actions: icons that may or may not relate to a given ticket; buttons for recycle, delete, unresolve live in this column</li>
 *		</ul>
 *	<li>required: whether the block is required to be displayed even if empty</li>
 *	<li>collapsed: whether the block should be compressed to a header with count of tickets or not (mostly for {@link shd_view_block()}'s benefit)</li>
 *	</ul>
 *
 *	This function declares the following blocks:
 *	<ul>
 *	<li>Assigned to me (staff only)</li>
 *	<li>New tickets (staff only)</li>
 *	<li>Pending with staff (for staff, this is just tickets with that status, for regular users this is both pending staff and new unreplied to tickets)</li>
 *	<li>Pending with user (both)</li>
 *	</ul>
 *
 *	@see shd_count_helpdesk_tickets()
 *	@since 1.0
*/
function shd_main_helpdesk()
{
	global $context, $txt;

	$is_staff = shd_allowed_to('shd_staff', 0);
	// Stuff we need to add to $context, page title etc etc
	$context += array(
		'page_title' => $txt['shd_helpdesk'],
		'ticket_blocks' => array( // the numbers tie back to the master status idents
			'assigned' => array(
				'block_icon' => 'assign.png',
				'title' => $txt['shd_status_assigned_heading'],
				'where' => 'hdt.id_member_assigned = ' . MID . ' AND hdt.status NOT IN (' . TICKET_STATUS_CLOSED . ',' . TICKET_STATUS_DELETED . ')',
				'display' => $is_staff,
				'count' => shd_count_helpdesk_tickets('assigned'),
				'columns' => shd_get_block_columns('assigned'),
				'required' => $is_staff,
				'collapsed' => false,
			),
			'new' => array(
				'block_icon' => 'status.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_NEW . '_heading'],
				'where' => 'hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_NEW,
				'display' => $is_staff,
				'count' => shd_count_helpdesk_tickets('new'),
				'columns' => shd_get_block_columns('new'),
				'required' => false,
				'collapsed' => false,
			),
			'staff' => array(
				'block_icon' => 'staff.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_PENDING_STAFF . '_heading'],
				'where' => $is_staff ? ('hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_PENDING_STAFF) : ('hdt.status IN (' . TICKET_STATUS_NEW . ',' . TICKET_STATUS_PENDING_STAFF . ')'), // put new and with staff together in 'waiting for staff' for end user
				'display' => true,
				'count' => shd_count_helpdesk_tickets('staff', $is_staff),
				'columns' => shd_get_block_columns('staff'),
				'required' => true,
				'collapsed' => false,
			),
			'user' => array(
				'block_icon' => 'user.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_PENDING_USER . '_heading'],
				'where' => $is_staff ? ('hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_PENDING_USER) : ('hdt.status = ' . TICKET_STATUS_PENDING_USER),
				'display' => true,
				'count' => shd_count_helpdesk_tickets('with_user'),
				'columns' => shd_get_block_columns($is_staff ? 'user_staff' : 'user_user'),
				'required' => true,
				'collapsed' => false,
			),
		),
		'shd_home_view' => $is_staff ? 'staff' : 'user',
	);
	wetem::load('hdmain');

	if (!empty($context['shd_dept_name']) && $context['shd_multi_dept'])
		$context['linktree'][] = array(
			'url' => '<URL>?' . $context['shd_home'] . $context['shd_dept_link'],
			'name' => $context['shd_dept_name'],
		);

	shd_helpdesk_listing();
}

/**
 *	Sets up viewing of a single block without any pagination.
 *
 *	This provides the ability to see all of a given type of ticket at once without paging through them, which are all sortable.
 *
 *	@see shd_main_helpdesk()
 *	@since 1.0
*/
function shd_view_block()
{
	global $context, $txt;

	$is_staff = shd_allowed_to('shd_staff', 0);
	// Stuff we need to add to $context, page title etc etc
	$context += array(
		'page_title' => $txt['shd_helpdesk'],
		'ticket_blocks' => array( // the numbers tie back to the master status idents
			'assigned' => array(
				'block_icon' => 'assign.png',
				'title' => $txt['shd_status_assigned_heading'],
				'where' => 'hdt.id_member_assigned = ' . MID . ' AND hdt.status NOT IN (' . TICKET_STATUS_CLOSED . ',' . TICKET_STATUS_DELETED . ')',
				'display' => $is_staff,
				'count' => shd_count_helpdesk_tickets('assigned'),
				'columns' => shd_get_block_columns('assigned'),
				'required' => $is_staff,
				'collapsed' => false,
			),
			'new' => array(
				'block_icon' => 'status.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_NEW . '_heading'],
				'where' => 'hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_NEW,
				'display' => $is_staff,
				'count' => shd_count_helpdesk_tickets('new'),
				'columns' => shd_get_block_columns('new'),
				'required' => false,
				'collapsed' => false,
			),
			'staff' => array(
				'block_icon' => 'staff.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_PENDING_STAFF . '_heading'],
				'where' => $is_staff ? ('hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_PENDING_STAFF) : ('hdt.status IN (' . TICKET_STATUS_NEW . ',' . TICKET_STATUS_PENDING_STAFF . ')'), // put new and with staff together in 'waiting for staff' for end user
				'display' => true,
				'count' => shd_count_helpdesk_tickets('staff', $is_staff),
				'columns' => shd_get_block_columns('staff'),
				'required' => true,
				'collapsed' => false,
			),
			'user' => array(
				'block_icon' => 'user.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_PENDING_USER . '_heading'],
				'where' => $is_staff ? ('hdt.id_member_assigned != ' . MID . ' AND hdt.status = ' . TICKET_STATUS_PENDING_USER) : ('hdt.status = ' . TICKET_STATUS_PENDING_USER),
				'display' => true,
				'count' => shd_count_helpdesk_tickets('with_user'),
				'columns' => shd_get_block_columns($is_staff ? 'user_staff' : 'user_user'),
				'required' => true,
				'collapsed' => false,
			),
		),
		'shd_home_view' => $is_staff ? 'staff' : 'user',
	);
	wetem::load('hdmain');

	if (empty($_REQUEST['block']) || empty($context['ticket_blocks'][$_REQUEST['block']]) || empty($context['ticket_blocks'][$_REQUEST['block']]['count']))
		redirectexit($context['shd_home'] . $context['shd_dept_link']);

	$context['items_per_page'] = 10;
	foreach ($context['ticket_blocks'] as $block => $details)
	{
		if ($block == $_REQUEST['block'])
		{
			$context['items_per_page'] = $details['count'];
			$context['ticket_blocks'][$block]['viewing_as_block'] = true;
		}
		else
			$context['ticket_blocks'][$block]['collapsed'] = true;
	}

	if (!empty($context['shd_dept_name']) && $context['shd_multi_dept'])
		$context['linktree'][] = array(
			'url' => '<URL>?' . $context['shd_home'] . $context['shd_dept_link'],
			'name' => $context['shd_dept_name'],
		);

	shd_helpdesk_listing();
}

/**
 *	Set up the paginated lists of closed tickets.
 *
 *	Much like the main helpdesk, this function prepares a list of all the closed/resolved tickets, with a more specific
 *	list of columns that is better suited to resolved tickets.
 *
 *	@see shd_main_helpdesk()
 *	@since 1.0
*/
function shd_closed_tickets()
{
	global $context, $txt;

	if (!shd_allowed_to('shd_view_closed_own', $context['shd_department']) && !shd_allowed_to('shd_view_closed_any', $context['shd_department']))
		fatal_lang_error('shd_cannot_view_resolved', false);

	// Stuff we need to add to $context, the permission we want to use, page title etc etc
	$context += array(
		'page_title' => $txt['shd_helpdesk'],
		'ticket_blocks' => array(
			'closed' => array(
				'block_icon' => 'resolved.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_CLOSED . '_heading'],
				'where' => 'hdt.status = ' . TICKET_STATUS_CLOSED,
				'display' => true,
				'count' => shd_count_helpdesk_tickets('closed'),
				'columns' => shd_get_block_columns('closed'),
				'required' => true,
				'collapsed' => false,
			),
		),
		'shd_home_view' => shd_allowed_to('shd_staff', $context['shd_department']) ? 'staff' : 'user', // This might be removed in the future. We do this here to be able to re-use template_ticket_block() in the template.
	);
	wetem::load('closedtickets');

	// Build the link tree.
	if (!empty($context['shd_dept_name']) && $context['shd_multi_dept'])
		$context['linktree'][] = array(
			'url' => '<URL>?' . $context['shd_home'] . $context['shd_dept_link'],
			'name' => $context['shd_dept_name'],
		);
	$context['linktree'][] = array(
		'url' => '<URL>?action=helpdesk;sa=closedtickets' . $context['shd_dept_link'],
		'name' => $txt['shd_tickets_closed'],
	);

	shd_helpdesk_listing();
}

/**
 *	Set up the paginated lists of deleted/recyclebin tickets.
 *
 *	Much like the main helpdesk, this function prepares a list of all the deleted tickets, with a more specific
 *	list of columns that is better suited to recyclable or permadeletable tickets.
 *
 *	@see shd_main_helpdesk()
 *	@since 1.0
*/
function shd_recycle_bin()
{
	global $context, $txt;

	// Stuff we need to add to $context, the permission we want to use, page title etc etc
	$context += array(
		'shd_permission' => 'shd_access_recyclebin',
		'page_title' => $txt['shd_helpdesk'],
		'ticket_blocks' => array(
			'recycle' => array(
				'block_icon' => 'recycle.png',
				'title' => $txt['shd_status_' . TICKET_STATUS_DELETED . '_heading'],
				'tickets' => array(),
				'where' => 'hdt.status = ' . TICKET_STATUS_DELETED,
				'display' => true,
				'count' => shd_count_helpdesk_tickets('recycled'),
				'columns' => shd_get_block_columns('recycled'),
				'required' => true,
				'collapsed' => false,
			),
			'withdeleted' => array(
				'block_icon' => 'recycle.png',
				'title' => $txt['shd_status_withdeleted_heading'],
				'tickets' => array(),
				'where' => 'hdt.status != ' . TICKET_STATUS_DELETED . ' AND hdt.deleted_replies > 0',
				'display' => true,
				'count' => shd_count_helpdesk_tickets('withdeleted'),
				'columns' => shd_get_block_columns('withdeleted'),
				'required' => true,
				'collapsed' => false,
			),
		),
	);
	wetem::load('recyclebin');

	// Build the link tree.
	if (!empty($context['shd_dept_name']) && $context['shd_multi_dept'])
		$context['linktree'][] = array(
			'url' => '<URL>?' . $context['shd_home'] . $context['shd_dept_link'],
			'name' => $context['shd_dept_name'],
		);
	$context['linktree'][] = array(
		'url' => '<URL>?action=helpdesk;sa=recyclebin' . $context['shd_dept_link'],
		'name' => $txt['shd_recycle_bin'],
	);

	shd_helpdesk_listing();
}

/**
 *	Gather the data and prepare to display the ticket blocks.
 *
 *	Actually performs the queries to get data for each block, subject to the parameters specified by the calling functions.
 *
 *	It also sets up per-block pagination links, collects a variety of data (enough to populate all the columns as listed in shd_main_helpdesk,
 *	even if not entirely applicable, and populates it all into $context['ticket_blocks']['tickets'], extending the array that was
 *	already there.
 *
 *	@see shd_main_helpdesk()
 *	@see shd_closed_tickets()
 *	@see shd_recycle_bin()
 *	@since 1.0
*/
function shd_helpdesk_listing()
{
	global $context, $txt, $user_profile, $settings;

	if (!empty($context['shd_permission']))
		shd_is_allowed_to($context['shd_permission']);

	$block_list = array_keys($context['ticket_blocks']);
	$primary_url = '?action=helpdesk;sa=' . $_REQUEST['sa'];

	// First figure out the start positions of each item and sanitise them
	foreach ($context['ticket_blocks'] as $block_key => $block)
	{
		if (empty($block['viewing_as_block']))
		{
			$num_per_page = !empty($context['shd_preferences']['blocks_' . $block_key . '_count']) ? $context['shd_preferences']['blocks_' . $block_key . '_count'] : $context['items_per_page'];
			$start = empty($_REQUEST['st_' . $block_key]) ? 0 : (int) $_REQUEST['st_' . $block_key];
			$max_value = $block['count']; // easier to read
		}
		else
		{
			$num_per_page = $context['items_per_page'];
			$max_value = $context['items_per_page'];
			$start = 0;
		}

		if ($start < 0)
			$start = 0;
		elseif ($start >= $max_value)
			$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));
		else
			$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

		$context['ticket_blocks'][$block_key]['start'] = $start;
		$context['ticket_blocks'][$block_key]['num_per_page'] = $num_per_page;

		if ($start != 0)
			$_REQUEST['st_' . $block_key] = $start; // sanitise!
		elseif (isset($_REQUEST['st_' . $block_key]))
			unset($_REQUEST['st_' . $block_key]);
	}

	// Now ordering the columns, separate loop for breaking the two processes apart
	$sort_methods = array(
		'ticketid' => array(
			'sql' => 'hdt.id_ticket',
		),
		'ticketname' => array(
			'sql' => 'hdt.subject',
		),
		'replies' => array(
			'sql' => 'hdt.num_replies',
		),
		'allreplies' => array(
			'sql' => '(hdt.num_replies + hdt.deleted_replies)',
		),
		'urgency' => array(
			'sql' => 'hdt.urgency',
		),
		'updated' => array(
			'sql' => 'hdt.last_updated',
		),
		'assigned' => array(
			'sql' => 'assigned_name',
			'sql_select' => 'IFNULL(mem.real_name, 0) AS assigned_name',
			'sql_join' => 'LEFT JOIN {db_prefix}members AS mem ON (hdt.id_member_assigned = mem.id_member)',
		),
		'status' => array(
			'sql' => 'hdt.status',
		),
		'starter' => array(
			'sql' => 'starter_name',
			'sql_select' => 'IFNULL(mem.real_name, 0) AS starter_name',
			'sql_join' => 'LEFT JOIN {db_prefix}members AS mem ON (hdt.id_member_started = mem.id_member)',
		),
		'lastreply' => array(
			'sql' => 'last_reply',
			'sql_select' => 'IFNULL(mem.real_name, 0) AS last_reply',
			'sql_join' => 'LEFT JOIN {db_prefix}members AS mem ON (hdtr_last.id_member = mem.id_member)',
		),
	);

	foreach ($context['ticket_blocks'] as $block_key => $block)
	{
		$sort = isset($_REQUEST['so_' . $block_key]) ? $_REQUEST['so_' . $block_key] : (!empty($context['shd_preferences']['block_order_' . $block_key . '_block']) ? $context['shd_preferences']['block_order_' . $block_key . '_block'] : '');

		if (strpos($sort, '_') > 0 && substr_count($sort, '_') == 1)
		{
			list($sort_item, $sort_dir) = explode('_', $sort);

			if (empty($sort_methods[$sort_item]))
			{
				$sort_item = 'updated';
				$sort = '';
			}

			if (!in_array($sort_dir, array('asc', 'desc')))
			{
				$sort = '';
				$sort_dir = 'asc';
			}
		}
		else
		{
			$sort = '';
			$sort_item = 'updated';
			$sort_dir = $_REQUEST['sa'] == 'closedtickets' || $_REQUEST['sa'] == 'recyclebin' ? 'desc' : 'asc'; // default to newest first if on recyclebin or closed tickets, otherwise oldest first
		}

		if ($sort != '')
			$_REQUEST['so_' . $block_key] = $sort; // sanitise!
		elseif (isset($_REQUEST['so_' . $block_key]))
			unset($_REQUEST['so_' . $block_key]);

		$context['ticket_blocks'][$block_key]['sort'] = array(
			'item' => $sort_item,
			'direction' => $sort_dir,
			'add_link' => ($sort != ''),
			'sql' => array(
				'select' => !empty($sort_methods[$sort_item]['sql_select']) ? $sort_methods[$sort_item]['sql_select'] : '',
				'join' => !empty($sort_methods[$sort_item]['sql_join']) ? $sort_methods[$sort_item]['sql_join'] : '',
				'sort' => $sort_methods[$sort_item]['sql'] . ' ' . strtoupper($sort_dir),
			),
			'link_bits' => array(),
		);
	}

	// Having got all that, step through the blocks again to determine the full URL fragments
	foreach ($context['ticket_blocks'] as $block_key => $block)
		foreach ($sort_methods as $method => $sort_details)
			$context['ticket_blocks'][$block_key]['sort']['link_bits'][$method] = ';so_' . $block_key . '=' . $method . '_' . $block['sort']['direction'];

	// Figure out if the user is filtering on anything, and if so, set up containers for the extra joins, selects, pagination link fragments, etc
	$_REQUEST['field'] = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;
	$_REQUEST['filter'] = isset($_REQUEST['filter']) ? (int) $_REQUEST['filter'] : 0;
	if ($_REQUEST['field'] > 0 && $_REQUEST['filter'] > 0)
	{
		$context['filter_fragment'] = ';field=' . $_REQUEST['field'] . ';filter=' . $_REQUEST['filter'];
		$context['filter_join'] = '
				INNER JOIN {db_prefix}helpdesk_custom_fields_values AS hdcfv ON (hdcfv.id_post = hdt.id_ticket AND hdcfv.id_field = {int:field} AND hdcfv.post_type = {int:type_ticket})
				INNER JOIN {db_prefix}helpdesk_custom_fields AS hdcf ON (hdcf.id_field = hdcfv.id_field AND hdcf.active = {int:active})';
		$context['filter_where'] = '
				AND hdcfv.value = {string:filter}';
	}
	else
	{
		$context['filter_fragment'] = '';
		$context['filter_join'] = '';
		$context['filter_where'] = '';
	}

	// Now go actually do the whole block thang, setting up space for a list of users and tickets as we go along
	$users = array();
	$tickets = array();

	foreach ($context['ticket_blocks'] as $block_key => $block)
	{
		if (empty($block['display']) || !empty($block['collapsed']))
			continue;

		$context['ticket_blocks'][$block_key]['tickets'] = array();

		// If we're filtering, we have to query it first to figure out how many rows there are in this block. It's not pretty.
		if (!empty($context['filter_join']))
		{
			$query = wesql::query('
				SELECT COUNT(hdt.id_ticket)
				FROM {db_prefix}helpdesk_tickets AS hdt
					INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_first ON (hdt.id_first_msg = hdtr_first.id_msg)
					INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_last ON (hdt.id_last_msg = hdtr_last.id_msg)
					INNER JOIN {db_prefix}helpdesk_depts AS hdd ON (hdt.id_dept = hdd.id_dept)
					' . (!empty($block['sort']['sql']['join']) ? $block['sort']['sql']['join'] : '') . $context['filter_join'] . '
				WHERE {query_see_ticket}' . (!empty($block['where']) ? ' AND ' . $block['where'] : '') . (!empty($context['shd_department']) ? ' AND hdt.id_dept = {int:dept}' : '') . $context['filter_where'],
				array(
					'dept' => $context['shd_department'],
					'user' => MID,
					'field' => $_REQUEST['field'],
					'filter' => $_REQUEST['filter'],
					'type_ticket' => CFIELD_TICKET,
					'active' => 1,
				)
			);
			list($context['ticket_blocks'][$block_key]['count']) = wesql::fetch_row($query);
			$block['count'] = $context['ticket_blocks'][$block_key]['count'];
			wesql::free_result($query);

			if ($block['start'] >= $block['count'])
			{
				$context['ticket_blocks'][$block_key]['start'] = max(0, (int) $block['count'] - (((int) $block['count'] % (int) $block['num_per_page']) == 0 ? $block['num_per_page'] : ((int) $block['count'] % (int) $block['num_per_page'])));
				$block['start'] = $context['ticket_blocks'][$block_key]['start'];
			}
		}

		$query = wesql::query('
			SELECT hdt.id_ticket, hdt.id_dept, hdd.dept_name, hdt.id_last_msg, hdt.id_member_started, hdt.id_member_updated,
				hdt.id_member_assigned, hdt.subject, hdt.status, hdt.num_replies, hdt.deleted_replies, hdt.private, hdt.urgency,
				hdt.last_updated, hdtr_first.poster_name AS ticket_opener, hdtr_last.poster_name AS respondent, hdtr_last.poster_time,
				IFNULL(hdlr.id_msg, 0) AS log_read' . (!empty($block['sort']['sql']['select']) ? ', ' . $block['sort']['sql']['select'] : '') . '
			FROM {db_prefix}helpdesk_tickets AS hdt
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_first ON (hdt.id_first_msg = hdtr_first.id_msg)
				INNER JOIN {db_prefix}helpdesk_ticket_replies AS hdtr_last ON (hdt.id_last_msg = hdtr_last.id_msg)
				INNER JOIN {db_prefix}helpdesk_depts AS hdd ON (hdt.id_dept = hdd.id_dept)
				LEFT JOIN {db_prefix}helpdesk_log_read AS hdlr ON (hdt.id_ticket = hdlr.id_ticket AND hdlr.id_member = {int:user})
				' . (!empty($block['sort']['sql']['join']) ? $block['sort']['sql']['join'] : '') . $context['filter_join'] . '
			WHERE {query_see_ticket}' . (!empty($block['where']) ? ' AND ' . $block['where'] : '') . (!empty($context['shd_department']) ? ' AND hdt.id_dept = {int:dept}' : '') . $context['filter_where'] . '
			ORDER BY ' . (!empty($block['sort']['sql']['sort']) ? $block['sort']['sql']['sort'] : 'hdt.id_last_msg ASC') . '
			LIMIT {int:start}, {int:items_per_page}',
			array(
				'dept' => $context['shd_department'],
				'user' => MID,
				'start' => $block['start'],
				'items_per_page' => $block['num_per_page'],
				'field' => $_REQUEST['field'],
				'filter' => $_REQUEST['filter'],
				'type_ticket' => CFIELD_TICKET,
				'active' => 1,
			)
		);

		while ($row = wesql::fetch_assoc($query))
		{
			$is_own = MID == $row['id_member_started'];
			censorText($row['subject']);

			$new_block = array(
				'id' => $row['id_ticket'],
				'display_id' => str_pad($row['id_ticket'], $settings['shd_zerofill'], '0', STR_PAD_LEFT),
				'dept_link' => empty($context['shd_department']) && $context['shd_multi_dept'] ? '[<a href="<URL>?' . $context['shd_home'] . ';dept=' . $row['id_dept'] . '">' . $row['dept_name'] . '</a>] ' : '',
				'link' => '<a href="<URL>?action=helpdesk;sa=ticket;ticket=' . $row['id_ticket'] . ($_REQUEST['sa'] == 'recyclebin' ? ';recycle' : '') . '">' . $row['subject'] . '</a>',
				'subject' => $row['subject'],
				'status' => array(
					'level' => $row['status'],
					'label' => $txt['shd_status_' . $row['status']],
				),
				'starter' => array(
					'id' => $row['id_member_started'],
					'name' => $row['ticket_opener'],
				),
				'last_update' => timeformat($row['last_updated']),
				'assigned' => array(
					'id' => $row['id_member_assigned'],
				),
				'respondent' => array(
					'id' => $row['id_member_updated'],
					'name' => $row['respondent'],
				),
				'urgency' => array(
					'level' => $row['urgency'],
					'label' => $row['urgency'] > TICKET_URGENCY_HIGH ? '<span class="error">' . $txt['shd_urgency_' . $row['urgency']] . '</span>' : $txt['shd_urgency_' . $row['urgency']],
				),
				'is_unread' => ($row['id_last_msg'] > $row['log_read']),
				'new_href' => ($row['id_last_msg'] <= $row['log_read']) ? '' : ('<URL>?action=helpdesk;sa=ticket;ticket=' . $row['id_ticket'] . '.new' . ($_REQUEST['sa'] == 'recyclebin' ? ';recycle' : '') . '#new'),
				'private' => $row['private'],
				'actions' => array(
					'movedept' => !empty($context['shd_multi_dept']) && (shd_allowed_to('shd_move_dept_any', $context['shd_department']) || ($is_own && shd_allowed_to('shd_move_dept_own', $context['shd_department']))) ? '<a href="<URL>?action=helpdesk;sa=movedept;ticket=' . $row['id_ticket'] . ';home;' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_move_dept'] . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/movedept.png" alt="' . $txt['shd_move_dept'] . '"></a>' : '',
				),
				'num_replies' => $row['num_replies'],
				'replies_href' => '<URL>?action=helpdesk;sa=ticket;ticket=' . $row['id_ticket'] . '.msg' . $row['id_last_msg'] . '#msg' . $row['id_last_msg'] . ($_REQUEST['sa'] == 'recyclebin' ? ';recycle' : ''),
				'all_replies' => (int) $row['num_replies'] + (int) $row['deleted_replies'],
			);

			if ($row['status'] == TICKET_STATUS_CLOSED)
			{
				$new_block['actions'] += array(
					'resolve' => shd_allowed_to('shd_unresolve_ticket_any', $context['shd_department']) || ($is_own && shd_allowed_to('shd_unresolve_ticket_own', $context['shd_department'])) ? '<a href="<URL>?action=helpdesk;sa=resolveticket;ticket=' . $row['id_ticket'] . ';home;' . $context['shd_dept_link'] . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_ticket_unresolved'] . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/unresolved.png" alt="' . $txt['shd_ticket_unresolved'] . '"></a>' : '',
				);
			}
			elseif ($row['status'] == TICKET_STATUS_DELETED) // and thus, we're in the recycle bin
			{
				$new_block['actions'] += array(
					'restore' => shd_allowed_to('shd_restore_ticket_any', $context['shd_department']) || ($is_own && shd_allowed_to('shd_restore_ticket_own', $context['shd_department'])) ? '<a href="<URL>?action=helpdesk;sa=restoreticket;ticket=' . $row['id_ticket'] . ';home;' . $context['shd_dept_link'] . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_ticket_restore'] . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/restore.png" alt="' . $txt['shd_ticket_restore'] . '"></a>' : '',
					'permadelete' => shd_allowed_to('shd_delete_recycling', $context['shd_department']) ? '<a href="<URL>?action=helpdesk;sa=permadelete;ticket=' . $row['id_ticket'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_delete_permanently'] . '" onclick="return confirm(' . JavaScriptEscape($txt['shd_delete_permanently_confirm']) . ');"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/delete.png" alt="' . $txt['shd_delete_permanently'] . '"></a>' : '',
				);
			}
			else
			{
				$langstring = '';
				if (shd_allowed_to('shd_assign_ticket_any', $context['shd_department']))
					$langstring = empty($row['id_member_assigned']) ? $txt['shd_ticket_assign'] : $txt['shd_ticket_reassign'];
				elseif (shd_allowed_to('shd_assign_ticket_own', $context['shd_department']) && (empty($row['id_member_assigned']) || $row['id_member_assigned'] == MID))
					$langstring = $row['id_member_assigned'] == MID ? $txt['shd_ticket_unassign'] : $txt['shd_ticket_assign_self'];

				if (!empty($langstring))
					$new_block['actions']['assign'] = '<a href="<URL>?action=helpdesk;sa=assign;ticket=' . $row['id_ticket'] . ';home;' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $langstring . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/assign.png" alt="' . $langstring . '"></a>';

				$new_block['actions'] += array(
					'resolve' => shd_allowed_to('shd_resolve_ticket_any', $context['shd_department']) || ($is_own && shd_allowed_to('shd_resolve_ticket_own', $context['shd_department'])) ? '<a href="<URL>?action=helpdesk;sa=resolveticket;ticket=' . $row['id_ticket'] . ';home;' . $context['shd_dept_link'] . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_ticket_resolved'] . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/resolved.png" alt="' . $txt['shd_ticket_resolved'] . '"></a>' : '',
					'tickettotopic' => empty($settings['shd_helpdesk_only']) && shd_allowed_to('shd_ticket_to_topic', $context['shd_department']) && ($row['deleted_replies'] == 0 || shd_allowed_to('shd_access_recyclebin')) ? '<a href="<URL>?action=helpdesk;sa=tickettotopic;ticket=' . $row['id_ticket'] . ';' . $context['shd_dept_link'] . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_ticket_move_to_topic'] . '"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/tickettotopic.png" alt="' . $txt['shd_ticket_move_to_topic'] . '"></a>' : '',
					'delete' => shd_allowed_to('shd_delete_ticket_any', $context['shd_department']) || ($is_own && shd_allowed_to('shd_delete_ticket_own')) ? '<a href="<URL>?action=helpdesk;sa=deleteticket;ticket=' . $row['id_ticket'] . ';' . $context['shd_dept_link'] . $context['session_var'] . '=' . $context['session_id'] . '" title="' . $txt['shd_ticket_delete'] . '" onclick="return confirm(' . JavaScriptEscape($txt['shd_delete_confirm']) . ');"><img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/delete.png" alt="' . $txt['shd_ticket_delete'] . '"></a>' : '',
				);
			}

			$context['ticket_blocks'][$block_key]['tickets'][$row['id_ticket']] = $new_block;

			$users[] = $row['id_member_started'];
			$users[] = $row['id_member_updated'];
			$users[] = $row['id_member_assigned'];
			$tickets[$row['id_ticket']] = array();
		}
		wesql::free_result($query);
	}

	$users = array_unique($users);
	if (!empty($users))
		loadMemberData($users, false, 'minimal');

	foreach ($context['ticket_blocks'] as $block_id => $block)
	{
		if (empty($block['tickets']))
			continue;

		foreach ($block['tickets'] as $tid => $ticket)
		{
			// Set up names and profile links for topic starter
			if (!empty($user_profile[$ticket['starter']['id']]))
			{
				// We found the name, so let's use their current name and profile link
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['starter']['name'] = $user_profile[$ticket['starter']['id']]['real_name'];
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['starter']['link'] = shd_profile_link($user_profile[$ticket['starter']['id']]['real_name'], $ticket['starter']['id']);
			}
			else
				// We didn't, so keep using the name we found previously and don't make an actual link
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['starter']['link'] = $context['ticket_blocks'][$block_id]['tickets'][$tid]['starter']['name'];

			// Set up names and profile links for assigned user
			if ($ticket['assigned']['id'] == 0 || empty($user_profile[$ticket['assigned']['id']]))
			{
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['assigned']['name'] = $txt['shd_unassigned'];
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['assigned']['link'] = '<span class="error">' . $txt['shd_unassigned'] . '</span>';
			}
			else
			{
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['assigned']['name'] = $user_profile[$ticket['assigned']['id']]['real_name'];
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['assigned']['link'] = shd_profile_link($user_profile[$ticket['assigned']['id']]['real_name'], $ticket['assigned']['id']);
			}

			// And last respondent
			if ($ticket['respondent']['id'] == 0 || empty($user_profile[$ticket['respondent']['id']]))
			{
				// Didn't find the name, so reuse what we have
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['respondent']['link'] = $context['ticket_blocks'][$block_id]['tickets'][$tid]['respondent']['name'];
			}
			else
			{
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['respondent']['name'] = $user_profile[$ticket['respondent']['id']]['real_name'];
				$context['ticket_blocks'][$block_id]['tickets'][$tid]['respondent']['link'] = shd_profile_link($user_profile[$ticket['respondent']['id']]['real_name'], $ticket['respondent']['id']);
			}
		}
	}

	foreach ($context['ticket_blocks'] as $block_id => $block)
	{
		if (empty($block['display']) || (empty($block['count']) && !$block['required'] && empty($block['collapsed'])))
			unset($context['ticket_blocks'][$block_id]);
	}

	$base_url = '';
	foreach ($context['ticket_blocks'] as $block_id => $block)
	{
		if ($block['sort']['add_link'])
			$base_url .= $block['sort']['link_bits'][$block['sort']['item']];
	}

	if ($_REQUEST['sa'] != 'viewblock')
	{
		foreach ($context['ticket_blocks'] as $block_id => $block)
		{
			$num_per_page = !empty($context['shd_preferences']['blocks_' . $block_key . '_count']) ? $context['shd_preferences']['blocks_' . $block_key . '_count'] : $context['items_per_page'];
			$url_fragment = $base_url;

			foreach ($block_list as $block_item)
			{
				if ($block_item == $block_id)
					$url_fragment .= ';st_' . $block_item . '=%1$d';
				elseif (!empty($context['ticket_blocks'][$block_item]['start']))
					$url_fragment .= ';st_' . $block_item . '=' . $context['ticket_blocks'][$block_item]['start'];
			}

			$context['start'] = $context['ticket_blocks'][$block_id]['start'];
			$context['ticket_blocks'][$block_id]['page_index'] = shd_no_expand_pageindex('<URL>' . $primary_url . $url_fragment . $context['shd_dept_link'] . $context['filter_fragment'] . '#shd_block_' . $block_id, $context['start'], $block['count'], $block['num_per_page'], true);
		}
	}

	// Just need to deal with those pesky prefix fields, if there are any.
	if (empty($tickets))
		return; // We're all done here.

	// 1. Figure out if there are any custom fields that apply to us or not.
	if ($context['shd_multi_dept'] && empty($context['shd_department']))
		$dept_list = shd_allowed_to('access_helpdesk', false);
	else
		$dept_list = array($context['shd_department']);

	$fields = array();
	$query = wesql::query('
		SELECT hdcf.id_field, can_see, field_type, field_options, placement, field_name
		FROM {db_prefix}helpdesk_custom_fields AS hdcf
			INNER JOIN {db_prefix}helpdesk_custom_fields_depts AS hdcfd ON (hdcfd.id_field = hdcf.id_field)
		WHERE placement IN ({array_int:placement_prefix})
			AND field_loc IN ({array_int:locations})
			AND hdcfd.id_dept IN ({array_int:dept_list})
			AND active = {int:active}
		GROUP BY hdcf.id_field
		ORDER BY field_order',
		array(
			'locations' => array(CFIELD_TICKET, CFIELD_TICKET | CFIELD_REPLY),
			'placement_prefix' => array(CFIELD_PLACE_PREFIX, CFIELD_PLACE_PREFIXFILTER),
			'active' => 1,
			'dept_list' => $dept_list,
		)
	);
	$is_staff = shd_allowed_to('shd_staff', $context['shd_department']);
	$is_admin = we::$is_admin || shd_allowed_to('admin_helpdesk', $context['shd_department']);
	$context['shd_filter_fields'] = array();
	while ($row = wesql::fetch_assoc($query))
	{
		list($user_see, $staff_see) = explode(',', $row['can_see']);
		if ($is_admin || ($is_staff && $staff_see == '1') || (!$is_staff && $user_see == '1'))
		{
			if (!empty($row['field_options']))
			{
				$row['field_options'] = unserialize($row['field_options']);
				if (isset($row['field_options']['inactive']))
					unset($row['field_options']['inactive']);
				foreach ($row['field_options'] as $k => $v)
					if (strpos($v, '[') !== false)
						$row['field_options'][$k] = parse_bbc($v, 'wedgedesk-custom-fields');
			}
			$fields[$row['id_field']] = $row;

			if ($row['placement'] == CFIELD_PLACE_PREFIXFILTER)
				$context['shd_filter_fields'][$row['id_field']] = array(
					'name' => $row['field_name'],
					'options' => $row['field_options'],
					'in_use' => array(),
				);
		}
	}
	wesql::free_result($query);

	if (empty($fields))
		return; // No fields to process, time to go.

	// 2. Get the relevant values.
	$query = wesql::query('
		SELECT id_post, id_field, value
		FROM {db_prefix}helpdesk_custom_fields_values
		WHERE id_post IN ({array_int:tickets})
			AND id_field IN ({array_int:fields})
			AND post_type = {int:ticket}',
		array(
			'tickets' => array_keys($tickets),
			'fields' => array_keys($fields),
			'ticket' => CFIELD_TICKET,
		)
	);
	while ($row = wesql::fetch_assoc($query))
		$tickets[$row['id_post']][$row['id_field']] = $row['value'];

	// 3. Apply the values into the tickets.
	if ($_REQUEST['sa'] == 'closedtickets')
		$context['filterbase'] = '<URL>?action=helpdesk;sa=closedtickets';
	elseif ($_REQUEST['sa'] == 'recyclebin')
		$context['filterbase'] = '<URL>?action=helpdesk;sa=recyclebin';
	else
		$context['filterbase'] = '<URL>?' . $context['shd_home'];

	foreach ($context['ticket_blocks'] as $block_id => $block)
	{
		if (empty($block['tickets']))
			continue;

		foreach ($block['tickets'] as $ticket_id => $ticket)
		{
			if (isset($tickets[$ticket_id]))
			{
				$prefix_filter = '';
				$prefix = '';

				foreach ($fields as $field_id => $field)
				{
					if (empty($tickets[$ticket_id][$field_id]))
						continue;

					if ($field['placement'] == CFIELD_PLACE_PREFIXFILTER)
					{
						if (!isset($field['field_options'][$tickets[$ticket_id][$field_id]]))
							continue;

						$prefix_filter .= '[<a href="' . $context['filterbase'] . $context['shd_dept_link'] . ';field=' . $field_id . ';filter=' . $tickets[$ticket_id][$field_id] . '">' . $field['field_options'][$tickets[$ticket_id][$field_id]] . '</a>] ';
					}
					else
					{
						if ($field['field_type'] == CFIELD_TYPE_CHECKBOX)
							$prefix .= !empty($tickets[$ticket_id][$field_id]) ? $txt['yes'] . ' ' : $txt['no'] . ' ';
						elseif ($field['field_type'] == CFIELD_TYPE_SELECT || $field['field_type'] == CFIELD_TYPE_RADIO)
							$prefix .= $field['field_options'][$tickets[$ticket_id][$field_id]] . ' ';
						elseif ($field['field_type'] == CFIELD_TYPE_MULTI)
						{
							$values = explode(',', $tickets[$ticket_id][$field_id]);
							foreach ($values as $value)
								$prefix .= $field['field_options'][$value] . ' ';
						}
						else
							$prefix .= $tickets[$ticket_id][$field_id] . ' ';
					}
				}

				// First, set aside the subject, and if there is a non category prefix, strip links from it.
				$subject = $ticket['subject'];
				if (!empty($prefix))
					$prefix = '[' . trim(preg_replace('~<a (.*?)</a>~is', '', $prefix)) . '] ';
				// Then, if we have a category prefix, prepend that to any other prefix we have.
				if (!empty($prefix_filter))
					$prefix = $prefix_filter . $prefix;
				// Lastly, if we have some kind of prefix to put in front of this ticket, do so.
				if (!empty($prefix))
				{
					$context['ticket_blocks'][$block_id]['tickets'][$ticket_id]['subject'] = $prefix . $subject;
					$context['ticket_blocks'][$block_id]['tickets'][$ticket_id]['link'] = $prefix . '<a href="<URL>?action=helpdesk;sa=ticket;ticket=' . $ticket_id . ($_REQUEST['sa'] == 'recyclebin' ? ';recycle' : '') . '">' . $subject . '</a>';
				}
			}
		}
	}

	// 4. We've collected the list of prefix-filter fields in use, now establish which values are actually in use.
	if (!empty($context['shd_filter_fields']))
	{
		$query = wesql::query('
			SELECT id_field, value
			FROM {db_prefix}helpdesk_custom_fields_values
			WHERE id_field IN ({array_int:fields})',
			array(
				'fields' => array_keys($context['shd_filter_fields']),
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['shd_filter_fields'][$row['id_field']]['in_use'][$row['value']] = true;
		wesql::free_result($query);

		foreach ($context['shd_filter_fields'] as $id_field => $field)
		{
			if (empty($field['in_use']))
				unset($context['shd_filter_fields'][$id_field]);
			else
			{
				foreach ($field['options'] as $k => $v)
					if (!isset($field['in_use'][$k]))
						unset($context['shd_filter_fields'][$id_field]['options'][$k]);

				if (empty($context['shd_filter_fields'][$id_field]['options']))
					unset($context['shd_filter_fields'][$id_field]);
			}
		}
	}
}

/**
 *	Return the list of columns that is applicable to a given block.
 *
 *	In order to centralise the list of actions to be displayed in a block, and in its counterpart that displays all the values,
 *	the lists of columns per block is kept here.
 *
 *	@param string $block The block we are calling from:
 *	- assigned: assigned to me
 *	- new: new tickets
 *	- staff: pending staff
 *	- user_staff: pending with user (staff view)
 *	- user_user: pending with user (user view)
 *	- closed: resolved tickets
 *	- recycled: deleted tickets
 *	- withdeleted: tickets with deleted replies
 *
 *	@return array An indexed array of the columns in the order they should be displayed.
 *	@see shd_main_helpdesk()
 *	@see shd_closed_tickets()
 *	@see shd_recycle_bin()
 *	@since 1.0
*/
function shd_get_block_columns($block)
{
	switch ($block)
	{
		case 'assigned':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'replies',
				'status',
				'urgency',
				'updated',
				'actions',
			);
		case 'new':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'assigned',
				'urgency',
				'updated',
				'actions',
			);
		case 'staff':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'replies',
				'assigned',
				'urgency',
				'updated',
				'actions',
			);
		case 'user_staff':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'last_reply',
				'replies',
				'urgency',
				'updated',
				'actions',
			);
		case 'user_user':
			return array(
				'ticket_id',
				'ticket_name',
				'last_reply',
				'replies',
				'urgency',
				'updated',
				'actions',
			);
		case 'closed':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'replies',
				'updated',
				'actions',
			);
		case 'recycled':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'allreplies',
				'assigned',
				'updated',
				'actions',
			);
		case 'withdeleted':
			return array(
				'ticket_id',
				'ticket_name',
				'starting_user',
				'allreplies',
				'assigned',
				'updated',
				'actions',
			);
		default:
			return array();
	}
}
?>