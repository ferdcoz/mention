<?php
namespace paul999\mention\hardening;

/** Absolute safety bounds intentionally independent of ACP and ACL. */
class policy
{
	const MAX_RECIPIENTS = 500;
	const MAX_TAGS = 500;
	const LARGE_THRESHOLD = 50;
	const AJAX_REQUESTS = 30;
	const AJAX_WINDOW = 10;
	const READ_BATCH = 250;

	public static function large_threshold($configured)
	{
		return min(self::LARGE_THRESHOLD, max(1, (int) $configured));
	}

	public static function confirmation($message, $forum_id, $count, $session_id)
	{
		return hash_hmac('sha256', $forum_id . ':' . $count . ':' . hash('sha256', $message), $session_id);
	}
}
