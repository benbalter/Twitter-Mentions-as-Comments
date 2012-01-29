<?php
/*
Plugin Name: Plugin Boilerplate Sample
Description:
Version: 1.0
Author: Benjamin J. Balter
Author URI: http://ben.balter.com
License: GPL3
*/

/*  Copyright 2012  Benjamin J. Balter  ( ben@balter.com | http://ben.balter.com )
 *
 *  This program is free software: you can redistribute it and/or modify
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
 *  @license GPL v3
 *  @version 1.0
 *  @package Plugin Boilerplate
 *  @subpackage Hello_Dolly2
 *  @author Benjamin J. Balter <ben@balter.com>
 */

require_once dirname( __FILE__ ) . '/includes/class.plugin-boilerplate.php';

class Hello_Dolly2 extends Plugin_Boilerplate {

	static $instance;
	public $name      = 'Hello Dolly 2.0'; //Human-readable name of plugin
	public $slug      = 'hello-dolly2'; //plugin slug, generally base filename and in url on wordpress.org
	public $slug_     = 'hello_dolly2'; //slug with underscores (PHP/JS safe)
	public $prefix    = 'hd2_'; //prefix to append to all options, API calls, etc. w/ trailing underscore
	public $directory = null;
	public $version   = '1.0';

	/**
	 * Construct the boilerplate and autoload all child classes
	 */
	function __construct() {

		self::$instance = &$this;
		$this->directory = dirname( __FILE__ );
		parent::__construct( &$this );

		add_action( 'admin_menu', array( &$this, 'options_menu_init' ) );
		add_action( 'admin_init', array( &$this, 'register_setting' ) );

	}


	/**
	 * Register our option with WordPress
	 */
	function register_setting() {
		register_setting( $this->slug_, $this->slug_, array( &$this, 'options_validate' ) );
	}


	/**
	 * Register our menu with WordPress
	 */
	function options_menu_init() {
		add_options_page( __( 'Hello Dolly Lyrics' ), __( 'Hello Dolly Lyrics' ), 'manage_options', 'hd2_options', array( &$this, 'options' ) );
	}


	/**
	 * Callback to load options template
	 */
	function options() {
		$this->template->load( 'sample-options');
	}


	/**
	 * Sanitizes options on save
	 * @param unknown $options
	 * @return unknown
	 */
	function options_validate( $options ) {

		if ( !is_array( $options['lyrics'] ) ) {
			$options['lyrics'] = explode( "\n", $options['lyrics'] );
		}

		foreach ( $options['lyrics'] as $ID => &$lyric )
			$lyric = trim( stripslashes( wp_filter_nohtml_kses( $lyric ) ) );

		return $options;

	}


}


$hd2 = new Hello_Dolly2();
