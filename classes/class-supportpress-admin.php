<?php
/**
 *
 */

class SupportPressAdmin extends SupportPress {

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_filter( 'manage_' . SupportPress()->post_type . '_posts_columns', array( $this, 'filter_manage_post_columns' ) );

		if ( $this->is_edit_screen() ) {
			add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		}
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {

		wp_enqueue_style( 'supportpress-admin', SupportPress()->plugin_url . 'css/admin.css', array(), SupportPress()->version );
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
		if ( ! $this->is_edit_screen() )
			return;

		remove_meta_box( 'submitdiv', SupportPress()->post_type, 'side' );
		remove_meta_box( 'commentstatusdiv', SupportPress()->post_type, 'normal' );
		remove_meta_box( 'slugdiv',          SupportPress()->post_type, 'normal' );

		add_meta_box( 'supportpress-subject', __( 'Subject', 'supportpress' ), array( $this, 'meta_box_subject' ), SupportPress()->post_type, 'normal' );
		add_meta_box( 'supportpress-respondents', __( 'Respondents', 'supportpress' ), array( $this, 'meta_box_respondents' ), SupportPress()->post_type, 'normal' );
		add_meta_box( 'supportpress-messages', __( 'Messages', 'supportpress' ), array( $this, 'meta_box_messages' ), SupportPress()->post_type, 'normal' );
		// @todo metabox for thread details
	}

	/**
	 * Allow agents to assign one or more respondents to a thread
	 * These are essentially the people who are being "supported"
	 */
	public function meta_box_subject() {
		global $post;

		$placeholder = __( 'What is your conversation about?', 'supportpress' );
		echo '<h4>' . __( 'Subject', 'supportpress' ) . '</h4>';
		echo '<input type="text" id="subject" name="post_title" placeholder="' . $placeholder . '" value="' . esc_attr( $post->title ) . '" />';
	}

	/**
	 * Allow agents to assign one or more respondents to a thread
	 * These are essentially the people who are being "supported"
	 */
	public function meta_box_respondents() {

		$respondents = implode( ', ', array() );
		$placeholder = __( 'Who are you starting a conversation with?', 'supportpress' );
		echo '<h4>' . __( 'Respondent(s)', 'supportpress' ) . '</h4>';
		echo '<input type="text" id="respondents" name="respondents" placeholder="' . $placeholder . '" value="' . esc_attr( $respondents ) . '" />';
	}

	/**
	 * Standard listing of messages includes a form at the top
	 * and any existing messages listed in reverse chronological order
	 */
	public function meta_box_messages() {

		$placeholders = array(
				__( "What's burning?",                              'supportpress' ),
				__( 'What do you need to get off your chest?',      'supportpress' ),
			);

		$rand = array_rand( $placeholders );
		echo '<h4>' . __( 'Conversation', 'supportpress' ) . '</h4>';
		echo "<textarea id='message' name='message' placeholder='" . esc_attr( $placeholders[$rand] ) . "'>";
		echo "</textarea>";

	}

	/**
	 * Modifications to the columns appearing in the All Threads view
	 */
	public function filter_manage_post_columns( $columns ) {

		$columns['title'] = __( 'Subject', 'supportpress' );
		return $columns;
	}

	/**
	 * Whether or not we're on a view for creating or updating a thread
	 *
	 * @return string $pagenow Return the context for the screen we're in
	 */
	public function is_edit_screen() {
		global $pagenow;

		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ) ) && ! empty( $_GET['post_type'] ) && $_GET['post_type'] == SupportPress()->post_type ) {
			return $pagenow;
		} elseif ( 'post.php' == $pagenow && ! empty( $_GET['action'] ) && 'edit' == $_GET['action'] && ! empty( $_GET['post'] ) ) {
			$the_post = get_post( $_GET['post'] );
			return ( $the_post->post_type == SupportPress()->post_type ) ? $pagenow : false;
		} else {
			return false;
		}

	}
}

SupportPress()->extend->admin = new SupportPressAdmin();