<?php
namespace paul999\mention\migrations;

class hardening_301 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\\paul999\\mention\\migrations\\version_300'];
	}

	public function update_schema()
	{
		return ['add_tables' => [
			$this->table_prefix . 'mention_rate' => [
				'COLUMNS' => ['user_id' => ['UINT', 0], 'window_start' => ['TIMESTAMP', 0], 'hits' => ['UINT', 0]],
				'PRIMARY_KEY' => 'user_id',
			],
			$this->table_prefix . 'mention_consent' => [
				'COLUMNS' => ['post_id' => ['UINT', 0], 'message_hash' => ['VCHAR:64', ''], 'recipient_count' => ['UINT', 0]],
				'PRIMARY_KEY' => 'post_id',
			],
		]];
	}

	public function revert_schema()
	{
		return ['drop_tables' => [$this->table_prefix . 'mention_rate', $this->table_prefix . 'mention_consent']];
	}
}
