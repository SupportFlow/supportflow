<?php
/**
 *
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Admin {

	function __construct() {
		add_action( 'wp_ajax_sf_forward_conversation', array( $this, 'action_wp_ajax_sf_email_conversation' ) );
		add_filter( 'heartbeat_received', array( $this, 'filter_heartbeat_received' ), 10, 2 );
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
		add_action( 'add_attachment', array( $this, 'action_add_attachment' ) );
	}

	public function setup_actions() {

		// Creating or updating a ticket
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
		add_action( 'map_meta_cap', array( $this, 'filter_map_meta_cap' ), 10, 4 );

		if ( ! $this->is_edit_screen() ) {
			return;
		}

		// Everything
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_filter( 'post_updated_messages', array( $this, 'filter_post_updated_messages' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		// Manage tickets view
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
	 * Do not allow users to view/edit replies outside of a ticket context.
	 */
	public function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( $cap == 'edit_post' && ! empty( $args[0] ) ) {
			$post = get_post( absint( $args[0] ) );
			if ( $post->post_type == SupportFlow()->reply_type && $post->post_parent > 0 ) {
				$caps[] = 'do_not_allow';
			}
		}

		return $caps;
	}

	/**
	 * Add any CSS or JS we need for the admin
	 */
	public function action_admin_enqueue_scripts() {
		global $pagenow;

		$handle = SupportFlow()->enqueue_style( 'supportflow-admin', 'admin.css' );

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
			wp_enqueue_media();

			$customers_autocomplete_handle   = SupportFlow()->enqueue_script( 'supportflow-customers-autocomplete', 'customers-autocomplete.js', array( 'jquery', 'jquery-ui-autocomplete' ) );
			$ticket_attachment_handle        = SupportFlow()->enqueue_script( 'supportflow-ticket-attachments', 'ticket_attachments.js' );
			$supportflow_tickets_handle      = SupportFlow()->enqueue_script( 'supportflow-tickets', 'tickets.js' );
			$auto_save_handle                = SupportFlow()->enqueue_script( 'supportflow-auto-save', 'auto_save.js', array( 'jquery', 'heartbeat' ) );

			wp_localize_script( $customers_autocomplete_handle, 'SFCustomersAc', array(
				'ajax_url'            => add_query_arg( 'action', SupportFlow()->extend->jsonapi->action, admin_url( 'admin-ajax.php' ) ),
				'get_customers_nonce' => wp_create_nonce( 'get_customers' ),
			) );

			wp_localize_script( $ticket_attachment_handle, 'SFTicketAttachments', array(
				'frame_title'       => __( 'Attach files', 'supportflow' ),
				'button_title'      => __( 'Insert as attachment', 'supportflow' ),
				'remove_attachment' => __( 'Remove', 'supportflow' ),
				'sure_remove'       => __( 'Are you sure want to remove this attachment?', 'supportflow' ),
			) );

			wp_localize_script( $supportflow_tickets_handle, 'SFTickets', array(
				'no_title_msg'      => __( 'You must need to specify the subject of the ticket', 'supportpress' ),
				'no_customer_msg'   => __( 'You must need to add atleast one customer', 'supportpress' ),
				'pagenow'           => $pagenow,
				'send_msg'          => __( 'Send Message', 'supportflow' ),
				'add_private_note'  => __( 'Add Private Note', 'supportflow' ),
			) );

			wp_localize_script( $auto_save_handle, 'SFAutoSave', array(
				'ticket_id' => get_the_ID(),
			) );

		}

		if ( 'post.php' == $pagenow ) {
			$email_conversation_handle = SupportFlow()->enqueue_script( 'supportflow-email-conversation', 'email_conversation.js' );

			wp_localize_script( $email_conversation_handle, 'SFEmailConversation', array(
				'post_id'                   => get_the_ID(),
				'sending_emails'            => __( 'Please wait while sending E-Mail(s)', 'supportflow' ),
				'failed_sending'            => __( 'Failed sending E-Mails', 'supportflow' ),
				'_email_conversation_nonce' => wp_create_nonce( 'sf_email_conversation' ),
			) );
		}
	}

	/**
	 *
	 */
	public function action_wp_ajax_sf_email_conversation() {
		if ( false === check_ajax_referer( 'sf_email_conversation', '_email_conversation_nonce', false ) ) {
			_e( 'Invalid request. Please try refreshing the page.', 'supportflow' );
			die;
		}

		if ( ! isset( $_REQUEST['email_ids'] ) || ! isset( $_REQUEST['post_id'] ) ) {
			_e( 'Invalid request. Please try refreshing the page.', 'supportflow' );
			die;
		}

		$email_ids = SupportFlow()->extract_email_ids( $_REQUEST['email_ids'] );
		$ticket_id = (int) $_REQUEST['post_id'];

		if ( ! current_user_can( 'edit_post', $ticket_id ) ) {
			_e( 'You are not allowed to edit this item.' );
			die;
		}

		if ( empty( $email_ids ) ) {
			_e( 'No valid E-Mail ID found', 'supportflow' );
			die;
		}

		SupportFlow()->extend->emails->email_conversation( $ticket_id, $email_ids );

		_e( 'Successfully sented E-Mails', 'supportflow' );
		exit;

	}

	/**
	 * Add random characters to attachment uploaded through SupportFlow web UI
	 *
	 * @todo Conversion to a better way to determine if attachment if uploaded through SF web UI rather than HTTP referer
	 */
	function action_add_attachment( $attachment_id ) {
		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			return;
		}

		$post_type = SupportFlow()->post_type;
		$referer   = $_SERVER['HTTP_REFERER'];

		$url  = parse_url( $referer );
		$path = $url['scheme'] . '://' . $url['host'] . $url['path'];

		if ( isset( $url['query'] ) ) {
			parse_str( $url['query'], $query );
		}

		// Check if referred by SupportFlow ticket page
		if ( admin_url( 'post-new.php' ) == $path ) {
			if ( empty( $query['post_type'] ) || $query['post_type'] != $post_type ) {
				return;
			}
		} elseif ( admin_url( 'post.php' ) == $path ) {
			if ( empty( $query['post'] ) || get_post_type( (int) $query['post'] ) != $post_type ) {
				return;
			}
		} else {
			return;
		}

		SupportFlow()->extend->attachments->secure_attachment_file( $attachment_id );
	}

	/**
	 * Filter the messages that appear to the user after they perform an action on a ticket
	 */
	public function filter_post_updated_messages( $messages ) {
		global $post;

		$messages[SupportFlow()->post_type] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Ticket updated.', 'supportflow' ),
			2  => __( 'Custom field updated.', 'supportflow' ),
			3  => __( 'Custom field deleted.', 'supportflow' ),
			4  => __( 'Ticket updated.', 'supportflow' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Ticket restored to revision from %s', 'supportflow' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Ticket updated.', 'supportflow' ),
			7  => __( 'Ticket updated.', 'supportflow' ),
			8  => __( 'Ticket updated.', 'supportflow' ),
			9  => __( 'Ticket updated.', 'supportflow' ),
			10 => __( 'Ticket updated.', 'supportflow' ),
		);

		return $messages;
	}

	public function filter_heartbeat_received( $response, $data ) {
		if (
			isset( $data['supportflow-autosave'] ) &&
			is_array( $data['supportflow-autosave'] ) &&
			isset( $data['supportflow-autosave']['ticket_id'] ) &&
			current_user_can( 'edit_post', (int) $data['supportflow-autosave']['ticket_id'] )
		) {
			// Save data received from client to the database as post meta

			$ticket_id = (int) $data['supportflow-autosave']['ticket_id'];
			unset( $data['supportflow-autosave']['ticket_id'] );

			if ( 'auto-draft' == get_post_status( $ticket_id ) ) {
				wp_update_post( array( 'ID' => $ticket_id, 'post_status' => 'draft' ) );
			}

			foreach ( $data['supportflow-autosave'] as $element_id => $element_value ) {
				update_post_meta( $ticket_id, "_sf_autosave_$element_id", $element_value );
			}

			echo esc_html( $data['supportflow-autosave']['post_title'] );
			if ( ! empty( $data['supportflow-autosave']['post_title'] ) ) {
				wp_update_post( array( 'ID' => $ticket_id, 'post_title' => $data['supportflow-autosave']['post_title'] ) );
			}
		}

		return $response;
	}


	/**
	 *
	 */
	public function filter_views( $views ) {
		$post_type    = SupportFlow()->post_type;
		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_tickets'] ) {
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

		$post_statuses = SupportFlow()->post_statuses;
		array_pop( $post_statuses );
		$post_statuses = "'" . implode( "','", array_map( 'sanitize_key', array_keys( $post_statuses ) ) ) . "'";

		// @todo Only show "Mine" if the user is an agent
		$mine_args = array(
			'post_type' => SupportFlow()->post_type,
			'author'    => get_current_user_id(),
		);
		$wp_query  = new WP_Query( array(
			'post_type'      => SupportFlow()->post_type,
			'author'         => get_current_user_id(),
			'post_status'    => $post_statuses,
			'posts_per_page' => 1,
		) );

		$my_posts  = $wp_query->found_posts;
		$view_mine = '<a href="' . add_query_arg( $mine_args, admin_url( 'edit.php' ) ) . '">' . sprintf( _nx( 'Mine <span class="count">(%s)</span>', 'Mine <span class="count">(%s)</span>', $my_posts, 'posts' ), number_format_i18n( $my_posts ) ) . '</a>';

		$unassigned_args = array(
			'post_type' => SupportFlow()->post_type,
			'author'    => 0,
		);
		$wp_query        = new WP_Query( array(
			'post_type'      => SupportFlow()->post_type,
			'author'         => 0,
			'post_status'    => $post_statuses,
			'posts_per_page' => 1,
		) );

		$unassigned_posts = $wp_query->found_posts;
		$view_unassigned  = '<a href="' . add_query_arg( $unassigned_args, admin_url( 'edit.php' ) ) . '">' . sprintf( _nx( 'Unassigned <span class="count">(%s)</span>', 'Unassigned <span class="count">(%s)</span>', $unassigned_posts, 'posts' ), number_format_i18n( $unassigned_posts ) ) . '</a>';

		// Put 'All' and 'Mine' at the beginning of the array
		array_shift( $views );
		$views               = array_reverse( $views );
		$views['unassigned'] = $view_unassigned;
		$views['mine']       = $view_mine;
		$views['all']        = $view_all;
		$views               = array_reverse( $views );

		// Remove private option from filter links as they are just private replies to ticket
		unset( $views['private'] );

		return $views;
	}

	/**
	 * Add custom filters for the Manage Tickets view
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
		$terms    = get_terms( SupportFlow()->tags_tax, array( 'hide_empty' => false ) );

		echo "<select name='" . esc_attr( $tax_slug ) . "' id='" . esc_attr( $tax_slug ) . "' class='postform'>";
		echo "<option value=''>" . __( 'Show All tags', 'supportflow' ) . "</option>";
		foreach ( $terms as $term ) {
			$selected = selected( isset( $_REQUEST[$tax_slug] ) && ( $_REQUEST[$tax_slug] == $term->slug ), true, false );
			echo "<option value='" . esc_attr( $term->slug ) . "' ". esc_attr( $selected ) .">" . esc_html( $term->name ) . '</option>';
		}
		echo "</select>";


		// Filter to specify E-Mail account
		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		echo "<select name='email_account' id='email_account' class='postform'>";
		echo "<option value=''>" . __( 'Show All Accounts', 'supportflow' ) . "</option>";
		foreach ( $email_accounts as $id => $email_account ) {
			$selected = selected( isset( $_REQUEST['email_account'] ) && ( $_REQUEST['email_account'] == $id ), true, false );
			echo "<option value='" . esc_attr( $id ) . "'". esc_attr( $selected ) .">" . esc_html( $email_account['username'] ) . '</option>';
		}
		echo "</select>";

	}

	/**
	 * Filter the actions available to the agent on the post type
	 */
	function filter_post_row_actions( $row_actions, $post ) {

		// Rename these actions
		if ( isset( $row_actions['edit'] ) ) {
			$row_actions['edit'] = str_replace( __( 'Edit' ), __( 'View', 'supportflow' ), str_replace( __( 'Edit this item' ), __( 'View Ticket', 'supportflow' ), $row_actions['edit'] ) );
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
				'ticket_id'   => $post->ID,
				'post_type'   => SupportFlow()->post_type,
			);
			$action_link = add_query_arg( $args, admin_url( 'edit.php' ) );
			if ( $last_status == $change_to ) {
				$title_attr  = esc_attr__( 'Close Ticket', 'supportflow' );
				$action_text = esc_html__( 'Close', 'supportflow' );
			} else {
				$title_attr  = esc_attr__( 'Reopen Ticket', 'supportflow' );
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
	 * Handle which tickets are show on the Manage Tickets view when
	 */
	function action_pre_get_posts( $query ) {
		global $pagenow;

		if ( 'edit.php' != $pagenow || ! $query->is_main_query() ) {
			return;
		}

		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_tickets'] ) {
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

		// Only show tickets with the last status if the last status is set
		$post_status = $query->get( 'post_status' );
		if ( ! $query->get( 's' ) && empty( $post_status ) ) {
			$query->set( 'post_status', $status_slugs );
		}

		add_action( 'posts_clauses', array( $this, 'filter_author_clause' ), 10, 2 );

		if ( isset( $_GET['email_account'] ) && ! empty( $_GET['email_account'] ) ) {
			$query->set( 'meta_key', 'email_account' );
			$query->set( 'meta_value', (int) $_GET['email_account'] );
		}
	}

	/*
	 * Show unassigned tickets when query author is 0
	 */
	public function filter_author_clause( $clauses, $query ) {

		if ( isset( $query->query['author'] ) && 0 == $query->query['author'] ) {
			$clauses['where'] .= ' AND post_author = 0 ';
		}

		return $clauses;
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

		if ( ! isset( $_GET['action'], $_GET['sf_nonce'], $_GET['post_status'], $_GET['ticket_id'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['sf_nonce'], 'sf-change-status' ) ) {
			wp_die( __( "Doin' something phishy, huh?", 'supportflow' ) );
		}

		$ticket_id = (int) $_GET['ticket_id'];

		if ( ! current_user_can( 'edit_post', $ticket_id ) ) {
			wp_die( __( 'You are not allowed to edit this item.' ) );
		}

		$post_status = sanitize_key( $_GET['post_status'] );
		$new_ticket  = array(
			'ID'          => $ticket_id,
			'post_status' => $post_status,
		);
		wp_update_post( $new_ticket );
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Manipulate the meta boxes appearing on the edit post view
	 *
	 * When creating a new ticket, you should be able to:
	 *
	 * When updating an existing ticket, you should be able to:
	 *
	 */
	public function action_add_meta_boxes() {
		global $pagenow;

		if ( ! $this->is_edit_screen() ) {
			return;
		}

		$customers_box = 'tagsdiv-' . SupportFlow()->customers_tax;
		remove_meta_box( 'submitdiv', SupportFlow()->post_type, 'side' );
		remove_meta_box( $customers_box, SupportFlow()->post_type, 'side' );
		remove_meta_box( 'slugdiv', SupportFlow()->post_type, 'normal' );

		add_meta_box( 'supportflow-details', __( 'Details', 'supportflow' ), array( $this, 'meta_box_details' ), SupportFlow()->post_type, 'side', 'high' );
		add_meta_box( 'supportflow-subject', __( 'Subject', 'supportflow' ), array( $this, 'meta_box_subject' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-customers', __( 'Customers', 'supportflow' ), array( $this, 'meta_box_customers' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-cc-bcc', __( 'CC and BCC', 'supportflow' ), array( $this, 'meta_box_cc_bcc' ), SupportFlow()->post_type, 'normal' );
		add_meta_box( 'supportflow-replies', __( 'Replies', 'supportflow' ), array( $this, 'meta_box_replies' ), SupportFlow()->post_type, 'normal' );

		if ( 'post.php' == $pagenow ) {
			add_meta_box( 'supportflow-other-customers-tickets', __( 'Customer(s) recent Tickets', 'supportflow' ), array( $this, 'meta_box_other_customers_tickets' ), SupportFlow()->post_type, 'side' );
			add_meta_box( 'supportflow-forward_conversation', __( 'Forward this conversation', 'supportflow' ), array( $this, 'meta_box_email_conversation' ), SupportFlow()->post_type, 'side' );
		}
	}

	public function meta_box_other_customers_tickets() {
		$ticket_customers = SupportFlow()->get_ticket_customers( get_the_ID(), array( 'fields' => 'slugs' ) );
		$statuses         = SupportFlow()->post_statuses;
		$status_slugs     = array_keys($statuses);

		$table = new SupportFlow_Table( '', false, false );

		if ( empty( $ticket_customers ) ) {
			$tickets = array();

		} else {
			$args = array(
				'post_type'    => SupportFlow()->post_type,
				'post_parent'  => 0,
				'post_status'  => $status_slugs,
				'posts_per_page'  => 10,
				'post__not_in' => array( get_the_id() ),
				'tax_query'    => array(
					array(
						'taxonomy' => SupportFlow()->customers_tax,
						'field'    => 'slug',
						'terms'    => $ticket_customers,
					),
				),
			);

			$wp_query = new WP_Query( $args );
			$tickets  = $wp_query->posts;
		}

		$no_items = __( 'No recent tickets found.', 'supportflow' );
		$table->set_no_items( $no_items );

		$table->set_columns( array(
			'title'  => __( 'Subject', 'supportflow' ),
			'status' => __( 'Status', 'supportflow' ),
		) );

		$data = array();
		foreach ( $tickets as $ticket ) {
			$post_date     = strtotime( $ticket->post_date );
			$post_modified = strtotime( $ticket->post_modified );
			$title         = '<b>' . esc_html( $ticket->post_title ) . '</b>';
			$title         = "<a href='post.php?post=" . $ticket->ID . "&action=edit'>" . $title . "</a>";
			$data[]        = array(
				'title'  => $title,
				'status' => $statuses[$ticket->post_status]['label'],
			);
		}
		$table->set_data( $data );
		$table->display();
	}

	public function meta_box_email_conversation() {
		?>
		<p class="description"><?php _e( "Please enter E-Mail address separated by comma to whom you want to send this conversation.", 'supportflow' ) ?></p>
		<br />
		<input type="text" id="email_conversation_to" />
		<?php submit_button( __( 'Send', 'supportflow' ), '', 'email_conversation_submit', false ); ?>
		<p id="email_conversation_status"></p>
	<?php
	}

	/**
	 * Show details about the ticket, and allow the post status and agent to be changed
	 */
	public function meta_box_details() {
		echo '<div id="minor-publishing">
				<div id="misc-publishing-actions">';

		$this->render_meta_box_details_email_account();
		$this->render_meta_box_details_opened();
		$this->render_meta_box_details_status();
		$this->render_meta_box_details_author();
		$this->render_meta_box_details_notifications();
		$this->render_meta_box_details_actions();

		echo '</div>
				</div>';

	}

	public function render_meta_box_details_email_account() {
		// Get post E-Mail account
		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );

		$user_permissions = SupportFlow()->extend->permissions->get_user_permissions_data( get_current_user_id() );
		$user_permissions = $user_permissions['email_accounts'];

		$email_account_id = get_post_meta( get_the_id(), 'email_account', true );

		if ( '' == $email_account_id ) {
			$email_account_dropdown = '<select class="meta-item-dropdown">';
			foreach ( $email_accounts as $id => $email_account ) {
				if ( empty( $email_account ) || ( ! current_user_can( 'manage_options' ) && ! in_array( $id, $user_permissions ) ) ) {
					continue;
				}
				$email_account_dropdown .= '<option value="' . esc_attr( $id ) . '" ' . '>' . esc_html( $email_account['username'] ) . '</option>';
			}
			$email_account_dropdown .= '</select>';

			$email_account_keys  = array_keys( $email_accounts );
			$email_account_first = $email_account_keys[0];
			$email_account_label = $email_accounts[$email_account_first]['username'];
		}

		if ( '' == $email_account_id ) {
			?>
			<div class="misc-pub-section meta-item">
				<label class="meta-item-toggle-button"><?php _e( 'Account', 'supportflow' ) ?>:</label>
				<span class="meta-item-label"><?php esc_html_e( $email_account_label, 'supportflow' ) ?></span>
				<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
					<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
				</a>
				<input name="post_email_account" class="meta-item-name" value="<?php echo esc_attr( $email_account_first ); ?>" type="hidden" />

				<div class="meta-item-toggle-content hide-if-js">
					<?php echo esc_html( $email_account_dropdown ); ?>
					<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php esc_html_e( 'OK', 'supportflow' ) ?></a>
					<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php esc_html_e( 'Cancel', 'supportflow' ) ?></a>
				</div>
			</div>
		<?php
		}
	}

	public function render_meta_box_details_opened() {
		global $pagenow;

		// Get post creation and last update time
		if ( 'post.php' == $pagenow ) {
			$opened        = get_the_date() . ' ' . get_the_time();
			$modified_gmt  = get_post_modified_time( 'U', true, get_the_ID() );
			$last_activity = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
			?>
			<div class="misc-pub-section meta-item">
				<label><?php esc_html_e( 'Opened', 'supportflow' ) ?>:</label>
				<span class="meta-item-label"><?php esc_html_e( $opened ) ?></span>
			</div>

			<!--Last ticket update time-->
			<div class="misc-pub-section meta-item">
				<label><?php esc_html_e( 'Last Activity', 'supportflow' ) ?>:</label>
				<span class="meta-item-label"><?php esc_html_e( $last_activity ) ?></span>
			</div>
		<?php
		}
	}

	public function render_meta_box_details_status() {
		// Get post status
		$post_statuses     = SupportFlow()->post_statuses;
		$current_status_id = get_post_status( get_the_ID() );

		if ( ! isset( $post_statuses[$current_status_id] ) ) {
			$post_statuses_key = array_keys( $post_statuses );
			$current_status_id = $post_statuses_key[0];
		}

		$current_status_label = $post_statuses[$current_status_id]['label'];
		?>
		<!--Ticket status box-->
		<div class="misc-pub-section meta-item">
			<label class="meta-item-toggle-button"><?php esc_html_e( 'Status', 'supportflow' ) ?>:</label>
			<span class="meta-item-label"><?php esc_html_e( $current_status_label, 'supportflow' ) ?></span>
			<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
				<span aria-hidden="true"><?php esc_html_e( 'Edit', 'supportflow' ) ?></span>
			</a>
			<input name="post_status" class="meta-item-name" value="<?php esc_attr_e( $current_status_id ) ?>" type="hidden" />

			<div class="meta-item-toggle-content hide-if-js">
				<select class="meta-item-dropdown">
					<?php foreach ( $post_statuses as $slug => $post_status ) : ?>
						<option value="<?php esc_attr_e( $slug ) ?>"<?php selected( $current_status_id, $slug ) ?>><?php esc_html_e( $post_status['label'] ) ?></option>;
					<?php endforeach; ?>
				</select>
				<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php esc_html_e( 'OK', 'supportflow' ) ?></a>
				<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php esc_html_e( 'Cancel', 'supportflow' ) ?></a>
			</div>
		</div>
	<?php
	}

	public function render_meta_box_details_author() {
		// Get post authors
		$post_author_id = get_post( get_the_ID() )->post_author;

		// WP change owner to current user if $post_author_id is 0 (returned when ticket is unassigned)
		if ( 0 == $post_author_id ) {
			$post_author_id = - 1;
		}

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
			'echo'             => true
		);
		?>
		<div class="misc-pub-section meta-item">
			<label class="meta-item-toggle-button"><?php esc_html_e( 'Owner', 'supportflow' ) ?>:</label>
			<span class="meta-item-label"><?php esc_html_e( $post_author_label, 'supportflow' ) ?></span>
			<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
				<span aria-hidden="true"><?php esc_html_e( 'Edit', 'supportflow' ) ?></span>
			</a>
			<input name="post_author" class="meta-item-name" value="<?php esc_attr_e( $post_author_id ) ?>" type="hidden" />

			<div class="meta-item-toggle-content hide-if-js">
				<?php wp_dropdown_users( $args ); ?>
				<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php esc_html_e( 'OK', 'supportflow' ) ?></a>
				<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php esc_html_e( 'Cancel', 'supportflow' ) ?></a>
			</div>
		</div>
	<?php
	}

	public function render_meta_box_details_notifications() {
		global $pagenow;

		// Get E-Mail notification settings
		$notification_id          = 0;
		$notification_label       = 'Default';
		$notification_label_title = 'Choose default if you want to receive E-Mail notifications based on what you set in `E-Mail notification` page. Choose Enable/Disable if you want to override those settings';

		if ( 'post.php' == $pagenow ) {
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
		}

		?>

		<div class="misc-pub-section meta-item">
			<label class="meta-item-toggle-button" title="<?php _e( $notification_label_title, 'supportflow' ) ?>"><?php _e( 'E-Mail Notifications', 'supportflow' ) ?>:</label>
			<span class="meta-item-label"><?php esc_html_e( $notification_label, 'supportflow' ) ?></span>
			<a href="#" class="meta-item-toggle-button meta-item-toggle-content hide-if-no-js">
				<span aria-hidden="true"><?php _e( 'Edit' ) ?></span>
			</a>
			<input name="post_email_notifications_override" class="meta-item-name" value="<?php echo esc_attr( $notification_id ); ?>" type="hidden" />

			<div class="meta-item-toggle-content hide-if-js">
				<select class="meta-item-dropdown">
					<?php if ( 'post-new.php' == $pagenow ) : ?>

						<option value="default"><?php esc_html_e( 'Default',      'supportflow' ); ?></option>
						<option value="enable" ><?php esc_html_e( 'Subscribed',   'supportflow' ); ?></option>
						<option value="disable"><?php esc_html_e( 'Unsubscribed', 'supportflow' ); ?></option>

					<?php elseif ( 'post.php' == $pagenow ) : ?>

						<option value="default" <?php selected( $notification_id, 0 ); ?> >
							<?php esc_html_e( 'Default', 'supportflow' ); ?>
						</option>

						<option value="enable" <?php selected( $notification_id, 1 ); ?> >
							<?php esc_html_e( 'Subscribed', 'supportflow' ); ?>
						</option>

						<option value="disable" <?php selected( $notification_id, 2 ); ?> >
							<?php esc_html_e( 'Unsubscribed', 'supportflow' ); ?>
						</option>

					<?php endif; ?>
				</select>

				<a href="#" class="hide-if-no-js button meta-item-ok-button meta-item-toggle-button"><?php _e( 'OK' ) ?></a>
				<a href="#" class="hide-if-no-js button-cancel meta-item-cancel-button meta-item-toggle-button"><?php _e( 'Cancel' ) ?></a>
			</div>
		</div>
	<?php
	}

	public function render_meta_box_details_actions() {
		global $pagenow;

		$post_statuses     = SupportFlow()->post_statuses;
		$current_status_id = get_post_status( get_the_ID() );

		if ( ! isset( $post_statuses[$current_status_id] ) ) {
			$post_statuses_key = array_keys( $post_statuses );
			$current_status_id = $post_statuses_key[0];
		}

		$current_status_label = $post_statuses[$current_status_id]['label'];

		$close_ticket_label = __( 'Close ticket', 'supportflow' );

		// Get submit button label
		if ( 'post-new.php' == $pagenow ) {
			$submit_text = __( 'Start Ticket', 'supportflow' );
		} else {
			$submit_text = __( 'Update Ticket', 'supportflow' );
		}
		?>
		<div id="major-publishing-actions">
			<?php if ( 'post.php' == $pagenow && $current_status_id != 'sf_closed' ) : ?>
				<div id="delete-action">
					<?php submit_button( $close_ticket_label, '', 'close-ticket-submit', false, array( 'id' => 'close-ticket-submit' ) ); ?>
				</div>
			<?php endif; ?>
			<div id="publishing-action">
				<?php submit_button( $submit_text, 'save-button primary', 'update-ticket', false ); ?>
			</div>
			<div class="clear"></div>
		</div>
	<?php
	}

	/**
	 * A box that appears at the top
	 */
	public function meta_box_subject() {
		?>

		<h4><?php _e( 'Subject', 'supportflow' ); ?></h4>

		<input
			type="text"
			id="subject"
			name="post_title"
			class="sf_autosave"
			placeholder="<?php _e( 'What is your conversation about?', 'supportflow' ); ?>"
			value="<?php echo esc_attr( get_the_title() ); ?>"
			autocomplete="off"
		/>

		<p class="description">
			<?php _e( 'Please describe what this ticket is about in several words', 'supportflow' ) ?>
		</p>

		<?php
	}

	/**
	 * Add a form element where the user can change the customers
	 */
	public function meta_box_customers() {

		$placeholder = 'Who are you starting a conversation with?';
		if ( 'draft' == get_post_status( get_the_ID() ) ) {
			$customers_string = get_post_meta( get_the_ID(), '_sf_autosave_customers', true );
		} else {
			$customers        = SupportFlow()->get_ticket_customers( get_the_ID(), array( 'fields' => 'emails' ) );
			$customers_string = implode( ', ', $customers );
			$customers_string .= empty( $customers_string ) ? '' : ', ';
		}
		echo '<h4>' . __( 'Customer(s)', 'supportflow' ) . '</h4>';
		echo '<input type="text" id="customers" name="customers" class="sf_autosave" placeholder="' . esc_attr__( $placeholder, 'supportflow' ) . '" value="' . esc_attr( $customers_string ) . '" autocomplete="off" />';
		echo '<p class="description">' . __( 'Enter each customer email address, separated with a comma', 'supportflow' ) . '</p>';
	}

	/**
	 * Add a form element where you can choose cc and bcc receiver of reply
	 */
	public function meta_box_cc_bcc() {
		$cc_value = get_post_meta( get_the_ID(), '_sf_autosave_cc', true );
		$bcc      = get_post_meta( get_the_ID(), '_sf_autosave_bcc', true );
		?>
		<p class="description"> <?php _e( "Please add all the E-Mail ID's seperated by comma.", 'supportflow' ) ?></p>
		<h4 class="inline"><?php _e( "CC: ", 'supportflow' ) ?></h4>
		<input type="text" class="sf_autosave" id="cc" name="cc" value="<?php echo esc_attr( $cc_value ); ?>" />
		<h4 class="inline"> <?php _e( "BCC: ", 'supportflow' ) ?></h4>
		<input type="text" class="sf_autosave" id="bcc" name="bcc" value="<?php echo esc_attr( $bcc ); ?>" />
	<?php
	}

	/**
	 * Standard listing of replies includes a form at the top
	 * and any existing replies listed in reverse chronological order
	 */
	public function meta_box_replies() {
		global $pagenow;

		$predefined_replies = get_posts( array( 'post_type' => SupportFlow()->predefinded_replies_type, 'posts_per_page' => -1 ) );
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

		$email_account_id = get_post_meta( get_the_ID(), 'email_account', true );
		$email_account    = SupportFlow()->extend->email_accounts->get_email_account( $email_account_id );

		$ticket_lock       = ( null == $email_account && '' != $email_account_id );
		$disabled_attr     = disabled( $ticket_lock, true, false );
		$submit_attr_array = $ticket_lock ? array( 'disabled' => 'true' ) : array();

		if ( $ticket_lock ) {
			$placeholder = __( "Ticket is locked permanently because E-Mail account associated with it is deleted. Please create a new ticket now. You can't now reply to it.", 'supportflow' );
		} else {
			$placeholders = array(
				__( "What's burning?", 'supportflow' ),
				__( 'What do you need to get off your chest?', 'supportflow' ),
			);
			$rand         = array_rand( $placeholders );
			$placeholder  = $placeholders[$rand];
		}

		echo '<div class="alignleft"><h4>' . __( 'Conversation', 'supportflow' ) . '</h4></div>';
		echo '<div class="alignright">';
		echo '<select id="predefs" ' . $disabled_attr . ' class="predefined_replies_dropdown">';
		foreach ( $pre_defs as $pre_def ) {
			echo '<option class="predef" data-content="' . esc_attr( $pre_def['content'] ) . '">' . esc_html( $pre_def['title'] ) . "</option>\n";
		}
		echo '</select></div>';

		echo '<div id="ticket-reply-box">';
		echo "<textarea id='reply' name='reply' $disabled_attr class='ticket-reply sf_autosave' rows='4' placeholder='" . esc_attr( $placeholder ) . "'>";
		echo esc_html( get_post_meta( get_the_ID(), '_sf_autosave_reply', true ) );
		echo "</textarea>";

		echo '<div id="message-tools">';
		echo '<div id="replies-attachments-wrap">';
		echo '<div class="drag-drop-buttons">';
		echo '<input id="reply-attachment-browse-button" ' . $disabled_attr . ' type="button" value="' . esc_attr( __( 'Attach files', 'supportflow' ) ) . '" class="button" />';
		echo '</div>';
		echo '<ul id="replies-attachments-list">';
		echo '</ul>';
		echo '<input type="hidden" id="reply-attachments" name="reply-attachments" value="," />';
		echo '</div>';
		echo '<div id="submit-action">';
		$signature_label_title = __( 'Append your signature at the bottom of the reply. Signature can be removed or changed in preferences page', 'supportflow' );
		$insert_signature_default = (boolean) get_user_meta( get_current_user_id(), 'sf_insert_signature_default', true );
		echo '<input type="checkbox" ' . $disabled_attr . checked( $insert_signature_default, true, false ) . ' id="insert-signature" name="insert-signature" />';
		echo "<label for='insert-signature' title='". esc_attr( $signature_label_title ) ."'>" . __( 'Insert signature', 'supportflow' ) . '</label>';
		echo '<input type="checkbox" ' . $disabled_attr . ' id="mark-private" name="mark-private" />';
		echo '<label for="mark-private">' . __( 'Mark private', 'supportflow' ) . '</label>';
		if ( 'post-new.php' == $pagenow ) {
			$submit_text = __( 'Start Ticket', 'supportflow' );
		} else {
			$submit_text = __( 'Send Message', 'supportflow' );
		}
		submit_button( $submit_text, 'primary save-button', 'insert-reply', false, $submit_attr_array );
		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '<div class="clear"></div>';

		$this->display_ticket_replies();
	}

	public function display_ticket_replies() {
		$private_replies = SupportFlow()->get_ticket_replies( get_the_ID(), array( 'status' => 'private' ) );

		if ( ! empty( $private_replies ) ) {
			echo '<ul class="private-replies">';
			foreach ( $private_replies as $reply ) {
				echo '<li>';
				echo '<div class="ticket-reply">';
				$post_content = stripslashes( $reply->post_content );
				$post_content = $this->hide_quoted_text( $post_content );
				$post_content = wpautop( make_clickable( $post_content ) );
				echo wp_kses( $post_content, 'post' );
				if ( $attachment_ids = get_post_meta( $reply->ID, 'sf_attachments' ) ) {
					echo '<ul class="ticket-reply-attachments">';
					foreach ( $attachment_ids as $attachment_id ) {
						$attachment_link = SupportFlow()->extend->attachments->get_attachment_url( $attachment_id );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( get_the_title( $attachment_id ) ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$reply_author    = get_post_meta( $reply->ID, 'reply_author', true );
				$reply_timestamp = sprintf( __( 'Noted by %1$s on %2$s at %3$s', 'supportflow' ), $reply_author, get_the_date( '', $reply->ID ), get_the_time( '', $reply->ID ) );
				$modified_gmt    = get_post_modified_time( 'U', true, $reply->ID );
				$last_activity   = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				echo '<div class="ticket-meta"><span class="reply-timestamp">' . esc_html( $reply_timestamp ) . ' (' . esc_html( $last_activity ) . ')' . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		$replies = SupportFlow()->get_ticket_replies( get_the_ID(), array( 'status' => 'public' ) );
		if ( ! empty( $replies ) ) {
			echo '<ul class="ticket-replies">';
			foreach ( $replies as $reply ) {
				$reply_author       = get_post_meta( $reply->ID, 'reply_author', true );
				$reply_author_email = get_post_meta( $reply->ID, 'reply_author_email', true );
				echo '<li>';
				echo '<div class="reply-avatar">' . get_avatar( $reply_author_email, 72 );
				echo '<p class="reply-author">' . esc_html( $reply_author ) . '</p>';
				echo '</div>';
				echo '<div class="ticket-reply">';

				$mail_status = get_post_meta( $reply->ID, '_sf_mail_status', true );
				if ( isset( $mail_status['result'] ) && $mail_status['result'] === false ) {
					printf( '<span class="sf-delivery-failed"><span class="dashicons dashicons-info"></span> %s</span>', esc_html__( 'Delivery failed! Please check your SMTP settings and try again.', 'supportflow' ) );
				}

				$post_content = stripslashes( $reply->post_content );
				$post_content = $this->hide_quoted_text( $post_content );
				$post_content = wpautop( make_clickable( $post_content ) );

				echo wp_kses( $post_content, 'post' );
				if ( $attachment_ids = get_post_meta( $reply->ID, 'sf_attachments' ) ) {
					echo '<ul class="ticket-reply-attachments">';
					foreach ( $attachment_ids as $attachment_id ) {
						$attachment_link = SupportFlow()->extend->attachments->get_attachment_url( $attachment_id );
						echo '<li><a target="_blank" href="' . esc_url( $attachment_link ) . '">' . esc_html( get_the_title( $attachment_id ) ) . '</a></li>';
					}
					echo '</ul>';
				}
				echo '</div>';
				$reply_timestamp = sprintf( __( '%s at %s', 'supportflow' ), get_the_date( '', $reply->ID ), get_the_time( '', $reply->ID ) );
				$modified_gmt    = get_post_modified_time( 'U', true, $reply->ID );
				$last_activity   = sprintf( __( '%s ago', 'supportflow' ), human_time_diff( $modified_gmt ) );
				echo '<div class="ticket-meta"><span class="reply-timestamp">' . esc_html( $reply_timestamp ) . ' (' . esc_html( $last_activity ) . ')' . '</span></div>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '<div class="clear"></div>';

	}

	/**
	 * Modifications to the columns appearing in the All Tickets view
	 */
	public function filter_manage_post_columns( $columns ) {

		$new_columns = array(
			'cb'          => $columns['cb'],
			'updated'     => __( 'Updated', 'supportflow' ),
			'title'       => __( 'Subject', 'supportflow' ),
			'sf_excerpt'  => __( 'Excerpt', 'supportflow' ),
			'customers' => __( 'Customers', 'supportflow' ),
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
	 * on the Manage Tickets view so mode=excerpt works well
	 */
	public function filter_get_the_excerpt( $orig ) {
		if ( $reply = array_pop( SupportFlow()->get_ticket_replies( get_the_ID() ) ) ) {
			$reply_author = get_post_meta( $reply->ID, 'reply_author' );

			return $reply_author . ': "' . wp_trim_excerpt( $reply->post_content ) . '"';
		} else {
			return $orig;
		}
	}

	/**
	 * Produce the column values for the custom columns we created
	 */
	function action_manage_posts_custom_column( $column_name, $ticket_id ) {

		switch ( $column_name ) {
			case 'updated':
				$modified_gmt = get_post_modified_time( 'U', true, $ticket_id );
				echo sprintf( __( '%s ago', 'supportflow' ), esc_html( human_time_diff( $modified_gmt ) ) );
				break;
			case 'sf_excerpt':
				$replies = SupportFlow()->get_ticket_replies( $ticket_id, array( 'posts_per_page' => 1, 'order' => 'ASC' ) );
				if ( ! isset( $replies[0] ) ) {
					echo '—';
					break;
				}
				$first_reply = $replies[0]->post_content;
				if ( strlen( $first_reply ) > 50 ) {
					$first_reply = substr( $first_reply, 0, 50 );
				}
				echo esc_html( $first_reply );
				break;
			case 'customers':
				$customers = SupportFlow()->get_ticket_customers( $ticket_id, array( 'fields' => 'emails' ) );
				if ( empty( $customers ) ) {
					echo '—';
					break;
				}
				foreach ( $customers as $key => $customer_email ) {
					$args              = array(
						SupportFlow()->customers_tax => SupportFlow()->get_email_hash( $customer_email ),
						'post_type'                    => SupportFlow()->post_type,
					);
					$customer_photo  = get_avatar( $customer_email, 16 );
					$customer_link   = '<a class="customer_link" href="' . esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ) . '">' . $customer_email . '</a>';
					$customers[$key] = $customer_photo . '&nbsp;' . $customer_link;
				}
				echo wp_kses( implode( '<br />', $customers ), 'comment' );
				break;
			case 'status':
				$post_status = get_post_status( $ticket_id );
				$args        = array(
					'post_type'   => SupportFlow()->post_type,
					'post_status' => $post_status,
				);
				$status_name = get_post_status_object( $post_status )->label;
				$filter_link = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $status_name ) . '</a>';
				break;
			case 'email':
				$email_account_id = get_post_meta( $ticket_id, 'email_account', true );
				$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts();
				$args             = array(
					'post_type'     => SupportFlow()->post_type,
					'email_account' => $email_account_id,
				);
				if ( ! isset( $email_accounts[$email_account_id] ) ) {
					echo '—';
					break;
				}
				$email_account_username = $email_accounts[$email_account_id]['username'];
				$filter_link            = add_query_arg( $args, admin_url( 'edit.php' ) );
				echo '<a href="' . esc_url( $filter_link ) . '">' . esc_html( $email_account_username ) . '</a>';
				break;
			case 'sf_replies':
				$replies = SupportFlow()->get_ticket_replies_count( $ticket_id );
				echo '<div class="post-com-count-wrapper">';
				echo "<span class='replies-count'>". esc_html( $replies ) ."</span>";
				echo '</div>';
				break;
			case 'created':
				$created_time = get_the_time( get_option( 'time_format' ) . ' T', $ticket_id );
				$created_date = get_the_time( get_option( 'date_format' ), $ticket_id );
				echo sprintf( __( '%s<br />%s', 'supportflow' ), esc_html( $created_time ), esc_html( $created_date ) );
				break;
		}
	}

	/**
	 * Whether or not we're on a view for creating or updating a ticket
	 *
	 * @return string $pagenow Return the context for the screen we're in
	 */
	public function is_edit_screen() {
		global $pagenow;

		if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ) ) && ! empty( $_GET['post_type'] ) && $_GET['post_type'] == SupportFlow()->post_type ) {
			return $pagenow;
		} elseif ( 'post.php' == $pagenow && ! empty( $_GET['action'] ) && 'edit' == $_GET['action'] && ! empty( $_GET['post'] ) ) {
			$the_post = get_post( absint( $_GET['post'] ) );

			return ( is_a( $the_post, 'WP_Post' ) && $the_post->post_type == SupportFlow()->post_type ) ? $pagenow : false;
		} else {
			return false;
		}

	}

	/**
	 * When a ticket is saved or updated, make sure we save the customer
	 * and new reply data
	 */
	public function action_save_post( $ticket_id ) {
		$email_account_id = get_post_meta( $ticket_id, 'email_account', true );
		$email_account    = SupportFlow()->extend->email_accounts->get_email_account( $email_account_id );
		$ticket_lock      = ( null == $email_account && '' != $email_account );

		if ( SupportFlow()->post_type != get_post_type( $ticket_id ) ) {
			return;
		}

		if ( isset( $_POST['customers'] ) ) {
			$customers = array_map( 'sanitize_email', explode( ',', $_POST['customers'] ) );
			SupportFlow()->update_ticket_customers( $ticket_id, $customers );
		}

		if ( isset( $_POST['post_email_account'] ) && is_numeric( $_POST['post_email_account'] ) && '' == $email_account_id ) {
			$email_account = (int) $_POST['post_email_account'];
			update_post_meta( $ticket_id, 'email_account', $email_account );
		}

		if ( isset( $_POST['post_email_notifications_override'] ) && in_array( $_POST['post_email_notifications_override'], array( 'default', 'enable', 'disable' ) ) ) {
			$email_notifications_override                        = get_post_meta( $ticket_id, 'email_notifications_override', true );
			$email_notifications_override[get_current_user_id()] = $_POST['post_email_notifications_override'];
			update_post_meta( $ticket_id, 'email_notifications_override', $email_notifications_override );
		}

		if ( isset( $_POST['reply'] ) && ! empty( $_POST['reply'] ) && ! $ticket_lock ) {
			$reply = $_POST['reply'];

			if ( isset( $_POST['insert-signature'] ) && 'on' == $_POST['insert-signature'] ) {
				$agent_signature = get_user_meta( get_current_user_id(), 'sf_user_signature', true );
				if ( ! empty( $agent_signature ) ) {
					$reply .= "\n\n-----\n$agent_signature";
				}
			}

			$reply = SupportFlow()->sanitize_ticket_reply( $reply );

			$visibility = ( ! empty( $_POST['mark-private'] ) ) ? 'private' : 'public';
			if ( ! empty( $_POST['reply-attachments'] ) ) {
				$attachements   = explode( ',', trim( $_POST['reply-attachments'], ',' ) );
				// Remove same attachment added more than once
				$attachements   = array_unique($attachements);
				// Remove non-int attachment ID's from array
				$attachements   = array_filter( $attachements, function ( $val ) {
					return (string) (int) $val === (string) $val;
				} );
				$attachment_ids = array_map( 'intval', $attachements );
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
			SupportFlow()->add_ticket_reply( $ticket_id, $reply, $reply_args );
		}

	}

	/**
	 * Hide quoted content in a message and display a link to show it.
	 * Line startings with ">" sign are considered quoted content
	 */
	public function hide_quoted_text( $text ) {
		$res = preg_replace_callback( "#(?:^(?:&gt;)+\s.+$\s*)+#im", array( $this, 'hide_quoted_text_regex_callback' ), $text );
		return $res;
	}

	/**
	 * Just a function used by hide_quoted_text() for its regex callback
	 * Anonymous function are not used as they unavailable in PHP 5.2.x
	 * create_function() is not used as it it not readable
	 */
	protected function hide_quoted_text_regex_callback( $matches ) {
		$match    = esc_attr( $matches[0] );
		$show_msg = __( 'Show quoted content', 'supportflow' );

		return "<span><a href='' class='sf_toggle_quoted_text' data-quoted_text='$match'><br />$show_msg</a><br /></span>";
	}
}

SupportFlow()->extend->admin = new SupportFlow_Admin();
