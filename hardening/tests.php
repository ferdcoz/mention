<?php
require __DIR__ . '/test-bootstrap.php';
use paul999\mention\hardening\policy;

function check($ok, $label) { if (!$ok) { throw new \RuntimeException($label); } echo "PASS $label\n"; }
class fixture_cache
{
	public function get($key) { return $key === '_acl_options' ? ['global' => ['u_can_mention'=>0, 'u_can_mention_groups'=>1, 'u_can_mention_large_groups'=>2], 'local'=>['f_read'=>0]] : false; }
	public function put($key, $value) {}
	public function sql_exists($query) { return false; }
}
class fixture_template extends \phpbb\template\twig\twig
{
	public $vars = [];
	public function __construct() {}
	public function assign_vars($vars) { $this->vars = array_merge($this->vars, $vars); return $this; }
}
class fixture_helper extends \phpbb\controller\helper { public function __construct() {} }
class fixture_user extends \phpbb\user
{
	public function __construct() { $this->data = ['user_id'=>2, 'username'=>'Author', 'is_registered'=>true, 'is_bot'=>false]; $this->session_id='synthetic-session'; }
	public function lang() { return implode(' ', func_get_args()); }
}
class fixture_request extends \phpbb\request\request
{
	public $values = [];
	public function __construct() {}
	public function variable($name, $default, $multibyte = false, $super_global = \phpbb\request\request_interface::REQUEST) { return $this->values[$name] ?? $default; }
}
class fixture_manager extends \phpbb\notification\manager
{
	public $sent = [], $read = [];
	public function __construct() {}
	public function add_notifications($type, $data, array $options = []) { $this->sent[]=$data; }
	public function mark_notifications_by_parent($type, $ids, $user, $time=false, $mark_read=true) { $this->read[] = [$ids,$user,$time]; }
	public function mark_notifications($type, $ids, $user, $time=false, $mark_read=true) { $this->read[] = [$ids,$user,$time]; }
}
class fixture_log extends \phpbb\log\log
{
	public $entries = [];
	public function __construct() {}
	public function add($mode, $user_id, $ip, $operation, $time=false, $data=[]) { $this->entries[]=[$operation,$data]; }
}
class fixture_rate extends \paul999\mention\hardening\rate_limiter
{
	public function consume($user_id, $now = null) { return parent::consume($user_id, $now === null ? 900 : $now); }
}
$cache = new fixture_cache();
$db = new \phpbb\db\driver\mysqli();
$db->sql_connect('127.0.0.1', 'root', '', 'mention_test', 3306);
if (($argv[1] ?? '') === 'rate-worker')
{
	$limiter = new \paul999\mention\hardening\rate_limiter($db, 'test_');
	echo $limiter->consume(999, 100) ? 'allowed' : 'denied'; exit;
}
$config = new \phpbb\config\config(['simple_mention_large_groups'=>50, 'simple_mention_minlength'=>2, 'simple_mention_maxresults'=>25]);
foreach (['users','groups','user_group','topics','posts','mention_rate','mention_consent'] as $table) { $db->sql_query('DROP TABLE IF EXISTS test_' . $table); }
$tools = new \phpbb\db\tools\tools($db);
$migration = new \paul999\mention\migrations\hardening_301($config, $db, $tools, '/var/www/html/', 'php', 'test_');
$tools->perform_schema_changes($migration->update_schema());
$db->sql_query('CREATE TABLE test_users (user_id INT PRIMARY KEY, username VARCHAR(255), username_clean VARCHAR(255), user_permissions TEXT, user_type INT)');
$db->sql_query('CREATE TABLE test_groups (group_id INT PRIMARY KEY, group_name VARCHAR(255), group_type INT)');
$db->sql_query('CREATE TABLE test_user_group (user_id INT, group_id INT, user_pending INT)');
$db->sql_query('CREATE TABLE test_topics (topic_id INT PRIMARY KEY, forum_id INT, topic_title VARCHAR(255), topic_visibility INT DEFAULT 1)');
$db->sql_query('CREATE TABLE test_posts (post_id INT PRIMARY KEY, topic_id INT, poster_id INT, post_text TEXT, post_visibility INT DEFAULT 1)');
function bits($s) { return str_pad(base_convert(str_pad($s,31,'0'), 2,36),6,'0',STR_PAD_LEFT); }
$permissions = bits('111') . "\n" . bits('1') . "\n" . bits('0');
$db->sql_transaction('begin');
for ($i=1;$i<=5205;$i++)
{
	$row=['user_id'=>$i, 'username'=> $i===3 ? 'José con espacios' : 'Synthetic '.$i, 'username_clean'=>$i===3 ? utf8_clean_string('José con espacios') : 'synthetic '.$i, 'user_permissions'=>$permissions, 'user_type'=>USER_NORMAL];
	$db->sql_query('INSERT INTO test_users '.$db->sql_build_array('INSERT',$row));
}
foreach ([10=>40,11=>40,12=>500,13=>1000,14=>5000,15=>51] as $group=>$size)
{
	$db->sql_query("INSERT INTO test_groups VALUES ($group, 'Synthetic $group', ".GROUP_OPEN.')');
	for ($i=3; $i<3+$size;$i++) { $id = $group===11 ? $i+40 : $i; $db->sql_query("INSERT INTO test_user_group VALUES ($id, $group, 0)"); }
}
$db->sql_query('INSERT INTO test_groups VALUES (16, \'Hidden\', '.GROUP_HIDDEN.'), (17, \'BOTS\', '.GROUP_SPECIAL.'), (18, \'Pending\', '.GROUP_OPEN.')');
$db->sql_query('INSERT INTO test_user_group VALUES (3,16,0),(3,17,0),(3,18,1),(2,10,1)');
$author=['user_id'=>2,'user_type'=>USER_NORMAL,'user_permissions'=>$permissions];
$auth = new \phpbb\auth\auth(); $auth->acl($author);
$user = new fixture_user(); $template = new fixture_template(); $manager=new fixture_manager(); $request=new fixture_request(); $log=new fixture_log();
$listener = new \paul999\mention\event\main_listener(new fixture_helper(),$template,$db,$manager,$user,$auth,$config,'php',$request,$log,'test_');
$parse=new \ReflectionMethod($listener,'parse_message'); $parse->setAccessible(true);
function state($name) { global $listener; $p=new \ReflectionProperty($listener,$name); $p->setAccessible(true); return $p->getValue($listener); }
function tag($id, $kind='g') { return '[smention '.$kind.'='.$id.']</s>Synthetic<e>[/smention]'; }
function parse($message,$forum=1,$local_auth=null,$author=null) { global $parse,$listener; $parse->invoke($listener,$message,$forum,true,$local_auth,$author); return state('mention_data'); }
check(count(parse(tag(10)))===40,'active membership only');
check(parse(tag(18))===[],'pending-only group excluded');
check(parse(tag(16).tag(17))===[],'hidden and bot groups excluded');
check(parse(tag(2,'u').tag(3,'u').tag(3,'u'))=== [3],'dedup and author excluded');
check(parse(tag(3,'u'),2)===[],'private forum ACL enforced');
check(parse('[mention]</s>José con espacios<e>[/mention]')===[3],'legacy Unicode username with spaces');
check(count(parse(tag(10).tag(11)))===80 && state('needs_confirmation'),'small groups combined require consent');
check(count(parse(tag(12)))===500 && state('needs_confirmation'),'500 members allowed with consent');
check(parse(tag(12).tag(1000,'u'))===[] && state('mention_error'),'500-member group plus individual cannot evade aggregate cap');
check(parse(tag(13))===[] && state('mention_error'),'1000-member group denied');
check(parse(tag(14))===[] && state('mention_error'),'5000-member group denied');
$limited=['user_id'=>2,'user_type'=>USER_NORMAL,'user_permissions'=>bits('110')."\n".bits('1')];
$limited_auth=new \phpbb\auth\auth(); $limited_auth->acl($limited);
check(parse(tag(10).tag(11),1,$limited_auth)===[] && state('mention_error'),'aggregate ACL cannot be bypassed with small groups');
check(parse(str_repeat(tag(3,'u'),501))===[] && state('mention_error'),'tag flood bounded before SQL');
check(parse(tag(999999,'u'))===[],'unknown user excluded');
$db->sql_query('UPDATE test_users SET user_type='.USER_INACTIVE.' WHERE user_id=4');
check(parse(tag(4,'u'))===[],'inactive user excluded');
$db->sql_query('UPDATE test_users SET user_type='.USER_NORMAL.' WHERE user_id=4');
check(parse(tag(3,'u'),1,null,3)===[],'original author excluded during approval');
$message=tag(15); $message_parser=(object)['message'=>$message];
$event=new \phpbb\event\data(['submit'=>true,'mode'=>'reply','forum_id'=>1,'error'=>[]]);
$listener->validate_submission($event);
check(count($event['error'])===1 && !empty($template->vars['MENTION_CONFIRM_TOKEN']),'server rejects missing consent');
$request->values=['mention_confirm'=>1,'mention_confirm_token'=>$template->vars['MENTION_CONFIRM_TOKEN']];
$event['error']=[]; $listener->validate_submission($event);
check($event['error']===[],'valid consent accepted');
$message_parser->message .= 'changed'; $event['error']=[]; $listener->validate_submission($event);
check(count($event['error'])===1,'changed message invalidates consent');
$message_parser->message=$message; $request->values['mention_confirm_token']=policy::confirmation($message,1,50,$user->session_id);
$event['error']=[]; $listener->validate_submission($event);
check(count($event['error'])===1,'wrong recipient count invalidates consent');
$request->values['mention_confirm_token']=policy::confirmation($message,1,51,$user->session_id);
$submit=new \phpbb\event\data(['mode'=>'reply','data'=>['message'=>$message,'forum_id'=>1,'post_id'=>800,'topic_id'=>1,'topic_title'=>'Test'],'post_visibility'=>ITEM_UNAPPROVED]);
$listener->modify_submit_post($submit); $listener->submit_post($submit);
check($manager->sent===[],'pending posts do not notify');
$db->sql_query('INSERT INTO test_topics (topic_id,forum_id,topic_title) VALUES (1,1,\'Test\')');
$db->sql_query('INSERT INTO test_posts '.$db->sql_build_array('INSERT',['post_id'=>800,'topic_id'=>1,'poster_id'=>2,'post_text'=>$message]));
$approval=new \phpbb\event\data(['action'=>'approve','post_info'=>[800=>[]]]); $listener->handle_post_approval($approval);
check(count($manager->sent)===1 && count($manager->sent[0]['user_ids'])===51,'approval preserves consent');
check($log->entries[0]===['LOG_MENTION_MASS',[800,51]],'administrative log contains only counts and post ID');
$db->sql_query('INSERT INTO test_user_group VALUES (100,15,0)'); $listener->handle_post_approval($approval);
check(count($manager->sent)===1,'approval rejects recipient growth');
$listener->modify_submit_post(new \phpbb\event\data(['mode'=>'edit','data'=>[]]));
check(state('mention_data')===[],'edits clear notification state');
for ($i=2;$i<=5001;$i++) { $db->sql_query("INSERT INTO test_topics (topic_id,forum_id,topic_title) VALUES ($i,1,'Test')"); }
$listener->mark_read(new \phpbb\event\data(['mode'=>'topics','forum_id'=>[1,1],'post_time'=>123]));
$ids=[]; foreach($manager->read as $batch) { check(count($batch[0])<=250 && $batch[1]===2 && $batch[2]===123,'read batch respects size, user and time'); $ids=array_merge($ids,$batch[0]); }
check(count($ids)===5001 && count(array_unique($ids))===5001,'all 5001 topics marked once');
$listener->mark_read(new \phpbb\event\data(['mode'=>'topic','topic_id'=>1,'post_time'=>124]));
check(end($manager->read)===[1,2,124],'single topic preserves user and cutoff');
$listener->mark_read(new \phpbb\event\data(['mode'=>'all','post_time'=>125]));
check(end($manager->read)===[false,2,125],'mark all preserves user and cutoff');
$db->sql_transaction('commit');
$limiter=new \paul999\mention\hardening\rate_limiter($db,'test_');
$allowed=0; for($i=0;$i<35;$i++) { $allowed+=(int)$limiter->consume(2,100); }
check($allowed===30,'30 requests per account window (observed '.$allowed.')');
check($limiter->consume(2,110),'next window resets');
$controller=new \paul999\mention\controller\main($user,$db,$auth,$request,$config,new fixture_rate($db,'test_'));
$request->values=['q'=>'sy'];
$response=$controller->handle();
check($response->getStatusCode()===200 && count(json_decode($response->getContent(),true))<=25,'AJAX result budget');
for($i=0;$i<35;$i++) { $response=$controller->handle(); }
check($response->getStatusCode()===429 && $response->headers->get('Retry-After')==='10','AJAX 429 response');
$db->sql_query('DELETE FROM test_mention_rate WHERE user_id=2');
$db->sql_query("INSERT INTO test_posts (post_id,topic_id,poster_id,post_text,post_visibility) VALUES (900,1,100,'fixture',1),(901,1,101,'fixture',0),(902,2,102,'fixture',1)");
$request->values=['q'=>'sy','t'=>1];
$data=json_decode($controller->handle()->getContent(),true);
check($data[0]['topic_participant'] && (int) $data[0]['user_id']===100 && $data[1]['topic_participant'] && (int) $data[1]['user_id']===2,'topic participants precede global users');
check(count(array_unique(array_column($data,'user_id')))===count($data) && count($data)===25,'priority list deduplicated and capped');
check(!array_filter($data, function($item) { return (int) $item['user_id']===101 && $item['topic_participant']; }),'pending post author not revealed as participant');
$db->sql_query('UPDATE test_topics SET forum_id=2 WHERE topic_id=2');
$request->values=['q'=>'sy','t'=>2];
$data=json_decode($controller->handle()->getContent(),true);
check(!array_filter($data,function($item) {return $item['topic_participant'];}),'private topic cannot reveal participant priority');
$request->values=['q'=>'sy','t'=>999999];
$data=json_decode($controller->handle()->getContent(),true);
check(!array_filter($data,function($item) {return $item['topic_participant'];}),'invalid topic falls back to global search');
$user->data['user_id']=ANONYMOUS;
try { $controller->handle(); check(false,'anonymous rejected'); } catch (\phpbb\exception\http_exception $e) { check($e->getStatusCode()===401,'anonymous rejected before lookup'); }
$user->data['user_id']=2;
$configurator=new \s9e\TextFormatter\Configurator();
$config['simple_mention_link']=1;
$listener->configure_bbcode(new \phpbb\event\data(['configurator'=>$configurator]));
$parser=$configurator->finalize()['parser'];
$xml=$parser->parse('[smention u=3]José con espacios[/smention]');
check(parse($xml)===[3],'real s9e parser output recognized');
$quote=new \phpbb\event\data(['submit'=>false,'preview'=>false,'refresh'=>false,'mode'=>'quote','page_data'=>['MESSAGE'=>'[smention u=3]José con espacios[/smention]']]);
$listener->remove_mention_in_quote($quote);
check($quote['page_data']['MESSAGE']==='@José con espacios','quote removes active mention tag');
$yaml=\Symfony\Component\Yaml\Yaml::parse(file_get_contents(dirname(__DIR__).'/config/services.yml'));
check(isset($yaml['services']['paul999.mention.rate_limiter']),'service YAML parses');
foreach(['common.php','hardening.php','acp_common.php','permissions_mention.php','info_acp_mention.php'] as $file)
{
    $lang=[]; include dirname(__DIR__).'/language/en/'.$file; $keys=array_keys($lang);
    $lang=[]; include dirname(__DIR__).'/language/es/'.$file;
    check(!array_diff($keys,array_keys($lang)),'Spanish coverage '.$file);
}
$db->sql_query('DELETE FROM test_mention_rate WHERE user_id=999');
$db->sql_transaction('commit');
$processes=[];
for($i=0;$i<60;$i++) { $pipes=[]; $p=proc_open([PHP_BINARY,__FILE__,'rate-worker'],[1=>['pipe','w'],2=>['pipe','w']],$pipes); $processes[]=[$p,$pipes]; }
$allowed=0;
foreach($processes as [$p,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($p)===0 && $err==='','parallel worker successful'); $allowed+=(int)($out==='allowed'); }
check($allowed===30,'60 concurrent requests admit exactly 30');
$tools->perform_schema_changes($migration->revert_schema());
check(!$tools->sql_table_exists('test_mention_rate') && !$tools->sql_table_exists('test_mention_consent'),'schema rollback');
echo "All synthetic integration tests passed; no mail or real notification backend used.\n";
