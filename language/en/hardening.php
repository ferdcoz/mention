<?php
// phpBB Mentions maintained translation; GPL-2.0-only.
if (!defined('IN_PHPBB')) { exit; }
$lang = array_merge(isset($lang) ? $lang : [], [
	'MENTION_LIMIT_EXCEEDED' => 'This post exceeds the safety limit of %s unique mention candidates or mention tags. Reduce the mentions.',
	'MENTION_MASS_DENIED' => 'The permission to mention large groups is required for this many recipients.',
	'MENTION_CONFIRM_REQUIRED' => 'Confirm that you want to notify up to %s recipients, then submit again. Changing the message requires a new confirmation.',
	'MENTION_CONFIRM_GROUP' => 'This group has {CNT} active members. Insert the group mention? You must also confirm before submitting.',
	'LOG_MENTION_MASS' => 'Mass mention submitted: post %1$s, %2$s eligible recipients.',
]);
