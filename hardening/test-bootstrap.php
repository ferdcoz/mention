<?php
// CLI only, no phpBB bootstrap/config.php, no notifications or mail transports.
if (PHP_SAPI !== 'cli') { exit(1); }
define('IN_PHPBB', true);
$table_prefix = 'test_';
$phpbb_root_path = getenv('MENTION_CORE_PATH') ?: '/core/phpBB/';
$phpEx = 'php';
require getenv('MENTION_VENDOR_AUTOLOAD') ?: '/var/www/html/vendor/autoload.php';
require $phpbb_root_path . 'includes/constants.php';
require $phpbb_root_path . 'includes/utf/utf_tools.php';
spl_autoload_register(function ($class) use ($phpbb_root_path) {
	$prefix = 'paul999\\mention\\';
	if (strpos($class, $prefix) === 0) { require dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
	elseif (strpos($class, 'phpbb\\') === 0) { require $phpbb_root_path . str_replace('\\', '/', $class) . '.php'; }
}, true, true);
