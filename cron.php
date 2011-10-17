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
if ( !class_exists('TMAC') )
	exit();

global $tmac;
if ( !$tmac )
	$tmac = new TMAC();
	
$mentions = $tmac->mentions_check();

?>
<strong><?php printf( _n( '%n mention found', '%n mentions found', $mentions), $mentions ); ?></strong>