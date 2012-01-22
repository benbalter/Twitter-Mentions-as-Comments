<?php

class Twitter_Mentions_As_Comments_Calls {
	
	static $parent;
	
	function __construct() {
	
		//create or store parent instance
		if ( $instance === null ) 
			self::$parent = new Plugin_Boilerplate;
		else
			self::$parent = &$instance;
	
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
	
}