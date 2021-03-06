<?php

if (!defined('WEDGE'))
	die('Hacking attempt...');

function template_recentitems_infocenter()
{
	global $context, $txt, $settings;

	echo '
		<section class="ic">
			<we:title>
				<a href="<URL>?action=recent"><img src="', ASSETS, '/post/xx.gif"></a>
				', $txt['recent_items_' . $settings['recentitems_posttopic']], '
			</we:title>
			<dl id="ic_recentposts" class="stats">';

		/* Each post in latest_posts has:
			board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
			subject, short_subject (shortened with...), time, link, and href. */
	foreach ($context['latest_posts'] as $post)
		echo '
				<dt><strong>', $post['link'], '</strong> ', $txt['by'], ' ', $post['poster']['link'], ' (', $post['board']['link'], ')</dt>
				<dd>', time_tag($post['timestamp']), '</dd>';

	echo '
			</dl>
		</section>';
}
