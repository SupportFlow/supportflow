<?php
/**
 *
 */

class SupportPressAdmin extends SupportPress {

	function __construct() {

		if ( $this->is_edit_screen() ) {
			add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
			add_filter( 'enter_title_here', array( $this, 'filter_enter_title_here' ) );
		}
	}

	/**
	 * Manipulate the meta boxes appearing on the edit post view
	 *
	 * When creating a new thread, you should be able to:
	 *
	 * When updating an existing thread, you should be able to:
	 *
	 */
	public function action_add_meta_boxes() {

		remove_meta_box( 'commentstatusdiv', $this->post_type, 'normal' );
	}

	/**
	 * Filter the title field to request a subject
	 */
	public function filter_enter_title_here( $orig ) {
		return __( 'Enter subject here', 'supportpress' );
	}

	/**
	 * Whether or not we're on a view for creating or updating a thread
	 *
	 * @return string $pagenow Return the context for the screen we're in
	 */
	public function is_edit_screen() {
		global $pagenow;
		
		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ) )
			&& $_GET['post_type'] && $_GET['post_type'] == $this->post_type )
			return $pagenow;
		else
			return false;

	}
}