<?php
/**
 * Functions for admin UI
 * @package Twitter_Mentions_As_Comments
 */
class Twitter_Mentions_As_Comments_Admin {

	static $parent;

	function __construct( &$instance ) {
	
		if ( !is_admin() )
			return;
	
		self::$parent = &$instance;
		
		add_action( 'admin_menu', array( &$this, 'options_menu_init' ) );
	
		add_action( 'tmac_init', array( &$this, 'init' ) );
		
		add_filter( 'tmac_options_validate', array( &$this, 'options_validate' ) );
	
	}
	
	function init() {
		self::$parent->enqueue->data = array ( 'hide_manual_cron_details' => !( self::$parent->options->manual_cron ) );
	}
		
	/**
	 * Creates the options sub-panel
	 * @since .1a
	 */
	function options() {

		$mentions = false;
		$options = &self::$parent->options;
		
		if ( isset( $_GET['force_refresh'] ) && $_GET['force_refresh'] == true )
			$mentions = self::$parent->mentions_check();	
		
		self::$parent->template->options( compact( 'mentions', 'options' ) );
		
	}
	
	/**
	 * Sanitize options
	 */
	function options_validate( $options ) {
		
		
		$options['posts_per_check'] = (int) $options['posts_per_check'];
		
		$bools = array( 'hide-donate', 'RTs', 'manual_cron' );
		foreach ( $bools as $bool )
			$options[ $bool ] = ( isset( $options[ $bool ] ) ) ? (bool) $options[ $bool ]: false;
		
		return $options;
	
	}
	
	function options_menu_init() {
		add_options_page( 'Twitter Mentions as Comments Options', 'Twitter -> Comments', 'manage_options', 'tmac_options', array( &$this, 'options' ) );	
	}
}