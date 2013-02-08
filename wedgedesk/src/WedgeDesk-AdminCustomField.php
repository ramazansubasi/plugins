<?php
/**
 * WedgeDesk
 *
 * This file handles the core of WedgeDesk's custom ticket fields interface and code.
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
 *	The start point for all interaction with the WedgeDesk custom field administration.
 *
 *	Directed here from the main administration centre, after permission checks and a few dependencies loaded, this deals solely with managing custom fields.
 *
 *	@since 2.0
*/
function shd_admin_custom()
{
	global $context, $txt;

	loadPluginTemplate('Arantor:WedgeDesk', 'tpl/WedgeDesk-AdminCustomField');

	$subactions = array(
		'main' => 'shd_admin_custom_main',
		'new' => 'shd_admin_custom_new',
		'edit' => 'shd_admin_custom_edit',
		'move' => 'shd_admin_custom_move',
		'save' => 'shd_admin_custom_save',
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'main';

	$context['field_types'] = array(
		CFIELD_TYPE_TEXT => array($txt['shd_admin_custom_fields_ui_text'], 'text'),
		CFIELD_TYPE_LARGETEXT => array($txt['shd_admin_custom_fields_ui_largetext'], 'largetext'),
		CFIELD_TYPE_INT => array($txt['shd_admin_custom_fields_ui_int'], 'int'),
		CFIELD_TYPE_FLOAT => array($txt['shd_admin_custom_fields_ui_float'], 'float'),
		CFIELD_TYPE_SELECT => array($txt['shd_admin_custom_fields_ui_select'], 'select'),
		CFIELD_TYPE_CHECKBOX => array($txt['shd_admin_custom_fields_ui_checkbox'], 'checkbox'),
		CFIELD_TYPE_RADIO => array($txt['shd_admin_custom_fields_ui_radio'], 'radio'),
		CFIELD_TYPE_MULTI => array($txt['shd_admin_custom_fields_ui_multi'], 'multi'),
	);

	$subactions[$_REQUEST['sa']]();
}

/**
 *	Display all the custom fields, including new/edit/save/delete UI hooks
 *
 *	@since 2.0
*/
function shd_admin_custom_main()
{
	global $context, $txt;

	$context['custom_fields'] = array();

	$query = wesql::query('
		SELECT id_field, active, field_order, field_name, field_desc, field_loc, icon, field_type, can_see, can_edit
		FROM {db_prefix}helpdesk_custom_fields
		ORDER BY field_order',
		array()
	);

	while ($row = wesql::fetch_assoc($query))
	{
		$row['active_string'] = empty($row['active']) ? 'inactive' : 'active';
		$row['field_type'] = $context['field_types'][$row['field_type']][1]; // convert the integer in the DB into the string for language + image uses
		$row['can_see'] = explode(',', $row['can_see']);
		$row['can_edit'] = explode(',', $row['can_edit']);
		$row['field_desc'] = parse_bbc($row['field_desc'], false);
		$context['custom_fields'][] = $row;
	}

	if (!empty($context['custom_fields']))
	{
		$context['custom_fields'][0]['is_first'] = true;
		$context['custom_fields'][count($context['custom_fields']) - 1]['is_last'] = true;
	}

	// Final stuff before we go.
	$context['page_title'] = $txt['shd_admin_custom_fields'];
	wetem::load('shd_custom_field_home');
}

/**
 *	Display the new field UI
 *
 *	@since 2.0
*/
function shd_admin_custom_new()
{
	global $context, $txt;

	$context = array_merge($context, array(
		'page_title' => $txt['shd_admin_new_custom_field'],
		'section_title' => $txt['shd_admin_new_custom_field'],
		'section_desc' => $txt['shd_admin_new_custom_field_desc'],
		'field_type_value' => CFIELD_TYPE_TEXT,
		'field_icons' => shd_admin_cf_icons(),
		'field_icon_value' => '',
		'new_field' => true,
		'field_loc' => CFIELD_TICKET,
		'field_active' => ' checked="checked"',
		'placement' => CFIELD_PLACE_DETAILS,
	));
	wetem::load('shd_custom_field_edit');

	$context['custom_field']['options'] = array(1 => '', '', '', 'inactive' => array());
	$context['custom_field']['default_value'] = false;

	// Get the list of departments, and whether a field is required in each department.
	$context['dept_fields'] = array();
	$query = wesql::query('
		SELECT hdd.id_dept, hdd.dept_name, 0 AS present, 0 AS required
		FROM {db_prefix}helpdesk_depts AS hdd
		ORDER BY hdd.dept_order',
		array()
	);
	while ($row = wesql::fetch_assoc($query))
		$context['dept_fields'][$row['id_dept']] = $row;
	wesql::free_result($query);
}

/**
 *	Display the edit field UI
 *
 *	@since 2.0
*/
function shd_admin_custom_edit()
{
	global $context, $txt;

	$_REQUEST['field'] = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;

	$query = wesql::query('
		SELECT id_field, active, field_order, field_name, field_desc, field_loc, icon, field_type,
		field_length, field_options, bbc, default_value, can_see, can_edit, display_empty, placement
		FROM {db_prefix}helpdesk_custom_fields
		WHERE id_field = {int:field}',
		array(
			'field' => $_REQUEST['field'],
		)
	);

	if ($row = wesql::fetch_assoc($query))
	{
		wesql::free_result($query);
		$context['custom_field'] = $row;
		$context['section_title'] = $txt['shd_admin_edit_custom_field'];
		$context['section_desc'] = $txt['shd_admin_edit_custom_field_desc'];
		$context['page_title'] = $txt['shd_admin_edit_custom_field'];
		wetem::load('shd_custom_field_edit');
		$context['custom_field']['options'] = !empty($row['field_options']) ? unserialize($row['field_options']) : array(1 => '', '', '');
		if (empty($context['custom_field']['options']['inactive']))
			$context['custom_field']['options']['inactive'] = array();

		// If this is a textarea, we need to get its dimensions too.
		if ($context['custom_field']['field_type'] == CFIELD_TYPE_LARGETEXT)
			$context['custom_field']['dimensions'] = explode(',', $context['custom_field']['default_value']);

		$context['custom_field']['can_see'] = explode(',', $context['custom_field']['can_see']);
		$context['custom_field']['can_edit'] = explode(',', $context['custom_field']['can_edit']);

		$context = array_merge($context, array(
			'field_type_value' => $context['custom_field']['field_type'],
			'field_icons' => shd_admin_cf_icons(),
			'field_icon_value' => $context['custom_field']['icon'],
			'field_loc' => $context['custom_field']['field_loc'],
			'field_active' => $context['custom_field']['active'] == 1 ? ' checked="checked"' : '',
			'placement' => $context['custom_field']['placement'],
		));

		// Get the possible field types and exclude types that we can't change it to.
		$types = shd_admin_cf_change_types($context['custom_field']['field_type']);
		foreach ($context['field_types'] as $k => $v)
			if (!in_array($k, $types))
				unset($context['field_types'][$k]);

		// Get the list of departments, and whether a field is required in each department.
		$context['dept_fields'] = array();
		$query = wesql::query('
			SELECT hdd.id_dept, hdd.dept_name, cfd.id_dept AS present, cfd.required
			FROM {db_prefix}helpdesk_depts AS hdd
				LEFT JOIN {db_prefix}helpdesk_custom_fields_depts AS cfd ON (cfd.id_field = {int:field} AND hdd.id_dept = cfd.id_dept)
			ORDER BY hdd.dept_order',
			array(
				'field' => $_REQUEST['field'],
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['dept_fields'][$row['id_dept']] = $row;
		wesql::free_result($query);
	}
	else
	{
		wesql::free_result($query);
		fatal_lang_error('shd_admin_cannot_edit_custom_field', false);
	}
}

/**
 *	Handle saving a field
 *
 *	@since 2.0
*/
function shd_admin_custom_save()
{
	global $context;

	checkSession('request');

	// Deletifyingistuffithingi?
	if (isset($_REQUEST['delete']) && !empty($_REQUEST['field']))
	{
		$_REQUEST['field'] = (int) $_REQUEST['field'];
		// We actually have to get the current position, because we need to shove everything else up one after.
		$query = wesql::query('
			SELECT field_order
			FROM {db_prefix}helpdesk_custom_fields
			WHERE id_field = {int:field}',
			array(
				'field' => $_REQUEST['field'],
			)
		);
		if (wesql::num_rows($query) == 0)
		{
			wesql::free_result($query);
			redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);
		}

		list($current_pos) = wesql::fetch_row($query);
		wesql::free_result($query);

		wesql::query('
			DELETE FROM {db_prefix}helpdesk_custom_fields
			WHERE id_field = {int:field}',
			array(
				'field' => $_REQUEST['field'],
			)
		);

		wesql::query('
			UPDATE {db_prefix}helpdesk_custom_fields
			SET field_order = field_order - 1
			WHERE field_order > {int:current_pos}',
			array(
				'current_pos' => $current_pos,
			)
		);

		wesql::query('
			DELETE FROM {db_prefix}helpdesk_custom_fields_values
			WHERE id_field = {int:field}',
			array(
				'field' => $_REQUEST['field'],
			)
		);

		wesql::query('
			DELETE FROM {db_prefix}helpdesk_custom_fields_depts
			WHERE id_field = {int:field}',
			array(
				'field' => $_REQUEST['field'],
			)
		);

		// End of the road
		redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Aborting mission!
	if (isset($_POST['cancel']))
		redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);

	// OK, we're going to need this
	loadSource('Class-Editor');

	// Fix all the input
	if (trim($_POST['field_name']) == '')
		fatal_lang_error('shd_admin_no_fieldname', false);
	$_POST['field_name'] = westr::htmlspecialchars($_POST['field_name']);
	$_POST['description'] = westr::htmlspecialchars(isset($_POST['description']) ? $_POST['description'] : '');
	wedit::preparsecode($_POST['description']);
	$_POST['bbc'] = isset($_POST['bbc']) && in_array($_POST['field_type'], array(CFIELD_TYPE_TEXT, CFIELD_TYPE_LARGETEXT)) ? 1 : 0;
	$_POST['display_empty'] = isset($_POST['display_empty']) ? 1 : 0;

	$_POST['field_type'] == isset($_POST['field_type']) ? (int) $_POST['field_type'] : 0;

	$_POST['active'] = isset($_POST['active']) ? 1 : 0;
	$_POST['field_length'] = isset($_POST['field_length']) ? (int) $_POST['field_length'] : 0;
	if ($_POST['field_length'] < 0)
		$_POST['field_length'] = 0;
	elseif ($_POST['field_length'] > 32000)
		$_POST['field_length'] = 32000;
	$_POST['default_check'] = isset($_POST['default_check']) && $_POST['field_type'] == CFIELD_TYPE_CHECKBOX ? 1 : '';

	if ($_POST['field_type'] == CFIELD_TYPE_LARGETEXT)
		$_POST['default_check'] = (int) $_POST['rows'] . ',' . (int) $_POST['cols'];
	if (!isset($_POST['placement']) || !in_array($_POST['placement'], array(CFIELD_PLACE_DETAILS, CFIELD_PLACE_INFO, CFIELD_PLACE_PREFIX, CFIELD_PLACE_PREFIXFILTER)))
		$_POST['placement'] = CFIELD_PLACE_DETAILS;
	if ($_POST['placement'] == CFIELD_PLACE_PREFIXFILTER && !in_array($_POST['field_type'], array(CFIELD_TYPE_SELECT, CFIELD_TYPE_RADIO)))
		$_POST['placement'] = CFIELD_PLACE_PREFIX;
	$_POST['field_icon'] = isset($_POST['field_icon']) && preg_match('~^[A-Za-z0-9.\-_]+$~', $_POST['field_icon']) ? $_POST['field_icon'] : '';
	$options = '';

	$users_see = !empty($_POST['see_users']) ? '1' : '0';
	$users_edit = $users_see == '1' && !empty($_POST['edit_users']) ? '1' : '0';

	$staff_see = !empty($_POST['see_staff']) ? '1' : '0';
	$staff_edit = $staff_see == '1' && !empty($_POST['edit_staff']) ? '1' : '0';

	$can_see = $users_see . ',' . $staff_see;
	$can_edit = $users_edit . ',' . $staff_edit;

	// Get the list of departments, and whether a field is required in each department.
	$context['dept_fields'] = array();
	$query = wesql::query('
		SELECT hdd.id_dept, hdd.dept_name
		FROM {db_prefix}helpdesk_depts AS hdd
		ORDER BY hdd.dept_order',
		array(
			'field' => $_REQUEST['field'],
		)
	);
	while ($row = wesql::fetch_assoc($query))
	{
		// Is the field meant to be in this department?
		if (empty($_POST['present_dept' . $row['id_dept']]))
			continue;
		$context['dept_fields'][$row['id_dept']] = $row;
		if ($_POST['field_type'] == CFIELD_TYPE_MULTI)
		{
			$required = isset($_POST['required_dept_multi_' . $row['id_dept']]) ? (int) $_POST['required_dept_multi_' . $row['id_dept']] : 0;
			if ($required < 0)
				$required = 0;
			elseif ($required > 100)
				$required = 100;
			$context['dept_fields'][$row['id_dept']]['required'] = $required;
		}
		else
			$context['dept_fields'][$row['id_dept']]['required'] = isset($_POST['required_dept' . $row['id_dept']]) ? 1 : 0;
	}
	wesql::free_result($query);

	// Select options?
	$newOptions = array();
	$defaultOptions = array();
	if (!empty($_POST['select_option']) && ($_POST['field_type'] == CFIELD_TYPE_SELECT || $_POST['field_type'] == CFIELD_TYPE_RADIO || $_POST['field_type'] == CFIELD_TYPE_MULTI))
	{
		foreach ($_POST['select_option'] as $k => $v)
		{
			// Clean, clean, clean...
			$v = westr::htmlspecialchars($v);
			wedit::preparsecode($v);

			// Nada, zip, etc...
			if (trim($v) == '' || !is_numeric($k))
				continue;

			// This is just for working out what happened with old options...
			$newOptions[$k] = $v;
			if (!empty($_POST['default_select_multi'][$k]))
				$defaultOptions[] = $k;

			// Is it default?
			if (isset($_POST['default_select']) && $_POST['default_select'] == $k)
				$_POST['default_check'] = $k;
		}
		$options = serialize($newOptions);
	}

	// Sort out the default selection if it's a multi-select, as well as required amounts
	if ($_POST['field_type'] == CFIELD_TYPE_MULTI)
	{
		$_POST['default_check'] = implode(',', $defaultOptions);
		$max = count($newOptions);
		foreach ($context['dept_fields'] as $dept => $field)
			if ($field['required'] > $max)
				$context['dept_fields'][$dept]['required'] = $max;
	}

	// Do I feel a new field being born?
	if (isset($_REQUEST['new']))
	{
		$types = shd_admin_cf_change_types(false);
		if (!in_array($_POST['field_type'], $types))
			fatal_lang_error('shd_admin_custom_field_invalid', false);

		// Order??
		$count_query = wesql::query('
			SELECT COUNT(id_field) AS count
			FROM {db_prefix}helpdesk_custom_fields',
			array()
		);

		$row = wesql::fetch_assoc($count_query);

		wesql::insert('insert',
			'{db_prefix}helpdesk_custom_fields',
			array(
				'active' => 'int', 'field_order' => 'int', 'field_name' => 'string', 'field_desc' => 'string',
				'field_loc' => 'int', 'icon' => 'string', 'field_type' => 'int', 'field_length' => 'int',
				'field_options' => 'string', 'bbc' => 'int', 'default_value' => 'string', 'can_see' => 'string',
				'can_edit' => 'string', 'display_empty' => 'int', 'placement' => 'int',
			),
			array(
				$_POST['active'], $row['count'], $_POST['field_name'], $_POST['description'],
				$_POST['field_visible'], $_POST['field_icon'], $_POST['field_type'], $_POST['field_length'],
				$options, $_POST['bbc'], $_POST['default_check'], $can_see,
				$can_edit, $_POST['display_empty'], $_POST['placement'],
			),
			array(
				'id_field',
			)
		);

		$new_field = wesql::insert_id();
		if (empty($new_field))
			fatal_lang_error('shd_admin_could_not_create_field', false);

		// Also update fields
		wesql::query('
			DELETE FROM {db_prefix}helpdesk_custom_fields_depts
			WHERE id_field = {int:field}',
			array(
				'field' => $new_field,
			)
		);
		$fields = array();
		foreach ($context['dept_fields'] as $id => $row)
			$fields[] = array($new_field, $id, $row['required']);

		wesql::insert('replace',
			'{db_prefix}helpdesk_custom_fields_depts',
			array(
				'id_field' => 'int', 'id_dept' => 'int', 'required' => 'int',
			),
			$fields,
			array(
				'id_field', 'id_dept',
			)
		);

		redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);
	}
	// No? Meh. Update it is then.
	else
	{
		// Before we do, just double check the type of data in the field.
		$query = wesql::query('
			SELECT field_type, field_options
			FROM {db_prefix}helpdesk_custom_fields
			WHERE id_field = {int:id_field}',
			array(
				'id_field' => $_REQUEST['field'],
			)
		);
		if ($row = wesql::fetch_assoc($query))
		{
			wesql::free_result($query);
			$types = shd_admin_cf_change_types($row['field_type']);
			if (!in_array($_POST['field_type'], $types))
				fatal_lang_error('shd_admin_custom_field_reselect_invalid', false);
		}
		else
		{
			wesql::free_result($query);
			fatal_lang_error('shd_admin_cannot_edit_custom_field', false);
		}

		// Depending on the field type, we may need to be funky about overlaying things, hence grabbing the old options.
		if (!empty($row['field_options']) && in_array($row['field_type'], array(CFIELD_TYPE_SELECT, CFIELD_TYPE_RADIO, CFIELD_TYPE_MULTI)))
		{
			$row['field_options'] = unserialize($row['field_options']);
			ksort($row['field_options']);
			ksort($newOptions);
			$inactive = array();
			$new_fields = array();
			// First, figure out what fields we had before.
			foreach ($row['field_options'] as $k => $v)
			{
				if ($k == 'inactive')
					continue;
				if (!isset($newOptions[$k]))
					$inactive[] = $k;
				$new_fields[$k] = $v;
			}
			// Now, take any of the new stuff and overwrite the old.
			foreach ($newOptions as $k => $v)
				$new_fields[$k] = $v;
			$new_fields['inactive'] = $inactive;
			$options = serialize($new_fields);
		}

		wesql::query('
			UPDATE {db_prefix}helpdesk_custom_fields
			SET
				active = {int:active}, field_name = {string:field_name},
				field_desc = {string:field_desc}, field_loc = {int:field_visible},
				icon = {string:field_icon}, field_type = {int:field_type},
				field_length = {int:field_length}, field_options = {string:field_options},
				bbc = {int:bbc}, default_value = {string:default_value}, can_see = {string:can_see},
				can_edit = {string:can_edit}, display_empty = {int:display_empty}, placement = {int:placement}
			WHERE id_field = {int:id_field}',
			array(
				'id_field' => $_REQUEST['field'],
				'active' => $_POST['active'],
				'field_name' => $_POST['field_name'],
				'field_desc' => $_POST['description'],
				'field_visible' => $_POST['field_visible'],
				'field_icon' => $_POST['field_icon'],
				'field_type' => $_POST['field_type'],
				'field_length' => $_POST['field_length'],
				'field_options' => $options,
				'bbc' => $_POST['bbc'],
				'default_value' => $_POST['default_check'],
				'can_see' => $can_see,
				'can_edit' => $can_edit,
				'display_empty' => $_POST['display_empty'],
				'placement' => $_POST['placement'],
			)
		);

		// Also update fields
		wesql::query('
			DELETE FROM {db_prefix}helpdesk_custom_fields_depts
			WHERE id_field = {int:field}',
			array(
				'field' => $_REQUEST['field'],
			)
		);
		$fields = array();
		foreach ($context['dept_fields'] as $id => $row)
			$fields[] = array($_REQUEST['field'], $id, $row['required']);

		wesql::insert('replace',
			'{db_prefix}helpdesk_custom_fields_depts',
			array(
				'id_field' => 'int', 'id_dept' => 'int', 'required' => 'int',
			),
			$fields,
			array(
				'id_field', 'id_dept',
			)
		);

		redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);
	}
}

