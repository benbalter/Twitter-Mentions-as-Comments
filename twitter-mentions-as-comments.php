<?php
/*
Plugin Name: Twitter Mentions as Comments
Plugin URI: http://ben.balter.com/2010/11/29/twitter-mentions-as-comments/
Description: Queries the Twitter API on a regular basis for tweets about your posts.
Version: 1.5.4
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL2v3 or later
*/

/*  Twitter Mentions as Comments
 *
 *  Twitter Mentions as Comments scours Twitter for people talking about your site
 *  & silently inserts their Tweets alongside your existing comments.
 *
 *  Copyright (C) 2011-2012  Benjamin J. Balter  ( ben@balter.com -- http://ben.balter.com )
 *
 *	This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @copyright 2011-2012
 *  @license GPL v3 or later
 *  @version 1.5.4
 *  @package Twitter_Mentions_As_Comments
 *  @author Benjamin J. Balter <ben@balter.com>
 */

require_once dirname( __FILE__ ) . '/includes/class.plugin-boilerplate.php';
require_once dirname( __FILE__ ) . '/includes/tlc-transients/tlc-transients.php';

class Twitter_Mentions_As_Comments extends Plugin_Boilerplate_v_1 {

	//plugin boilerplate settings
	static $instance;
	public $name      = 'Twitter Mentions as Comments';
	public $slug      = 'twitter-mentions-as-comments';
	public $slug_     = 'twitter_mentions_as_comments';
	public $prefix    = 'tmac_';
	public $directory = null;
	public $version   = '1.5.4';

	/**
	 * Registers hooks and filters
	 * @since 1.0
	 */
	function __construct() {

		self::$instance = &$this;
		$this->directory = dirname( __FILE__ );
		parent::__construct( $this );

		add_action('tmac_hourly_check', array( $this, 'hourly' ) );

		//Kill cron on deactivation
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
		
		//schedule cron
		add_action( 'admin_init', array( $this, 'maybe_schedule_cron' ) );
		
		//float bug fix
		add_filter( 'tmac_lastID', array( $this, 'lastID_float_fix'), 10, 2 );

		//avatars
		add_filter( 'get_avatar', array( $this, 'filter_avatar' ), 10, 2  );
		
		//init options
		add_action( 'tmac_options_init', array( $this, 'options_init' ) );

	}


