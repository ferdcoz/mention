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

namespace paul999\mention\event;

/**
 * @ignore
 */
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\db\driver\driver;
use phpbb\db\driver\driver_interface;
use phpbb\notification\manager;
use phpbb\template\template;
use phpbb\user;
use paul999\mention\hardening\policy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * phpBB mentions Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	/**
	 * @var helper
	 */
	protected $helper;

	/**
	 * @var template
	 */
	protected $template;

	/**
	 * @var driver
	 */
	private $db;

	/**
	 * @var manager
	 */
	private $notification_manager;

	/**
	 * @var user
	 */
	private $user;

	/**
	 * @var array
	 */
	private $mention_data;

	/**
	 * @var auth
	 */
	private $auth;
	/**
	 * @var config
	 */
	private $config;
	/**
	 * @var string
	 */
	private $php_ext;
    private $request;
    private $log;
    private $consent_table;
    private $mention_error;
    private $author_id;
    private $message_hash;
    private $needs_confirmation = false;

	/**
	 * Constructor
	 *
	 * @param helper $helper Controller helper object
	 * @param template $template Template object
	 * @param driver_interface $db
	 * @param manager $notification_manager
	 * @param user $user
	 * @param auth $auth
	 * @param config $config
	 * @param string $php_ext
	 * @internal param viewonline_helper $viewonline_helper
	 */
	public function __construct(helper $helper, template $template, driver_interface $db, manager $notification_manager, user $user, auth $auth, config $config, $php_ext, \phpbb\request\request_interface $request, \phpbb\log\log_interface $log, $table_prefix)
	{
		$this->helper = $helper;
		$this->template = $template;
		$this->db = $db;
		$this->notification_manager = $notification_manager;
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->php_ext = $php_ext;
		$this->request = $request;
		$this->log = $log;
		$this->consent_table = $table_prefix . 'mention_consent';
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.delete_posts_before' => 'delete_post_notifications',
			'core.submit_post_end'                  	=> 'submit_post',
			'core.posting_modify_submission_errors' => 'validate_submission',
			'core.modify_submit_post_data'          	=> 'modify_submit_post',
			'core.approve_posts_after'              	=> 'handle_post_approval',
			'core.permissions'                      	=> 'add_permission',
			'core.user_setup'			            	=> 'load_language_on_setup',
			'core.modify_posting_auth'              	=> 'posting',
			'core.viewtopic_modify_page_title'      	=> 'viewtopic',
			'core.text_formatter_s9e_parse_before'  	=> 'permissions',
			'core.posting_modify_template_vars'     	=> 'remove_mention_in_quote',
			'core.markread_before'                  	=> 'mark_read',
			'rxu.postsmerging.posts_merging_end'		=> 'submit_post',
			'core.page_header'                      	=> 'page_header',
			'core.text_formatter_s9e_configure_after'	=> 'configure_bbcode',
		];
	}

	public function delete_post_notifications($event)
    {
        $types = $event['delete_notifications_types'];
        $types[] = 'paul999.mention.notification.type.mention';
        $event['delete_notifications_types'] = array_values(array_unique($types));
    }

    public function configure_bbcode($event)
	{
		$configurator = $event['configurator'];
		$html = ($this->config['simple_mention_link'])
			? '<a href="./memberlist.php?mode=viewprofile&un={TEXT}" class="mention">@{TEXT}</a>'
			: '<span class="mention">@{TEXT}</span>';
		$configurator->BBCodes->addCustom(
			'[smention u={NUMBER?} g={NUMBER?}]{TEXT}[/smention]',
			$html
		);
	}

	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_can_mention'] = array('lang' => 'ACL_U_CAN_MENTION', 'cat' => 'misc');
		$permissions['u_can_mention_groups'] = array('lang' => 'ACL_U_CAN_MENTION_GROUPS', 'cat' => 'misc');
		$permissions['u_can_mention_large_groups'] = array('lang' => 'ACL_U_CAN_MENTION_LARGE_GROUPS', 'cat' => 'misc');
		$event['permissions'] = $permissions;
	}

	/**
	 * Load common language files during user setup
	 *
	 * @param object $event The event object
	 * @access public
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'paul999/mention',
			'lang_set' => ['common', 'hardening'],
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * @param array $event
	 */
	public function viewtopic($event)
	{
		$s_quick_reply = false;
		if ($this->user->data['is_registered'] && $this->config['allow_quick_reply'] && ($event['topic_data']['forum_flags'] & FORUM_FLAG_QUICK_REPLY) && $this->auth->acl_get('f_reply', $event['forum_id']))
		{
			// Quick reply enabled forum
			$s_quick_reply = (($event['topic_data']['forum_status'] == ITEM_UNLOCKED && $event['topic_data']['topic_status'] == ITEM_UNLOCKED) || $this->auth->acl_get('m_edit', $event['forum_id'])) ? true : false;
		}
		if ($s_quick_reply && $this->auth->acl_get('u_can_mention'))
		{
			$this->template->assign_vars([
				'U_AJAX_MENTION_URL'    => $this->helper->route('paul999_mention_controller', ['t'=>(int) ($event['topic_id'] ?? $event['topic_data']['topic_id'] ?? 0), 'f'=>(int) ($event['forum_id'] ?? 0)], false),
				'MIN_MENTION_LENGTH' => max(2, (int) $this->config['simple_mention_minlength']),
				'MENTION_LARGE_THRESHOLD' => policy::large_threshold($this->config['simple_mention_large_groups']),
			]);
		}
	}

	/**
	 * Mark notifications as read when topics are read,
	 * or when user uses the mark as read function.
	 *
	 * @param array $event
	 */
	public function mark_read($event)
	{
		switch ($event['mode'])
		{
			case 'all':
				$this->mark_all_read($event['post_time']);
			break;

			case 'topics':
				$this->mark_forum_read($event['forum_id'], $event['post_time']);
			break;

			case 'topic':
				$this->mark_topic_read($event['topic_id'], $event['post_time']);
			break;
		}
	}

	/**
	 * Set the mention color on pages.
	 */
	public function page_header()
	{
		$this->template->assign_vars([
			'MENTION_BACKGROUND' => preg_match('/^([a-f0-9]{3}){1,2}$/i', $this->config['simple_mention_background'] ?? '') ? $this->config['simple_mention_background'] : '',
            'MENTION_TEXT' => preg_match('/^([a-f0-9]{3}){1,2}$/i', $this->config['simple_mention_text'] ?? '') ? $this->config['simple_mention_text'] : 'ffffff',
			'MENTION_STYLE' => $this->config['simple_mention_style'],
		]);
	}

	/**
	 * Mark all notifications as read
	 * @param int $post_time
	 */
	private function mark_all_read($post_time)
	{
		$this->notification_manager->mark_notifications([
			'paul999.mention.notification.type.mention',
		], false, $this->user->data['user_id'], $post_time);
	}

	/**
	 * Mark notifications for a topic_id as read
	 * @param int|array $topic_id
	 * @param int $post_time
	 */
	private function mark_topic_read($topic_id, $post_time)
	{
		$this->notification_manager->mark_notifications_by_parent(array(
			'paul999.mention.notification.type.mention',
		), $topic_id, $this->user->data['user_id'], $post_time);
	}

	/**
	 * Mark notifications for forum_id as read
	 * @param int|array $forum_id
	 * @param int $post_time
	 */
	private function mark_forum_read($forum_id, $post_time)
	{
		// Mark all topics in forums read
		if (!is_array($forum_id))
		{
			$forum_id = [$forum_id];
		}
		else
		{
			$forum_id = array_unique($forum_id);
		}

        if (!$forum_id) { return; }
        // Keyset batches preserve manager/backend semantics and bound PHP memory.
        $last = 0;
        do
        {
            $ids = [];
            $sql = 'SELECT topic_id FROM ' . TOPICS_TABLE . ' WHERE ' .
                $this->db->sql_in_set('forum_id', $forum_id) . ' AND topic_id > ' . $last . ' ORDER BY topic_id';
            $result = $this->db->sql_query_limit($sql, policy::READ_BATCH);
            while ($row = $this->db->sql_fetchrow($result)) { $ids[] = (int) $row['topic_id']; }
            $this->db->sql_freeresult($result);
            if ($ids) { $this->mark_topic_read($ids, $post_time); $last = end($ids); }
        } while (count($ids) === policy::READ_BATCH);
	}

	public function posting($event)
	{
		if ($this->auth->acl_get('u_can_mention'))
		{
			$this->template->assign_vars([
			   'U_AJAX_MENTION_URL'		=> $this->helper->route('paul999_mention_controller', ['t'=>(int) ($event['topic_id'] ?? $event['topic_data']['topic_id'] ?? 0), 'f'=>(int) ($event['forum_id'] ?? 0)], false),
				'MIN_MENTION_LENGTH' => max(2, (int) $this->config['simple_mention_minlength']),
				'MENTION_LARGE_THRESHOLD' => policy::large_threshold($this->config['simple_mention_large_groups']),
			]);
		}
	}

	/**
	 * @param array $event
	 */
	public function permissions($event)
	{
		$disable = false;
		if (!$this->auth->acl_get('u_can_mention'))
		{
			$disable = true;
		}

		if ($this->user->page['page_name'] != 'posting.' . $this->php_ext)
		{
			// Only enable mention BBCode on posting page.
			$disable = true;
		}

		if ($disable)
		{
			$event['parser']->disable_bbcode('mention');
			$event['parser']->disable_bbcode('smention');
		}
	}

	/**
	 * Remove mention BBCode from quote.
	 * @param array $event
	 */
	public function remove_mention_in_quote($event)
	{
		if ($event['submit'] || $event['preview'] || $event['refresh'] || $event['mode'] != 'quote' || !isset($event['page_data']) || !isset($event['page_data']['MESSAGE']))
		{
			return;
		}
		$page_data = $event['page_data'];
		$page_data['MESSAGE'] = preg_replace('#\[mention\](.*?)\[\/mention\]#uis', '@\\1', $page_data['MESSAGE']);
		$page_data['MESSAGE'] = preg_replace('#\[smention u=([0-9]+)\](.*?)\[\/smention\]#uis', '@\\2', $page_data['MESSAGE']);
		$page_data['MESSAGE'] = preg_replace('#\[smention g=([0-9]+)\](.*?)\[\/smention\]#uis', '@\\2', $page_data['MESSAGE']);
		$event['page_data'] = $page_data;
	}

	/**
	 * @param array $event
	 */
    public function validate_submission($event)
    {
        if (!$event['submit'] || !in_array($event['mode'], ['post', 'reply', 'quote']) || !$this->auth->acl_get('u_can_mention')) { return; }
        global $message_parser;
        $message = $message_parser->message;
        $this->parse_message($message, $event['forum_id']);
        $errors = $event['error'];
        if ($this->mention_error) { $errors[] = $this->user->lang($this->mention_error, policy::MAX_RECIPIENTS); }
        elseif ($this->needs_confirmation && !$this->confirmed($message, $event['forum_id']))
        {
            $errors[] = $this->user->lang('MENTION_CONFIRM_REQUIRED', count($this->mention_data));
            $this->template->assign_vars([
                'MENTION_CONFIRM_TOKEN' => policy::confirmation($message, $event['forum_id'], count($this->mention_data), $this->user->session_id),
                'MENTION_CONFIRM_COUNT' => count($this->mention_data),
            ]);
        }
        $event['error'] = $errors;
    }

    private function confirmed($message, $forum_id)
    {
        return $this->request->variable('mention_confirm', 0) === 1 && hash_equals(
            policy::confirmation($message, $forum_id, count($this->mention_data), $this->user->session_id),
            $this->request->variable('mention_confirm_token', '')
        );
    }

	public function modify_submit_post($event)
	{
		$this->mention_data = [];
        $this->needs_confirmation = false;
        $this->mention_error = null;
        $handle = ['post', 'reply', 'quote'];

		if (!in_array($event['mode'], $handle) || !$this->auth->acl_get('u_can_mention'))
		{
			return;
		}

		$this->parse_message($event['data']['message'], $event['data']['forum_id']);
        // Guard direct submit_post callers too, before any post is stored.
        if ($this->mention_error) { trigger_error($this->user->lang($this->mention_error, policy::MAX_RECIPIENTS)); }
        if ($this->needs_confirmation && !$this->confirmed($event['data']['message'], $event['data']['forum_id']))
        { trigger_error($this->user->lang('MENTION_CONFIRM_REQUIRED', count($this->mention_data))); }
	}

	public function handle_post_approval($event)
	{
		if ($event['action'] != 'approve')
		{
			return;
		}

		$posts = [];
		foreach ($event['post_info'] as $post_id => $post_data)
		{
			$posts[] = $post_id;
		}

		if (!$posts) { return; }

		$sql = 'SELECT p.poster_id, p.post_text, p.post_id, t.topic_id, t.forum_id, t.topic_title 
				  FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t
				  WHERE t.topic_id = p.topic_id
						AND ' . $this->db->sql_in_set('p.post_id', $posts);
		$result = $this->db->sql_query($sql);

		$data = [];
		$users = [];
		$userdata = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data[] = $row;
			$users[] = (int) $row['poster_id'];
		}
		$this->db->sql_freeresult($result);

		if (!$users) { return; }

		$sql = 'SELECT username, user_id, user_permissions, user_type 
				  FROM ' . USERS_TABLE . ' 
				  WHERE ' . $this->db->sql_in_set('user_id', $users);
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$userdata[$row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		foreach ($data as $row)
		{
			$this->mention_data = [];

			if (!isset($userdata[$row['poster_id']]) || !in_array((int) $userdata[$row['poster_id']]['user_type'], [USER_NORMAL, USER_FOUNDER], true)) { continue; }
			$local_auth = new auth();
			$local_auth->acl($userdata[$row['poster_id']]);

			if (!$local_auth->acl_get('u_can_mention'))
			{
				continue;
			}
			$this->parse_message($row['post_text'], $row['forum_id'], false, $local_auth, (int) $row['poster_id']);
            if ($this->mention_error) { continue; }
            if ($this->needs_confirmation)
            {
                $result = $this->db->sql_query('SELECT message_hash, recipient_count FROM ' . $this->consent_table . ' WHERE post_id = ' . (int) $row['post_id']);
                $consent = $this->db->sql_fetchrow($result);
                $this->db->sql_freeresult($result);
                if (!$consent || !hash_equals($consent['message_hash'], $this->message_hash) || count($this->mention_data) > (int) $consent['recipient_count']) { continue; }
            }

			if (count($this->mention_data))
			{
				$insert = [
					'post_id'       => $row['post_id'],
					'username'      => $userdata[$row['poster_id']]['username'],
					'user_id'       => $row['poster_id'],
					'topic_id'      => $row['topic_id'],
					'topic_title'   => $row['topic_title'],
				];
				$this->send_notification($insert);
			}

		}

	}

	public function submit_post($event)
	{
        if (isset($this->mention_data) && $this->needs_confirmation && !$this->mention_error)
        {
            $post_id = (int) $event['data']['post_id'];
            $this->db->sql_query('DELETE FROM ' . $this->consent_table . ' WHERE post_id = ' . $post_id);
            $this->db->sql_query('INSERT INTO ' . $this->consent_table . ' ' . $this->db->sql_build_array('INSERT', [
                'post_id' => $post_id, 'message_hash' => $this->message_hash, 'recipient_count' => count($this->mention_data),
            ]));
        }
		if ($event['post_visibility'] == ITEM_APPROVED && isset($this->mention_data))
		{
			$data = $event['data'];
			$data['username'] = $this->user->data['username'];
			$data['user_id'] = $this->user->data['user_id'];
			$this->send_notification($data);
		}
	}

	/**
	 * @param string $message
	 * @param int $forum_id
	 * @param bool $current
	 */
	private function parse_message($message, $forum_id, $current = true, ?auth $local_auth = null, $author_id = null)
	{
		if ($local_auth === null)
		{
			$local_auth = $this->auth;
		}
		$this->author_id = $author_id === null ? (int) $this->user->data['user_id'] : (int) $author_id;
        $this->message_hash = hash('sha256', $message);
        $this->mention_error = null;
        $this->needs_confirmation = false;
        if (substr_count($message, '[smention ') + substr_count($message, '[mention]') > policy::MAX_TAGS)
        { $this->mention_data = []; $this->mention_error = 'MENTION_LIMIT_EXCEEDED'; return; }
        $matches = [];
		$mentions = [];
		$this->mention_data = [];

		// Old style BBCode.
		if (preg_match_all('#\[mention\]<\/s>(.*?)<e>\[\/mention\]#', $message, $matches, PREG_OFFSET_CAPTURE) !== 0)
		{
			$data = [];

			for ($i = 0; $i < count($matches[1]); $i++)
			{
				$data[] = utf8_clean_string($matches[1][$i][0]);
			}

			$sql = 'SELECT user_id, username, user_permissions, user_type
				FROM ' . USERS_TABLE . '
				WHERE ' . $this->db->sql_in_set('username_clean', $data);
			$result = $this->db->sql_query($sql);
			$data = $this->getUserData($result, $mentions);
			$this->db->sql_freeresult($result);
			$this->handle_matches($data, $forum_id, $current);
		}

		$matches = [];
		if (preg_match_all('#\[smention u=([0-9]+)\]<\/s>(.*?)<e>\[\/smention\]#', $message, $matches, PREG_OFFSET_CAPTURE) !== 0)
		{
			$data = [];

			for ($i = 0; $i < count($matches[1]); $i++)
			{
				$data[] = $matches[1][$i][0];
			}

			$sql = 'SELECT user_id, username, user_permissions, user_type
				FROM ' . USERS_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', $data);
			$result = $this->db->sql_query($sql);
			$data = $this->getUserData($result, $mentions);
			$this->db->sql_freeresult($result);
			$this->handle_matches($data, $forum_id, $current);
		}

		if ($local_auth->acl_get('u_can_mention_groups') && preg_match_all('#\[smention g=([0-9]+)\]<\/s>(.*?)<e>\[\/smention\]#', $message, $matches, PREG_OFFSET_CAPTURE) !== 0)
		{
			// We are going to mention an group.
			$data = [];

			for ($i = 0; $i < count($matches[1]); $i++)
			{
				$data[] = $matches[1][$i][0];
			}

            $sql = 'SELECT COUNT(DISTINCT u.user_id) as cnt, ug.group_id
                FROM ' . USER_GROUP_TABLE . ' ug, ' . USERS_TABLE . ' u, ' . GROUPS_TABLE . ' g
                WHERE ug.user_pending = 0 AND ug.user_pending = 0
						AND u.user_id = ug.user_id AND g.group_id = ug.group_id
                AND g.group_type <> ' . GROUP_HIDDEN . '
                AND ' . $this->db->sql_in_set('g.group_name', ['GUESTS', 'BOTS'], true) . '
                AND u.user_id <> ' . ANONYMOUS . '
                AND ' . $this->db->sql_in_set('u.user_type', [USER_NORMAL, USER_FOUNDER]) . '
                AND ' . $this->db->sql_in_set('ug.group_id', array_unique($data)) . ' GROUP BY ug.group_id';
            $result = $this->db->sql_query($sql);
            $data = [];
            while ($row = $this->db->sql_fetchrow($result))
            {
                if ($row['cnt'] > policy::MAX_RECIPIENTS) { $this->mention_error = 'MENTION_LIMIT_EXCEEDED'; continue; }
                if ($row['cnt'] > policy::large_threshold($this->config['simple_mention_large_groups']))
                {
                    if (!$local_auth->acl_get('u_can_mention_large_groups')) { continue; }
                    $this->needs_confirmation = true;
                }
                $data[] = (int) $row['group_id'];
            }
            $this->db->sql_freeresult($result);
			if (count($data) > 0)
			{
				$sql = 'SELECT DISTINCT u.user_id, u.username, u.user_permissions, u.user_type
				FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . ' ug, ' . GROUPS_TABLE . ' g 
				WHERE 
						g.group_id = ug.group_id
						AND g.group_type <> ' . GROUP_HIDDEN . '
						AND ' . $this->db->sql_in_set('g.group_name', ['GUESTS', 'BOTS'], true) . '
						AND ug.user_pending = 0
						AND u.user_id = ug.user_id 
						AND u.user_id <> ' . ANONYMOUS . '
						AND ' . $this->db->sql_in_set('u.user_type', [USER_NORMAL, USER_FOUNDER]) . '
						AND ' . $this->db->sql_in_set('ug.group_id', $data);
				$result = $this->db->sql_query_limit($sql, policy::MAX_RECIPIENTS + 1);
				$data = $this->getUserData($result, $mentions);

				$this->db->sql_freeresult($result);
				$this->handle_matches($data, $forum_id, $current);
			}
		}
        if (count($mentions) > policy::MAX_RECIPIENTS) { $this->mention_error = 'MENTION_LIMIT_EXCEEDED'; }
        if (count($this->mention_data) > policy::large_threshold($this->config['simple_mention_large_groups']))
        {
            if (!$local_auth->acl_get('u_can_mention_large_groups')) { $this->mention_error = 'MENTION_MASS_DENIED'; }
            $this->needs_confirmation = true;
        }
        if ($this->mention_error) { $this->mention_data = []; }

	}

	/**
	 * @param $result
	 * @param array $mentions
	 * @return array
	 */
	private function getUserData($result, array &$mentions): array
	{
		$data = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['user_id'] != ANONYMOUS && in_array((int) $row['user_type'], [USER_NORMAL, USER_FOUNDER], true) && !isset($mentions[(int) $row['user_id']]))
			{
				$mentions[(int) $row['user_id']] = true;
                if (count($mentions) > policy::MAX_RECIPIENTS) { break; }
				$data[] = $row;
			}
		}
		return $data;
	}

	private function handle_matches(array $data, int $forum_id, $current)
	{
		$authCache = [];
		if (count($data))
		{
			foreach ($data as $row)
			{
				if ($this->author_id == $row['user_id'])
				{
					continue; // Do not send notification to current user.
				}
				if (!isset($authCache[$row['user_id']]))
				{
					// Not cached yet.
					$auth = new auth();
					$auth->acl($row);
					$authCache[$row['user_id']] = $auth->acl_get('f_read', $forum_id);
				}

				if ($authCache[$row['user_id']])
				{
					// Only do the mention when the user is able to read the forum
					$this->mention_data[] = (int) $row['user_id'];
				}
			}
		}
	}

	/**
	 * @param $data
	 */
	private function send_notification($data)
	{
        if (!$this->mention_data || $this->mention_error) { return; }
        if ($this->needs_confirmation)
        {
            // No recipient names, emails, group names or message text in audit data.
            $this->log->add('admin', (int) $data['user_id'], '', 'LOG_MENTION_MASS', false,
                [(int) $data['post_id'], count($this->mention_data)]);
        }
		$this->notification_manager->add_notifications('paul999.mention.notification.type.mention', [
			'user_ids' => $this->mention_data,
			'notification_id' => $data['post_id'],
			'username' => $data['username'],
			'poster_id' => $data['user_id'],
			'post_id' => $data['post_id'],
			'topic_id' => $data['topic_id'],
			'topic_title' => $data['topic_title'],
		],
		[
			'user_ids' => $this->mention_data,
		]);
	}

}