/**
 *	Handle moving a custom field up or down
 *
 *	@since 2.0
*/
function shd_admin_custom_move()
{
	global $context;

	checkSession('get');

	$_REQUEST['field'] = isset($_REQUEST['field']) ? (int) $_REQUEST['field'] : 0;
	$_REQUEST['direction'] = isset($_REQUEST['direction']) && in_array($_REQUEST['direction'], array('up', 'down')) ? $_REQUEST['direction'] : '';

	$query = wesql::query('
		SELECT id_field, field_order
		FROM {db_prefix}helpdesk_custom_fields',
		array()
	);

	if (wesql::num_rows($query) == 0 || empty($_REQUEST['direction']))
	{
		wesql::free_result($query);
		fatal_lang_error('shd_admin_cannot_move_custom_field', false);
	}

	$fields = array();
	while ($row = wesql::fetch_assoc($query))
	{
		$fields[$row['field_order']] = $row['id_field'];
		$fields_map[$row['id_field']] = $row['field_order'];
	}

	ksort($fields);

	if (empty($fields_map[$_REQUEST['field']]))
		fatal_lang_error('shd_admin_cannot_move_custom_field', false);

	$current_pos = $fields_map[$_REQUEST['field']];
	$destination = $current_pos + ($_REQUEST['direction'] == 'up' ? -1 : 1);

	if (empty($fields[$destination]))
		fatal_lang_error('shd_admin_cannot_move_custom_field_' . $_REQUEST['direction'], false);

	$other_field = $fields[$destination];

	wesql::query('
		UPDATE {db_prefix}helpdesk_custom_fields
		SET field_order = {int:new_pos}
		WHERE id_field = {int:field}',
		array(
			'new_pos' => $destination,
			'field' => $_REQUEST['field'],
		)
	);

	wesql::query('
		UPDATE {db_prefix}helpdesk_custom_fields
		SET field_order = {int:old_pos}
		WHERE id_field = {int:other_field}',
		array(
			'old_pos' => $current_pos,
			'other_field' => $other_field,
		)
	);

	redirectexit('action=admin;area=helpdesk_customfield;' . $context['session_var'] . '=' . $context['session_id']);
}

