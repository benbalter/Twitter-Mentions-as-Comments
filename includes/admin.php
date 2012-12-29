<?php
/**
 * Functions for admin UI
 * @author Benjamin J. Balter <ben@balter.com>
 * @package Twitter_Mentions_As_Comments
 */
class Twitter_Mentions_As_Comments_Admin {

	private $parent;

	/**
	 * Register Hooks with WordPress API
	 * @param class $parent (reference) the parent class
	 */
	function __construct( &$parent ) {

		if ( !is_admin() )
			return;

		$this->parent = &$parent;

		add_action( 'admin_menu', array( $this, 'options_menu_init' ) );

		add_action( 'admin_init', array( $this, 'enqueue_init' ) );

		add_filter( 'tmac_options_validate', array( $this, 'options_validate' ) );

	}


	/**
	 * Register enqueue data
	 */
	function enqueue_init() { 
		$this->parent->enqueue->data = array ( 'hide_manual_cron_details' => !( $this->parent->options->manual_cron ) );
	}


	/**
	 * Creates the options sub-panel
	 * @since .1a
	 */
	function options() {

		$mentions = false;
		$options = $this->parent->options;

		if ( isset( $_GET['force_refresh'] ) && $_GET['force_refresh'] == true )
			$mentions = $this->parent->mentions_check();

		$this->parent->template->options( compact( 'mentions', 'options' ) );

	}


	/**
	 * Sanitize options
	 * @param unknown $options
	 * @return unknown
	 */
	function options_validate( $options ) {

		$options['posts_per_check'] = (int) $options['posts_per_check'];

		$bools = array( 'hide-donate', 'RTs', 'manual_cron' );
		foreach ( $bools as $bool )
			$options[ $bool ] = ( isset( $options[ $bool ] ) ) ? (bool) $options[ $bool ]: false;

		return $options;

	}


	/**
	 * Register menu
	 */
	function options_menu_init() {
		add_options_page( 'Twitter Mentions as Comments Options', 'Twitter -> Comments', 'manage_options', 'tmac_options', array( $this, 'options' ) );
	}


}
