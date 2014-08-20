<?php
/**
 * Class to create/insert predefined replies into the tickets
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Predefined_Replies extends SupportFlow {

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

		add_submenu_page( "edit.php?post_type=$post_type", __( 'All predefined replies', 'supportflow' ), __( 'All predefined replies', 'supportflow' ), 'manage_options', "edit.php?post_type=$predefinded_replies_type" );
		add_submenu_page( "edit.php?post_type=$post_type", __( 'New predefined reply', 'supportflow' ), __( 'New predefined reply', 'supportflow' ), 'manage_options', "post-new.php?post_type=$predefinded_replies_type" );

	}

	/**
	 * @return array Array of predefined replies. Each array contains subarray with keys "title" and "content"
	 */
	public function get_predefined_replies() {
		$predefs            = array();
		$predefined_replies = get_posts( array( 'post_type' => SupportFlow()->predefinded_replies_type, 'posts_per_page' => - 1 ) );

		foreach ( $predefined_replies as $predefined_reply ) {
			$predefs[] = array( 'title' => $predefined_reply->post_title, 'content' => $predefined_reply->post_content );
		}

		return $predefs;
	}

	/**
	 * Echo HTML dropdown box containing replies.
	 * Returns predefined content as data property of option tags
	 *
	 * @param boolean $echo Should echo the dropdown
	 * @param int $trim_length Limit the length of content shown in box
	 */
	public function get_dropdown_input($echo = true, $trim_length = 75) {
		$predefined_replies = $this->get_predefined_replies();
		$pre_defs           = array( array( 'title' => __( 'Pre-defined Replies', 'supportflow' ), 'content' => '' ) );

		foreach ( $predefined_replies as $predefined_reply ) {
			$content = $predefined_reply['content'];

			if ( ! empty( $predefined_reply['title'] ) ) {
				$title = $predefined_reply['title'];
			} else {
				$title = $predefined_reply['content'];
			}

			// Limit size to $trim_length (default 75) characters
			if ( strlen( $title ) > $trim_length ) {
				$title = substr( $title, 0, $trim_length - 3 ) . '...';
			}

			if ( 0 != strlen( $content ) ) {
				$pre_defs[] = array( 'title' => $title, 'content' => $content );
			}
		}

		$output = '<select id="predefs" ' . $disabled_attr . ' class="predefined_replies_dropdown">';
		foreach ( $pre_defs as $pre_def ) {
			$output .= '<option class="predef" data-content="' . esc_attr( $pre_def['content'] ) . '">' . esc_html( $pre_def['title'] ) . "</option>\n";
		}
		$output .= '</select>';

		if ( $echo ) {
			echo $output;
		}

		return $output;
	}
}

SupportFlow()->extend->predefined_replies = new SupportFlow_Predefined_Replies();