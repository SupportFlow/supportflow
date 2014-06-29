<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

/**
 * Plugin Name: SupportFlow
 * Plugin URI:  https://wordpress.org/plugins/supportflow/
 * Description: Reinventing how you support your customers.
 * Author:      Daniel Bachhuber, Varun Agrawal, Alex Mills, Andrew Spittle
 * Version:     0.2
 *
 * Text Domain: supportflow
 * Domain Path: /languages/
 */

class SupportFlow {

	/** Magic *****************************************************************/

	/**
	 * SupportFlow uses many variables, most of which can be filtered to customize
	 * the way that it works. To prevent unauthorized access, these variables
	 * are stored in a private array that is magically updated using PHP 5.2+
	 * methods. This is to prevent third party plugins from tampering with
	 * essential information indirectly, which would cause issues later.
	 *
	 * @see SupportFlow::setup_globals()
	 * @var array
	 */
	private $data;

	/** Not Magic *************************************************************/

	/**
	 * @var obj Add-ons append to this (Akismet, etc...)
	 */
	public $extend;

	/** Singleton *************************************************************/

	/**
	 * @var SupportFlow The one true SupportFlow
	 */
	private static $instance;

	/**
	 * Main SupportFlow Instance
	 *
	 * SupportFlow is fun
	 * Please load it only one time
	 * For this, we thank you
	 *
	 * Ensures that only one instance of SupportFlow exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since     SupportFlow 0.1
	 * @staticvar array $instance
	 * @uses      SupportFlow::setup_globals() Setup the globals needed
	 * @uses      SupportFlow::includes() Include the required files
	 * @uses      SupportFlow::setup_actions() Setup the hooks and actions
	 * @see       SupportFlow()
	 * @return The one true SupportFlow
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SupportFlow;
			self::$instance->setup_globals();
			self::$instance->includes();
			self::$instance->setup_actions();
		}

		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent SupportFlow from being loaded more than once.
	 *
	 * @since SupportFlow 0.1
	 * @see   SupportFlow::instance()
	 * @see   SupportFlow();
	 */
	private function __construct() {
		/* Do nothing here */
	}

	/**
	 * A dummy magic method to prevent SupportFlow from being cloned
	 *
	 * @since SupportFlow 0.1
	 */
	public function __clone() {
		wp_die( __( 'Cheatin’ uh?' ) );
	}

	/**
	 * A dummy magic method to prevent SupportFlow from being unserialized
	 *
	 * @since SupportFlow 0.1
	 */
	public function __wakeup() {
		wp_die( __( 'Cheatin’ uh?' ) );
	}

	/**
	 * Magic method for checking the existence of a certain custom field
	 *
	 * @since SupportFlow 0.1
	 */
	public function __isset( $key ) {
		return isset( $this->data[$key] );
	}

	/**
	 * Magic method for getting SupportFlow varibles
	 *
	 * @since SupportFlow 0.1
	 */
	public function __get( $key ) {
		return isset( $this->data[$key] ) ? $this->data[$key] : null;
	}

	/**
	 * Magic method for setting SupportFlow varibles
	 *
	 * @since SupportFlow 0.1
	 */
	public function __set( $key, $value ) {
		$this->data[$key] = $value;
	}

