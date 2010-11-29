<?php
/*
Plugin Name: Twitter Mentions as Comments
Plugin URI: http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/
Description: Uses the BackTweet API to look for tweets about your posts. 
Version: 0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
Licesnse: GPL2
*/

/**
 * Load API Key and other options
 * @since .1a
 * @todo make an options panel
 */
function tmac_get_options() {
	$options = get_option('tmac_options');
	return $options;
}

/**
 * Appends BackTweet API Key to URLs
 * @params string url URL to add params to
 * @returns string URL with key appended
 * @since .1a
 */
function tmac_add_credentials($url) {

	$options = tmac_get_options();
	$url .= '&key='. $options['api_key'];
	return $url;

}

/**
 * Calls the BackTweets API and gets mentions for a given post
 * @parama int postID ID of current post
 * @returns array array of tweets mentioning current page
 * @since .1a
 */
function tmac_get_mentions( $postID ) {

	//Retrive last ID checked for on this post so we don't re-add a comment already added
	$lastID = get_post_meta( $postID, 'tmac_last_id', true );
	
	//Build URL
	$url = 'http://backtweets.com/search.json?since_id=' . $lastID . '&q=' . urlencode( get_permalink( $postID ) );
	
	//Add credentials
	$url = tmac_add_credentials( $url );
	
	//make the API call and pass it back
	$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );
	return $data->tweets;	
}

/**
 * Inserts mentions into the comments table, queues and checks for spam as necessary
 * @params int postID ID of post to check for comments
 * @since .1a
 */
function tmac_insert_metions( $postID ) {
	
	//get options
	$options = tmac_get_options();
	
	//Get array of mentions
	$mentions = tmac_get_mentions( $postID );
	
	//Init our variable so when can update the post meta
	$last_id = 0;
	
	//don't sanitize e-mail addresses
	remove_filter('pre_comment_author_email', 'sanitize_email');
	
	//loop through mentions
	foreach ($mentions as $tweet) {
	
		//store the first ID as the most recent ID checked so we can start their next time
		if ($last_id == 0)
			$last_id = $tweet->tweet_id;

		//If they exclude RTs, look for "RT" and skip if needed
		if (!$options['RTs']) {
			if (strpos($tweet->tweet_text,'RT') != FALSE) 
				continue;
		}
		
		//query twitter API for user details
		$twitterAPI = tmac_query_twitter(  $tweet->tweet_from_user );

		//prepare comment array
		$commentdata = array(
			'comment_post_ID' => $postID, 
			'comment_author' => $twitterAPI->name . ' (@' . $tweet->tweet_from_user . ')', 
			'comment_author_email' => '@' . $tweet->tweet_from_user, 
			'comment_author_url' => 'http://twitter.com/' . $tweet->tweet_from_user . '/status/' . $tweet->tweet_id,
			'comment_content' => $tweet->tweet_text,
			'comment_date_gmt' => $tweet->tweet_created_at,
			'comment_type' => ''
			);
		
		//insert comment using our modified function
		$comment_id = tmac_new_comment( $commentdata );
		
		//Cache the image URL using our previous twitter API call
		$image = $twitterAPI->profile_image_url;
		add_comment_meta($comment_id, 'tmac_image', $image, true);
	}
	
	//If we found a mention, update the last_Id post meta
	if ($last_id != 0)
		update_post_meta($postID, 'tmac_last_id', (int) $last_id );


	//add the e-mail filter back
	add_filter('pre_comment_author_email', 'sanitize_email');

}

/**
 * Function to run on cron to check for mentions
 * @since .1a
 */
function tmac_mentions_check(){
	
	//Get most recent 40 posts to stay within limit (24 hours * 40 posts <= 1000 API calls @ 1 call / post) 
	$posts = get_posts('numberposts=40');
	
	//Loop through each post and check for new mentions
	foreach ($posts as $post) {
		tmac_insert_metions( $post->ID );
	}

}

//Register Cron on activation
register_activation_hook(__FILE__, 'tmac_activation');
add_action('tmac_hourly_check', 'tmac_mentions_check');

/**
 * Callback to call hourly to check for new mentions
 * @since .1a
 */
function tmac_activation() {
	wp_schedule_event(time(), 'hourly', 'tmac_hourly_check');
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
	$commentdata['comment_date']     =  get_date_from_gmt($commentdata['comment_date_gmt']);
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
 * Calls the public twitter API and retrieves information on a given user
 * @param string $handle handle of twitter user
 * @returns array assoc. array of info returned
 */
function tmac_query_twitter( $handle ) {
		
		//build the URL
		$url = 'http://api.twitter.com/1/users/show/'. $handle .'.json';
		
		//make the call
		$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );
		
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
	tmac_mentions_check();	
?>
	<div class="updated fade">
		<p>Tweets Refreshed!</p>
	</div>
<?php
}

?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="tmac_options[api_key]">BackTweets API Key</label></th>
			<td>
				<input name="tmac_options[api_key]" type="text" id="tmac_options[api_key]" value="<?php echo $options['api_key']; ?>" class="regular-text" /><BR />
				<span class="description">If you don't have a BackTweets API key, you can get one on the <a href="http://www.backtype.com/signup">BackType Developer Page</a> (free registration required).</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="tmac_options[RTs]">Exclude ReTweets?</label></th>
			<td>
				<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][0]" value="0" <?php if (!$options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][0]">Include ReTweets</label><BR />
				<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][1]" value="1" <?php if ($options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][1]">Exclude ReTweets</label><BR />
				<span class="description">If "Exclude ReTweets" is selected, ReTweets (both old- and new-style) will be ignored.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Force Check</th>
			<td>
				<a href="?page=tmac_options&force_refresh=true">Check for New Tweets Now</a><br />
				<span class="description">Normally the plugin checks for new Tweets every hour. Click the link above to force a check immediately.</span>
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
	add_options_page( 'Twitter Mentions as Comments Options', 'Twitter->Comments', 'manage_options', 'tmac_options', 'tmac_options');

}

add_action('admin_menu','tmac_options_menu_init');
?>