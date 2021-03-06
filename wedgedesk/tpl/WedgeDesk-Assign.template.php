<?php
/**
 * WedgeDesk
 *
 * Displays the interface for dealing with ticket assignments.
 *
 * @package wedgedesk
 * @copyright 2011 Peter Spicer, portions SimpleDesk 2010-11 used under BSD licence
 * @license http://wedgedesk.com/index.php?action=license
 *
 * @since 1.0
 * @version 1.0
 */

/**
 *	Displays the list of possible users a ticket can have assigned.
 *
 *	Will have been populated by shd_assign() in WedgeDesk-Assign.php, adding into $context['member_list'].
 *
 *	This allows users to assign tickets to other users, or themselves, or to unassign a previously assigned ticket. Future versions will
 *	likely add further options here.
 *
 *	@see shd_assign()
 *	@since 1.0
*/
function template_assign()
{
	global $context, $txt;

	if (empty($context['shd_return_to']))
		$context['shd_return_to'] = 'ticket';

	// Back to the helpdesk.
	echo '
		<div class="pagesection">', template_button_strip(array($context['navigation']['back']), 'left'), '</div>';

	echo '
	<we:cat>
		<img src="', $context['plugins_url']['Arantor:WedgeDesk'], '/images/assign.png">
		', $txt['shd_ticket_assign_ticket'], '
	</we:cat>
	<div class="roundframe">
		<form action="<URL>?action=helpdesk;sa=assign2;ticket=', $context['ticket_id'], '" method="post" onsubmit="submitonce(this);">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_ticket_assignedto'], ':</strong>
					</dt>
					<dd>
						', $context['member_list'][$context['ticket_assigned']], '
					</dd>
					<dt>
						<strong>', $txt['shd_ticket_assign_to'], ':</strong>
					</dt>
					<dd>
						<select name="to_user">';

	foreach ($context['member_list'] as $id => $name)
		echo '
							<option value="', $id, '"', ($id == $context['ticket_assigned'] ? ' selected="selected"' : ''), '>', $name, '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<input type="submit" name="cancel" value="', ($context['shd_return_to'] == 'home' ? $txt['shd_cancel_home'] : $txt['shd_cancel_ticket']), '" accesskey="c" class="cancel">
					</dt>
					<dd>
						<input type="submit" value="', $txt['shd_ticket_assign_ticket'], '" onclick="return submitThisOnce(this);" accesskey="s" class="submit">
					</dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	if ($context['shd_return_to'] == 'home')
		echo '
				<input type="hidden" name="home" value="1">';

	echo '
			</div>
		</form>
	</div>';
}

?>