	/** Private Methods *******************************************************/

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since  SupportFlow 0.1
	 * @access private
	 * @uses   plugin_dir_path() To generate SupportFlow plugin path
	 * @uses   plugin_dir_url() To generate SupportFlow plugin url
	 * @uses   apply_filters() Calls various filters
	 */
	private function setup_globals() {

		/** Version ***********************************************************/

		$this->version = '0.1-alpha'; // SupportFlow version

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file       = __FILE__;
		$this->basename   = apply_filters( 'supportflow_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir = apply_filters( 'supportflow_plugin_dir_path', plugin_dir_path( $this->file ) );
		$this->plugin_url = apply_filters( 'supportflow_plugin_dir_url', plugin_dir_url( $this->file ) );

		// Languages
		$this->lang_dir = apply_filters( 'supportflow_lang_dir', trailingslashit( $this->plugin_dir . 'languages' ) );

		/** Identifiers *******************************************************/

		$this->post_type                = apply_filters( 'supportflow_thread_post_type', 'sf_thread' ); // Retained sf_thread for backward compatiblity with old versions
		$this->predefinded_replies_type = apply_filters( 'supportflow_predefinded_replies_type', 'sf_predefs' );
		$this->respondents_tax          = apply_filters( 'supportflow_respondents_taxonomy', 'sf_respondent' );
		$this->tags_tax                 = apply_filters( 'supportflow_tags_taxonomy', 'sf_tags' );
		$this->comment_type             = apply_filters( 'supportflow_ticket_comment_type', 'sf_comment' );
		$this->reply_type               = apply_filters( 'supportflow_ticket_reply_type', 'sf_thread' ); // Retained sf_thread for backward compatiblity with old versions

		$this->email_term_prefix = 'sf-';

		$this->ticket_secret_key = 'ticket_secret';

		$this->post_statuses = apply_filters(
			'supportflow_ticket_post_statuses', array(
				'sf_new'     => array(
					'show_tickets' => true,
					'label'        => __( 'New', 'supportflow' ),
					'label_count'  => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'supportflow' ),
				),
				'sf_open'    => array(
					'show_tickets' => true,
					'label'        => __( 'Open', 'supportflow' ),
					'label_count'  => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'supportflow' ),
				),
				'sf_pending' => array(
					'show_tickets' => true,
					'label'        => __( 'Pending', 'supportflow' ),
					'label_count'  => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'supportflow' ),
				),
				'sf_spam' => array(
					'label'       => __( 'Spam', 'supportflow' ),
					'label_count' => _n_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'supportflow' ),
				),
				'sf_closed'  => array(
					'show_tickets' => false,
					'label'        => __( 'Closed', 'supportflow' ),
					'label_count'  => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'supportflow' ),
				),
			)
		);

		/** Misc **************************************************************/

		$this->extend     = new stdClass(); // Plugins add data here
		$this->extend->ui = new stdClass(); // For UI-related plugins
		$this->errors     = new WP_Error(); // Feedback
	}

	/**
	 * Include required files
	 *
	 * @since  SupportFlow 0.1
	 * @access private
	 * @todo   Be smarter about conditionally loading code
	 * @uses   is_admin() If in WordPress admin, load additional file
	 */
	private function includes() {

		/** Core **************************************************************/

		require_once( $this->plugin_dir . 'classes/class-supportflow-json-api.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-attachments.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-emails.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-email-replies.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-permissions.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-email-accounts.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-predefined-replies.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-email-notifications.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-preferences.php' );

		/** Extensions ********************************************************/

		require_once( $this->plugin_dir . 'classes/class-supportflow-ui-submissionform.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-ui-widget.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-statistics.php' );

		/** Tools *************************************************************/
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once( $this->plugin_dir . '/classes/class-supportflow-wp-cli.php' );
		}

		# TODO: Akismet plugin?

		/** Admin *************************************************************/

		// Quick admin check and load if needed
		if ( is_admin() ) {
			require_once( $this->plugin_dir . 'classes/class-supportflow-admin.php' );
		}
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since  SupportFlow 0.1
	 * @access private
	 * @uses   add_action() To add various actions
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'action_init_register_post_type' ) );
		add_action( 'init', array( $this, 'action_init_register_taxonomies' ) );
		add_action( 'init', array( $this, 'action_init_register_post_statuses' ) );

		do_action_ref_array( 'supportflow_after_setup_actions', array( &$this ) );
	}

	/**
	 * Register the custom post type
	 *
	 * @since SupportFlow 0.1
	 * @uses  register_post_type() To register the post type
	 */
	public function action_init_register_post_type() {
		register_post_type(
			$this->post_type, array(
				'labels'             => array(
					'menu_name'          => __( 'SupportFlow', 'supportflow' ),
					'name'               => __( 'Tickets', 'supportflow' ),
					'singular_name'      => __( 'Ticket', 'supportflow' ),
					'all_items'          => __( 'All Tickets', 'supportflow' ),
					'add_new'            => __( 'New Ticket', 'supportflow' ),
					'add_new_item'       => __( 'Start New Ticket', 'supportflow' ),
					'edit_item'          => __( 'Discussion', 'supportflow' ),
					'new_item'           => __( 'New Ticket', 'supportflow' ),
					'view_item'          => __( 'View Ticket', 'supportflow' ),
					'search_items'       => __( 'Search Tickets', 'supportflow' ),
					'not_found'          => __( 'No tickets found', 'supportflow' ),
					'not_found_in_trash' => __( 'No tickets found in trash', 'supportflow' ),
				),
				'public'             => true,
				'menu_position'      => 3,
				'publicly_queryable' => false,
				'supports'           => false,
			)
		);
	}

	/**
	 * Register our custom taxonomies
	 */
	public function action_init_register_taxonomies() {

		$capabilities = array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'manage_options',
		);

		$args_respondents_tax = array(
			'label'             => __( 'Respondents', 'supportflow' ),
			'labels'            => array(
				'search_items' => __( 'Search Respondents', 'supportflow' ),
				'edit_item'    => __( 'Edit Respondent', 'supportflow' ),
				'update_item'  => __( 'Update Respondent', 'supportflow' ),
				'add_new_item' => __( 'Add New Respondent', 'supportflow' ),
			),
			'public'            => true,
			'show_in_nav_menus' => true,
			'rewrite'           => false,
			'capabilities'      => $capabilities,
		);

		$args_tags_tax = array(
			'label'             => __( 'Tags', 'supportflow' ),
			'labels'            => array(
				'search_items' => __( 'Search Tags', 'supportflow' ),
				'edit_item'    => __( 'Edit Tag', 'supportflow' ),
				'update_item'  => __( 'Update Tag', 'supportflow' ),
				'add_new_item' => __( 'Add New Tag', 'supportflow' ),
			),
			'public'            => true,
			'show_in_nav_menus' => true,
			'rewrite'           => false,
			'capabilities'      => $capabilities,
		);

		register_taxonomy( $this->respondents_tax, $this->post_type, $args_respondents_tax );
		register_taxonomy( $this->tags_tax, $this->post_type, $args_tags_tax );
	}

	/**
	 * Register the custom post (ticket) statuses
	 *
	 * @since SupportFlow 0.1
	 * @uses  register_post_status() To register the post statuses
	 * @uses  apply_filters() To control what statuses are registered
	 */
	public function action_init_register_post_statuses() {
		foreach ( $this->post_statuses as $post_status => $args ) {
			$args['public'] = true;
			register_post_status( $post_status, $args );
		}
	}

	/** Helper Functions ******************************************************/

	/**
	 * Validates a user ID
	 */
	public function validate_user( $user ) {
		// User ID
		if ( is_numeric( $user ) ) {
			$user_object = get_user_by( 'ID', $user );
		} // User e-mail address
		elseif ( is_email( $user ) ) {
			$user_object = get_user_by( 'email', $user );
		} // User login
		else {
			$user_object = get_user_by( 'login', $user );
		}

		if ( ! $user_object ) {
			return false;
		}

		return $user_object->data->ID;
	}

	/**
	 * Turns an e-mail address into a term hash, or false on failure
	 */
	public function get_email_hash( $email ) {
		$email = strtolower( trim( $email ) );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$email = $this->email_term_prefix . md5( $email );

		return $email;
	}

	/** Ticket Functions ******************************************************/

	/**
	 * Check whether a post_id is a ticket
	 */
	public function is_ticket( $post ) {
		return (bool) ( $this->post_type == get_post_type( $post ) );
	}

	/**
	 * Create a new ticket
	 */
	public function create_ticket( $args ) {
		// The __get() magic doesn't allow key() usage so gotta copy it
		$post_statuses = $this->post_statuses;

		$defaults = array(
			'subject'            => '',
			'message'            => '',
			'date'               => '',
			'respondent_id'      => 0, // If the requester has a WordPress account (ID or username)
			'respondent_email'   => array(), // And an e-mail address
			'reply_author'       => '',
			'reply_author_email' => '',
			'status'             => key( $post_statuses ),
			'assignee'           => - 1, // WordPress user ID or username of ticket assignee/owner
			'email_account'      => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$ticket = array(
			'post_type'   => $this->post_type,
			'post_title'  => $args['subject'],
			'post_author' => $args['assignee'],
			'post_date'   => $args['date'],
		);

		// Validate the ticket status
		if ( ! get_post_status_object( $args['status'] ) ) {
			$args['status'] = $defaults['status'];
		}
		$ticket['post_status'] = $args['status'];
		$ticket_id             = wp_insert_post( $ticket );

		if ( is_wp_error( $ticket_id ) ) {
			return $ticket_id;
		}

		// Assign the respondent(s)
		if ( ! empty( $args['respondent_email'] ) ) {
			$this->update_ticket_respondents( $ticket_id, $args['respondent_email'] );
		}

		if ( is_numeric( $args['email_account'] ) ) {
			update_post_meta( $ticket_id, 'email_account', $args['email_account'] );
		}

		// If there was a message, add it to the ticket
		if ( ! empty( $args['message'] ) && ! empty( $args['respondent_email'] ) ) {
			$reply_details = array(
				'reply_author'       => $args['reply_author'],
				'reply_author_email' => $args['reply_author_email'],
				'user_id'            => $args['respondent_id'],
			);
			$this->add_ticket_reply( $ticket_id, $args['message'], $reply_details );
		}

		return $ticket_id;
	}

	/**
	 * @todo This should produce an object with ticket details, respondents, and replies
	 */
	public function get_ticket( $ticket_id ) {

		return get_post( $ticket_id );
	}

	/**
	 * @todo This should produce a series of ticket objects with respondents, replies, etc.
	 */
	public function get_tickets( $args = array() ) {

		$defaults = array(
			'respondent_email' => '',
			'post_status'      => '',
			'orderby'          => 'modified',
		);
		$args     = array_merge( $defaults, $args );

		$ticket_args = array();
		if ( empty( $args['post_status'] ) ) {
			$ticket_args['post_status'] = array_keys( $this->post_statuses );
		}

		if ( ! empty( $args['respondent_email'] ) ) {
			$ticket_args['tax_query'] = array(
				array(
					'taxonomy' => $this->respondents_tax,
					'field'    => 'slug',
					'terms'    => $this->get_email_hash( $args['respondent_email'] ),
				),
			);
		}

		$ticket_args['post_type'] = $this->post_type;
		$ticket_args['orderby']   = $args['orderby'];

		$tickets = new WP_Query( $ticket_args );
		if ( is_wp_error( $tickets ) ) {
			return $tickets;
		}

		return $tickets->posts;
	}

	/**
	 * Get respondents matching $query
	 *
	 * @param string $query partial email address to search for
	 */
	public function get_respondents( $args = array() ) {

		$defaults = array(
			'search' => '',
			'number' => 10
		);
		$args     = array_merge( $defaults, $args );

		$term_args = array(
			'orderby'    => 'name',
			'hide_empty' => 0,
			'fields'     => 'all',
			'name__like' => $args['search'],
			'number'     => $args['number'],
		);
		$matches   = get_terms( $this->respondents_tax, $term_args );

		if ( ! $matches ) {
			return array();
		}

		return $matches;
	}

	/**
	 * Get a ticket's respondents
	 *
	 * @todo support retrieving more fields
	 */
	public function get_ticket_respondents( $ticket_id, $args = array() ) {

		$default_args = array(
			'fields' => 'all', // 'all', 'emails'
		);
		$args         = array_merge( $default_args, $args );

		$raw_respondents = wp_get_object_terms( $ticket_id, $this->respondents_tax );
		if ( is_wp_error( $raw_respondents ) ) {
			return array();
		}

		$respondents = array();
		if ( 'emails' == $args['fields'] ) {
			foreach ( $raw_respondents as $raw_respondent ) {
				$respondents[] = $raw_respondent->name;
			}
		}

		return $respondents;
	}

	/**
	 * Update a ticket's respondents
	 *
	 * @param int   $ticket_id
	 * @param array $respondents An array of email addresses
	 * @param bool  $append      Whether or not to append these email addresses to any existing addresses
	 */
	public function update_ticket_respondents( $ticket_id, $respondents, $append = false ) {

		if ( is_string( $respondents ) ) {
			$respondents = array( $respondents );
		}

		$term_ids = array();
		foreach ( $respondents as $dirty_respondent ) {
			if ( empty( $dirty_respondent ) ) {
				continue;
			}
			// Create a term if it doesn't yet exist
			$email = ( is_array( $dirty_respondent ) ) ? $dirty_respondent['user_email'] : $dirty_respondent;
			if ( $term = get_term_by( 'name', $email, $this->respondents_tax ) ) {
				$term_ids[] = (int) $term->term_id;
			} else {
				$term       = wp_insert_term( $email, $this->respondents_tax, array( 'slug' => $this->get_email_hash( $email ) ) );
				$term_ids[] = $term['term_id'];
			}
		}
		wp_set_object_terms( $ticket_id, $term_ids, $this->respondents_tax, $append );
	}

	/**
	 * Get all of the replies associated with a ticket
	 */
	public function get_ticket_replies( $ticket_id, $args = array() ) {

		$args['post_id'] = $ticket_id;
		$ticket_replies  = SupportFlow()->get_replies( $args );

		return $ticket_replies;
	}

	/**
	 * Get all replies based on various arguments
	 */
	public function get_replies( $args ) {

		$default_args = array(
			'status'      => 'public', // 'public', 'private', 'all'
			'post_id'     => '',
			'search'      => '',
			'order'       => 'DESC', // 'DESC', 'ASC',
			'numberposts' => - 1,
		);

		$args      = array_merge( $default_args, $args );
		$post_args = array(
			'search'           => $args['search'],
			'post_parent'      => $args['post_id'],
			'post_status'      => $args['status'],
			'post_type'        => $this->reply_type,
			'order'            => $args['order'],
			'numberposts'      => $args['numberposts'],
			'suppress_filters' => false,
		);
		add_filter( 'posts_clauses', array( $this, 'filter_reply_clauses' ), 10, 2 );
		$ticket_replies = get_posts( $post_args );

		remove_filter( 'posts_clauses', array( $this, 'filter_reply_clauses' ) );

		return $ticket_replies;
	}

	/**
	 * Convert 'any' reply approved requests to the proper SQL
	 */
	public function filter_reply_clauses( $clauses, $query ) {
		if ( in_array( $query->query_vars['post_status'], array( 'public', 'private' ) ) ) {
			$new_post_status = "post_status = '{$query->query_vars['post_status']}' ";
		} else {
			$new_post_status = "post_status IN ( 'private', 'public' )";
		}

		if ( preg_match( "~post_status = '[^']+'~", $clauses['where'] ) ) {
			$clauses['where'] = preg_replace( "~post_status = '[^']+'~", $new_post_status, $clauses['where'] );
		} else {
			$clauses['where'] .= " AND ($new_post_status)";
		}

		return $clauses;
	}

	/**
	 * Get the total number of replies associated with a ticket
	 *
	 * @todo support filtering to specific types or replier
	 */
	public function get_ticket_replies_count( $ticket_id, $args = array() ) {
		$args = array(
			'posts_per_page' => 1,
			'post_type'      => $this->post_type,
			'post_status'    => 'public',
			'post_parent'    => $ticket_id,
		);

		$query = new WP_Query( $args );
		$count = $query->found_posts;

		return (int) $count;
	}

	/**
	 * Add a reply to a given ticket
	 */
	public function add_ticket_reply( $ticket_id, $reply_text, $details = array() ) {
		global $wpdb;

		$default_details = array(
			'time'               => current_time( 'mysql' ),
			'reply_author'       => '',
			'reply_author_email' => '',
			'user_id'            => '',
			'post_status'        => 'public',
			'cc'                 => array(),
			'bcc'                => array(),
		);

		// @todo This actually probably shouldn't default to current user, so
		// we don't have to mandate the arguments be assigned each time
		if ( $user = wp_get_current_user() ) {
			$default_details['reply_author']       = $user->display_name;
			$default_details['reply_author_email'] = $user->user_email;
			$default_details['user_id']            = $user->ID;
		}

		$details = array_merge( $default_details, $details );

		// If there are attachments, store them for later
		if ( isset( $details['attachment_ids'] ) ) {
			$attachment_ids = $details['attachment_ids'];
			unset( $details['attachment_ids'] );
		} else {
			$attachment_ids = false;
		}

		$reply = array(
			'post_content' => $reply_text,
			'post_parent'  => (int) $ticket_id,
			'post_date'    => $details['time'],
			'post_status'  => $details['post_status'],
			'post_type'    => $this->reply_type,
			'post_title'   => 'supportflow reply',
			'user_id'      => (int) $details['user_id'],
		);

		$reply = apply_filters( 'supportflow_pre_insert_ticket_reply', $reply );
		remove_action( 'save_post', array( SupportFlow()->extend->admin, 'action_save_post' ) );
		$reply_id = wp_insert_post( $reply );
		add_action( 'save_post', array( SupportFlow()->extend->admin, 'action_save_post' ) );
		// If there are attachment IDs store them as meta
		if ( is_array( $attachment_ids ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $reply_id ) );
			}
		}

		add_post_meta( $reply_id, 'reply_author', esc_sql( $details['reply_author'] ) );
		add_post_meta( $reply_id, 'reply_author_email', esc_sql( $details['reply_author_email'] ) );


		// Adding a ticket reply updates the post modified time for the ticket
		remove_action( 'save_post', array( SupportFlow()->extend->admin, 'action_save_post' ) );
		wp_update_post( array( 'ID' => $ticket_id, 'post_modified' => current_time( 'mysql' ) ) );
		add_action( 'save_post', array( SupportFlow()->extend->admin, 'action_save_post' ) );
		clean_post_cache( $ticket_id );
		do_action( 'supportflow_ticket_reply_added', $reply_id, $details['cc'], $details['bcc'] );

		return $reply_id;
	}

	/**
	 * Generate the secure key for replying to this ticket
	 *
	 * @todo Rather than storing this in the database, it should be generated on the fly
	 * with an encryption algorithim
	 */
	public function get_secret_for_ticket( $ticket_id ) {

		if ( $secret = get_post_meta( $ticket_id, $this->ticket_secret_key, true ) ) {
			return $secret;
		}

		$secret = wp_generate_password( 8, false );
		update_post_meta( $ticket_id, $this->ticket_secret_key, $secret );

		return $secret;
	}

	/**
	 * Get the ticket ID from a secret
	 */
	public function get_ticket_from_secret( $secret ) {
		global $wpdb;
		$ticket_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s", $this->ticket_secret_key, $secret ) );

		return ( $ticket_id ) ? (int) $ticket_id : 0;
	}

	/**
	 * Get the special capability given a string
	 */
	public function get_cap( $cap ) {
		return SupportFlow()->extend->permissions->get_cap( $cap );
	}

	/**
	 * Convert multiple comma seperated E-Mail ID's into a array
	 * @return array
	 */
	public function extract_email_ids( $string, & $invalid_email_ids = array() ) {
		$emails = str_replace( ' ', '', $string );
		$emails = explode( ',', $emails );
		$emails = array_filter( $emails, function ( $email ) use ( & $invalid_email_ids ) {
			$is_valid_email_id = sanitize_email( $email ) == $email && '' != $email;
			if ( ! $is_valid_email_id && '' != $email ) {
				$invalid_email_ids[] = $email;
			}

			return $is_valid_email_id;
		} );

		return $emails;
	}
}

/**
 * The main function responsible for returning the one true SupportFlow instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $supportflow = SupportFlow(); ?>
 *
 * @return The one true SupportFlow Instance
 */
function SupportFlow() {
	return SupportFlow::instance();
}

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Five Minutes', 'supportflow' )
	);

	return $schedules;
} );

register_activation_hook( __FILE__, function () {
	wp_schedule_event( time(), 'five_minutes', 'sf_cron_retrieve_email_replies' );
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'sf_cron_retrieve_email_replies' );
} );

add_action( 'plugins_loaded', 'SupportFlow' );