/**
 *	Get possible icons
 *
 *	@return array A list of possible images for the icon selector (everything in $plugindir/images/cf/ that's an image). Each item in the principle array is an array of value/caption pairs.
 *	@since 2.0
*/
function shd_admin_cf_icons()
{
	global $context, $txt;

	static $iconlist = null;

	if ($iconlist !== null)
		return $iconlist;

	$iconlist = array(
		array('', $txt['shd_admin_custom_fields_none']),
	);

	// Open the directory..
	$dir = dir($context['plugins_dir']['Arantor:WedgeDesk'] . '/images/cf/');
	$files = array();

	if (!$dir)
		return $iconlist;

	while ($line = $dir->read())
		$files[] = $line;
	$dir->close();

	// Sort the results...
	natcasesort($files);
	$valid_ext = array('gif', 'jpg', 'jpeg', 'png');

	foreach ($files as $line)
	{
		$filename = substr($line, 0, (strlen($line) - strlen(strrchr($line, '.'))));
		$extension = substr(strrchr($line, '.'), 1);

		// Make sure it is an image.
		$ext = strtolower($extension);
		if (!in_array($ext, $valid_ext))
			continue;

		$iconlist[] = array(htmlspecialchars($filename . '.' . $extension), htmlspecialchars(str_replace('_', ' ', $filename)));
	}

	return $iconlist;
}

