<?php
/**
 * File to be run by cron to check for Twitter mentions at a regular interval
 * @package Twitter Mentions as Comments
 * @since 0.3-beta
 */

//grab WP bootstrap
require_once('../../../wp-load.php');
define('TMAC_DOING_CRON', TRUE);

//verify that the plugin is activated, otherwise exit
if ( !function_exists('tmac_mentions_check') )
	exit();
	
tmac_mentions_check();
	
?>