<?php
/**
 * Makes calls to the Twitter API (or pulls from local cache)
 * @author Benjamin J. Balter <ben@balter.com>
 * @package Twitter_Mentions_As_Comments
 */
class Twitter_Mentions_As_Comments_Calls {

	private $parent;
	public $count;
	public $user_ttl = '604800'; // 1 week in seconds

	/**
	 * Store parent on construct
	 * @param class $parent (reference) the parent class
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;

	}


	/**
	 * Calls the public twitter API and retrieves information on a given user
	 * @since .1
	 * @param string $handle handle of twitter user
	 * @returns array assoc. array of info returned
	 */
	function query_twitter( $handle ) {

		//increment API counter
		$this->count++;

		//if we are over the limit, kick
		if ( $this->count > $this->parent->options->api_call_limit ) {

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

		$this->parent->api->apply_filters( 'query_url', $url, $handle );

		//make the call
		$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );

		return $data;

	}


	/**
	 * Checks for previous tweet-commments by the author and tries to retrieve their cached real name
	 * @since .2
	 * @param string $twitterID handle of twitter user
	 * @returns string their real name
	 */
	function get_author_name( $twitterID, $force = false ) {

		$user = $this->get_user_object( $twitterID, $force );
		$name = $user->name;   

		//Because our query will return the name in the form of REAL NAME (@Handle), split the string at "(@"
		$name = substr( $name, strrpos( $name, '(@' ) );

		$name = $this->parent->api->apply_filters( 'author_name', $name, $twitterID );

		return $name;

	}


	/**
	 * Retrieves twitter profile image given a twitter username using TLC-Transients
	 * @param string twitterID twitter handle to lookup
	 * @param int comment_id ID of comment to store meta on (for caching)
	 * @since .1a
	 * @param string $twitterID the user's name
	 * @param int $comment_id DEPRICATED
	 * @returns string url of profile image
	 */
	function get_profile_image( $twitterID, $comment_id = null, $force = false ) {
	
		if ( $comment_id != null )
			_doing_it_wrong( 'get_profile_image', 'Passing the comment ID is deprecated as of Twitter Mentions as Comments version', '1.5.2' );
			
		$user = $this->get_user_object( $twitterID, $force );
		
		$image = $user ? $user->profile_image_url : false;
		$this->parent->api->apply_filters( 'user_image', $image, $twitterID, null );

		return $image;
	
	}
	
	/**
	 * Returns a user object for the specified user
	 * @param string $twitterID the user screen name to retrieve object for
	 * @param bool $force (optional) whether to force a live fetch (defaults to false)
	 * @return object the user object
	 */
	function get_user_object( $twitterID, $force = false ) {

		//render the name case incensitive
		$twitterID = strtolower( $twitterID );
		
		$trans = tlc_transient( 'tmac_user_' . $twitterID  )
		    ->updates_with( array( $this, 'get_user_object_callback' ), array( $twitterID ) )
		    ->expires_in( $this->user_ttl );
		    
		if ( !$force )
			$trans->background_only();
		
		return $trans->get();
		    
	}
	
	/**
	 * Callback used by tlc_transient_server to query twitter API for user objects
	 * @param string $twitterID the user to query
	 * @returns object the twitter user object
	 */
	function get_user_object_callback( $twitterID ) {
		global $wpdb;
		
		//loop through all queued screen_names to check, and do one (or possibly a few) API calls to refresh ALL of them
		$this->fetch_all_queued_user_objects();
		
		//have tlc_transient create the key for consistency
		$trans = tlc_transient( 'tmac_user_' . $twitterID );
		$data = get_transient( $trans->key );
		
		//ruh roh
		if ( !$data )
			return false;
			
		//was just refreshed, no need to check
		return $data[1];
	
	}
	
	/**
	 * In order to save API calls, loop through all queued requests for user objects 
	 * and group into requests of 100, manually setting transients for each in a format
	 * compatible with tlc_transients()
	 */
	function fetch_all_queued_user_objects() {
		
		global $wpdb;
		
		//fields to cache
		$fields = array( 'profile_image_url', 'name' );
		
		//build array of all pending user object queries
		$base = '_transient_tlc_up__tmac_user_';
		$user_queue = $wpdb->get_col( "SELECT option_name from $wpdb->options WHERE option_name like '$base%'" );
	
		//strip transient base to get screen name alone
		foreach ( $user_queue as &$user )
			$user = str_replace( $base, '', $user );
		
		//api accepts max 100 screen_names per call, so chunk
		foreach ( array_chunk( $user_queue, 100 ) as $chunk ) {
			$user_string = implode(',', $chunk );
	
			//build query URL
			$url = add_query_arg( 'screen_name', $user_string, 'http://api.twitter.com/1/users/lookup.json' );
			$data = wp_remote_get( $url );
			
			if ( is_wp_error( $data ) )
				throw new Exception( $data->get_error_message() );
			
			$data = json_decode( wp_remote_retrieve_body( $data ) );
			
			if ( !$data )
				throw new Exception( 'Could not parse JSON repsonse' );
			
			//loop through each user and build a cache object
			foreach ( $data as $user_object ) {

				//only cache certain fields to save space in database
				$cache = new stdClass();
				foreach ( $fields as $field )
					$cache->$field = $user_object->$field;	
				
				//store the cache via tlc_transient
				$trans = tlc_transient( 'tmac_user_' . strtolower( $user_object->screen_name ) )
				    ->expires_in( $this->user_ttl )
					->set( $cache );
					
				//manually kill lock since it's a private function
				delete_transient( 'tlc_up__' . $trans->key );
				
			}
		}
		
	}

	/**
	 * Calls the Twitter API and gets mentions for a given post
	 * @parama int postID ID of current post
	 * @since .1a
	 * @todo multiple calls for multiple pages of results (e.g., > 100)
	 * @param unknown $postID
	 * @returns array array of tweets mentioning current page
	 */
	function get_mentions( $postID ) {

		//Retrive last ID checked for on this post so we don't re-add a comment already added
		$lastID = $this->parent->get_lastID( $postID );

		//Build URL, verify that $lastID is a string and not scientific notation, see http://jetlogs.org/2008/02/05/php-problems-with-big-integers-and-scientific-notation/
		$url = 'http://search.twitter.com/search.json?rpp=100&since_id=' . $lastID . '&q=' . urlencode( get_permalink( $postID ) );

		$url = $this->parent->api->apply_filters( 'query_url', $url, $postID );

		//make the API call and pass it back
		$data = json_decode( $response = wp_remote_retrieve_body( wp_remote_get( $url ) ) );

		return $this->parent->api->apply_filters( 'query_response', $data, $postID );

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
	function reset_count() {

		$this->count = 0;
		$this->parent->options->api_call_counter = 0;
		$this->parent->api->do_action( 'api_counter_reset' );

	}


}
