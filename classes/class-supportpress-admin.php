<?php
/**
 *
 */

class SupportPressAdmin extends SupportPress {

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		// Creating or updating a thread
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );

		if ( !$this->is_edit_screen() )
			return;

		// Everything
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );

		// Manage threads view
		add_filter( 'manage_' . SupportPress()->post_type . '_posts_columns', array( $this, 'filter_manage_post_columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'action_manage_posts_custom_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );

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
			$row_actions['edit'] = str_replace( __( 'Edit' ), __( 'Discuss', 'supportpress' ), str_replace( __( 'Edit this item' ), __( 'Discuss Thread', 'supportpress' ), $row_actions['edit'] ) );

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
		remove_meta_box( 'commentsdiv',          SupportPress()->post_type, 'normal' );

		add_meta_box( 'supportpress-details', __( 'Details', 'supportpress' ), array( $this, 'meta_box_details' ), SupportPress()->post_type, 'side' );
		add_meta_box( 'supportpress-subject', __( 'Subject', 'supportpress' ), array( $this, 'meta_box_subject' ), SupportPress()->post_type, 'normal' );
		add_meta_box( 'supportpress-respondents', __( 'Respondents', 'supportpress' ), array( $this, 'meta_box_respondents' ), SupportPress()->post_type, 'normal' );
		add_meta_box( 'supportpress-comments', __( 'Commentss', 'supportpress' ), array( $this, 'meta_box_comments' ), SupportPress()->post_type, 'normal' );
	}

	/**
	 * Show details about the thread, and allow the post status and agent to be changed
	 */
	public function meta_box_details() {
		global $pagenow;

		echo '<div id="misc-publishing-actions">';
		// Post status dropdown
		$current_status = get_post_status( get_the_ID() );
		echo '<div class="misc-pub-section">';
		echo '<label for="post_status">' . __( 'Status', 'supportpress' ) . ':</label>';
		echo '<select id="post_status" name="post_status">';
		foreach( SupportPress()->post_statuses as $slug => $post_status ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $current_status, $slug ) . '>' . esc_html( $post_status['label'] ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		// Agent assignment dropdown
		$post_author = get_post( get_the_ID() )->post_author;
		echo '<div class="misc-pub-section">';
		echo '<label for="post_author">' . __( 'Owner', 'supportpress' ) . ':</label>';
		$args = array(
				'show_option_none'    => __( '-- Unassigned --', 'supportpress' ),
				'selected'            => $post_author,
				'id'                  => 'post_author',
				'name'                => 'post_author',
				'who'                 => 'author',
			);
		wp_dropdown_users( $args );
		echo '</div>';

		echo '</div>';

		if ( 'post-new.php' == $pagenow )
			$submit_text = __( 'Start Thread', 'supportpress' );
		else
			$submit_text = __( 'Update Thread', 'supportpress' );
		echo '<div id="major-publishing-actions">';
		echo '<div id="publishing-action">';
		submit_button( $submit_text, 'primary', 'save', false );
		echo '</div>';
		echo '<div class="clear"></div>';
		echo '</div>';

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
	 * Standard listing of comments includes a form at the top
	 * and any existing comments listed in reverse chronological order
	 */
	public function meta_box_comments() {
		global $pagenow;

		$placeholders = array(
				__( "What's burning?",                              'supportpress' ),
				__( 'What do you need to get off your chest?',      'supportpress' ),
			);

		$rand = array_rand( $placeholders );
		echo '<h4>' . __( 'Conversation', 'supportpress' ) . '</h4>';
		echo '<div class="comment-reply">';
		echo "<textarea id='comment' name='comment' class='thread-comment' rows='4' placeholder='" . esc_attr( $placeholders[$rand] ) . "'>";
		echo "</textarea>";
		echo '</div>';
		if ( 'post-new.php' == $pagenow )
			$submit_text = __( 'Start Thread', 'supportpress' );
		else
			$submit_text = __( 'Send Message', 'supportpress' );
		echo '<p class="submit">';
		echo '<input type="checkbox" id="mark-private" name="mark-private" />';
		echo '<label for="mark-private">' . __( 'Mark private', 'supportpress' ) . '</label>';
		submit_button( $submit_text, 'primary', 'save', false );
		echo '</p>';

		$this->display_thread_comments();
	}

	public function display_thread_comments() {

		$private_comments = SupportPress()->get_thread_comments( get_the_ID(), array( 'comment_approved' => 'private' ) );
		if ( !empty( $private_comments ) ) {
			echo '<ul class="private-comments">';
			foreach( $private_comments as $comment ) {
				echo '<li>';
				echo '<div class="thread-comment">' . wpautop( stripslashes( $comment->comment_content ) ) . '</div>';
				$comment_date = get_comment_date( get_option( 'date_format' ), $comment->comment_ID );
				$comment_time = get_comment_date( get_option( 'time_format' ), $comment->comment_ID );
				$comment_timestamp = sprintf( __( 'Noted by %1$s on %2$s at %3$s', 'supportpress' ), $comment->comment_author, $comment_date, $comment_time );
				echo '<div class="thread-meta"><span class="comment-timestamp">' . esc_html( $comment_timestamp ) . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		$comments = SupportPress()->get_thread_comments( get_the_ID(), array( 'comment_approved' => 'public' ) );
		if ( !empty( $comments ) ) {
			echo '<ul class="thread-comments">';
			foreach( $comments as $comment ) {
				echo '<li>';
				echo '<div class="comment-avatar">' . get_avatar( $comment->comment_author_email, 72 );
				echo '<p class="comment-author">' . esc_html( $comment->comment_author ) .'</p>';
				echo '</div>';
				echo '<div class="thread-comment">' . wpautop( stripslashes( $comment->comment_content ) ) . '</div>';
				$comment_timestamp = sprintf( __( '%s at %s', 'supportpress' ), get_comment_date( get_option( 'date_format' ), $comment->comment_ID ), get_comment_date( get_option( 'time_format' ), $comment->comment_ID ) );
				echo '<div class="thread-meta"><span class="comment-timestamp">' . esc_html( $comment_timestamp ) . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<div class="clear"></div>';

	}

	/**
	 * Modifications to the columns appearing in the All Threads view
	 */
	public function filter_manage_post_columns( $columns ) {

		$new_columns = array(
				'title'               => __( 'Subject', 'supportpress' ),
				'status'              => __( 'Status', 'supportpress' ),
				'author'              => __( 'Agent', 'supportpress' ),
				'sp_comments'         => '<span class="vers"><img alt="' . esc_attr__( 'Comments', 'supportpress' ) . '" src="' . esc_url( admin_url( 'images/comment-grey-bubble.png' ) ) . '" /></span>',
				// 'updated'             => __( 'Updated', 'supportpress' ),
				// 'created'             => __( 'Created', 'support' ),
			);
		return $new_columns;
	}

	/**
	 * Produce the column values for the custom columns we created
	 */
	function action_manage_posts_custom_column( $column_name, $thread_id ) {

		switch( $column_name ) {
			case 'status':
				$post_status = get_post_status( $thread_id );
				$args = array(
						'post_type'       => SupportPress()->post_type,
						'post_status'     => $post_status,
					);
				$status_name = get_post_status_object( $post_status )->label;
				$filter_link = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $status_name ) . '</a>';
				break;
			case 'sp_comments':
				$comments = SupportPress()->get_thread_comment_count( $thread_id );
				echo '<div class="post-com-count-wrapper">';
				echo "<span class='comment-count'>{$comments}</span>";
				echo '</div>';
				break;
		}
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
	 * and new comment data
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

		if ( isset( $_POST['comment'] ) && !empty( $_POST['comment' ] ) ) {
			$comment = wp_filter_nohtml_kses( $_POST['comment'] );
			$visibility = ( !empty( $_POST['mark-private'] ) ) ? 'private' : 'public';
			SupportPress()->add_thread_comment( $thread_id, $comment, array( 'comment_approved' => $visibility ) );
		}

	}
}

SupportPress()->extend->admin = new SupportPressAdmin();