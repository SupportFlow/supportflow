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

		add_action( 'save_post', array( $this, 'action_save_post' ) );

		if ( $this->is_edit_screen() ) {
			add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
			// Modify the messages that appear when saving or creating
			add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );
			add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
		}
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {

		wp_enqueue_style( 'supportpress-admin', SupportPress()->plugin_url . 'css/admin.css', array(), SupportPress()->version );
	}

	/**
	 * Filter the messages that appear to the user after they perform an action on a thread
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post;

		$messages[SupportPress()->post_type] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => __( 'Thread updated.', 'supportpress' ),
			2 => __( 'Custom field updated.', 'supportpress' ),
			3 => __( 'Custom field deleted.', 'supportpress' ),
			4 => __( 'Thread updated.', 'supportpress' ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __( 'Thread restored to revision from %s', 'supportpress' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Thread updated.', 'supportpress' ),
			7 => __( 'Thread updated.', 'supportpress' ),
			8 => __( 'Thread updated.', 'supportpress' ),
			9 => __( 'Thread updated.', 'supportpress' ),
			10 => __( 'Thread updated.', 'supportpress' ),
		);
		return $messages;
	}

	/**
	 * Filter the actions available to the agent on the post type
	 */
	function filter_post_row_actions( $row_actions, $post ) {

		// Rename these actions
		if ( isset( $row_actions['edit'] ) )
			$row_actions['edit'] = str_replace( __( 'Edit' ), __( 'Continue Thread', 'supportpress' ), str_replace( __( 'Edit this item' ), __( 'Continue Thread', 'supportpress' ), $row_actions['edit'] ) );

		// Actions we don't want
		unset( $row_actions['inline hide-if-no-js'] );
		unset( $row_actions['view'] );

		return $row_actions;
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
	 * A box that appears at the top 
	 */
	public function meta_box_subject() {

		$placeholder = __( 'What is your conversation about?', 'supportpress' );
		echo '<h4>' . __( 'Subject', 'supportpress' ) . '</h4>';
		echo '<input type="text" id="subject" name="post_title" placeholder="' . $placeholder . '" value="' . get_the_title() . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Please describe what this thread is about in several words', 'supportpress' ) . '</p>';

	}

	/**
	 * Add a form element where the user can change the respondents
	 */
	public function meta_box_respondents() {

		$respondents = SupportPress()->get_thread_respondents( get_the_ID(), array( 'fields' => 'emails' ) );
		$respondents_string = implode( ', ', $respondents );
		$placeholder = __( 'Who are you starting a conversation with?', 'supportpress' );
		echo '<h4>' . __( 'Respondent(s)', 'supportpress' ) . '</h4>';
		echo '<input type="text" id="respondents" name="respondents" placeholder="' . $placeholder . '" value="' . esc_attr( $respondents_string ) . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Enter each respondent email address, separated with a comma', 'supportpress' ) . '</p>';
	}

	/**
	 * Standard listing of messages includes a form at the top
	 * and any existing messages listed in reverse chronological order
	 */
	public function meta_box_messages() {
		global $pagenow;

		$placeholders = array(
				__( "What's burning?",                              'supportpress' ),
				__( 'What do you need to get off your chest?',      'supportpress' ),
			);

		$rand = array_rand( $placeholders );
		echo '<h4>' . __( 'Conversation', 'supportpress' ) . '</h4>';
		echo '<div class="message-reply">';
		echo "<textarea id='message' name='message' class='thread-message' rows='4' placeholder='" . esc_attr( $placeholders[$rand] ) . "'>";
		echo "</textarea>";
		echo '</div>';
		if ( 'post-new.php' == $pagenow )
			$submit_text = __( 'Start Thread', 'supportpress' );
		else
			$submit_text = __( 'Update Thread', 'supportpress' );
		submit_button( $submit_text );

		$this->display_thread_messages();
	}

	public function display_thread_messages() {

		$messages = SupportPress()->get_thread_messages( get_the_ID() );
		echo '<ul class="thread-messages">';
		foreach( $messages as $message ) {
			echo '<li>';
			echo '<div class="message-avatar">' . get_avatar( $message->comment_author_email, 72 );
			echo '<p class="message-author">' . esc_html( $message->comment_author ) .'</p>';
			echo '</div>';
			echo '<div class="thread-message">' . wpautop( $message->comment_content ) . '</div>';
			$message_timestamp = sprintf( __( '%s at %s', 'supportpress' ), get_comment_date( get_option( 'date_format' ), $message->comment_ID ), get_comment_date( get_option( 'time_format' ), $message->comment_ID ) );
			echo '<div class="thread-meta"><span class="message-timestamp">' . esc_html( $message_timestamp ) . '</span></div>';
			echo '</li>';
		}
		echo '</ul>';

		echo '<div class="clear-left"></div>';

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

	/**
	 * When a thread is saved or updated, make sure we save the respondent
	 * and new message data
	 *
	 * @todo nonce and cap checks
	 */
	public function action_save_post( $thread_id ) {

		if( SupportPress()->post_type != get_post_type( $thread_id ) )
			return;

		if ( isset( $_POST['respondents'] ) ) {
			$respondents = array_map( 'sanitize_email', explode( ',', $_POST['respondents'] ) );
			SupportPress()->update_thread_respondents( $thread_id, $respondents );
		}

		if ( isset( $_POST['message'] ) && !empty( $_POST['message' ] ) ) {
			$message = wp_filter_nohtml_kses( $_POST['message'] );
			SupportPress()->add_thread_message( $thread_id, $message );
		}

	}
}

SupportPress()->extend->admin = new SupportPressAdmin();