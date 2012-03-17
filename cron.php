<?php
/**
 * File to be run by cron to check for Twitter mentions at a regular interval
 * @package Twitter Mentions as Comments
 * @since 0.3-beta
 */

//grab WP bootstrap
require_once('../../../wp-load.php');

//verify that the plugin is activated, otherwise exit
if ( !class_exists( 'Twitter_Mentions_As_Comments' ) )
	exit();

global $tmac;
if ( !$tmac )
	$tmac = new Twitter_Mentions_As_Comments();
	
$mentions = $tmac->mentions_check();

printf( _n( '%n mention found', '%n mentions found', $mentions, 'twitter-mentions-as-comments' ), $mentions );