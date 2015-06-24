<?php
/**
 * Class to create/insert predefined replies into the tickets
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Predefined_Replies {

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init_register_predefined_replies' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu_predefined_replies' ) );
		add_action( 'admin_head', array( $this, 'action_admin_head' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
	}


	public function action_admin_head() {
		global $menu, $pagenow, $post_type;

		//
		if ( 'post.php' == $pagenow && SupportFlow()->predefinded_replies_type == $post_type ) {
			foreach ( $menu as $key => $single_menu ) {
				if ( 'edit.php?post_type=' . SupportFlow()->post_type == $single_menu[2] ) {
					$menu[$key][4] .= ' wp-has-current-submenu wp-menu-open open-if-no-js ';
				}
			}
		}
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {
		global $pagenow;

		if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) {
			$handle = SupportFlow()->enqueue_script( 'supportflow-predefined-replies', 'predefined-replies.js' );

			wp_localize_script( $handle, 'SFPredefinedReplies', array(
				'message' => __( 'Are you sure want to proceed? It will replace your existing content.', 'supportflow' ),
			) );
		}
	}

	/**
	 * Register the custom post type to create predefined replies
	 * @uses  register_post_type() To register the post type
	 */
	function action_init_register_predefined_replies() {
		register_post_type(
			SupportFlow()->predefinded_replies_type, array(
				'labels'             => array(
					'menu_name'          => __( 'SupportFlow', 'supportflow' ),
					'name'               => __( 'Predefined Replies', 'supportflow' ),
					'singular_name'      => __( 'Predefined Reply', 'supportflow' ),
					'all_items'          => __( 'All predefined replies', 'supportflow' ),
					'add_new'            => __( 'New predefined reply', 'supportflow' ),
					'add_new_item'       => __( 'New predefined reply', 'supportflow' ),
					'edit_item'          => __( 'Edit predefined reply', 'supportflow' ),
					'new_item'           => __( 'New Predefined Reply', 'supportflow' ),
					'view_item'          => __( 'View predefined reply', 'supportflow' ),
					'search_items'       => __( 'Search predefined replies', 'supportflow' ),
					'not_found'          => __( 'No predefined reply found', 'supportflow' ),
					'not_found_in_trash' => __( 'No predefined reply found in trash', 'supportflow' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'publicly_queryable' => false,
				'show_in_menu'       => false,
				'supports'           => array(
					'title', 'editor',
				),
			)
		);
	}

	/**
	 * Add predefined options under Supportflow menu
	 */
	public function action_admin_menu_predefined_replies() {
		$post_type                = SupportFlow()->post_type;
		$predefinded_replies_type = SupportFlow()->predefinded_replies_type;

		add_submenu_page( "edit.php?post_type=$post_type", 'All predefined replies', 'All predefined replies', 'manage_options', "edit.php?post_type=$predefinded_replies_type" );
		add_submenu_page( "edit.php?post_type=$post_type", 'New predefined reply', 'New predefined reply', 'manage_options', "post-new.php?post_type=$predefinded_replies_type" );

	}
}

SupportFlow()->extend->predefined_replies = new SupportFlow_Predefined_Replies();
