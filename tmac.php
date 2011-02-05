<?php
/*
Plugin Name: Twitter Mentions as Comments
Plugin URI: http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/
Description: Queries the Twitter API on a regular basis for tweets about your posts. 
Version: 0.3-Beta
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

/**
 * Load API Key and other options
 * @since .1a
 */
function tmac_get_options() {

	//get stored options
	$options = get_option('tmac_options');
	
	//hard code these as options for now so we can make them settings in future versions
	$options['api_call_limit'] = '150';
	
	//return options
	return $options;
}

/**
 * Calls the Twitter API and gets mentions for a given post
 * @parama int postID ID of current post
 * @returns array array of tweets mentioning current page
 * @since .1a
 * @todo multiple calls for multiple pages of results (e.g., > 100)
 */
function tmac_get_mentions( $postID ) {
	
	//Retrive last ID checked for on this post so we don't re-add a comment already added
	$lastID = get_post_meta( $postID, 'tmac_last_id', true );
	
	//Build URL
	$url = 'http://search.twitter.com/search.json?rpp=100&since_id=' . $lastID . '&q=' . urlencode( get_permalink( $postID ) );	
	
	echo "$url \r\n";

	//make the API call and pass it back
	$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );

	return $data;	
}

/**
 * Inserts mentions into the comments table, queues and checks for spam as necessary
 * @params int postID ID of post to check for comments
 * @since .1a
 * @returns int number of tweets found
 */
function tmac_insert_metions( $postID ) {
	
	//get options
	$options = tmac_get_options();
	
	//Get array of mentions
	$mentions = tmac_get_mentions( $postID );
	
	//if there are no tweets, update post meta to speed up subsequent calls and return
	if ( sizeof( $mentions->results ) == 0) {
		update_post_meta($postID, 'tmac_last_id', $mentions->max_id );
		return 0;
	}
		
	//loop through mentions
	foreach ($mentions->results as $tweet) {
	
		//If they exclude RTs, look for "RT" and skip if needed
		if (!isset($options['RTs']) || !$options['RTs']) {
			if (strpos($tweet->text,'RT') != FALSE) 
				continue;
		}
		
		//Format the author's name based on cache or call API if necessary
		$author = tmac_build_author_name( $tweet->from_user );
			
		//prepare comment array
		$commentdata = array(
			'comment_post_ID' => $postID, 
			'comment_author' => $author,
			'comment_author_email' => $tweet->from_user . '@twitter.com', 
			'comment_author_url' => 'http://twitter.com/' . $tweet->from_user . '/status/' . $tweet->id_str . '/',
			'comment_content' => $tweet->text,
			'comment_date_gmt' => date('Y-m-d H:i:s', strtotime($tweet->created_at) ),
			'comment_type' => $options['comment_type']
			);
			
		//insert comment using our modified function
		$comment_id = tmac_new_comment( $commentdata );
		
		//Cache the user's profile image
		add_comment_meta($comment_id, 'tmac_image', $tweet->profile_image_url, true);
	
	}
	
	//If we found a mention, update the last_Id post meta
	update_post_meta($postID, 'tmac_last_id', $mentions->max_id );

	//return number of mentions found
	return sizeof( $mentions->results );
}

/**
 * Function to run on cron to check for mentions
 * @since .1a
 * @returns int number of mentions found
 * @todo break query into multiple queries so recent posts are checked more frequently
 */
function tmac_mentions_check(){
	global $tmac_api_calls;
	$mentions = 0;
		
	//get limit	
	$options = tmac_get_options();
	
	//set API call counter
	$tmac_api_calls = ( isset($options['api_call_counter'] ) ) ? $options['api_call_counter'] : 0;
	
	//Get all posts
	$posts = get_posts('numberposts=' . $options['posts_per_check'] );
	
	//Loop through each post and check for new mentions
	foreach ($posts as $post) {
		$mentions += tmac_insert_metions( $post->ID );
	}

	//update the stored API counter
	$options['api_call_counter'] = $tmac_api_calls;
	update_option( 'tmac_options', $options );

	return $mentions;
}

/**
 * Resets internal API counter every hour
 *
 * User API is limited to 150 unauthenticated calls / hour
 * Authenticated API is limited to 350 / hour
 * Search calls do not count toward total, although search has an unpublished limit
 *
 * @since .2
 * @todo query the API for our actual limit
 */
function tmac_reset_api_counter() {
	
	$options = tmac_get_options();
	$options['api_call_counter'] = 0;
	update_option( 'tmac_options', $options);	
	
}

