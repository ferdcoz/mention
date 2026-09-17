<?php
/**
 *
 * phpBB mentions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Joe and Moe, https://github.com/MoeMorox/mention
 * @copyright (c) 2016, paul999, https://www.phpbbextensions.io
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'MENTION_LENGTH'                => 'Simple mention minimum length',
	'MENTION_LENGTH_EXPLAIN'        => 'The minimum text length before the simple mention dropdown is shown. On larger boards you might want to increase this value.',
	'MENTION_COLOR'                 => 'Mention color',
	'MENTION_COLOR_EXPLAIN'         => 'This color is used within the post to define what user is mentioned. Only hex values can be used.',
	'MENTION_COLOR_INVALID'         => 'The selected mention color (%s) is invalid. Please select a valid HEX color, without #',
	'MENTION_STYLE'					=> 'Mention font style',
	'MENTION_STYLE_EXPLAIN'			=> 'The font style that is applied to mentions within posts.',
	'MENTION_STYLE_INVALID'			=> 'The selected mention font style (%s) is invalid.',
	'MENTION_STYLE_NONE'			=> 'None',
	'MENTION_STYLE_ITALIC'			=> 'Italic',
	'MENTION_STYLE_BOLD'			=> 'Bold',
	'MENTION_STYLE_ITALIC_BOLD'		=> 'Bold and italic',
	'MENTION_MAX_RESULTS'			=> 'Mention max results',
	'MENTION_MAX_RESULTS_EXPLAIN'	=> 'The maximum number of users show in the dropdown. On larger boards you might want to decrease this value',
	'MENTION_LARGE_GROUPS'			=> 'Mention large group size',
	'MENTION_LARGE_GROUPS_EXPLAIN'	=> 'If the group has more members as the specified number, the “Can mention large groups” permission is required.',
	'MENTION_LINK'					=> 'Profile link',
	'MENTION_LINK_EXPLAIN'			=> 'When enabled, mentioned users are linked to their profile. When disabled, mentions are displayed as plain text.'
));

$lang['MENTION_LARGE_GROUPS_EXPLAIN'] = 'Mass mention threshold (1–50), also applied to combined recipients per post. Absolute limits: 500 unique candidates and 500 tags.';

$lang = array_merge($lang, [
    'MENTION_BACKGROUND' => 'Tag background colour',
    'MENTION_BACKGROUND_EXPLAIN' => 'Hexadecimal with or without #. Leave empty to use the active theme colour.',
    'MENTION_TEXT' => 'Tag text colour',
    'MENTION_EMAIL' => 'Mention notification channels',
    'MENTION_EMAIL_EXPLAIN' => 'Board only blocks mention emails. Allow email respects each user’s choice in User Control Panel → Board preferences → Edit notification options → Mentions. It does not automatically enable email or change existing preferences.',
    'MENTION_BOARD_ONLY' => 'Board notifications only',
    'MENTION_ALLOW_EMAIL' => 'Allow email according to user preference',
]);

$lang = array_merge($lang, [
    'MENTION_PREVIEW' => 'Preview',
    'MENTION_PREVIEW_EXPLAIN' => 'Updates as you edit, before saving. With an empty background the final colour depends on the forum theme; a reference colour is shown here.',
    'MENTION_PREVIEW_SAMPLE' => 'this is how the mention will appear in a message.',
    'MENTION_PREVIEW_INVALID' => 'Enter valid hexadecimal colours to update the preview.',
]);
