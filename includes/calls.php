<?php
/**
 * Makes calls to the Twitter API (or pulls from local cache)
 * @author Benjamin J. Balter <ben@balter.com>
 * @package Twitter_Mentions_As_Comments
 */
class Twitter_Mentions_As_Comments_Calls {

	private $parent;
	public $count;
	public $image_ttl = '86400'; // 24 hours

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
	function get_author_name( $twitterID ) {

		global $wpdb;

		if ( $name = $this->parent->cache->get( $twitterID . '_name' ) )
			return $name;

		//Check to see if twitter user has previously commented, if so just grab their name
		$name = $wpdb->get_var( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_author_email = %s and comment_approved = '1' LIMIT 1", $twitterID . '@twitter.com' ) );

		//if they do not previosly have a comment, call the Twitter API
		if ( empty( $name ) ) {

			//Query the API
			$data = $this->query_twitter( $twitterID );

			//If we hit the API limit, kick
			if ( !$data )
				return false;

			$name = $data->name;

		}

		//Because our query will return the name in the form of REAL NAME (@Handle), split the string at "(@"
		$name = substr( $name, strrpos( $name, '(@' ) );

		$name = $this->parent->api->apply_filters( 'author_name', $name, $twitterID );

		$this->parent->cache->set( $twitterID . '_name', $name );

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
	function get_profile_image( $twitterID, $comment_id = null ) {
	
		if ( $comment_id != null )
			_doing_it_wrong( 'get_profile_image', 'Passing the comment ID is deprecated as of Twitter Mentions as Comments version', '1.5.2' );
		
		//render the name case incensitive
		$twitterID = strtolower( $twitterID );
			
		$image = tlc_transient( 'tmac_image_' . $twitterID  )
		    ->updates_with( array( &$this, 'get_profile_image_callback' ), array( $twitterID ) )
		    ->expires_in( $this->image_ttl )
		    ->background_only()
		    ->get();

		return $image;
	
	}
	
	/**
	 * Callback to query the Twitter API to retrieve a user's profile image
	 * @param string $twitterID the user's name
	 * @return string the URL to the image
	 */
	function get_profile_image_callback( $twitterID ) {

		$data = $this->query_twitter( $twitterID );
		$image = $data->profile_image_url;
		$image = $this->parent->api->apply_filters( 'user_image', $image, $twitterID, null );
		return $image;
	
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