/**
 * Function run hourly via WP cron
 * @since 0.3-beta
 */
function tmac_hourly() {

	define ('TMAC_DOING_HOURLY', true);
	
	//reset API counter
	tmac_reset_api_counter();
	
	//if we are in manual cron mode, kick
	$options = tmac_get_options();
	
	if ( $options['manual_cron'] )
		return;
	
	//if we are in hourly cron mode, check for Tweets
	tmac_mentions_check();
	
}

//Register Cron on activation
register_activation_hook(__FILE__, 'tmac_activation');
add_action('tmac_hourly_check', 'tmac_hourly');

/**
 * Callback to call hourly to check for new mentions
 * @since .1a
 */
function tmac_activation() {
	wp_schedule_event(time(), 'hourly', 'tmac_hourly_check');
	
	$options = get_option('tmac_options');
	
	//If the comment_type option is not set (upgrade), set it
	if ( empty( $options['comment_type'] ) )
		$options['comment_type'] = '';

	if ( empty( $options['posts_per_check'] ) )
		$options['posts_per_check'] = '-1';
		
	update_option('tmac_options', $options);
	
}

//Kill cron on deactivation
register_deactivation_hook(__FILE__, 'tmac_deactivation');

/**
 * Callback to remove cron job
 * @since .1a
 */
function tmac_deactivation() {
	wp_clear_scheduled_hook('tmac_hourly_check');
}

/**
 * Custom new_comment function to allow overiding of timestamp to meet tweet's timestamp
 *
 * (Adaptation of wp_new_comment from /wp-includes/comments.php,
 * original function does not allow for overriding timestamp,
 * copied from v 3.1 )
 *
 * @params array $commentdata assoc. array of comment data, same format as wp_insert_comment()
 * @since .1a
 */
