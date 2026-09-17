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

namespace paul999\mention\controller;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\exception\http_exception;
use phpbb\request\request_interface;
use phpbb\user;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * phpBB mentions main controller.
 */
class main
{
	/**
	 * @var user
	 */
	protected $user;

	/**
	 * @var driver_interface
	 */
	private $db;

	/**
	 * @var auth
	 */
	private $auth;

	/**
	 * @var request_interface
	 */
	private $request;

	/**
	 * @var config
	 */
	private $config;
	private $rate_limiter;

	/**
	 * Constructor
	 *
	 * @param user $user
	 * @param driver_interface $db
	 * @param auth $auth
	 * @param request_interface $request
	 * @param config $config
	 */
	public function __construct(user $user, driver_interface $db, auth $auth, request_interface $request, config $config, \paul999\mention\hardening\rate_limiter $rate_limiter)
	{
		$this->user = $user;
		$this->db = $db;
		$this->auth = $auth;
		$this->request = $request;
		$this->config = $config;
		$this->rate_limiter = $rate_limiter;
	}

	/**
	 * get a list of users matching on a username (Minimal 3 chars)
	 *
	 *
	 * @return JsonResponse A Symfony Response object
	 */
	public function handle() : JsonResponse
	{
		if ($this->user->data['user_id'] == ANONYMOUS || $this->user->data['is_bot'] || !$this->auth->acl_get('u_can_mention'))
		{
			throw new http_exception(401);
		}
		if (!$this->rate_limiter->consume($this->user->data['user_id']))
		{
			return new JsonResponse([], 429, ['Retry-After' => '10', 'Cache-Control' => 'no-store']);
		}
		$name = utf8_clean_string($this->request->variable('q', '', true));

		if (utf8_strlen($name) < max(2, (int) $this->config['simple_mention_minlength']) || utf8_strlen($name) > 255)
		{
			return new JsonResponse([]);
		}

        $limit = min(50, max(1, (int) $this->config['simple_mention_maxresults']));
        $return = [];
        $selected = [];
        $topic_id = max(0, $this->request->variable('t', 0));
        $readable_topic = false;
        $forum_id = 0;
        if ($topic_id)
        {
            $result = $this->db->sql_query('SELECT forum_id, topic_visibility FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . $topic_id);
            $topic = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
            if ($topic)
            {
                $forum_id = (int) $topic['forum_id'];
                // Global announcements require an explicitly readable viewing forum.
                if (!$forum_id) { $forum_id = max(0, $this->request->variable('f', 0)); }
                $readable_topic = $forum_id && $this->auth->acl_get('f_read', $forum_id) &&
                    ((int) $topic['topic_visibility'] === ITEM_APPROVED || $this->auth->acl_get('m_approve', $forum_id));
            }
        }
        $where = 'u.user_id <> ' . ANONYMOUS . ' AND ' .
            $this->db->sql_in_set('u.user_type', [USER_NORMAL, USER_FOUNDER]) .
            ' AND u.username_clean ' . $this->db->sql_like_expression($this->db->get_any_char() . $name . $this->db->get_any_char());
        if ($readable_topic)
        {
            $visible = 'p.post_visibility = ' . ITEM_APPROVED;
            if ($this->auth->acl_get('m_approve', $forum_id)) { $visible = '1 = 1'; }
            $sql = 'SELECT u.user_id, u.username FROM ' . USERS_TABLE . ' u WHERE ' . $where .
                ' AND EXISTS (SELECT 1 FROM ' . POSTS_TABLE . ' p WHERE p.poster_id = u.user_id AND p.topic_id = ' . $topic_id . ' AND ' . $visible . ')' .
                ' ORDER BY u.username_clean, u.user_id';
            $result = $this->db->sql_query_limit($sql, $limit);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $selected[] = (int) $row['user_id'];
                $return[] = ['key'=>$row['username'], 'value'=>$row['username'], 'user_id'=>$row['user_id'], 'type'=>'user', 'topic_participant'=>true];
            }
            $this->db->sql_freeresult($result);
        }
        if (count($return) < $limit)
        {
            $sql = 'SELECT u.user_id, u.username FROM ' . USERS_TABLE . ' u WHERE ' . $where;
            if ($selected) { $sql .= ' AND ' . $this->db->sql_in_set('u.user_id', $selected, true); }
            $sql .= ' ORDER BY u.username_clean, u.user_id';
            $result = $this->db->sql_query_limit($sql, $limit - count($return));
            while ($row = $this->db->sql_fetchrow($result))
            {
                $return[] = ['key'=>$row['username'], 'value'=>$row['username'], 'user_id'=>$row['user_id'], 'type'=>'user', 'topic_participant'=>false];
            }
            $this->db->sql_freeresult($result);
        }

		if (count($return) < $limit && $this->auth->acl_get('u_can_mention_groups'))
		{
			// We can mention groups. So also add that to the list.

			$sql = 'SELECT COUNT(ug.user_id) as cnt, g.group_name, g.group_id, g.group_type
				FROM '. USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g, ' . USERS_TABLE . ' u
				WHERE 
						g.group_id = ug.group_id
						AND ug.user_pending = 0
						AND u.user_id = ug.user_id
						AND u.user_id <> ' . ANONYMOUS . '
						AND ' . $this->db->sql_in_set('u.user_type', [USER_NORMAL, USER_FOUNDER]) . '
						AND g.group_type <> ' . GROUP_HIDDEN . '
						AND ' . $this->db->sql_in_set('g.group_name', ['GUESTS', 'BOTS'], true) . '
						AND (
								LOWER(g.group_name) ' . $this->db->sql_like_expression($this->db->get_any_char() . strtolower($name) . $this->db->get_any_char()) . '
								OR g.group_type = ' . GROUP_SPECIAL . ' 
							) 
				GROUP BY g.group_id, g.group_name, g.group_type';

			$result = $this->db->sql_query_limit($sql, $limit - count($return), 0);

			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['cnt'] > \paul999\mention\hardening\policy::MAX_RECIPIENTS || ($row['cnt'] > \paul999\mention\hardening\policy::large_threshold($this->config['simple_mention_large_groups']) && !$this->auth->acl_get('u_can_mention_large_groups')))
				{
					// User can't send mentions to large groups.
					continue;
				}
                $display_name = $row['group_type'] == GROUP_SPECIAL ? $this->user->lang('G_' . $row['group_name']) : $row['group_name'];
                if (strpos(utf8_clean_string($display_name), $name) === false) { continue; }
				$return[] = [
					'key'       => $row['group_type'] == GROUP_SPECIAL ? $this->user->lang('G_' . $row['group_name']) : $row['group_name'],
					'value'     => $row['group_type'] == GROUP_SPECIAL ? $this->user->lang('G_' . $row['group_name']) : $row['group_name'],
					'group_id'	=> $row['group_id'],
					'cnt'		=> $row['cnt'],
					'type'		=> 'group',
				];
			}
			$this->db->sql_freeresult($result);
		}

		return new JsonResponse(array_slice($return, 0, min(50, max(1, (int) $this->config['simple_mention_maxresults']))), 200, ['Cache-Control' => 'no-store']);
	}
}
