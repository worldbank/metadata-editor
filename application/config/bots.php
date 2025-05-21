<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| Search engines and Bots ignored by sitelogs
|--------------------------------------------------------------------------
|
| Site logs can grow very quickly and fill up the database. If you don't want to log everything
| you can exclude logging by user-agent. The most common search engine user-agent headers are 
| provided below:
|
*/

//enable/disable logging for search engines, by default everything is logged
$config['ignore_bot_logging'] = FALSE;

//user-agents used by search engines
$config['bot_ignore'][] = 'google';
$config['bot_ignore'][] = 'ia_archiver';
$config['bot_ignore'][] = 'jeeves/teoma';
$config['bot_ignore'][] = 'googlebot';
$config['bot_ignore'][] = 'bot.html';
$config['bot_ignore'][] = 'msnbot';
$config['bot_ignore'][] = 'googlebot';
$config['bot_ignore'][] = 'search.msn.com';
$config['bot_ignore'][] = 'msnbot.htm';
$config['bot_ignore'][] = 'Yahoo! Slurp';
$config['bot_ignore'][] = 'help.yahoo.com/help/us/ysearch/slurp';

?>