<?php
namespace paul999\mention\hardening;

/** One row per account; conditional SQL updates serialize concurrent callers. */
class rate_limiter
{
	private $db;
	private $table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $prefix)
	{
		$this->db = $db;
		$this->table = $prefix . 'mention_rate';
	}

	public function consume($user_id, $now = null)
	{
		$user_id = (int) $user_id;
		$now = $now === null ? time() : (int) $now;
		$window = $now - ($now % policy::AJAX_WINDOW);
		$this->db->sql_query('UPDATE ' . $this->table . ' SET window_start = ' . $window . ', hits = 1
			WHERE user_id = ' . $user_id . ' AND window_start < ' . $window);
		if ($this->db->sql_affectedrows())
		{
			return true;
		}
		if ($this->increment($user_id, $window)) { return true; }
		$result = $this->db->sql_query('SELECT user_id FROM ' . $this->table . ' WHERE user_id = ' . $user_id);
		$exists = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($exists) { return false; }
		// Only a first-ever request needs insertion; concurrent first requests may race.
		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query('INSERT INTO ' . $this->table . ' ' .
			$this->db->sql_build_array('INSERT', ['user_id' => $user_id, 'window_start' => $window, 'hits' => 1]));
		$this->db->sql_return_on_error(false);
		return $result !== false || $this->increment($user_id, $window);
	}

	private function increment($user_id, $window)
	{
		$this->db->sql_query('UPDATE ' . $this->table . ' SET hits = hits + 1
			WHERE user_id = ' . $user_id . ' AND window_start = ' . $window . '
			AND hits < ' . policy::AJAX_REQUESTS);
		return $this->db->sql_affectedrows() === 1;
	}
}
