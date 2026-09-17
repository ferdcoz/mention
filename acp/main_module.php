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

namespace paul999\mention\acp;

/**
 * phpBB mentions ACP module.
 */
class main_module
{
	/** @var string $page_title */
	public $page_title;

	/** @var string $tpl_name */
	public $tpl_name;

	/** @var string $u_action */
	public $u_action;

	/**
	 * Main ACP module
	 */
	public function main()
	{
		global $config, $request, $template, $user, $phpbb_log;

		$this->tpl_name = 'acp_mention_settings';
		$this->page_title = $user->lang('ACP_MENTION_SETTINGS');

		$user->add_lang_ext('paul999/mention', 'acp_common');

		$form_key = 'paul999_mention_settings';
		add_form_key($form_key);

		$errors = [];

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID');
			}

			$minlength = min(255, max(2, $request->variable('simple_mention_minlength', 2)));
			$maxresults = min(50, max(1, $request->variable('simple_mention_maxresults', 25)));
			$large_groups = \paul999\mention\hardening\policy::large_threshold($request->variable('simple_mention_large_groups', 50));
			$color = strtolower(ltrim($request->variable('simple_mention_text', 'ffffff'), '#'));
            $background = strtolower(ltrim($request->variable('simple_mention_background', ''), '#'));
            $email_enabled = $request->variable('simple_mention_email_enabled', false);
			$link = $request->variable('simple_mention_link', 0);
			$style = $request->variable('simple_mention_style', 'italic');

			if (!preg_match('/^([a-f0-9]{3}){1,2}$/', $color) || ($background !== '' && !preg_match('/^([a-f0-9]{3}){1,2}$/', $background)))
			{
				$errors[] = $user->lang('MENTION_COLOR_INVALID', $color);
			}

			if (!in_array($style, ['none', 'italic', 'bold', 'italic_bold']))
			{
				$errors[] = $user->lang('MENTION_STYLE_INVALID', $style);
			}

			if (!count($errors))
			{
				$old_link = $config['simple_mention_link'];

				$config->set('simple_mention_minlength', $minlength);
				$config->set('simple_mention_maxresults', $maxresults);
				$config->set('simple_mention_text', $color);
                $config->set('simple_mention_background', $background);
                $config->set('simple_mention_email_enabled', (int) $email_enabled);
				$config->set('simple_mention_large_groups', $large_groups);
				$config->set('simple_mention_link', $link);
				$config->set('simple_mention_style', $style);

				if ($old_link != $link)
				{
					global $phpbb_container;
					$phpbb_container->get('text_formatter.cache')->invalidate();
				}

				$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_MENTION_SETTINGS');

				trigger_error($user->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
			}
		}

		$template->assign_vars([
			'SIMPLE_MENTION_MINLENGTH'		=> $config['simple_mention_minlength'],
			'SIMPLE_MENTION_MAXRESULTS'		=> $config['simple_mention_maxresults'],
			'SIMPLE_MENTION_TEXT' => $config['simple_mention_text'] ?? 'ffffff',
            'SIMPLE_MENTION_BACKGROUND' => $config['simple_mention_background'] ?? '',
            'SIMPLE_MENTION_EMAIL_ENABLED' => (bool) ($config['simple_mention_email_enabled'] ?? 1),
			'SIMPLE_MENTION_LARGE_GROUPS'	=> $config['simple_mention_large_groups'],
			'SIMPLE_MENTION_LINK'			=> (bool) $config['simple_mention_link'],
			'SIMPLE_MENTION_STYLE'			=> $config['simple_mention_style'],
			'ERROR_MSG'						=> (count($errors)) ? implode('<br>', $errors) : '',
			'U_ACTION'						=> $this->u_action,
		]);
	}
}