function tmac_new_comment( $commentdata ) {
	$commentdata = apply_filters('preprocess_comment', $commentdata);

	$commentdata['comment_post_ID'] = (int) $commentdata['comment_post_ID'];
	if ( isset($commentdata['user_ID']) )
		$commentdata['user_id'] = $commentdata['user_ID'] = (int) $commentdata['user_ID'];
	elseif ( isset($commentdata['user_id']) )
		$commentdata['user_id'] = (int) $commentdata['user_id'];

	$commentdata['comment_parent'] = isset($commentdata['comment_parent']) ? absint($commentdata['comment_parent']) : 0;
	$parent_status = ( 0 < $commentdata['comment_parent'] ) ? wp_get_comment_status($commentdata['comment_parent']) : '';
	$commentdata['comment_parent'] = ( 'approved' == $parent_status || 'unapproved' == $parent_status ) ? $commentdata['comment_parent'] : 0;

	//	BEGIN TMAC MODIFICATIONS (don't use current timestamp but rather twitter timestamp)
	$commentdata['comment_author_IP'] = '';
	$commentdata['comment_agent']     = 'Twitter Mentions as Comments';
	$commentdata['comment_date']     =  get_date_from_gmt( $commentdata['comment_date_gmt'] );
	//	END TMAC MODIFICATIONS
	
	$commentdata = wp_filter_comment($commentdata);

	$commentdata['comment_approved'] = wp_allow_comment($commentdata);

	$comment_ID = wp_insert_comment($commentdata);

	do_action('comment_post', $comment_ID, $commentdata['comment_approved']);

	if ( 'spam' !== $commentdata['comment_approved'] ) { // If it's spam save it silently for later crunching
		if ( '0' == $commentdata['comment_approved'] )
			wp_notify_moderator($comment_ID);

		$post = &get_post($commentdata['comment_post_ID']); // Don't notify if it's your own comment

		if ( get_option('comments_notify') && $commentdata['comment_approved'] && ( ! isset( $commentdata['user_id'] ) || $post->post_author != $commentdata['user_id'] ) )
			wp_notify_postauthor($comment_ID, empty( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '' );
	}

	return $comment_ID;
}

/**
 * Retrieves twitter profile image given a twitter username, stores in comment meta
 * @param string twitterID twitter handle to lookup
 * @param int comment_id ID of comment to store meta on (for caching)
 * @since .1a
 * @returns string url of profile image
 */
function tmac_get_profile_image( $twitterID, $comment_id) {
	
	//Check to see if we already have the image stored in comment meta
	$image = get_comment_meta($comment_id, 'tmac_image', true);
	
	//If we don't already have the immage, call the twitter API
	if (!$image) {
		
		$data = tmac_query_twitter( $twitterID );
		$image = $data->profile_image_url;
		
		//Cache the image URL
		add_comment_meta($comment_id, 'tmac_image', $image, true);
	}
	
	return $image;
}

/**
 * Checks for previous tweet-commments by the author and tries to retrieve their cached real name
 * @param string $twitterID handle of twitter user
 * @returns string their real name
 * @since .2
 */

function tmac_get_author_name( $twitterID ) {
	global $wpdb;
	
	//Check to see if twitter user has previously commented, if so just grab their name
	$name = $wpdb->get_var( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_author_email = %s and comment_approved = '1' LIMIT 1", $twitterID . '@twitter.com' ) );
	
	//if they do not previosly have a comment, or that comment doesn't have a real name, call the Twitter API
	if ( empty( $name ) | substr($name, 0, 1) == '@' ) {
	
		//Query the API
		$data = tmac_query_twitter( $twitterID );
		
		//If we hit the API limit, kick
		if ( !$data )
			return false;
		
		//Because our query will return the name in the form of REAL NAME (@Handle), split the string at "(@"
		$name = substr( $data->name, strrpos($data->name, '(@') );
	}
	
	//return the name
	return $name;
	
}

/**
 * Formats a comment authors name in either the Real Name (@handle) or @handle format depending on information available
 * @param string $twitterID twitter handle of user
 * @returns string the formatted name
 * @since .2
 */
function tmac_build_author_name( $twitterID ) {
	
	//get the cached real name or query the API
	$real_name = tmac_get_author_name( $twitterID );

	//If we don't have a real name, just use their twitter handle
	if ( !$real_name ) 
		$name = '@' . $twitterID;
		
	//if we have their real name, build a pretty name
	else
		$name = $real_name . ' (@' . $twitterID . ')';
	
	//return the name
	return $name;
	
}

/**
 * Calls the public twitter API and retrieves information on a given user
 * @param string $handle handle of twitter user
 * @returns array assoc. array of info returned
 * @since .1
 */
function tmac_query_twitter( $handle ) {
	global $tmac_api_calls;
	
	$options = tmac_get_options();
	
	//increment API counter
	$tmac_api_calls++;
	
	//if we are over the limit, kick
	if ( $tmac_api_calls > $options['api_call_limit'] ) {
		
		//if we already sent an e-mail this go around, don't send again
		global $tmac_api_limit_msg_sent;
		if ( $tmac_api_limit_msg_sent ) 
			return false; 
		$tmac_api_limit_msg_sent = true;
		
		//e-mail the admin to tell them we've hit the API limit
		wp_mail(	
					get_settings('admin_email'),
					'Twitter Mentions as Comments API Limit Reached', 
					'The WordPress Twitter Mentions as Comments Plugin has reached its API limit. You may want to consider checking less frequently.'
				);
		
		return false;
	
	}
			
	//build the URL
	$url = 'http://api.twitter.com/1/users/show/'. $handle .'.json';
		
	//make the call
	$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );
	
	//increment counter	
	$tmac_api_calls++;
	
	return $data;
		
}

/**
 * Filters default avatar since tweets don't have an e-mail but do have a profile image
 * @param string $avatar default image
 * @param object data comment data
 * @since .1a
 */
function tmac_filter_avatar( $avatar, $data) {
	
	//If this is a real comment (not a tweet), kick
	if ($data->comment_agent != 'Twitter Mentions as Comments')
		return $avatar;

	//get the url of the image
	$url = tmac_get_profile_image ( substr( $data->comment_author, 1 ), $data->comment_ID);
	
	//replace the twitter image with the default avatar and return
	return preg_replace("/http:\/\/([^']*)/", $url, $avatar);
}

add_filter('get_avatar', 'tmac_filter_avatar', 10, 2);

/**
 * Tells WP that we're using a custom settings field
 */
function tmac_options_int() {
    
    register_setting( 'tmac_options', 'tmac_options' );

}

add_action( 'admin_init', 'tmac_options_int' );

/**
 * Creates the options sub-panel
 * @since .1a
 */
function tmac_options() { 	
?>
<div class="wrap">
	<h2>Twitter Mentions as Comments Options</h2>
	<form method="post" action='options.php' id="tmac_form">
<?php 

//provide feedback
settings_errors();

//Tell WP that we are on the wp_resume_options page
settings_fields( 'tmac_options' ); 

//Pull the existing options from the DB
$options = tmac_get_options();

if (isset($_GET['force_refresh']) && $_GET['force_refresh'] == true) {
	define ('TMAC_DOING_FORCED_REFRESH', true);
	$mentions = tmac_mentions_check();	
?>
	<div class="updated fade">
		<p>Tweets Refreshed!
		<?php if ($mentions == 0) { ?>
			No Tweets found.
		<?php } else { ?>
			<a href="edit-comments.php"><strong><?php echo $mentions; ?></strong> Tweet<?php if ($mentions != 1) echo 's'; ?> found</a>.</p>
		<?php } ?>
	</div>
<?php
}

?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="tmac_options[RTs]">Exclude ReTweets?</label></th>
			<td>
				<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][0]" value="0" <?php if (!isset($options['RTs']) || !$options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][0]">Include ReTweets</label><BR />
				<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][1]" value="1" <?php if (isset($options['RTs']) && $options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][1]">Exclude ReTweets</label><BR />
				<span class="description">If "Exclude ReTweets" is selected, ReTweets (both old- and new-style) will be ignored.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="tmac_options[posts_per_check]">Number of Posts to Check</label></th>
			<td>
				Check the <input type="text" name="tmac_options[posts_per_check]" id="tmac_options[posts_per_check]" value="<?php echo $options['posts_per_check']; ?>" size="2"> most recent posts for mentions<br />
				<span class="description">If set to "-1", will check all posts, if blank will check all posts on your site's front page.</span>
			</td>
		</tr>		
		<tr valign="top">
			<th scope="row"><label for="tmac_options[comment_type]">Comment Type</label></th>
			<td>
				<select name="tmac_options[comment_type]" id="tmac_options[comment_type]">
					<option value=""<?php if ($options['comment_type'] == '') echo ' SELECTED'; ?>>Comment</option>
					<option value="trackback"<?php if ($options['comment_type'] == 'trackback') echo ' SELECTED'; ?>>Trackback</option>
					<option value="pingback"<?php if ($options['comment_type'] == 'pingback') echo ' SELECTED'; ?>>Pingback</option>
				</select><br />
				<span class="description">Most users will probably not need to change this setting, although you may prefer that Tweets appear as trackbacks or pingbacks if your theme displays these differently</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="tmac_options[manual_cron]">Checking Frequency</label></th>
			<td>
				<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][0]" value="0" <?php if ( !isset($options['manual_cron']) || !$options['manual_cron']) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][0]">Hourly</label><BR />
				<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][1]" value="1" <?php if (isset($options['manual_cron']) && $options['manual_cron']) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][1]">Manually</label><BR />
				<span class="description">The plugin can check for Tweets hourly (default), or, if you have the ability to set up a <a href="http://en.wikipedia.org/wiki/Cron">cron job</a>, can check any any desired frequency.</span><BR />
				<span class="description" id="cron-details"><br />For manual checking, you must set a crontab to execute the file <code><?php echo dirname(__FILE__) . '/cron.php'; ?></code>. The exact command will depend on your server's setup.<br /> To run every 15 minutes, for example (in most setups), the command would be: <br /><code>/15 * * * * php <?php echo dirname(__FILE__) . '/cron.php'; ?></code><br /> Please be aware that Twitter does have some <a href="http://dev.twitter.com/pages/rate-limiting">API limits</a>. The plugin will make one search call per post, and one users/show call for each new user it finds (to get the user's real name).</span>
			<script>
				jQuery(document).ready(function($){
					$('#cron-details').siblings('input').click(function(){ 
						if ( $(this).val() == 1)
							$('#cron-details').slideDown();
						else 
							$('#cron-details').slideUp();
						});	
				<?php if ( !$options['manual_cron'] ) { ?>
					$('#cron-details').hide();
				<?php } ?>
				});
			</script>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Force Check</th>
			<td>
				<a href="?page=tmac_options&force_refresh=true">Check for New Tweets Now</a><br />
				<span class="description">Normally the plugin checks for new Tweets on its own. Click the link above to force a check immediately.</span>
			</td>
		</tr>
	</table>
	<p class="submit">
         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>
	</form>
<?php
}

function tmac_options_menu_init() {
	add_options_page( 'Twitter Mentions as Comments Options', 'Twitter -> Comments', 'manage_options', 'tmac_options', 'tmac_options');

}

add_action('admin_menu','tmac_options_menu_init');
?>