<?php
namespace paul999\mention\migrations;
class preferences_304 extends \phpbb\db\migration\migration
{
    static public function depends_on() { return ['\\paul999\\mention\\migrations\\hardening_301']; }
    public function update_data()
    {
        return [
            ['config.add', ['simple_mention_background', '', false]],
            ['config.add', ['simple_mention_text', 'ffffff', false]],
            ['config.add', ['simple_mention_email_enabled', 1, false]],
        ];
    }
}
