<?php
/**
 *
 */

class SupportFlow_Admin extends SupportFlow {

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
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
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		// Manage threads view
		add_filter( 'manage_' . SupportFlow()->post_type . '_posts_columns', array( $this, 'filter_manage_post_columns' ) );
		add_filter( 'manage_edit-' . SupportFlow()->post_type . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'action_manage_posts_custom_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . SupportFlow()->post_type, array( $this, 'filter_bulk_actions' ) );
		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ) );
		add_action( 'admin_action_change_status', array( $this, 'handle_action_change_status' ) );

	}

	/**
	 * Re-sort the custom statuses so trash appears last
	 */
	function action_admin_init() {
		global $wp_post_statuses;

		$trash_status = $wp_post_statuses['trash'];
		unset( $wp_post_statuses['trash'] );
		$wp_post_statuses['trash'] = $trash_status;
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {

		wp_enqueue_style( 'supportflow-admin', SupportFlow()->plugin_url . 'css/admin.css', array(), SupportFlow()->version );
		wp_enqueue_script( 'supportflow-plupload', SupportFlow()->plugin_url . 'js/plupload.js' , array( 'wp-plupload', 'jquery' ) );
		self::add_default_plupload_settings();
	}

	/**
	 * Sets up some default Plupload settings so we can upload media
	 */
	private static function add_default_plupload_settings() {
		global $wp_scripts;

		$defaults = array(
			'runtimes'            => 'html5,silverlight,flash,html4',
			'file_data_name'      => 'async-upload',
			'multiple_queues'     => true,
			'url'                 => add_query_arg( 'post_id', get_the_id(), admin_url( 'admin-ajax.php', 'relative' ) ),
			'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'             => array( array( 'title' => __( 'Allowed Files' ), 'extensions' => '*') ),
			'multipart'           => true,
			'urlstream_upload'    => true,
			'multipart_params'    => array(
				'action'          => 'upload-attachment',
				'_wpnonce'        => wp_create_nonce( 'media-form' )
			)
		);

		$settings = array(
			'defaults' => $defaults,
			'browser'  => array(
				'mobile'    => wp_is_mobile(),
				'supported' => _device_can_upload(),
			)
		);

		$script = 'var _wpPluploadSettings = ' . json_encode( $settings ) . ';';
		$data   = $wp_scripts->get_data( 'wp-plupload', 'data' );

		if ( ! empty( $data ) )
			$script = "$data\n$script";

		$wp_scripts->add_data( 'wp-plupload', 'data', $script );
	}

	/**
	 * Filter the messages that appear to the user after they perform an action on a thread
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post;

		$messages[SupportFlow()->post_type] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => __( 'Thread updated.', 'supportflow' ),
			2 => __( 'Custom field updated.', 'supportflow' ),
			3 => __( 'Custom field deleted.', 'supportflow' ),
			4 => __( 'Thread updated.', 'supportflow' ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __( 'Thread restored to revision from %s', 'supportflow' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Thread updated.', 'supportflow' ),
			7 => __( 'Thread updated.', 'supportflow' ),
			8 => __( 'Thread updated.', 'supportflow' ),
			9 => __( 'Thread updated.', 'supportflow' ),
			10 => __( 'Thread updated.', 'supportflow' ),
		);
		return $messages;
	}

	/**
	 * Filter the actions available to the agent on the post type
	 */
	function filter_post_row_actions( $row_actions, $post ) {

		// Rename these actions
		if ( isset( $row_actions['edit'] ) )
			$row_actions['edit'] = str_replace( __( 'Edit' ), __( 'Discussion', 'supportflow' ), str_replace( __( 'Edit this item' ), __( 'Discuss Thread', 'supportflow' ), $row_actions['edit'] ) );

		// Save the trash action for the end
		if ( isset( $row_actions['trash'] ) ) {
			$trash_action = $row_actions['trash'];
			unset( $row_actions['trash'] );
		} else {
			$trash_action = false;
		}

		// Allow an agent to easily close a ticket
		$statuses = SupportFlow()->post_statuses;
		$status_slugs = array_keys( $statuses );
		$last_status = array_pop( $status_slugs );
		if ( !in_array( get_query_var( 'post_status' ), array( 'trash' ) ) ) {

			if ( $last_status == get_post_status( $post->ID ) )
				$change_to = $status_slugs[2];
			else
				$change_to = $last_status;

			$args = array(
					'action'          => 'change_status',
					'sp_nonce'        => wp_create_nonce( 'sp-change-status' ),
					'post_status'     => $change_to,
					'thread_id'       => $post->ID,
					'post_type'       => SupportFlow()->post_type,
				);
			$action_link = add_query_arg( $args, admin_url( 'edit.php' ) );
			if ( $last_status == $change_to ) {
				$title_attr = esc_attr__( 'Close Thread', 'supportflow' );
				$action_text = esc_html__( 'Close', 'supportflow' );
			} else {
				$title_attr = esc_attr__( 'Reopen Thread', 'supportflow' );
				$action_text = esc_html__( 'Reopen', 'supportflow' );
			}
			$row_actions['change_status'] = '<a href="' . esc_url( $action_link ) . '" title="' . $title_attr . '">' . $action_text . '</a>';
		}

		// Actions we don't want
		unset( $row_actions['inline hide-if-no-js'] );
		unset( $row_actions['view'] );

		if ( $trash_action )
			$row_actions['trash'] = $trash_action;

		return $row_actions;
	}

	/**
	 * Remove the 'edit' bulk action. Doesn't do much for us
	 */
	public function filter_bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Handle which threads are show on the Manage Threads view when
	 */
	function action_pre_get_posts( $query ) {
		global $pagenow;

		if ( 'edit.php' != $pagenow || !$query->is_main_query() )
			return;

		$statuses = SupportFlow()->post_statuses;
		$status_slugs = array_keys( $statuses );
		$last_status = array_pop( $status_slugs );

		// Order posts by post_modified if there's no orderby set
		if ( !$query->get( 'orderby' ) ) {
			$sort_order = array(
					'orderby'       => 'modified',
				);
			$sort_order['order'] = ( in_array( $query->get( 'post_status' ), array( 'trash', $last_status ) ) ) ? 'desc' : 'asc';
			foreach( $sort_order as $key => $value ) {
				$query->set( $key, $value );
				$_GET[$key] = $value;
			}
		}

		// Do our own custom search handling so we can search against comment text
		if ( $search = $query->get( 's' ) ) {
			// Get any comments that match our results
			$args = array(
					'search'                   => $search,
					'comment_approved'         => 'any',
				);
			$matching_comments = SupportFlow()->get_comments( $args );
			$post_ids = wp_list_pluck( $matching_comments, 'comment_post_ID' );

			$args = array(
					's'                        => $search,
					'post_type'                => SupportFlow()->post_type,
					'no_found_rows'            => true,
					'update_post_meta_cache'   => false,
					'update_post_term_cache'   => false,
					'fields'                   => 'ids',
				);
			$post_query = new WP_Query( $args );
			if ( !is_wp_error( $post_query ) )
				$post_ids = array_merge( $post_ids, $post_query->posts );

			$query->set( 'post__in', $post_ids );
			// Ignore the original search query
			add_filter( 'posts_search', array( $this, 'filter_posts_search' ) );
		}

		// Only show threads with the last status if the last status is set
		$post_status = $query->get( 'post_status' );
		if ( !$query->get( 's' ) && empty( $post_status ) )
			$query->set( 'post_status', $status_slugs );

	}

	/**
	 * Sometimes we want to ignore the original search query because we do our own
	 */
	public function filter_posts_search( $posts_search ) {
		return '';
	}

	/**
	 * Handle $_GET actions in the admin
	 *
	 * @todo need a caps check too to make sure this user can edit the thread
	 */
	function handle_action_change_status() {

		if ( ! isset( $_GET['action'], $_GET['sp_nonce'], $_GET['post_status'], $_GET['thread_id'] ) )
			return;

		if ( ! wp_verify_nonce( $_GET['sp_nonce'], 'sp-change-status' ) )
			wp_die( __( "Doin' something phishy, huh?", 'supportflow' ) );

		$post_status = sanitize_key( $_GET['post_status'] );
		$thread_id = (int)$_GET['thread_id'];

		$new_thread = array(
				'ID'               => $thread_id,
				'post_status'      => $post_status,
			);
		wp_update_post( $new_thread );
		wp_safe_redirect( wp_get_referer() );
		exit;
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

		$respondents_box = 'tagsdiv-' . SupportFlow()->respondents_tax;
		remove_meta_box( 'submitdiv',               SupportFlow()->post_type, 'side' );
		remove_meta_box( $respondents_box,          SupportFlow()->post_type, 'side' );
		remove_meta_box( 'commentstatusdiv',        SupportFlow()->post_type, 'normal' );
		remove_meta_box( 'slugdiv',                 SupportFlow()->post_type, 'normal' );
		remove_meta_box( 'commentsdiv',             SupportFlow()->post_type, 'normal' );

		add_meta_box( 'supportflow-details', __( 'Details', 'supportflow' ), array( $this, 'meta_box_details' ), SupportFlow()->post_type, 'side' );
		add_meta_box( 'supportflow-subject', __( 'Subject', 'supportflow' ), array( $this, 'meta_box_subject' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-respondents', __( 'Respondents', 'supportflow' ), array( $this, 'meta_box_respondents' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-comments', __( 'Commentss', 'supportflow' ), array( $this, 'meta_box_comments' ), SupportFlow()->post_type, 'normal' );
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
		echo '<label for="post_status">' . __( 'Status', 'supportflow' ) . ':</label>';
		echo '<select id="post_status" name="post_status">';
		foreach( SupportFlow()->post_statuses as $slug => $post_status ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $current_status, $slug ) . '>' . esc_html( $post_status['label'] ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		// Agent assignment dropdown
		$post_author = get_post( get_the_ID() )->post_author;
		echo '<div class="misc-pub-section">';
		echo '<label for="post_author">' . __( 'Owner', 'supportflow' ) . ':</label>';
		$args = array(
				'show_option_none'    => __( '-- Unassigned --', 'supportflow' ),
				'selected'            => $post_author,
				'id'                  => 'post_author',
				'name'                => 'post_author',
				'who'                 => 'author',
			);
		wp_dropdown_users( $args );
		echo '</div>';

		echo '</div>';

		if ( 'post-new.php' == $pagenow )
			$submit_text = __( 'Start Thread', 'supportflow' );
		else
			$submit_text = __( 'Update Thread', 'supportflow' );
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

		$placeholder = __( 'What is your conversation about?', 'supportflow' );
		echo '<h4>' . __( 'Subject', 'supportflow' ) . '</h4>';
		echo '<input type="text" id="subject" name="post_title" placeholder="' . $placeholder . '" value="' . get_the_title() . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Please describe what this thread is about in several words', 'supportflow' ) . '</p>';

	}

	/**
	 * Add a form element where the user can change the respondents
	 */
	public function meta_box_respondents() {

		$respondents = SupportFlow()->get_thread_respondents( get_the_ID(), array( 'fields' => 'emails' ) );
		$respondents_string = implode( ', ', $respondents );
		$placeholder = __( 'Who are you starting a conversation with?', 'supportflow' );
		echo '<h4>' . __( 'Respondent(s)', 'supportflow' ) . '</h4>';
		echo '<input type="text" id="respondents" name="respondents" placeholder="' . $placeholder . '" value="' . esc_attr( $respondents_string ) . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Enter each respondent email address, separated with a comma', 'supportflow' ) . '</p>';
	}

	/**
	 * Standard listing of comments includes a form at the top
	 * and any existing comments listed in reverse chronological order
	 */
	public function meta_box_comments() {
		global $pagenow;

		$placeholders = array(
				__( "What's burning?",                              'supportflow' ),
				__( 'What do you need to get off your chest?',      'supportflow' ),
			);

		$rand = array_rand( $placeholders );
		echo '<h4>' . __( 'Conversation', 'supportflow' ) . '</h4>';
		echo '<div id="comment-reply">';

		echo "<textarea id='comment' name='comment' class='thread-comment' rows='4' placeholder='" . esc_attr( $placeholders[$rand] ) . "'>";
		echo "</textarea>";

		echo '<div id="message-tools">';
		echo '<div id="comment-attachments-wrap">';
		echo '<div id="upload-messages">' . __( 'Drop a file in the message to attach it' ) . '</div>';
		echo '<ul id="comment-attachments-list">';
		echo '</ul>';
		echo '<input type="hidden" id="comment-attachments" name="comment-attachments" />';
		echo '</div>';
		echo '<div id="submit-action">';
		echo '<input type="checkbox" id="mark-private" name="mark-private" />';
		echo '<label for="mark-private">' . __( 'Mark private', 'supportflow' ) . '</label>';
		if ( 'post-new.php' == $pagenow )
			$submit_text = __( 'Start Thread', 'supportflow' );
		else
			$submit_text = __( 'Send Message', 'supportflow' );
		submit_button( $submit_text, 'primary', 'save', false );
		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="clear"></div>';

		$this->display_thread_comments();
	}

	public function display_thread_comments() {

		$private_comments = SupportFlow()->get_thread_comments( get_the_ID(), array( 'comment_approved' => 'private' ) );
		if ( !empty( $private_comments ) ) {
			echo '<ul class="private-comments">';
			foreach( $private_comments as $comment ) {
				echo '<li>';
				echo '<div class="thread-comment">';
				echo wpautop( stripslashes( $comment->comment_content ) );
				if ( $attachment_ids = get_comment_meta( $comment->comment_ID, 'attachment_ids', true ) ) {
					echo '<ul class="thread-comment-attachments">';
					foreach( $attachment_ids as $attachment_id ) {
						$attachment_link = wp_get_attachment_url( $attachment_id );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( get_the_title( $attachment_id ) ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$comment_date = get_comment_date( get_option( 'date_format' ), $comment->comment_ID );
				$comment_time = get_comment_date( get_option( 'time_format' ), $comment->comment_ID );
				$comment_timestamp = sprintf( __( 'Noted by %1$s on %2$s at %3$s', 'supportflow' ), $comment->comment_author, $comment_date, $comment_time );
				echo '<div class="thread-meta"><span class="comment-timestamp">' . esc_html( $comment_timestamp ) . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		$comments = SupportFlow()->get_thread_comments( get_the_ID(), array( 'comment_approved' => 'public' ) );
		if ( !empty( $comments ) ) {
			echo '<ul class="thread-comments">';
			foreach( $comments as $comment ) {
				echo '<li>';
				echo '<div class="comment-avatar">' . get_avatar( $comment->comment_author_email, 72 );
				echo '<p class="comment-author">' . esc_html( $comment->comment_author ) .'</p>';
				echo '</div>';
				echo '<div class="thread-comment">';
				echo wpautop( stripslashes( $comment->comment_content ) );
				if ( $attachment_ids = get_comment_meta( $comment->comment_ID, 'attachment_ids', true ) ) {
					echo '<ul class="thread-comment-attachments">';
					foreach( $attachment_ids as $attachment_id ) {
						$attachment_link = wp_get_attachment_url( $attachment_id );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( get_the_title( $attachment_id ) ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$comment_timestamp = sprintf( __( '%s at %s', 'supportflow' ), get_comment_date( get_option( 'date_format' ), $comment->comment_ID ), get_comment_date( get_option( 'time_format' ), $comment->comment_ID ) );
				echo '<div class="thread-meta"><span class="comment-timestamp">' . esc_html( $comment_timestamp ) . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<div class="clear"></div>';

	}

	/**
	 * Modifications to the columns appearing in the All Threads view
	 *
	 * @todo maybe add 'Created' column
	 */
	public function filter_manage_post_columns( $columns ) {

		$new_columns = array(
				'cb'                  => $columns['cb'],
				'updated'             => __( 'Updated', 'supportflow' ),
				'title'               => __( 'Subject', 'supportflow' ),
				'respondents'         => __( 'Respondents', 'supportflow' ),
				'status'              => __( 'Status', 'supportflow' ),
				'author'              => __( 'Agent', 'supportflow' ),
				'sp_comments'         => '<span class="vers"><img alt="' . esc_attr__( 'Comments', 'supportflow' ) . '" src="' . esc_url( admin_url( 'images/comment-grey-bubble.png' ) ) . '" /></span>',
				'created'             => __( 'Created', 'support' ),
			);
		return $new_columns;
	}

	/**
	 * Make some other columns sortable too
	 */
	public function manage_sortable_columns( $columns ) {
		$columns['updated'] = 'modified';
		$columns['created'] = 'date';
		return $columns;
	}

	/**
	 * Produce the column values for the custom columns we created
	 */
	function action_manage_posts_custom_column( $column_name, $thread_id ) {

		switch( $column_name ) {
			case 'updated':
				$modified_gmt = get_post_modified_time( 'U', true, $thread_id );
				echo sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				break;
			case 'respondents':
				$respondents = SupportFlow()->get_thread_respondents( $thread_id, array( 'fields' => 'emails' ) );
				if ( empty( $respondents ) )
					break;
				foreach( $respondents as $key => $respondent_email ) {
					$args = array(
							SupportFlow()->respondents_tax     => SupportFlow()->get_email_hash( $respondent_email ),
							'post_type'                         => SupportFlow()->post_type,
						);
					$respondent_link = '<a href="' . esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ) . '">' . $respondent_email . '</a>';
					$respondents[$key] = get_avatar( $respondent_email, 16 ) . '&nbsp;&nbsp;' . $respondent_link;
				}
				echo implode( '<br />', $respondents );
				break;
			case 'status':
				$post_status = get_post_status( $thread_id );
				$args = array(
						'post_type'       => SupportFlow()->post_type,
						'post_status'     => $post_status,
					);
				$status_name = get_post_status_object( $post_status )->label;
				$filter_link = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $status_name ) . '</a>';
				break;
			case 'sp_comments':
				$comments = SupportFlow()->get_thread_comment_count( $thread_id );
				echo '<div class="post-com-count-wrapper">';
				echo "<span class='comment-count'>{$comments}</span>";
				echo '</div>';
				break;
			case 'created':
				$created_time = get_the_time( get_option( 'time_format' ) . ' T', $thread_id );
				$created_date = get_the_time( get_option( 'date_format' ), $thread_id );
				echo sprintf( __( '%s<br />%s', 'supportflow' ), $created_time, $created_date );
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

		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ) ) && ! empty( $_GET['post_type'] ) && $_GET['post_type'] == SupportFlow()->post_type ) {
			return $pagenow;
		} elseif ( 'post.php' == $pagenow && ! empty( $_GET['action'] ) && 'edit' == $_GET['action'] && ! empty( $_GET['post'] ) ) {
			$the_post = get_post( $_GET['post'] );
			return ( $the_post->post_type == SupportFlow()->post_type ) ? $pagenow : false;
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

		if( SupportFlow()->post_type != get_post_type( $thread_id ) )
			return;

		if ( isset( $_POST['respondents'] ) ) {
			$respondents = array_map( 'sanitize_email', explode( ',', $_POST['respondents'] ) );
			SupportFlow()->update_thread_respondents( $thread_id, $respondents );
		}

		if ( isset( $_POST['comment'] ) && !empty( $_POST['comment' ] ) ) {
			$comment = wp_filter_nohtml_kses( $_POST['comment'] );
			$visibility = ( !empty( $_POST['mark-private'] ) ) ? 'private' : 'public';
			if ( !empty( $_POST['comment-attachments'] ) )
				$attachment_ids = array_map( 'intval', explode( ',', trim( $_POST['comment-attachments'], ',' ) ) );
			else
				$attachment_ids = '';
			$comment_args = array(
					'comment_approved'        => $visibility,
					'attachment_ids'          => $attachment_ids,
				);
			SupportFlow()->add_thread_comment( $thread_id, $comment, $comment_args );
		}

	}
}

SupportFlow()->extend->admin = new SupportFlow_Admin();