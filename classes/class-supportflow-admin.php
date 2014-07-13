<?php
/**
 *
 */

class SupportFlow_Admin extends SupportFlow {

	function __construct() {
		add_action( 'wp_ajax_thread_attachment_upload', array( $this, 'action_wp_ajax_thread_attachment_upload' ) );
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		// Creating or updating a thread
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );

		if ( ! $this->is_edit_screen() ) {
			return;
		}

		// Everything
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		// Manage threads view
		add_filter( 'manage_' . SupportFlow()->post_type . '_posts_columns', array( $this, 'filter_manage_post_columns' ) );
		add_filter( 'manage_edit-' . SupportFlow()->post_type . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'action_manage_posts_custom_column' ), 10, 2 );
		add_filter( 'views_edit-' . SupportFlow()->post_type, array( $this, 'filter_views' ) );
		add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . SupportFlow()->post_type, array( $this, 'filter_bulk_actions' ) );
		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ) );
		add_action( 'admin_action_change_status', array( $this, 'handle_action_change_status' ) );
		add_action( 'restrict_manage_posts', array( $this, 'action_restrict_manage_posts' ) );

	}

	/**
	 * Re-sort the custom statuses so trash appears last
	 */
	function action_admin_init() {
		global $wp_post_statuses, $pagenow;

		$trash_status = $wp_post_statuses['trash'];
		unset( $wp_post_statuses['trash'] );
		$wp_post_statuses['trash'] = $trash_status;

		if ( 'edit.php' == $pagenow ) {
			add_filter( 'get_the_excerpt', array( $this, 'filter_get_the_excerpt' ) );
		}
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {
		global $pagenow;

		wp_enqueue_style( 'supportflow-admin', SupportFlow()->plugin_url . 'css/admin.css', array(), SupportFlow()->version );
		if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) {
			wp_enqueue_media();

			wp_enqueue_script( 'supportflow-thread-attachments', SupportFlow()->plugin_url . 'js/thread_attachments.js', array( 'jquery' ) );
			wp_enqueue_script( 'supportflow-respondents-autocomplete', SupportFlow()->plugin_url . 'js/respondents-autocomplete.js', array( 'jquery', 'jquery-ui-autocomplete' ) );
			wp_enqueue_script( 'supportflow-threads', SupportFlow()->plugin_url . 'js/threads.js', array( 'jquery' ) );

			$ajaxurl = add_query_arg( 'action', SupportFlow()->extend->jsonapi->action, admin_url( 'admin-ajax.php' ) );

			wp_localize_script( 'supportflow-respondents-autocomplete', 'SFRespondentsAc', array( 'ajax_url' => $ajaxurl ) );
			wp_localize_script( 'supportflow-thread-attachments', 'SFThreadAttachments', array(
				'frame_title'  => __( 'Attach files', 'supportflow' ),
				'button_title' => __( 'Insert as attachment', 'supportflow' ),
			) );
			wp_localize_script( 'supportflow-threads', 'SFThreads', array(
				'no_title_msg'      => __( 'You must need to specify the subject of the thread', 'supportpress' ),
				'no_respondent_msg' => __( 'You must need to add atleast one thread respondent', 'supportpress' ),
			) );
		}
	}

	/**
	 * Filter the messages that appear to the user after they perform an action on a thread
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post;

		$messages[SupportFlow()->post_type] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Thread updated.', 'supportflow' ),
			2  => __( 'Custom field updated.', 'supportflow' ),
			3  => __( 'Custom field deleted.', 'supportflow' ),
			4  => __( 'Thread updated.', 'supportflow' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Thread restored to revision from %s', 'supportflow' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Thread updated.', 'supportflow' ),
			7  => __( 'Thread updated.', 'supportflow' ),
			8  => __( 'Thread updated.', 'supportflow' ),
			9  => __( 'Thread updated.', 'supportflow' ),
			10 => __( 'Thread updated.', 'supportflow' ),
		);

		return $messages;
	}

	/**
	 *
	 */
	public function filter_views( $views ) {
		global $wpdb;

		$post_type     = SupportFlow()->post_type;
		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_threads'] ) {
				$status_slugs[] = $status;
			}
		}

		$wp_query    = new WP_Query( array(
			'post_type'      => $post_type,
			'post_parent'    => 0,
			'posts_per_page' => 1,
			'post_status'    => $status_slugs,
		) );
		$total_posts = $wp_query->found_posts;

		$class    = empty( $class ) && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['show_sticky'] ) ? ' class="current"' : '';
		$view_all = "<a href='edit.php?post_type=$post_type'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

		// @todo Only show "Mine" if the user is an agent
		$mine_args     = array(
			'post_type' => SupportFlow()->post_type,
			'author'    => get_current_user_id(),
		);
		$post_statuses = SupportFlow()->post_statuses;
		array_pop( $post_statuses );
		$post_statuses = "'" . implode( "','", array_map( 'sanitize_key', array_keys( $post_statuses ) ) ) . "'";
		$my_posts      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type=%s AND post_author=%d AND post_status IN ({$post_statuses})", SupportFlow()->post_type, get_current_user_id() ) );
		$view_mine     = '<a href="' . add_query_arg( $mine_args, admin_url( 'edit.php' ) ) . '">' . sprintf( _nx( 'Mine <span class="count">(%s)</span>', 'Mine <span class="count">(%s)</span>', $my_posts, 'posts' ), number_format_i18n( $my_posts ) ) . '</a>';

		// Put 'All' and 'Mine' at the beginning of the array
		array_shift( $views );
		$views         = array_reverse( $views );
		$views['mine'] = $view_mine;
		$views['all']  = $view_all;
		$views         = array_reverse( $views );

		// Remove private option from filter links as they are just private replies to thread
		unset( $views['private'] );

		return $views;
	}

	/**
	 * Add custom filters for the Manage Threads view
	 */
	public function action_restrict_manage_posts() {

		// Filter to specific agents
		$agent_dropdown_args = array(
			'show_option_all' => __( 'Show all agents', 'supportflow' ),
			'name'            => 'author',
			'selected'        => ( ! empty( $_REQUEST['author'] ) ) ? (int) $_REQUEST['author'] : false,
			'who'             => 'authors',
		);
		$agent_dropdown_args = apply_filters( 'supportflow_admin_agent_dropdown_args', $agent_dropdown_args );
		wp_dropdown_users( $agent_dropdown_args );

		// Filter to specify tag
		$tax_slug = SupportFlow()->tags_tax;
		$terms    = get_terms( 'sf_tags', array( 'hide_empty' => false ) );

		echo "<select name='" . esc_attr( $tax_slug ) . "' id='" . esc_attr( $tax_slug ) . "' class='postform'>";
		echo "<option value=''>" . __( 'Show All tags', 'supportflow' ) . "</option>";
		foreach ( $terms as $term ) {
			$selected = selected( isset( $_REQUEST[$tax_slug] ) && ( $_REQUEST[$tax_slug] == $term->slug ), true, false );
			echo "<option value='" . esc_attr( $term->slug ) . "' $selected>" . esc_html( $term->name ) . '</option>';
		}
		echo "</select>";


		// Filter to specify E-Mail account
		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		echo "<select name='email_account' id='email_account' class='postform'>";
		echo "<option value=''>" . __( 'Show All Accounts', 'supportflow' ) . "</option>";
		foreach ( $email_accounts as $id => $email_account ) {
			if ( empty( $email_account ) ) {
				continue;
			}
			$selected = selected( isset( $_REQUEST['email_account'] ) && ( $_REQUEST['email_account'] == $id ), true, false );
			echo "<option value='" . esc_attr( $id ) . "'$selected>" . esc_html( $email_account['username'] . ' (' . $email_account['imap_host'] . ')' ) . '</option>';
		}
		echo "</select>";

	}

	/**
	 * Filter the actions available to the agent on the post type
	 */
	function filter_post_row_actions( $row_actions, $post ) {

		// Rename these actions
		if ( isset( $row_actions['edit'] ) ) {
			$row_actions['edit'] = str_replace( __( 'Edit' ), __( 'View', 'supportflow' ), str_replace( __( 'Edit this item' ), __( 'View Thread', 'supportflow' ), $row_actions['edit'] ) );
		}

		// Save the trash action for the end
		if ( isset( $row_actions['trash'] ) ) {
			$trash_action = $row_actions['trash'];
			unset( $row_actions['trash'] );
		} else {
			$trash_action = false;
		}

		// Allow an agent to easily close a ticket
		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array_keys( $statuses );
		$last_status  = array_pop( $status_slugs );
		if ( ! in_array( get_query_var( 'post_status' ), array( 'trash' ) ) ) {

			if ( $last_status == get_post_status( $post->ID ) ) {
				$change_to = $status_slugs[2];
			} else {
				$change_to = $last_status;
			}

			$args        = array(
				'action'      => 'change_status',
				'sf_nonce'    => wp_create_nonce( 'sf-change-status' ),
				'post_status' => $change_to,
				'thread_id'   => $post->ID,
				'post_type'   => SupportFlow()->post_type,
			);
			$action_link = add_query_arg( $args, admin_url( 'edit.php' ) );
			if ( $last_status == $change_to ) {
				$title_attr  = esc_attr__( 'Close Thread', 'supportflow' );
				$action_text = esc_html__( 'Close', 'supportflow' );
			} else {
				$title_attr  = esc_attr__( 'Reopen Thread', 'supportflow' );
				$action_text = esc_html__( 'Reopen', 'supportflow' );
			}

			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$row_actions['change_status'] = '<a href="' . esc_url( $action_link ) . '" title="' . $title_attr . '">' . $action_text . '</a>';
			}
		}

		// Actions we don't want
		unset( $row_actions['inline hide-if-no-js'] );
		unset( $row_actions['view'] );

		if ( $trash_action ) {
			$row_actions['trash'] = $trash_action;
		}

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

		if ( 'edit.php' != $pagenow || ! $query->is_main_query() ) {
			return;
		}

		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_threads'] ) {
				$status_slugs[] = $status;
			}
		}

		// Order posts by post_modified if there's no orderby set
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'modified' );
			$query->set( 'order', 'DESC' );
		}

		// Do our own custom search handling so we can search against reply text
		if ( $search = $query->get( 's' ) ) {

			// Get all replies that match our results
			$args             = array(
				'search' => $search,
				'status' => 'any',
			);
			$matching_replies = SupportFlow()->get_replies( $args );
			$post_ids         = wp_list_pluck( $matching_replies, 'post_parent' );

			$args       = array(
				's'                      => $search,
				'post_type'              => SupportFlow()->post_type,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			);
			$post_query = new WP_Query( $args );
			if ( ! is_wp_error( $post_query ) ) {
				$post_ids = array_merge( $post_ids, $post_query->posts );
			}

			$query->set( 'post__in', $post_ids );
			// Ignore the original search query
			add_filter( 'posts_search', array( $this, 'filter_posts_search' ) );
		}

		// Only show threads with the last status if the last status is set
		$post_status = $query->get( 'post_status' );
		if ( ! $query->get( 's' ) && empty( $post_status ) ) {
			$query->set( 'post_status', $status_slugs );
		}


		if ( isset( $_GET['email_account'] ) && ! empty( $_GET['email_account'] ) ) {
			$query->set( 'meta_key', 'email_account' );
			$query->set( 'meta_value', (int) $_GET['email_account'] );
		}
	}

	/**
	 * Sometimes we want to ignore the original search query because we do our own
	 */
	public function filter_posts_search( $posts_search ) {
		return '';
	}

	/**
	 * Handle $_GET actions in the admin
	 */
	function handle_action_change_status() {

		if ( ! isset( $_GET['action'], $_GET['sf_nonce'], $_GET['post_status'], $_GET['thread_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['sf_nonce'], 'sf-change-status' ) ) {
			wp_die( __( "Doin' something phishy, huh?", 'supportflow' ) );
		}

		$thread_id = (int) $_GET['thread_id'];

		if ( ! current_user_can( 'edit_post', $thread_id ) ) {
			wp_die( __( 'You are not allowed to edit this item.' ) );
		}

		$post_status = sanitize_key( $_GET['post_status'] );
		$new_thread  = array(
			'ID'          => $thread_id,
			'post_status' => $post_status,
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
		if ( ! $this->is_edit_screen() ) {
			return;
		}

		$respondents_box = 'tagsdiv-' . SupportFlow()->respondents_tax;
		remove_meta_box( 'submitdiv', SupportFlow()->post_type, 'side' );
		remove_meta_box( $respondents_box, SupportFlow()->post_type, 'side' );
		remove_meta_box( 'slugdiv', SupportFlow()->post_type, 'normal' );

		add_meta_box( 'supportflow-details', __( 'Details', 'supportflow' ), array( $this, 'meta_box_details' ), SupportFlow()->post_type, 'side' );
		add_meta_box( 'supportflow-subject', __( 'Subject', 'supportflow' ), array( $this, 'meta_box_subject' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-respondents', __( 'Respondents', 'supportflow' ), array( $this, 'meta_box_respondents' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-cc-bcc', __( 'CC and BCC', 'supportflow' ), array( $this, 'meta_box_cc_bcc' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-replies', __( 'Replies', 'supportflow' ), array( $this, 'meta_box_replies' ), SupportFlow()->post_type, 'normal' );
	}

	/**
	 * Show details about the thread, and allow the post status and agent to be changed
	 */
	public function meta_box_details() {
		global $pagenow;

		// Get post creation and last update time
		if ( 'post.php' == $pagenow ) {
			$opened        = get_the_date() . ' ' . get_the_time();
			$modified_gmt  = get_post_modified_time( 'U', true, get_the_ID() );
			$last_activity = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
		}


		// Get post status
		$post_statuses     = SupportFlow()->post_statuses;
		$current_status_id = get_post_status( get_the_ID() );

		if ( ! isset( $post_statuses[$current_status_id] ) ) {
			$post_statuses_key = array_keys( $post_statuses );
			$current_status_id = $post_statuses_key[0];
		}

		$current_status_label = $post_statuses[$current_status_id]['label'];


		// Get post authors
		$post_author_id = get_post( get_the_ID() )->post_author;
		if ( 0 < $post_author_id ) {
			$post_author_label = get_userdata( $post_author_id )->data->user_nicename;
		} else {
			$post_author_label = __( '-- Unassigned --', 'supportflow' );
		}
		$args                  = array(
			'show_option_none' => __( '-- Unassigned --', 'supportflow' ),
			'selected'         => $post_author_id,
			'id'               => '',
			'name'             => '',
			'who'              => 'author',
			'class'            => 'meta-item-dropdown',
			'echo'             => false
		);
		$post_authors_dropdown = wp_dropdown_users( $args );


		// Get post E-Mail account
		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );

		$user_permissions = SupportFlow()->extend->permissions->get_user_permissions_data( get_current_user_id() );
		$user_permissions = $user_permissions['email_accounts'];

		$email_account_dropdown = '<select class="meta-item-dropdown">';
		foreach ( $email_accounts as $id => $email_accoun_id ) {
			if ( empty( $email_accoun_id ) || ( ! current_user_can( 'manage_options' ) && ! in_array( $id, $user_permissions ) ) ) {
				continue;
			}
			$email_account_dropdown .= '<option value="' . esc_attr( $id ) . '" ' . '>' . esc_html( $email_accoun_id['username'] ) . '</option>';
		}
		$email_account_dropdown .= '</select>';

		$email_account_keys  = array_keys( $email_accounts );
		$email_account_id    = $email_account_keys[0];
		$email_account_label = $email_accounts[$email_account_id]['username'];

		// Get E-Mail notification settings
		$notification_id          = 0;
		$notification_label       = 'Default';
		$notification_label_title = 'Choose default if you want to receive E-Mail notifications based on what you set in `E-Mail notification` page. Choose Enable/Disable if you want to override those settings';
		$notification_dropdown    = '';
		$notification_dropdown   .= '<select class="meta-item-dropdown">';

		if ( 'post-new.php' == $pagenow ) {
			$notification_dropdown .= '<option value="default">' . __( 'Default', 'supportflow' ) . '</option>';
			$notification_dropdown .= '<option value="enable">' . __( 'Subscribed', 'supportflow' ) . '</option>';
			$notification_dropdown .= '<option value="disable">' . __( 'Unsubscribed', 'supportflow' ) . '</option>';
		} elseif ( 'post.php' == $pagenow ) {
			$email_notifications_override = get_post_meta( get_the_ID(), 'email_notifications_override', true );
			$current_user_id              = get_current_user_id();

			if ( isset( $email_notifications_override[$current_user_id] ) ) {
				$override_status = $email_notifications_override[$current_user_id];
				if ( 'enable' == $override_status ) {
					$notification_label = 'Subscribed';
					$notification_id    = 1;
				} elseif ( 'disable' == $override_status ) {
					$notification_label = 'Unsubscribed';
					$notification_id    = 2;
				}
			}

			$notification_dropdown .= '<option value="default"' . selected( $notification_id, 0, false ) . '>' . __( 'Default', 'supportflow' ) . '</option>';
			$notification_dropdown .= '<option value="enable"' . selected( $notification_id, 1, false ) . '>' . __( 'Subscribed', 'supportflow' ) . '</option>';
			$notification_dropdown .= '<option value="disable"' . selected( $notification_id, 2, false ) . '>' . __( 'Unsubscribed', 'supportflow' ) . '</option>';
		}

		$notification_dropdown .= '</select>';


		// Get submit button label
		if ( 'post-new.php' == $pagenow ) {
			$submit_text = __( 'Start Thread', 'supportflow' );
		} else {
			$submit_text = __( 'Update Thread', 'supportflow' );
		}
		?>

		<div id="minor-publishing">
			<div id="misc-publishing-actions">

				<?php if ( 'post-new.php' == $pagenow ) : ?>
					<div class="misc-pub-section meta-item">
						<label class="meta-item-toggle-button"><?php _e( 'Account', 'supportflow' ) ?>:</label>
						<span class="meta-item-label"><?php _e( $email_account_label, 'supportflow' ) ?></span>
						<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
							<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
						</a>
						<input name="post_email_account" class="meta-item-name" value="<?php echo $email_account_id ?>" type="hidden" />

						<div class="meta-item-toggle-content hide-if-js">
							<?php echo $email_account_dropdown ?>
							<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php _e( 'OK' ) ?></a>
							<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php _e( 'Cancel' ) ?></a>
						</div>
					</div>
				<?php endif; ?>

				<!--Thread opening date/time-->
				<?php if ( 'post.php' == $pagenow ) : ?>
					<div class="misc-pub-section meta-item">
						<label><?php _e( 'Opened', 'supportflow' ) ?>:</label>
						<span class="meta-item-label"><?php esc_html_e( $opened ) ?></span>
					</div>

					<!--Last thread update time-->
					<div class="misc-pub-section meta-item">
						<label><?php _e( 'Last Activity', 'supportflow' ) ?>:</label>
						<span class="meta-item-label"><?php esc_html_e( $last_activity ) ?></span>
					</div>
				<?php endif; ?>

				<!--Thread status box-->
				<div class="misc-pub-section meta-item">
					<label class="meta-item-toggle-button"><?php _e( 'Status', 'supportflow' ) ?>:</label>
					<span class="meta-item-label"><?php esc_html_e( $current_status_label, 'supportflow' ) ?></span>
					<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
						<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
					</a>
					<input name="post_status" class="meta-item-name" value="<?php esc_attr_e( $current_status_id ) ?>" type="hidden" />

					<div class="meta-item-toggle-content hide-if-js">
						<select class="meta-item-dropdown">
							<?php foreach ( $post_statuses as $slug => $post_status ) : ?>
								<option value="<?php esc_attr_e( $slug ) ?>"<?php selected( $current_status_id, $slug ) ?>><?php esc_html_e( $post_status['label'] ) ?></option>;
							<?php endforeach; ?>
						</select>
						<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php _e( 'OK' ) ?></a>
						<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php _e( 'Cancel' ) ?></a>
					</div>
				</div>

				<div class="misc-pub-section meta-item">
					<label class="meta-item-toggle-button"><?php _e( 'Owner', 'supportflow' ) ?>:</label>
					<span class="meta-item-label"><?php _e( $post_author_label, 'supportflow' ) ?></span>
					<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
						<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
					</a>
					<input name="post_author" class="meta-item-name" value="<?php esc_attr_e( $post_author_id ) ?>" type="hidden" />

					<div class="meta-item-toggle-content hide-if-js">
						<?php echo $post_authors_dropdown ?>
						<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php _e( 'OK' ) ?></a>
						<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php _e( 'Cancel' ) ?></a>
					</div>
				</div>

				<div class="misc-pub-section meta-item">
					<label class="meta-item-toggle-button" title="<?php _e( $notification_label_title, 'supportflow' ) ?>"><?php _e( 'E-Mail Notifications', 'supportflow' ) ?>:</label>
					<span class="meta-item-label"><?php esc_html_e( $notification_label, 'supportflow' ) ?></span>
					<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
						<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
					</a>
					<input name="post_email_notifications_override" class="meta-item-name" value="<?php $notification_id ?>" type="hidden" />

					<div class="meta-item-toggle-content hide-if-js">
						<?php echo $notification_dropdown ?>
						<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php _e( 'OK' ) ?></a>
						<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php _e( 'Cancel' ) ?></a>
					</div>
				</div>

			</div>
			<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">
			<div id="publishing-action">
				<?php submit_button( $submit_text, 'primary', 'save', false ); ?>
			</div>
			<div class="clear"></div>
		</div>
	<?php
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

		$respondents        = SupportFlow()->get_thread_respondents( get_the_ID(), array( 'fields' => 'emails' ) );
		$respondents_string = implode( ', ', $respondents );
		$respondents_string .= empty( $respondents_string ) ? '' : ', ';
		$placeholder = __( 'Who are you starting a conversation with?', 'supportflow' );
		echo '<h4>' . __( 'Respondent(s)', 'supportflow' ) . '</h4>';
		echo '<input type="text" id="respondents" name="respondents" placeholder="' . $placeholder . '" value="' . esc_attr( $respondents_string ) . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Enter each respondent email address, separated with a comma', 'supportflow' ) . '</p>';
	}

	/**
	 * Add a form element where you can choose cc and bcc receiver of reply
	 */
	public function meta_box_cc_bcc() {
		?>
		<p class="description"> <?php _e( "Please add all the E-Mail ID's seperated by comma.", 'supportflow' ) ?></p>
		<h4 class="inline"><?php _e( "CC: ", 'supportflow' ) ?></h4>
		<input type="text" id="cc" name="cc" />
		<h4 class="inline"> <?php _e( "BCC: ", 'supportflow' ) ?></h4>
		<input type="text" id="bcc" name="bcc" />
	<?php
	}

	/**
	 * Standard listing of replies includes a form at the top
	 * and any existing replies listed in reverse chronological order
	 */
	public function meta_box_replies() {
		global $pagenow;

		$predefined_replies = get_posts( array( 'post_type' => 'sf_predefs' ) );
		$pre_defs           = array( array( 'title' => __( 'Pre-defined Replies', 'supportflow' ), 'content' => '' ) );

		foreach ( $predefined_replies as $predefined_reply ) {
			$content = $predefined_reply->post_content;

			if ( ! empty( $predefined_reply->post_title ) ) {
				$title = $predefined_reply->post_title;
			} else {
				$title = $predefined_reply->post_content;
			}

			// Limit size to 75 characters
			if ( strlen( $title ) > 75 ) {
				$title = substr( $title, 0, 75 - 3 ) . '...';
			}

			if ( 0 != strlen( $content ) ) {
				$pre_defs[] = array( 'title' => $title, 'content' => $content );
			}
		}

		$placeholders = array(
			__( "What's burning?", 'supportflow' ),
			__( 'What do you need to get off your chest?', 'supportflow' ),
		);

		$rand = array_rand( $placeholders );
		echo '<div class="alignleft"><h4>' . __( 'Conversation', 'supportflow' ) . '</h4></div>';
		echo '<div class="alignright">';
		echo '<select id="predefs"  class="predefined_replies_dropdown">';
		foreach ( $pre_defs as $pre_def ) {
			echo '<option class="predef" data-content="' . esc_attr( $pre_def['content'] ) . '">' . esc_html( $pre_def['title'] ) . "</option>\n";
		}
		echo '</select></div>';

		echo '<div id="thread-reply-box">';
		echo "<textarea id='reply' name='reply' class='thread-reply' rows='4' placeholder='" . esc_attr( $placeholders[$rand] ) . "'>";
		echo "</textarea>";

		echo '<div id="message-tools">';
		echo '<div id="replies-attachments-wrap">';
		echo '<div class="drag-drop-buttons">';
		echo '<input id="reply-attachment-browse-button" type="button" value="' . esc_attr( __( 'Attach files', 'supportflow' ) ) . '" class="button" />';
		echo '</div>';
		echo '<ul id="replies-attachments-list">';
		echo '</ul>';
		echo '<input type="hidden" id="reply-attachments" name="reply-attachments" />';
		echo '</div>';
		echo '<div id="submit-action">';
		echo '<input type="checkbox" id="mark-private" name="mark-private" />';
		echo '<label for="mark-private">' . __( 'Mark private', 'supportflow' ) . '</label>';
		if ( 'post-new.php' == $pagenow ) {
			$submit_text = __( 'Start Thread', 'supportflow' );
		} else {
			$submit_text = __( 'Send Message', 'supportflow' );
		}
		submit_button( $submit_text, 'primary save-button', 'save', false );
		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="clear"></div>';

		$this->display_thread_replies();
	}

	public function display_thread_replies() {

		$private_replies = SupportFlow()->get_thread_replies( get_the_ID(), array( 'status' => 'private' ) );

		if ( ! empty( $private_replies ) ) {
			echo '<ul class="private-replies">';
			foreach ( $private_replies as $reply ) {
				echo '<li>';
				echo '<div class="thread-reply">';
				$post_content = wpautop( stripslashes( $reply->post_content ) );
				// Make link clickable
				$post_content = make_clickable( $post_content );
				echo $post_content;
				$attachment_args = array(
					'post_parent' => $reply->ID,
					'post_type'   => 'attachment'
				);
				if ( $attachments = get_posts( $attachment_args ) ) {
					echo '<ul class="thread-reply-attachments">';
					foreach ( $attachments as $attachment ) {
						$attachment_link = wp_get_attachment_url( $attachment->ID );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( $attachment->post_title ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$reply_author    = get_post_meta( $reply->ID, 'reply_author', true );
				$reply_timestamp = sprintf( __( 'Noted by %1$s on %2$s at %3$s', 'supportflow' ), $reply_author, get_the_date(), get_the_time() );
				$modified_gmt    = get_post_modified_time( 'U', true, get_the_ID() );
				$last_activity   = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				echo '<div class="thread-meta"><span class="reply-timestamp">' . esc_html( $reply_timestamp ) . ' (' . $last_activity . ')' . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		$replies = SupportFlow()->get_thread_replies( get_the_ID(), array( 'status' => 'public' ) );
		if ( ! empty( $replies ) ) {
			echo '<ul class="thread-replies">';
			foreach ( $replies as $reply ) {
				$reply_author       = get_post_meta( $reply->ID, 'reply_author', true );
				$reply_author_email = get_post_meta( $reply->ID, 'reply_author_email', true );
				echo '<li>';
				echo '<div class="reply-avatar">' . get_avatar( $reply_author_email, 72 );
				echo '<p class="reply-author">' . esc_html( $reply_author ) . '</p>';
				echo '</div>';
				echo '<div class="thread-reply">';
				$post_content = wpautop( stripslashes( $reply->post_content ) );
				// Make link clickable
				$post_content = make_clickable( $post_content );
				echo $post_content;
				$attachment_args = array(
					'post_parent' => $reply->ID,
					'post_type'   => 'attachment'
				);
				if ( $attachments = get_posts( $attachment_args ) ) {
					echo '<ul class="thread-reply-attachments">';
					foreach ( $attachments as $attachment ) {
						$attachment_link = wp_get_attachment_url( $attachment->ID );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( $attachment->post_title ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$reply_timestamp = sprintf( __( '%s at %s', 'supportflow' ), get_the_date(), get_the_time() );
				$modified_gmt    = get_post_modified_time( 'U', true, get_the_ID() );
				$last_activity   = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				echo '<div class="thread-meta"><span class="reply-timestamp">' . esc_html( $reply_timestamp ) . ' (' . $last_activity . ')' . '</span></div>';
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
			'cb'          => $columns['cb'],
			'updated'     => __( 'Updated', 'supportflow' ),
			'title'       => __( 'Subject', 'supportflow' ),
			'sf_excerpt'  => __( 'Excerpt', 'supportflow' ),
			'respondents' => __( 'Respondents', 'supportflow' ),
			'status'      => __( 'Status', 'supportflow' ),
			'author'      => __( 'Agent', 'supportflow' ),
			'sf_replies'  => '<span title="' . __( 'Reply count', 'supportflow' ) . '" class="comment-grey-bubble"></span>',
			'email'       => __( 'E-Mail account', 'supportflow' ),
			'created'     => __( 'Created', 'support' ),
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
	 * Use the most recent public reply as the post excerpt
	 * on the Manage Threads view so mode=excerpt works well
	 */
	public function filter_get_the_excerpt( $orig ) {
		if ( $reply = array_pop( SupportFlow()->get_thread_replies( get_the_ID() ) ) ) {
			$reply_author = get_post_meta( $reply->ID, 'reply_author' );

			return $reply_author . ': "' . wp_trim_excerpt( $reply->post_content ) . '"';
		} else {
			return $orig;
		}
	}

	/**
	 * Produce the column values for the custom columns we created
	 */
	function action_manage_posts_custom_column( $column_name, $thread_id ) {

		switch ( $column_name ) {
			case 'updated':
				$modified_gmt = get_post_modified_time( 'U', true, $thread_id );
				echo sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				break;
			case 'sf_excerpt':
				$replies = SupportFlow()->get_thread_replies( $thread_id, array( 'numberposts' => 1, 'order' => 'ASC' ) );
				if ( ! isset( $replies[0] ) ) {
					break;
				}
				$first_reply = $replies[0]->post_content;
				if ( strlen( $first_reply ) > 50 ) {
					$first_reply = substr( $first_reply, 0, 50 );
				}
				echo $first_reply;
				break;
			case 'respondents':
				$respondents = SupportFlow()->get_thread_respondents( $thread_id, array( 'fields' => 'emails' ) );
				if ( empty( $respondents ) ) {
					echo '—';
					break;
				}
				foreach ( $respondents as $key => $respondent_email ) {
					$args              = array(
						SupportFlow()->respondents_tax => SupportFlow()->get_email_hash( $respondent_email ),
						'post_type'                    => SupportFlow()->post_type,
					);
					$respondent_link   = '<a href="' . esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ) . '">' . $respondent_email . '</a>';
					$respondents[$key] = get_avatar( $respondent_email, 16 ) . '&nbsp;&nbsp;' . $respondent_link;
				}
				echo implode( '<br />', $respondents );
				break;
			case 'status':
				$post_status = get_post_status( $thread_id );
				$args        = array(
					'post_type'   => SupportFlow()->post_type,
					'post_status' => $post_status,
				);
				$status_name = get_post_status_object( $post_status )->label;
				$filter_link = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $status_name ) . '</a>';
				break;
			case 'email':
				$email_account_id       = get_post_meta( $thread_id, 'email_account', true );
				$email_accounts         = SupportFlow()->extend->email_accounts->get_email_accounts();
				$args                   = array(
					'post_type'     => SupportFlow()->post_type,
					'email_account' => $email_account_id,
				);
				$email_account_username = $email_accounts[$email_account_id]['username'];
				$filter_link            = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $email_account_username ) . '</a>';
				break;
			case 'sf_replies':
				$replies = SupportFlow()->get_thread_replies_count( $thread_id );
				echo '<div class="post-com-count-wrapper">';
				echo "<span class='replies-count'>{$replies}</span>";
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
	 * and new reply data
	 */
	public function action_save_post( $thread_id ) {

		if ( SupportFlow()->post_type != get_post_type( $thread_id ) ) {
			return;
		}

		if ( isset( $_POST['respondents'] ) ) {
			$respondents = array_map( 'sanitize_email', explode( ',', $_POST['respondents'] ) );
			SupportFlow()->update_thread_respondents( $thread_id, $respondents );
		}

		if ( isset( $_POST['post_email_account'] ) && is_numeric( $_POST['post_email_account'] ) ) {
			$email_account = (int) $_POST['post_email_account'];
			update_post_meta( $thread_id, 'email_account', $email_account );
		}

		if ( isset( $_POST['post_email_notifications_override'] ) && in_array( $_POST['post_email_notifications_override'], array( 'default', 'enable', 'disable' ) ) ) {
			$email_notifications_override                        = get_post_meta( $thread_id, 'email_notifications_override', true );
			$email_notifications_override[get_current_user_id()] = $_POST['post_email_notifications_override'];
			update_post_meta( $thread_id, 'email_notifications_override', $email_notifications_override );
		}

		if ( isset( $_POST['reply'] ) && ! empty( $_POST['reply'] ) ) {
			$reply      = wp_filter_nohtml_kses( $_POST['reply'] );
			$visibility = ( ! empty( $_POST['mark-private'] ) ) ? 'private' : 'public';
			if ( ! empty( $_POST['reply-attachments'] ) ) {
				$attachment_ids = array_map( 'intval', explode( ',', trim( $_POST['reply-attachments'], ',' ) ) );
			} else {
				$attachment_ids = '';
			}
			$cc  = ( ! empty( $_POST['cc'] ) ) ? SupportFlow()->extract_email_ids( $_POST['cc'] ) : '';
			$bcc = ( ! empty( $_POST['bcc'] ) ) ? SupportFlow()->extract_email_ids( $_POST['bcc'] ) : '';

			$reply_args = array(
				'post_status'    => $visibility,
				'attachment_ids' => $attachment_ids,
				'cc'             => $cc,
				'bcc'            => $bcc,
			);
			SupportFlow()->add_thread_reply( $thread_id, $reply, $reply_args );
		}

	}
}

SupportFlow()->extend->admin = new SupportFlow_Admin();
