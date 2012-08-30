<?php

/**
 * Plugin Name: SupportPress
 * Plugin URI:  http://supportpress.com/
 * Description: Reinventing how you support your customers.
 * Author:      Daniel Bachhuber, Alex Mills, Andrew Spittle, Automattic
 * Author URI:  http://automattic.com/
 * Version:     0.1
 *
 * Text Domain: supportpress
 * Domain Path: /languages/
 */

class SupportPress {

	/** Magic *****************************************************************/

	/**
	 * SupportPress uses many variables, most of which can be filtered to customize
	 * the way that it works. To prevent unauthorized access, these variables
	 * are stored in a private array that is magically updated using PHP 5.2+
	 * methods. This is to prevent third party plugins from tampering with
	 * essential information indirectly, which would cause issues later.
	 *
	 * @see SupportPress::setup_globals()
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
	 * @var SupportPress The one true SupportPress
	 */
	private static $instance;

	/**
	 * Main SupportPress Instance
	 *
	 * SupportPress is fun
	 * Please load it only one time
	 * For this, we thank you
	 *
	 * Insures that only one instance of SupportPress exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since SupportPress 0.1
	 * @staticvar array $instance
	 * @uses SupportPress::setup_globals() Setup the globals needed
	 * @uses SupportPress::includes() Include the required files
	 * @uses SupportPress::setup_actions() Setup the hooks and actions
	 * @see SupportPress()
	 * @return The one true SupportPress
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SupportPress;
			self::$instance->setup_globals();
			self::$instance->includes();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent SupportPress from being loaded more than once.
	 *
	 * @since SupportPress 0.1
	 * @see SupportPress::instance()
	 * @see SupportPress();
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent SupportPress from being cloned
	 *
	 * @since SupportPress 0.1
	 */
	public function __clone() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/**
	 * A dummy magic method to prevent SupportPress from being unserialized
	 *
	 * @since SupportPress 0.1
	 */
	public function __wakeup() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/**
	 * Magic method for checking the existence of a certain custom field
	 *
	 * @since SupportPress 0.1
	 */
	public function __isset( $key ) { return isset( $this->data[$key] ); }

	/**
	 * Magic method for getting SupportPress varibles
	 *
	 * @since SupportPress 0.1
	 */
	public function __get( $key ) { return isset( $this->data[$key] ) ? $this->data[$key] : null; }

	/**
	 * Magic method for setting SupportPress varibles
	 *
	 * @since SupportPress 0.1
	 */
	public function __set( $key, $value ) { $this->data[$key] = $value; }