/**
 *	This function takes a given type of field and indicates what possible types it could be changed to afterwards.
 *
 *	@param mixed $from_type One of the CFIELD_TYPE constants to indicate the type of field. Alternatively, can be boolean false to return a list of all known types.
 *	@return array An array of CFIELD_TYPE constants indicating the possible field types it can be turned into.
 */
function shd_admin_cf_change_types($from_type)
{
	switch ($from_type)
	{
		case false: // All known types.
			return array(CFIELD_TYPE_TEXT, CFIELD_TYPE_LARGETEXT, CFIELD_TYPE_INT, CFIELD_TYPE_FLOAT, CFIELD_TYPE_SELECT, CFIELD_TYPE_CHECKBOX, CFIELD_TYPE_RADIO, CFIELD_TYPE_MULTI);
		case CFIELD_TYPE_TEXT: // Textbox and large textbox are interchangeable in all practical respects.
		case CFIELD_TYPE_LARGETEXT:
			return array(CFIELD_TYPE_TEXT, CFIELD_TYPE_LARGETEXT);
		case CFIELD_TYPE_INT: // Can always convert an int to a float
			return array(CFIELD_TYPE_INT, CFIELD_TYPE_FLOAT);
		case CFIELD_TYPE_FLOAT: // But you can't safely go back the other way
			return array(CFIELD_TYPE_FLOAT);
		case CFIELD_TYPE_SELECT: // Different ways of showing/selecting the same thing, really - and multiselect is a superset of these.
		case CFIELD_TYPE_RADIO:
			return array(CFIELD_TYPE_SELECT, CFIELD_TYPE_RADIO, CFIELD_TYPE_MULTI);
		case CFIELD_TYPE_CHECKBOX: // And, well, this is all you can do.
			return array(CFIELD_TYPE_CHECKBOX);
		case CFIELD_TYPE_MULTI: // Multiselectors can't become anything else, because you're implicitly holding multiple values.
			return array(CFIELD_TYPE_MULTI);
		default:
			return array();
	}
}

?>