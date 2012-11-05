<?php

/**
 * Plugin Name: SupportFlow
 * Plugin URI:  
 * Description: Reinventing how you support your customers.
 * Author:      Daniel Bachhuber, Alex Mills, Andrew Spittle
 * Author URI:  
 * Version:     0.1
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
	 * @since SupportFlow 0.1
	 * @staticvar array $instance
	 * @uses SupportFlow::setup_globals() Setup the globals needed
	 * @uses SupportFlow::includes() Include the required files
	 * @uses SupportFlow::setup_actions() Setup the hooks and actions
	 * @see SupportFlow()
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
	 * @see SupportFlow::instance()
	 * @see SupportFlow();
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
	 * @since SupportFlow 0.1
	 * @access private
	 * @uses plugin_dir_path() To generate SupportFlow plugin path
	 * @uses plugin_dir_url() To generate SupportFlow plugin url
	 * @uses apply_filters() Calls various filters
	 */
	private function setup_globals() {

		/** Version ***********************************************************/

		$this->version        = '0.1-alpha'; // SupportFlow version

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file           = __FILE__;
		$this->basename       = apply_filters( 'supportflow_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir     = apply_filters( 'supportflow_plugin_dir_path',  plugin_dir_path( $this->file ) );
		$this->plugin_url     = apply_filters( 'supportflow_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		// Languages
		$this->lang_dir       = apply_filters( 'supportflow_lang_dir',         trailingslashit( $this->plugin_dir . 'languages' ) );

		/** Identifiers *******************************************************/

		$this->post_type        = apply_filters( 'supportflow_thread_post_type',      'sf_thread' );
		$this->respondents_tax  = apply_filters( 'supportflow_respondents_taxonomy',  'sf_respondent' );
		$this->comment_type     = apply_filters( 'supportflow_thread_comment_type',   'sf_comment' );

		$this->email_term_prefix = 'sf-';

		$this->thread_secret_key = 'thread_secret';

		$this->post_statuses  = apply_filters( 'supportflow_thread_post_statuses', array(
			'sf_new'     => array(
				'label'       => __( 'New', 'supportflow' ),
				'label_count' => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'supportflow' ),
			),
			'sf_open'    => array(
				'label'       => __( 'Open', 'supportflow' ),
				'label_count' => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'supportflow' ),
			),
			'sf_pending' => array(
				'label'       => __( 'Pending', 'supportflow' ),
				'label_count' => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'supportflow' ),
			),
			'sf_closed'  => array(
				'label'       => __( 'Closed', 'supportflow' ),
				'label_count' => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'supportflow' ),
			),
		) );

		/** Misc **************************************************************/

		$this->extend         = new stdClass(); // Plugins add data here
		$this->extend->ui     = new stdClass(); // For UI-related plugins
		$this->errors         = new WP_Error(); // Feedback
	}

	/**
	 * Include required files
	 *
	 * @since SupportFlow 0.1
	 * @access private
	 * @todo Be smarter about conditionally loading code
	 * @uses is_admin() If in WordPress admin, load additional file
	 */
	private function includes() {

		/** Core **************************************************************/

		require_once( $this->plugin_dir . 'classes/class-supportflow-json-api.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-attachments.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-emails.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-email-replies.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-permissions.php' );

		/** Extensions ********************************************************/

		require_once( $this->plugin_dir . 'classes/class-supportflow-ui-submissionform.php' );
		require_once( $this->plugin_dir . 'classes/class-supportflow-ui-widget.php' );

		/** Tools *************************************************************/
		if ( defined('WP_CLI') && WP_CLI )
			require_once( $this->plugin_dir . '/classes/class-supportflow-wp-cli.php' );

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
	 * @since SupportFlow 0.1
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'action_init_register_post_type' ) );
		add_action( 'init', array( $this, 'action_init_register_taxonomies' ) );
		add_action( 'init', array( $this, 'action_init_register_post_statuses' ) );

		add_filter( 'wp_insert_post_empty_content', array( $this, 'filter_wp_insert_post_empty_content' ), 10, 2 );

		do_action_ref_array( 'supportflow_after_setup_actions', array( &$this ) );
	}

	/**
	 * Register the custom post type
	 *
	 * @since SupportFlow 0.1
	 * @uses register_post_type() To register the post type
	 */
	public function action_init_register_post_type() {
		register_post_type( $this->post_type, array(
			'labels' => array(
				'menu_name'          => __( 'SupportFlow',               'supportflow' ),
				'name'               => __( 'Threads',                   'supportflow' ),
				'singular_name'      => __( 'Thread',                    'supportflow' ),
				'all_items'          => __( 'All Threads',               'supportflow' ),
				'add_new'            => __( 'New Thread',                'supportflow' ),
				'add_new_item'       => __( 'Start New Thread',          'supportflow' ),
				'edit_item'          => __( 'Discussion',                'supportflow' ),
				'new_item'           => __( 'New Thread',                'supportflow' ),
				'view_item'          => __( 'View Thread',               'supportflow' ),
				'search_items'       => __( 'Search Threads',            'supportflow' ),
				'not_found'          => __( 'No threads found',          'supportflow' ),
				'not_found_in_trash' => __( 'No threads found in trash', 'supportflow' ),
				),
			'public'        => true,
			'menu_position' => 3,
			'supports'      => array(
				'comments',
			),
		) );
	}

	/**
	 * Register our custom taxonomies
	 */
	public function action_init_register_taxonomies() {

		$args = array(
			'label'                  => __( 'Respondents',                'supportflow' ),
			'labels' => array(
				'search_items'       => __( 'Search Respondents',         'supportflow' ),
				'edit_item'          => __( 'Edit Respondent',            'supportflow' ),
				'update_item'        => __( 'Update Respondent',          'supportflow' ),
				'add_new_item'       => __( 'Add New Respondent',         'supportflow' ),
				),
			'public'                 => true,
			'show_in_nav_menus'      => true,
			'rewrite'                => false,
			);
		register_taxonomy( $this->respondents_tax, $this->post_type, $args );
	}

	/**
	 * Register the custom post (thread) statuses
	 *
	 * @since SupportFlow 0.1
	 * @uses register_post_status() To register the post statuses
	 * @uses apply_filters() To control what statuses are registered
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
		}
		// User e-mail address
		elseif ( is_email( $user ) ) {
			$user_object = get_user_by( 'email', $user );
		}
		// User login
		else {
			$user_object = get_user_by( 'login', $user );
		}

		if ( ! $user_object )
			return false;

		return $user_object->data->ID;
	}

	/**
	 * Turns an e-mail address into a term hash, or false on failure
	 */
	public function get_email_hash( $email ) {
		$email = strtolower( trim( $email ) );

		if ( ! is_email( $email ) )
			return false;

		$email = $this->email_term_prefix . md5( $email );

		return $email;
	}

	/** Thread Functions ******************************************************/

	/**
	 * Check whether a post_id is a thread
	 */
	public function is_thread( $post ) {
		return (bool) ( $this->post_type == get_post_type( $post ) );
	}

	/**
	 * Create a new thread
	 */
	public function create_thread( $args ) {
		// The __get() magic doesn't allow key() usage so gotta copy it
		$post_statuses = $this->post_statuses;

		$defaults = array(
			'subject'                  => '',
			'message'                  => '',
			'date'                     => '',
			'respondent_id'            => 0,  // If the requester has a WordPress account (ID or username)
			'respondent_name'          => '', // Otherwise supply a name
			'respondent_email'         => '', // And an e-mail address
			'status'                   => key( $post_statuses ),
			'assignee'                 => -1,  // WordPress user ID or username of ticket assignee/owner
		);

		$args = wp_parse_args( $args, $defaults );

		$thread = array(
			'post_type'                => $this->post_type,
			'post_title'               => $args['subject'],
			'post_author'              => $args['assignee'],
			'post_date'                => $args['date'],
		);

		// Validate the thread status
		if ( ! get_post_status_object( $args['status'] ) )
			$args['status'] = $defaults['status'];
		$thread['post_status'] = $args['status'];

		$thread_id = wp_insert_post( $thread, true );

		if ( is_wp_error( $thread_id ) )
			return $thread_id;

		// Assign the respondent(s)
		if ( !empty( $args['respondent_email'] ) ) {
			$this->update_thread_respondents( $thread_id, $args['respondent_email'] );
		}

		// If there was a message, add it to the thread
		if ( !empty( $args['message'] ) && !empty( $args['respondent_email'] ) ) {
			$comment_details = array(
					'comment_author'             => $args['respondent_name'],
					'comment_author_email'       => $args['respondent_email'],
					'user_id'                    => $args['respondent_id'],
				);
			$this->add_thread_comment( $thread_id, $args['message'], $comment_details );
		}

		return $thread_id;
	}

	/**
	 * Threads must have subjects
	 */
	public function filter_wp_insert_post_empty_content( $maybe_empty, $postarr ) {

		if ( $this->post_type != $postarr['post_type'] )
			return $maybe_empty;

		if ( empty( $postarr['post_title'] ) )
			return true;

		return $maybe_empty;
	}

	/**
	 * @todo This should produce an object with thread details, respondents, and comments
	 */
	public function get_thread( $thread_id ) {

		return get_post( $thread_id );
	}

	/**
	 * @todo This should produce a series of thread objects with respondents, comments, etc.
	 */
	public function get_threads( $args = array() ) {

		$defaults = array(
				'respondent_email'         => '',
				'post_status'              => '',
				'orderby'                  => 'modified',
			);
		$args = array_merge( $defaults, $args );

		$thread_args = array();
		if ( empty( $args['post_status'] ) )
			$thread_args['post_status'] = array_keys( $this->post_statuses );

		if ( !empty( $args['respondent_email'] ) )
			$thread_args['tax_query'] = array(
					array(
						'taxonomy' => $this->respondents_tax,
						'field'    => 'slug',
						'terms'    => $this->get_email_hash( $args['respondent_email'] ),
					),
			);

		$thread_args['post_type'] = $this->post_type;
		$thread_args['orderby'] = $args['orderby'];

		$threads = new WP_Query( $thread_args );
		if ( is_wp_error( $threads ) )
			return $threads;

		return $threads->posts;
	}

	/**
	 * Get a thread's respondents
	 *
	 * @todo support retrieving more fields
	 */
	public function get_thread_respondents( $thread_id, $args = array() ) {

		$default_args = array(
				'fields' => 'all',          // 'all', 'emails'
			);
		$args = array_merge( $default_args, $args );

		$raw_respondents = wp_get_object_terms( $thread_id, $this->respondents_tax );
		if ( is_wp_error( $raw_respondents ) )
			return array();

		$respondents = array();
		if ( 'emails' == $args['fields'] ) {
			foreach( $raw_respondents as $raw_respondent ) {
				$respondents[] = $raw_respondent->name;
			}
		}
		return $respondents;
	}

	/**
	 * Update a thread's respondents
	 *
	 * @param int $thread_id
	 * @param array $respondents An array of email addresses
	 * @param bool $append Whether or not to append these email addresses to any existing addresses
	 */
	public function update_thread_respondents( $thread_id, $respondents, $append = false ) {

		if ( is_string( $respondents ) ) {
			$respondents = array( $respondents );
		}

		$term_ids = array();
		foreach( $respondents as $dirty_respondent ) {
			if ( empty( $dirty_respondent ) )
				continue;
			// Create a term if it doesn't yet exist
			$email = ( is_array( $dirty_respondent ) ) ? $dirty_respondent['user_email'] : $dirty_respondent;
			if ( $term = get_term_by( 'name', $email, $this->respondents_tax ) ) {
				$term_ids[] = (int)$term->term_id;
			} else {
				$term = wp_insert_term( $email, $this->respondents_tax, array( 'slug' => $this->get_email_hash( $email ) ) );
				$term_ids[] = $term['term_id'];
			}
		}
		wp_set_object_terms( $thread_id, $term_ids, $this->respondents_tax, $append );
	}

	/**
	 * Get all of the comments associated with a thread
	 */
	public function get_thread_comments( $thread_id, $args = array() ) {

		$args['post_id'] = $thread_id;
		$thread_comments = SupportFlow()->get_comments( $args );
		return $thread_comments;
	}

	/**
	 * Get all comments based on various arguments
	 */
	public function get_comments( $args ) {

		$default_args = array(
				'status'                 => 'public', // 'public', 'private', 'all'
				'post_id'                => '',
				'search'                 => '',
				'order'                  => 'DESC',   // 'DESC', 'ASC',
			);

		$args = array_merge( $default_args, $args );
		$comment_args = array(
				'search'                 => $args['search'],
				'post_id'                => $args['post_id'],
				'status'                 => $args['status'],
				'comment_type'           => $this->comment_type,
				'order'                  => $args['order'],
			);

		add_filter( 'comments_clauses', array( $this, 'filter_comment_clauses' ), 10, 2 );
		$thread_comments = get_comments( $comment_args );
		remove_filter( 'comments_clauses', array( $this, 'filter_comment_clauses' ) );
		return $thread_comments;
	}

	/**
	 * Convert 'any' comment_approved requests to the proper SQL
	 */
	public function filter_comment_clauses( $clauses, $query ) {
		$old_comment_approved = "( comment_approved = '0' OR comment_approved = '1' )";
		if ( in_array( $query->query_vars['status'], array( 'public', 'private' ) ) )
			$new_comment_approved = "comment_approved = '{$query->query_vars['status']}' ";
		else
			$new_comment_approved = "comment_approved IN ( 'private', 'public' )";
		$clauses['where'] = str_replace( $old_comment_approved, $new_comment_approved, $clauses['where'] );
		return $clauses;
	}

	/**
	 * Get the total number of comments associated with a thread
	 *
	 * @todo support filtering to specific types or commenters
	 */
	public function get_thread_comment_count( $thread_id, $args = array() ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_post_ID =%s AND comment_type = %s", $thread_id, $this->comment_type ) );
		return (int)$count;
	}

	/**
	 * Add a comment to a given thread
	 */
	public function add_thread_comment( $thread_id, $comment_text, $details = array() ) {
		global $wpdb;

		$default_details = array(
				'time'                   => current_time( 'mysql' ),
				'comment_author'         => '',
				'comment_author_email'   => '',
				'user_id'                => '',
				'comment_approved'       => 'public',
			);

		// @todo This actually probably shouldn't default to current user, so
		// we don't have to mandate the arguments be assigned each time
		if ( $user = wp_get_current_user() ) {
			$default_details['comment_author'] = $user->display_name;
			$default_details['comment_author_email'] = $user->user_email;
			$default_details['user_id'] = $user->ID;
		}

		$details = array_merge( $default_details, $details );

		// If there are attachments, store them for later
		if ( isset( $details['attachment_ids'] ) ) {
			$attachment_ids = $details['attachment_ids'];
			unset( $details['attachment_ids'] );
		} else {
			$attachment_ids = false;
		}

		$comment = array(
				'comment_content'        => esc_sql( $comment_text ),
				'comment_post_ID'        => (int)$thread_id,
				'comment_date'           => esc_sql( $details['time'] ),
				'comment_approved'       => esc_sql( $details['comment_approved'] ),
				'comment_type'           => esc_sql( $this->comment_type ),
				'comment_author'         => esc_sql( $details['comment_author'] ),
				'comment_author_email'   => esc_sql( $details['comment_author_email'] ),
				'user_id'                => (int)$details['user_id'],
			);
		$comment = apply_filters( 'supportflow_pre_insert_thread_comment', $comment );
		$comment_id = wp_insert_comment( $comment );

		// If there are attachment IDs store them as meta
		if ( !empty( $attachment_ids ) )
			add_comment_meta( $comment_id, 'attachment_ids', $attachment_ids, true );

		// Adding a thread comment updates the post modified time for the thread
		$query = $wpdb->update( $wpdb->posts, array( 'post_modified' => current_time( 'mysql') ), array( 'ID' => $thread_id ) );
		clean_post_cache( $thread_id );

		do_action( 'supportflow_thread_comment_added', $comment_id );

		return $comment_id;
	}

	/**
	 * Generate the secure key for replying to this thread
	 *
	 * @todo Rather than storing this in the database, it should be generated on the fly
	 * with an encryption algorithim
	 */
	public function get_secret_for_thread( $thread_id ) {

		if ( $secret = get_post_meta( $thread_id, $this->thread_secret_key, true ) )
			return $secret;

		$secret = wp_generate_password( 8, false );
		update_post_meta( $thread_id, $this->thread_secret_key, $secret );
		return $secret;
	}

	/**
	 * Get the thread ID from a secret
	 */
	public function get_thread_from_secret( $secret ) {
		global $wpdb;
		$thread_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s", $this->thread_secret_key, $secret ) );
		return ( $thread_id ) ? (int)$thread_id : 0;
	}

	/**
	 * Get the special capability given a string
	 */
	public function get_cap( $cap ) {
		return SupportFlow()->extend->permissions->get_cap( $cap );
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

add_action( 'plugins_loaded', 'SupportFlow' );