	/** Private Methods *******************************************************/

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since SupportPress 0.1
	 * @access private
	 * @uses plugin_dir_path() To generate SupportPress plugin path
	 * @uses plugin_dir_url() To generate SupportPress plugin url
	 * @uses apply_filters() Calls various filters
	 */
	private function setup_globals() {

		/** Version ***********************************************************/

		$this->version        = '0.1-alpha'; // SupportPress version

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file           = __FILE__;
		$this->basename       = apply_filters( 'supportpress_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir     = apply_filters( 'supportpress_plugin_dir_path',  plugin_dir_path( $this->file ) );
		$this->plugin_url     = apply_filters( 'supportpress_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		// Languages
		$this->lang_dir       = apply_filters( 'supportpress_lang_dir',         trailingslashit( $this->plugin_dir . 'languages' ) );

		/** Identifiers *******************************************************/

		$this->post_type      = apply_filters( 'supportpress_thread_post_type', 'sp_thread' );

		$this->post_statuses  = apply_filters( 'supportpress_thread_post_statuses', array(
			'new'     => array(
				'label'       => __( 'New', 'supportpress' ),
				'label_count' => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'supportpress' ),
			),
			'open'    => array(
				'label'       => __( 'Open', 'supportpress' ),
				'label_count' => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'supportpress' ),
			),
			'pending' => array(
				'label'       => __( 'Pending', 'supportpress' ),
				'label_count' => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'supportpress' ),
			),
			'closed'  => array(
				'label'       => __( 'Closed', 'supportpress' ),
				'label_count' => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'supportpress' ),
			),
		) );

		$this->post_meta_requester_id    = apply_filters( 'supportpress_post_meta_requester_id',    'supportpress_requester_id'    );
		$this->post_meta_requester_name  = apply_filters( 'supportpress_post_meta_requester_name',  'supportpress_requester_name'  );
		$this->post_meta_requester_email = apply_filters( 'supportpress_post_meta_requester_email', 'supportpress_requester_email' );

		/** Misc **************************************************************/

		$this->extend         = new stdClass(); // Plugins add data here
		$this->extend->ui     = new stdClass(); // For UI-related plugins
		$this->errors         = new WP_Error(); // Feedback
	}

	/**
	 * Include required files
	 *
	 * @since SupportPress 0.1
	 * @access private
	 * @todo Be smarter about conditionally loading code
	 * @uses is_admin() If in WordPress admin, load additional file
	 */
	private function includes() {

		/** Core **************************************************************/

		require_once( $this->plugin_dir . 'classes/class-supportpress-ui-submissionform.php' );

		/** Extensions ********************************************************/

		# TODO: Akismet plugin?

		/** Admin *************************************************************/

		// Quick admin check and load if needed
		if ( is_admin() ) {
			require_once( $this->plugin_dir . 'classes/class-supportpress-admin.php' );
		}
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since SupportPress 0.1
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'action_init_register_post_type' ) );
		add_action( 'init', array( $this, 'action_init_register_post_statuses' ) );

		do_action_ref_array( 'supportpress_after_setup_actions', array( &$this ) );
	}

	/**
	 * Register the custom post type
	 *
	 * @since SupportPress 0.1
	 * @uses register_post_type() To register the post type
	 */
	public function action_init_register_post_type() {
		register_post_type( $this->post_type, array(
			'labels' => array(
				'menu_name'          => __( 'SupportPress',              'supportpress' ),
				'name'               => __( 'Threads',                   'supportpress' ),
				'singular_name'      => __( 'Thread',                    'supportpress' ),
				'all_items'          => __( 'All Threads',               'supportpress' ),
				'add_new'            => __( 'Start New',                 'sp_thread' ),
				'add_new_item'       => __( 'Start New Thread',          'supportpress' ),
				'edit_item'          => __( 'Edit Thread',               'supportpress' ),
				'new_item'           => __( 'New Thread',                'supportpress' ),
				'view_item'          => __( 'View Thread',               'supportpress' ),
				'search_item'        => __( 'Search Threads',            'supportpress' ),
				'not_found'          => __( 'No threads found',          'supportpress' ),
				'not_found_in_trash' => __( 'No threads found in trash', 'supportpress' ),
				),
			'public'        => true,
			'menu_position' => 3,
			'supports'      => array(
				'comments',
			),
		) );
	}

	/**
	 * Register the custom post (thread) statuses
	 *
	 * @since SupportPress 0.1
	 * @uses register_post_status() To register the post statuses
	 * @uses apply_filters() To control what statuses are registered
	 */
	public function action_init_register_post_statuses() {
		foreach ( $this->post_statuses as $post_status => $args ) {
			$args['public'] = true;
			register_post_status( 'sp_' . $post_status, $args );
		}
	}

	/** Helper Functions ******************************************************/

	/**
	 * Validates a user ID
	 */
	public function validate_user( $user_ID_or_username ) {
		if ( is_numeric( $user_ID_or_username ) ) {
			$user = get_user_by( 'ID', $user_ID_or_username );

			if ( ! $user )
				return false;

			return $user->data->ID;
		} else {
			$user = get_user_by( 'login', $user_ID_or_username );

			if ( ! $user )
				return false;

			return $user->data->ID;
		}
	}

	/** Thread Functions ******************************************************/

	/**
	 * Create a new thread
	 */
	public function create_thread( $args ) {
		// The __get() magic doesn't allow key() usage so gotta copy it
		$post_statuses = $this->post_statuses;

		$defaults = array(
			'subject'         => '',
			'message'         => '',
			'requester_id'    => 0,  // If the requester has a WordPress account (ID or username)
			'requester_name'  => '', // Otherwise supply a name
			'requester_email' => '', // And an e-mail address
			'status'          => key( $post_statuses ),
			'assignee'        => 0,  // WordPress user ID or username of ticket assignee/owner
			'tags'            => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$post = array(
			'post_type'    => $this->post_type,
			'post_title'   => $args['subject'],
			'post_content' => esc_html( $args['message'] ),
			'post_author'  => $this->validate_user( $args['assignee'] ),
			'tags_input'   => $args['tags'],
		);

		if ( ! get_post_status_object( $args['status'] ) )
			$args['status'] = $defaults['status'];

		$post['post_status'] = 'sp_' . $args['status'];

		$thread_id = wp_insert_post( $post, true );

		if ( is_wp_error( $thread_id ) )
			return $thread_id;

		if ( ! empty( $args['requester_id'] ) && $requester_id = $this->validate_user( $args['requester_id'] ) ) {
			add_post_meta( $thread_id, $this->post_meta_requester_id, $requester_id );
		} else {
			if ( ! empty( $args['requester_name'] ) )
				add_post_meta( $thread_id, $this->post_meta_requester_name, strip_tags( $args['requester_name'] ) );

			if ( ! empty( $args['requester_email'] ) && is_email( $args['requester_email'] ) )
				add_post_meta( $thread_id, $this->post_meta_requester_email, $args['requester_email'] );
		}

		return $thread_id;
	}
}

/**
 * The main function responsible for returning the one true SupportPress instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $supportpress = SupportPress(); ?>
 *
 * @return The one true SupportPress Instance
 */
function SupportPress() {
	return SupportPress::instance();
}

add_action( 'plugins_loaded', 'SupportPress' );

?>