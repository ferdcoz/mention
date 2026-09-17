<?php
require __DIR__ . '/tests.php';
class preference_manager extends fixture_manager
{
    public function get_default_methods() { return ['notification.method.board']; }
}
class preference_mention extends \paul999\mention\notification\type\mention
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->user_notifications_table = 'test_user_notifications';
        $this->notification_manager = new preference_manager();
    }
}
$db->sql_query('CREATE TABLE test_user_notifications (user_id INT, method VARCHAR(100), notify INT, item_type VARCHAR(100), item_id INT)');
$type = 'paul999.mention.notification.type.mention';
foreach ([[100,'board',1],[100,'email',1],[101,'board',0],[101,'email',0],[102,'email',1]] as $row)
{
    $db->sql_query('INSERT INTO test_user_notifications ' . $db->sql_build_array('INSERT', ['user_id'=>$row[0], 'method'=>'notification.method.'.$row[1], 'notify'=>$row[2], 'item_type'=>$type, 'item_id'=>0]));
}
$notification = new preference_mention($db);
$settings = new \phpbb\config\config(['simple_mention_email_enabled'=>1]);
$notification->set_config($settings);
$result = $notification->find_users_for_notification([], ['user_ids'=>[100,101,102,103]]);
check($result[100] === ['notification.method.board','notification.method.email'], 'user can opt in to board and email');
check($result[101] === [], 'explicit user opt out respected');
check($result[103] === ['notification.method.board'], 'new user defaults to board without email');
$settings['simple_mention_email_enabled'] = 0;
$result = $notification->find_users_for_notification([], ['user_ids'=>[100,101,102,103]]);
check($result[100] === ['notification.method.board'] && $result[102] === ['notification.method.board'], 'ACP board only blocks user email subscriptions');
check(!isset($result[101]), 'ACP setting does not override board opt out');
check($notification->get_email_template() === false, 'email template unavailable when globally blocked');
$settings['simple_mention_email_enabled'] = 1;
check($notification->get_email_template() === '@paul999_mention/mention_mail', 'email template restored when allowed');
$db->sql_query('DROP TABLE test_user_notifications');
echo "All notification preference tests passed; no emails sent.\n";
$delete_event = new \phpbb\event\data(['delete_notifications_types'=>['notification.type.quote']]);
$listener->delete_post_notifications($delete_event);
$listener->delete_post_notifications($delete_event);
check($delete_event['delete_notifications_types'] === ['notification.type.quote','paul999.mention.notification.type.mention'], 'post deletion registers mention cleanup exactly once');