	/**
	 * Registers default options
	 */
	function options_init() {

		$this->options->defaults = array(  
			'comment_type'    => '',
			'posts_per_check' => -1,
			'hide-donate'     => false,
			'api_call_limit'  => 150,
			'RTs'             => 1,
		);

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
	 * @since 1.0
	 * @param string $lastID the lastID as a string in scientific notation
	 * @param int $postID the post we're checking
	 * @returns int the ID of the last tweet checked
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
			if ( $comment->comment_agent != $this->name )
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
	 * @param unknown $postID
	 * @returns int number of tweets found
	 */
	function insert_metions( $postID ) {

		//Get array of mentions
		$mentions = $this->calls->get_mentions( $postID );

		//if there are no tweets, update post meta to speed up subsequent calls and return
		if ( empty( $mentions->results ) ) {
			update_post_meta( $postID, 'tmac_last_id', $mentions->max_id_str );
			return 0;
		}

		//loop through mentions
		foreach ( $mentions->results as $tweet ) {

			//If they exclude RTs, look for "RT" and skip if needed
			if ( $this->options->RTs && substr( $tweet->text, 0, 2 ) == 'RT' )
				continue;

			//Format the author's name based on cache or call API if necessary
			$author = $this->build_author_name( $tweet->from_user, true );

			//prepare comment array
			$commentdata = array(
				'comment_post_ID'      => $postID,
				'comment_author'       => $author,
				'comment_author_email' => $tweet->from_user . '@twitter.com',
				'comment_author_url'   => 'http://twitter.com/' . $tweet->from_user . '/status/' . $tweet->id_str . '/',
				'comment_content'      => $tweet->text,
				'comment_date_gmt'     => date('Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
				'comment_type'         => $this->options->comment_type
			);

			//insert comment using our modified function
			$commentdata = $this->api->apply_filters( 'commentdata', $commentdata );

			if ( !empty( $commentdata ) )
				$comment_id = $this->new_comment( $commentdata );

			//Prime profile image cache
			$this->calls->get_profile_image( $tweet->from_user, true );

			$this->api->do_action( 'insert_mention', $comment_id, $commentdata );

		}

		//If we found a mention, update the last_Id post meta
		update_post_meta( $postID, 'tmac_last_id', $mentions->max_id_str );

		//return number of mentions found
		return sizeof( $mentions->results );
	}


	/**
	 * Function to run on cron to check for mentions
	 * @since .1a
	 * @returns int number of mentions found
	 */
	function mentions_check() {

		$mentions = 0;

		//set API call counter
		$this->calls->count = $this->options->api_call_counter;

		//Get all posts
		$posts = get_posts( 'numberposts=' . $this->options->posts_per_check );
		$posts = $this->api->apply_filters( 'mentions_check_posts', $posts );

		//Loop through each post and check for new mentions
		foreach ( $posts as $post )
			$mentions += $this->insert_metions( $post->ID );

		//update the stored API counter
		$this->options->api_call_counter = $this->calls->count;

		$this->api->do_action( 'mentions_check' );

		return $mentions;
	}


	/**
	 * Function run hourly via WP cron
	 * @since 0.3-beta
	 */
	function hourly() {

		//reset API counter
		$this->calls->reset_count();

		if ( $this->options->manual_cron )
			return;

		//if we are in hourly cron mode, check for Tweets
		$this->mentions_check();

	}


	/**
	 * Upgrades options database and sets default options
	 * @since 1.0
	 * @param unknown $from
	 * @param unknown $to
	 */
	function upgrade( $from, $to ) {

		//change option name pre-1.5
		if ( $from < '1.5' ) {
			$this->options->set_options ( get_option( 'tmac_options' ) );
			delete_option( 'tmac_options' );
		}
		
		//remove tmac image cache from comment_meta table
		if ( $from < '1.5.2' ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM $wpdb->commentmeta WHERE meta_key LIKE 'tmac_image'" );
			
		}

	}

	/**
	 * Check on admin_init to ensure our event is scheduled, if not, schedule
	 * Doing on Upgrade requires that we bump versions (so would not work if deactivated then reactivated)
	 * Doing on activation doesn't fire on upgrade.
	 * Hence, we check on admin init
	 */
	function maybe_schedule_cron() {
	
		if ( wp_next_scheduled( 'tmac_hourly_check' ) )
			return;
			
		wp_schedule_event( time(), 'hourly', 'tmac_hourly_check' );
	
	}

	/**
	 * Callback to remove cron job
	 * @since .1a
	 */
	function deactivation(  ) {
		wp_clear_scheduled_hook( 'tmac_hourly_check' );
	}


	/**
	 * Custom new_comment function to allow overiding of timestamp to meet tweet's timestamp
	 *
	 * (Adaptation of wp_new_comment from /wp-includes/comments.php,
	 * original function does not allow for overriding timestamp,
	 * copied from v 3.3 )
	 *
	 * @since .1a
	 * @params array $commentdata assoc. array of comment data, same format as wp_insert_comment()
	 * @return unknown
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

		// BEGIN TMAC MODIFICATIONS (don't use current timestamp but rather twitter timestamp)
		$commentdata['comment_author_IP'] = '';
		$commentdata['comment_agent']     = $this->name;
		$commentdata['comment_date']     =  get_date_from_gmt( $commentdata['comment_date_gmt'] );
		$commentdata = apply_filters( 'tmac_comment', $commentdata );
		// END TMAC MODIFICATIONS

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
	 * @since 0.4
	 * @param array $commentdata commentdata
	 * @return unknown
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
	 * Formats a comment authors name in either the Real Name (@handle) or @handle format depending on information available
	 * @since .2
	 * @param string $twitterID twitter handle of user
	 * @returns string the formatted name
	 */
	function build_author_name( $twitterID, $force = false ) {

		//get the cached real name or query the API
		$real_name = $this->calls->get_author_name( $twitterID, $force );

		//If we don't have a real name, just use their twitter handle
		if ( !$real_name || substr( $real_name, 0, 1 ) == '@' )
			$name = '@' . $twitterID;

		//if we have their real name, build a pretty name
		else
			$name = $real_name . ' (@' . $twitterID . ')';

		//return the name
		return $name;

	}


	/**
	 * Filters default avatar since tweets don't have an e-mail but do have a profile image
	 * @param object data comment data
	 * @since .1a
	 * @param string $avatar default image
	 * @param unknown $data
	 * @return unknown
	 */
	function filter_avatar( $avatar, $data) {

		//If this is a real comment (not a tweet), kick
		if ( !isset( $data->comment_agent ) || $data->comment_agent != $this->name )
			return $avatar;

		$author = str_replace( '@twitter.com', '', $data->comment_author_email );

		//get the url of the image
		$url = $this->calls->get_profile_image ( $author );

		//call failed
		if ( !$url )
			return $avatar;

		//replace the twitter image with the default avatar and return
		return preg_replace("/http:\/\/([^']*)/", $url, $avatar);

	}


}


$tmac = new Twitter_Mentions_As_Comments();
