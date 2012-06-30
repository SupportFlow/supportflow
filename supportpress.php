<?php
/*
Plugin Name: SupportPress
Plugin URI: http://supportpress.com/
Description: Reinventing how you support your customers
Author: Daniel Bachhuber, Alex Mills, Andrew Spittle, Automattic
Version: 0.0
Author URI: http://automattic.com/
*/
define( 'SUPPORTPRESS_VERSION', '0.0' );
define( 'SUPPORTPRESS_ROOT' , dirname( __FILE__ ) );

require_once( SUPPORTPRESS_ROOT . '/classes/class-supportpress-admin.php' );

class SupportPress {

	var $post_statuses = array();
	var $post_type = 'sp_thread';

	/**
	 * Initialize your support
	 */
	function __construct() {

		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
	}

	/**
	 * Code to run on the 'init' action:
	 * - Register the thread post type
	 * - Register the default custom post statuses
	 */
	function action_init() {

		$args = array(
				'label' => __( 'Threads', 'supportpress' ),
				'labels' => array(
						'singular_name' => __( 'Thread', 'supportpress' ),
						'all_items' => __( 'All Threads', 'supportpress' ),
						'add_new_item' => __( 'Add New Thread', 'supportpress' ),
						'edit_item' => __( 'Edit Thread', 'supportpress' ),
						'new_item' => __( 'New Thread', 'supportpress' ),
						'view_item' => __( 'View Thread', 'supportpress' ),
						'search_item' => __( 'Search Threads', 'supportpress' ),
						'not_found' => __( 'No threads found', 'supportpress' ),
						'not_found_in_trash' => __( 'No threads found in trash', 'supportpress' ),
					),
				'public' => true,
				'menu_position' => 3,
				'supports' => array(
						'title',
						'comments',
					),
			);
		register_post_type( $this->post_type, $args );
	}

	/**
	 * Code to run on the 'admin_init' action:
	 */
	function action_admin_init() {

		// Initialize the admin class
		$this->admin = new SupportPressAdmin();

	}

	/**
	 * 
	 */
	function add_thread() {

	}

	/**
	 *
	 */
	function update_thread() {

	}

	/**
	 *
	 */
	function delete_thread() {

	}

}

global $supportpress;
$supportpress = new SupportPress();