<?php
/*
Plugin Name: Twitter Mentions as Comments
Plugin URI: http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/
Description: Queries the Twitter API on a regular basis for tweets about your posts. 
Version: 1.0.1
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

class TMAC {

	static $instance;
	public $version = '1.0';
	public $api_call_limit = '150';
	public $ttl = 3600;
	public $ua = 'Twitter Mentions as Comments';

	/**
	 * Registers hooks and filters
	 * @since 1.0
	 */
	function __construct() {
	
		self::$instance = &$this;
		
		//Register Cron on activation
		register_activation_hook(__FILE__, array( &$this, 'activation' ) );
		add_action('tmac_hourly_check', array( &$this, 'hourly' ) );
		
		//Kill cron on deactivation
		register_deactivation_hook(__FILE__, array( &$this, 'deactivation' ) );
		
		//api limit filter
		add_filter( 'tmac_options', array( &$this, 'api_call_limit_filter'), 10, 1 );
		
		//float bug fix
		add_filter( 'tmac_lastID', array( &$this, 'lastID_float_fix'), 10, 2 );
		
		//avatars
		add_filter( 'get_avatar', array( &$this, 'filter_avatar' ), 10, 2  );
		
		//admin menus
		add_action( 'admin_init', array( &$this, 'options_int' ) );
		add_action( 'admin_menu', array( &$this, 'options_menu_init' ) );
		add_action( 'wp_ajax_tmac_hide_donate', array(&$this, 'hide_donate') );
		add_action( 'admin_head', array( &$this, 'enqueue_scripts' ) );
		
		//i18n
		load_plugin_textdomain( 'twitter-mentions-as-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * Load TMAC options
	 * @since .1a
	 */
	function get_options() {
	
		return apply_filters( 'tmac_options', get_option('tmac_options') );
	
	}

	/**
	 * Filter to apply call limit to options
	 * Hard coded as option for now so we can make them settings in future versions
	 * @param array $options the options
	 * @returns array the modified options
	 * @since 1.0
	 */	
	function api_call_limit_filter( $options ) {

		$options['api_call_limit'] = $this->api_call_limit;
			
		return $options;
		
	}
	
	/**
	 * Calls the Twitter API and gets mentions for a given post
	 * @parama int postID ID of current post
	 * @returns array array of tweets mentioning current page
	 * @since .1a
	 * @todo multiple calls for multiple pages of results (e.g., > 100)
	 */
	function get_mentions( $postID ) {
		
		//Retrive last ID checked for on this post so we don't re-add a comment already added
		$lastID = $this->get_lastID( $postID );
		
		//Build URL, verify that $lastID is a string and not scientific notation, see http://jetlogs.org/2008/02/05/php-problems-with-big-integers-and-scientific-notation/
		$url = 'http://search.twitter.com/search.json?rpp=100&since_id=' . $lastID . '&q=' . urlencode( get_permalink( $postID ) ) . '%20OR%20' . urlencode( get_bloginfo( 'wpurl' ) . '/?p=' . $postID );	
		
		$url = apply_filters( 'tmac_query_url', $url, $postID );
		
		//make the API call and pass it back
		$data = json_decode( $response = wp_remote_retrieve_body( wp_remote_get( $url ) ) );
			
		return apply_filters( 'tmac_query_response', $data, $postID );;	
	}
	
	/**
	 * Retrieves the ID of the last tweet checked for a given post
	 * If the lastID meta field is empty, it checks for comments (backwards compatability) and then defaults to 0
	 * @since 0.4
	 * @param int $postID ID of post
	 * @returns int ID of last tweet
	 */
	function get_lastID( $postID ) {
		
		//Check for an ID stored in meta, if so, return
		$lastID = get_post_meta( $postID, 'tmac_last_id', true );
		
		return apply_filters( 'tmac_lastID', $lastID, $postID );
	
	} 
	
	/**
	 * Fix for bug pre 1.6.3 where lastID's were stored as float-strings in scientific notation rather than integers
	 * @param string $lastID the lastID as a string in scientific notation
	 * @param int $postID the post we're checking
	 * @returns int the ID of the last tweet checked
	 * @since 1.0
	 */
	function lastID_float_fix( $lastID, $postID ) {
		
		//if we have an ID, return, but check for bad pre-1.6.3 data	
		if ( $lastID && !is_float( $lastID ) ) 
			return $lastID;
			
		//grab all the comments for the post	
		$comments = get_comments( array( 'post_id' => $postID ) );
		$lastID = 0;
		
		//loop through the comments
		foreach ($comments as $comment) {
		
			//if this isn't a TMAC comment, tkip
			if ( $comment->comment_agent != $this->ua )
				continue;
			
			//parse the ID from the author URL
			$lastID = substr($comment->comment_author_url, strrpos($comment->comment_author_url, '/', -2 ) + 1, -1 );
			
			//we're done looking (comments are in reverse cron order)
			break;
		}
			
		return $lastID;
	}
	
	/**
	 * Inserts mentions into the comments table, queues and checks for spam as necessary
	 * @params int postID ID of post to check for comments
	 * @since .1a
	 * @returns int number of tweets found
	 */
	function insert_metions( $postID ) {
		
		//get options
		$options = $this->get_options();
		
		//Get array of mentions
		$mentions = $this->get_mentions( $postID );
	
		//if there are no tweets, update post meta to speed up subsequent calls and return
		if ( empty( $mentions->results ) ) {
			update_post_meta( $postID, 'tmac_last_id', $mentions->max_id_str );
			return 0;
		}
			
		//loop through mentions
		foreach ( $mentions->results as $tweet ) {
		
			//If they exclude RTs, look for "RT" and skip if needed
			if (!isset($options['RTs']) || !$options['RTs']) {
				if ( substr( $tweet->text, 0, 2 ) == 'RT' ) 
					continue;
			}
			
			//Format the author's name based on cache or call API if necessary
			$author = $this->build_author_name( $tweet->from_user );
				
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
			$commentdata = apply_filters( 'tmac_commentdata', $commentdata );
			$comment_id = $this->new_comment( $commentdata );
			
			//Cache the user's profile image
			add_comment_meta($comment_id, 'tmac_image', $tweet->profile_image_url, true);

			do_action( 'tmac_insert_mention', $comment_id, $commentdata );
		
		}
		
		//If we found a mention, update the last_Id post meta
		update_post_meta($postID, 'tmac_last_id', $mentions->max_id_str );
	
		//return number of mentions found
		return sizeof( $mentions->results );
	}
	
	/**
	 * Function to run on cron to check for mentions
	 * @since .1a
	 * @returns int number of mentions found
	 * @todo break query into multiple queries so recent posts are checked more frequently
	 */
	function mentions_check(){
		global $tmac_api_calls;
		
		do_action( 'pre_tmac_mentions_check' );
				
		$mentions = 0;
			
		//get limit	
		$options = $this->get_options();
				 		
		//set API call counter
		$tmac_api_calls = ( isset($options['api_call_counter'] ) ) ? $options['api_call_counter'] : 0;
		$tmac_api_calls = apply_filters( 'tmac_api_calls', $tmac_api_calls );
		
		//Get all posts
		$posts = get_posts('numberposts=' . $options['posts_per_check'] );
		$posts = apply_filters( 'tmac_mentions_check_posts', $posts );
		
		//Loop through each post and check for new mentions
		foreach ( $posts as $post ) {
			$mentions += $this->insert_metions( $post->ID );
		}
	
		//update the stored API counter
		$options['api_call_counter'] = $tmac_api_calls;
		update_option( 'tmac_options', $options );
		
		do_action( 'tmac_mentions_check' );
	
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
	function reset_api_counter() {
		
		$options = $this->get_options();
		$options['api_call_counter'] = 0;
		update_option( 'tmac_options', $options);	
		do_action( 'tmac_api_counter_reset' );
		
	}
	
	/**
	 * Function run hourly via WP cron
	 * @since 0.3-beta
	 */
	function hourly() {
	
		define ('TMAC_DOING_HOURLY', true);
		
		//reset API counter
		$this->reset_api_counter();
		
		//if we are in manual cron mode, kick
		$options = $this->get_options();
		
		if ( $options['manual_cron'] )
			return;
		
		//if we are in hourly cron mode, check for Tweets
		$this->mentions_check();
		
	}

	
	/**
	 * Callback to call hourly to check for new mentions
	 * @since .1a
	 */
	function activation() {
		
		wp_schedule_event( time(), 'hourly', 'tmac_hourly_check' );
		
		$options = $this->get_options();

		if ( !isset( $options['db_version'] ) || $options['db_version'] < $this->version )
			$this->upgrade();
				
	}
	
	/**
	 * Upgrades options database and sets default options
	 * @since 1.0
	 */
	function upgrade() {
	
		$options = $this->get_options();
		
		//key => default value
		$defaults = array( 'comment_type' => '', 'posts_per_check' => '-1', 'hide-donate' => false );
		$defaults = apply_filters( 'tmac_default_options', $defaults );
		
		//If the comment_type option is not set (upgrade), set it
		foreach ( $defaults as $key => $value ) {
			if ( !isset( $options[ $key ] ) )
				$options[ $key ] = $value;
		}
		
		$options['db_version'] = $this->version;
				
		update_option('tmac_options', $options);
		
		do_action( 'tmac_db_upgrade' );

	
	}
	
	/**
	 * Callback to remove cron job
	 * @since .1a
	 */
	function deactivation() {
		wp_clear_scheduled_hook('tmac_hourly_check');
	}
	
	/**
	 * Custom new_comment function to allow overiding of timestamp to meet tweet's timestamp
	 *
	 * (Adaptation of wp_new_comment from /wp-includes/comments.php,
	 * original function does not allow for overriding timestamp,
	 * copied from v 3.3 )
	 *
	 * @params array $commentdata assoc. array of comment data, same format as wp_insert_comment()
	 * @since .1a
	 */
	function new_comment( $commentdata ) {
	
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
		$commentdata['comment_agent']     = $this->ua;
		$commentdata['comment_date']     =  get_date_from_gmt( $commentdata['comment_date_gmt'] );
		$commentdata = apply_filters( 'tmac_comment', $commentdata );
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
				wp_notify_postauthor($comment_ID, isset( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '' );
		}
	
		return $comment_ID;
	}
		
	/**
	 * Stopgap measure to prevent duplicate comment error
	 * @param array $commentdata commentdata
	 * @since 0.4
	 */
	function dupe_check( $commentdata ) {
	
		global $wpdb;
		extract($commentdata, EXTR_SKIP);
	
		// Simple duplicate check
		// expected_slashed ($comment_post_ID, $comment_author, $comment_author_email, $comment_content)
		$dupe = "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = '$comment_post_ID' AND comment_approved != 'trash' AND ( comment_author = '$comment_author' ";
		if ( $comment_author_email )
			$dupe .= "OR comment_author_email = '$comment_author_email' ";
		$dupe .= ") AND comment_content = '$comment_content' LIMIT 1";
		
		return $wpdb->get_var($dupe);
	
	}
	
	/**
	 * Retrieves twitter profile image given a twitter username, stores in comment meta
	 * @param string twitterID twitter handle to lookup
	 * @param int comment_id ID of comment to store meta on (for caching)
	 * @since .1a
	 * @returns string url of profile image
	 */
	function get_profile_image( $twitterID, $comment_id) {
	
		if ( $image = wp_cache_get( $twitterID, 'tmac_profile_images' ) )
			return $image;
		
		//Check to see if we already have the image stored in comment meta
		$image = get_comment_meta($comment_id, 'tmac_image', true);
		
		//If we don't already have the immage, call the twitter API
		if (!$image) {
			
			$data = $this->query_twitter( $twitterID );
			$image = $data->profile_image_url;
			
			//Cache the image URL
			add_comment_meta($comment_id, 'tmac_image', $image, true);
			
		}
		
		$image = apply_filters( 'tmac_user_image', $image, $twitterID, $comment_id );
		
		wp_cache_set( $twitterID, $image, 'tmac_profile_images', $this->ttl );
		
		return $image;
	}
	
	/**
	 * Checks for previous tweet-commments by the author and tries to retrieve their cached real name
	 * @param string $twitterID handle of twitter user
	 * @returns string their real name
	 * @since .2
	 */
	function get_author_name( $twitterID ) {
	
		global $wpdb;
		
		if ( $name = wp_cache_get( $twitterID, 'tmac_author_names' ) )
			return $name;
		
		//Check to see if twitter user has previously commented, if so just grab their name
		$name = $wpdb->get_var( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_author_email = %s and comment_approved = '1' LIMIT 1", $twitterID . '@twitter.com' ) );
		
		//if they do not previosly have a comment, or that comment doesn't have a real name, call the Twitter API
		if ( empty( $name ) || substr($name, 0, 1) == '@' ) {
		
			//Query the API
			$data = $this->query_twitter( $twitterID );
			
			//If we hit the API limit, kick
			if ( !$data )
				return false;
			
			//Because our query will return the name in the form of REAL NAME (@Handle), split the string at "(@"
			$name = substr( $data->name, strrpos($data->name, '(@') );
		
		}
		
		$name = apply_filters( 'tmac_author_name', $name, $twitterID );
		
		wp_cache_set( $twitterID, $name, 'tmac_author_names', $this->ttl );
		
		return $name;
		
	}
	
	/**
	 * Formats a comment authors name in either the Real Name (@handle) or @handle format depending on information available
	 * @param string $twitterID twitter handle of user
	 * @returns string the formatted name
	 * @since .2
	 */
	function build_author_name( $twitterID ) {
		
		//get the cached real name or query the API
		$real_name = $this->get_author_name( $twitterID );
	
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
	function query_twitter( $handle ) {
		global $tmac_api_calls;
		
		do_action( 'pre_tmac_query' );
		
		$options = $this->get_options();
		
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
			
		apply_filters( 'tmac_query_url', $url, $handle );
		
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
	function filter_avatar( $avatar, $data) {
		
		//If this is a real comment (not a tweet), kick
		if ( !isset( $data->comment_agent ) || $data->comment_agent != $this->ua )
			return $avatar;
	
		//get the url of the image
		$url = $this->get_profile_image ( substr( $data->comment_author, 1 ), $data->comment_ID);
		
		//replace the twitter image with the default avatar and return
		return preg_replace("/http:\/\/([^']*)/", $url, $avatar);
		
	}
		
	/**
	 * Tells WP that we're using a custom settings field
	 */
	function options_int() {
	    
	    register_setting( 'tmac_options', 'tmac_options' );
	
	}
		
	/**
	 * Creates the options sub-panel
	 * @since .1a
	 */
	function options() { ?>
	<div class="wrap">
		<h2><?php _e( 'Twitter Mentions as Comments Options', 'twitter-mentions-as-comments' ); ?></h2>
		<form method="post" action='options.php' id="tmac_form">
	<?php 
	
	//provide feedback
	settings_errors();
	
	//Tell WP that we are on the wp_resume_options page
	settings_fields( 'tmac_options' ); 
	
	//Pull the existing options from the DB
	$options = $this->get_options();
	
	if (isset($_GET['force_refresh']) && $_GET['force_refresh'] == true) {
		define ('TMAC_DOING_FORCED_REFRESH', true);
			
		$mentions = $this->mentions_check();	
	?>
		<div class="updated fade">
			<p><?php _e( 'Tweets Refreshed!', 'twitter-mentions-as-comments' ); ?>
			<?php if ($mentions == 0) { ?>
				<?php _e( 'No Tweets found.', 'twitter-mentions-as-comments' ); ?>
			<?php } else { ?>
				<a href="edit-comments.php"><?php printf(_n( "<strong>%d</strong> tweet found.", "<strong>%d</strong> tweets found.", $mentions ), $mentions ); ?></a>.</p>
			<?php } ?>
		</div>
	<?php
	} ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="tmac_options[RTs]"><?php _e( 'Exclude ReTweets?', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][0]" value="0" <?php if (!isset($options['RTs']) || !$options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][0]"><?php _e( 'Include ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="tmac_options[RTs]" type="radio" id="tmac_options[RTs][1]" value="1" <?php if (isset($options['RTs']) && $options['RTs']) echo 'checked="checked"'; ?>/> <label for="tmac_options[RTs][1]"><?php _e( 'Exclude ReTweets', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'If "Exclude ReTweets" is selected, ReTweets (both old- and new-style) will be ignored.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="tmac_options[posts_per_check]"><?php _e( 'Number of Posts to Check', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					Check the <input type="text" name="tmac_options[posts_per_check]" id="tmac_options[posts_per_check]" value="<?php echo $options['posts_per_check']; ?>" size="2"> <?php _e( 'most recent posts for mentions', 'twitter-mentions-as-comments' ); ?><br />
					<span class="description"><?php _e( 'If set to "-1", will check all posts, if blank will check all posts on your site\'s front page.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>		
			<tr valign="top">
				<th scope="row"><label for="tmac_options[comment_type]"><?php _e( 'Comment Type', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<select name="tmac_options[comment_type]" id="tmac_options[comment_type]">
						<option value=""<?php if ($options['comment_type'] == '') echo ' SELECTED'; ?>><?php _e( 'Comment', 'twitter-mentions-as-comments' ); ?></option>
						<option value="trackback"<?php if ($options['comment_type'] == 'trackback') echo ' SELECTED'; ?>><?php _e( 'Trackback', 'twitter-mentions-as-comments' ); ?></option>
						<option value="pingback"<?php if ($options['comment_type'] == 'pingback') echo ' SELECTED'; ?>><?php _e( 'Pingback', 'twitter-mentions-as-comments' ); ?></option>
					</select><br />
					<span class="description"><?php _e( 'Most users will probably not need to change this setting, although you may prefer that Tweets appear as trackbacks or pingbacks if your theme displays these differently', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="tmac_options[manual_cron]"><?php _e( 'Checking Frequency', 'twitter-mentions-as-comments' ); ?></label></th>
				<td>
					<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][0]" value="0" <?php if ( !isset($options['manual_cron']) || !$options['manual_cron']) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][0]"><?php _e( 'Hourly', 'twitter-mentions-as-comments' ); ?></label><BR />
					<input name="tmac_options[manual_cron]" type="radio" id="tmac_options[manual_cron][1]" value="1" <?php if (isset($options['manual_cron']) && $options['manual_cron']) echo 'checked="checked"'; ?>/> <label for="tmac_options[manual_cron][1]"><?php _e( 'Manually', 'twitter-mentions-as-comments' ); ?></label><BR />
					<span class="description"><?php _e( 'The plugin can check for Tweets hourly (default), or, if you have the ability to set up a <a href="http://en.wikipedia.org/wiki/Cron">cron job</a>, can check any any desired frequency.', 'twitter-mentions-as-comments' ); ?></span><BR />
					<span class="description" id="cron-details"><br /><?php echo sprintf( __( 'For manual checking, you must set a crontab to execute the file <code>%s/cron.php</code>. The exact command will depend on your server\'s setup. To run every 15 minutes, for example (in most setups), the command would be: <code>/15 * * * * php %s/cron.php</code> Please be aware that Twitter does have some <a href="http://dev.twitter.com/pages/rate-limiting">API limits</a>. The plugin will make one search call per post, and one users/show call for each new user it finds (to get the user\'s real name).', 'twitter-mentions-as-comments' ), dirname( __FILE__ ), dirname( __FILE__ ) ); ?></span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Force Check</th>
				<td>
					<a href="?page=tmac_options&force_refresh=true<?php if (isset($_GET['debug'])) echo '&debug=' . $_GET['debug']; ?>"><?php _e( 'Check for New Tweets Now', 'twitter-mentions-as-comments' ); ?></a><br />
					<span class="description"><?php _e( 'Normally the plugin checks for new Tweets on its own. Click the link above to force a check immediately.', 'twitter-mentions-as-comments' ); ?></span>
				</td>
			</tr>
			<?php if ( !isset( $options['hide-donate'] ) || $options['hide-donate'] != true ) { ?>
				<tr valign="top" id="donate">
					<th scope="row">
						<?php _e( 'Support', 'twitter-mentions-as-comments' ); ?>
					</th>
					<td>
						<em><?php _e('Enjoy using Twitter Mentions as Comments? Please consider <a href="http://ben.balter.com/donate/">making a small donation</a> to support the software’s continued development.', 'twitter-mentions-as-comments'); ?></em> <span style="font-size: 10px;">(<a href="#" id="hide-donate"><?php _e( 'hide this message', 'twitter-mentions-as-comments' ); ?></a>)</span>
						<?php wp_nonce_field( 'tmac_hide_donate', '_ajax_nonce-tmac-hide-donate' ); ?>
	
					</td>
				</tr>
			<?php } //end if donate hidden ?>
		</table>
		<p class="submit">
	         <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
		</form>
	<?php
	}
	
	function options_menu_init() {
		add_options_page( 'Twitter Mentions as Comments Options', 'Twitter -> Comments', 'manage_options', 'tmac_options', array( &$this, 'options') );
	
	}

	/**
	 * Stores user's preference to hide the donate message via AJAX
	 */
	function hide_donate() {
		
		check_ajax_referer( 'tmac_hide_donate' , '_ajax_nonce-tmac-hide-donate' );

		$current_user_id = get_current_user_id();
		$options = $this->get_options();
		$options['hide-donate'] = true;
		
		update_option( 'tmac_options', $options);
		
		die( 1 );
		
	}

	/**
	 * Enqueues TMAC JS file
	 * @since 1.0
	 */
	function enqueue_scripts() {
	
		$options = $this->get_options();
		$file = ( WP_DEBUG ) ? '/tmac.dev.js' : '/tmac.js';
		wp_enqueue_script( 'tmac', plugins_url( $file, __FILE__ ), array( 'jquery'), $this->version, true );
		$data = array();
		$data['hide_manual_cron_details'] = !( isset( $options['manual_cron'] ) && $options['manual_cron'] );
		wp_localize_script( 'tmac', 'tmac', $data );
	
	} 

}

$tmac = new TMAC();