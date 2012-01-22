<?php
/*
Plugin Name: Twitter Mentions as Comments
Plugin URI: http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/
Description: Queries the Twitter API on a regular basis for tweets about your posts. 
Version: 1.0.4
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2
*/

require_once( dirname( __FILE__ ) . '/includes/class.plugin-boilerplate.php' );

class Twitter_Mentions_As_Comments extends Plugin_Boilerplate {

	//plugin boilerplate settings
	static $instance;
	public $name = 'Twitter Mentions as Comments';
	public $slug = 'twitter-menttions-as-comments';
	public $slug_ = 'twitter_mentions_as_comments';
	public $prefix = 'tmac_';

	public $version = '1.0.4';
	public $api_call_limit = '150';

	/**
	 * Registers hooks and filters
	 * @since 1.0
	 */
	function __construct() {
	
		self::$instance = &$this;
		parent::__construct();
		
		add_action('tmac_hourly_check', array( &$this, 'hourly' ) );
		
		//Kill cron on deactivation
		register_deactivation_hook(__FILE__, array( &$this, 'deactivation' ) );
		
		//api limit filter
		add_filter( 'tmac_options', array( &$this, 'api_call_limit_filter'), 10, 1 );
		
		//float bug fix
		add_filter( 'tmac_lastID', array( &$this, 'lastID_float_fix'), 10, 2 );
		
		//avatars
		add_filter( 'get_avatar', array( &$this, 'filter_avatar' ), 10, 2  );
		
		$this->options->defaults = array( 'comment_type' => '', 'posts_per_check' => '-1', 'hide-donate' => false );

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
		
		$url = $this->api->apply_filters( 'query_url', $url, $postID );
		
		//make the API call and pass it back
		$data = json_decode( $response = wp_remote_retrieve_body( wp_remote_get( $url ) ) );
			
		return  $this->api->apply_filters( 'query_response', $data, $postID );;	
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
		
		return $this->api->apply_filters( 'lastID', $lastID, $postID );
	
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
			if ( $this->options->RTs ) {
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
				'comment_type' => $this->options->comment_type
				);
		
			//insert comment using our modified function
			$commentdata = $this->api->apply_filters( 'commentdata', $commentdata );
			$comment_id = $this->new_comment( $commentdata );
			
			//Cache the user's profile image
			add_comment_meta($comment_id, 'tmac_image', $tweet->profile_image_url, true);

			$this->api->do_action( 'insert_mention', $comment_id, $commentdata );
		
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
						
		$mentions = 0;
			
		//set API call counter
		$tmac_api_calls = $this->options->api_call_counter;
		
		//Get all posts
		$posts = get_posts('numberposts=' . $options['posts_per_check'] );
		$posts = $this->api->apply_filters( 'mentions_check_posts', $posts );
		
		//Loop through each post and check for new mentions
		foreach ( $posts as $post )
			$mentions += $this->insert_metions( $post->ID );
	
		//update the stored API counter
		$this->options->api_call_counter = $tmac_api_calls;
		
		$this->api->do_action( 'mentions_check' );
	
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
		
		$this->options->api_call_counter = 0;
		$this->api->do_action( 'api_counter_reset' );
		
	}
	
	/**
	 * Function run hourly via WP cron
	 * @since 0.3-beta
	 */
	function hourly() {
	
		define ('TMAC_DOING_HOURLY', true);
		
		//reset API counter
		$this->reset_api_counter();
		
		if ( $this->options->manual_cron )
			return;
		
		//if we are in hourly cron mode, check for Tweets
		$this->mentions_check();
		
	}


	/**
	 * Upgrades options database and sets default options
	 * @since 1.0
	 */
	function upgrade() {
		
		wp_schedule_event( time(), 'hourly', 'tmac_hourly_check' );
			
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
	
		if ( $image = $this->cache->get( $twitterID . '_profile_image' ) )
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
		
		$image = $this->api->apply_filters( 'user_image', $image, $twitterID, $comment_id );
		
		$this->cache->set( $twitterID, $image . '_profile_image' );
		
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
		
		if ( $name = $this->cache->get( $twitterID . '_name' ) )
			return $name;
		
		//Check to see if twitter user has previously commented, if so just grab their name
		$name = $wpdb->get_var( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_author_email = %s and comment_approved = '1' LIMIT 1", $twitterID . '@twitter.com' ) );
		
		//if they do not previosly have a comment, or that comment doesn't have a real name, call the Twitter API
		if ( empty( $name ) || substr( $name, 0, 1 ) == '@' ) {
		
			//Query the API
			$data = $this->query_twitter( $twitterID );
			
			//If we hit the API limit, kick
			if ( !$data )
				return false;
				
			$name = $data->name;
		
		}
		
		//Because our query will return the name in the form of REAL NAME (@Handle), split the string at "(@"
		$name = substr( $name, strrpos( $name, '(@' ) );
		
		$name = $this->api->apply_filters( 'author_name', $name, $twitterID );
		
		$this->cache->set( $twitterID, $name . '_author_name' );
		
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
		
		//increment API counter
		$tmac_api_calls++;
		
		//if we are over the limit, kick
		if ( $tmac_api_calls > $this->options->api_call_limit ) {
			
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
			
		$this->api->apply_filters( 'query_url', $url, $handle );
		
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

}

$tmac = new Twitter_Mentions_As_Comments();