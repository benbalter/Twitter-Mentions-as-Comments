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
		
		//create or store parent instance
		if ( $instance === null ) 
			self::$parent = new Plugin_Boilerplate;
		else
			self::$parent = &$instance;
		
		add_action( 'admin_menu', array( &$this, 'options_menu_init' ) );
	
		add_action( 'tmac_init', array( &$this, 'init' ) );
	
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
	
	function options_menu_init() {
		add_options_page( 'Twitter Mentions as Comments Options', 'Twitter -> Comments', 'manage_options', 'tmac_options', array( &$this, 'options') );
	
	}
}