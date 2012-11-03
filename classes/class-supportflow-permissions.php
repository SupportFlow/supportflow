<?php
/**
 * Sets up the permissions for SupportFlow
 *
 * @since	0.1
 */
class SupportFlow_Permissions extends SupportFlow {

	/**
	 * Value of the "close threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_close_threads_capability = 'sf_close_threads';

	/**
	 * Value of the "open threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_open_threads_capability = 'sf_open_threads';

	/**
	 * Value of the "reopen threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_reopen_threads_capability = 'sf_reopen_threads';

	/**
	 * Value of the "comment on threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_comment_on_threads_capability = 'sf_comment_on_threads';

	/**
	 * Value of the "close others' threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_close_others_threads_capability = 'sf_close_others_threads';

	/**
	 * Value of the "open others' threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_open_others_threads_capability = 'sf_open_others_threads';

	/**
	 * Value of the "reopen others' threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_reopen_others_threads_capability = 'sf_reopen_others_threads';

	/**
	 * Value of the "comment on others' threads" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_comment_on_others_threads_capability = 'sf_comment_on_others_threads';

	/**
	 * Initiates the actions for the permissions class.
	 *
	 * @since 	0.1
	 * @uses	add_action
	 *
	 * @return	SupportFlow_Permissions
	 */
	public function __construct() {
		// Adds SupportFlow specific capabilities
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Initiates the actions for the permissions class.
	 *
	 * @since 	0.1
	 * @uses	add_action
	 *
	 * @return	void
	 */
	public function setup_actions() {
		add_action( 'init', array( $this, 'add_capabilities' ) );
	}

	/**
	 * Adds the standard SupportFlow capabilities to built-in WordPress roles.
	 *
	 * @since	0.1
	 * @uses	get_role, WP_Roles::add_cap
	 *
	 * @return	void
	 */
	public function add_capabilities() {
		// Modify the Admin role to include close, open, reopen and comment on thread capabilities
		$admin = get_role( 'administrator' );

		if ( null !== $admin ) {
			$admin->add_cap( $this->get_close_threads_capability() );
			$admin->add_cap( $this->get_open_threads_capability() );
			$admin->add_cap( $this->get_reopen_threads_capability() );
			$admin->add_cap( $this->get_comment_on_threads_capability() );

			$admin->add_cap( $this->get_close_others_threads_capability() );
			$admin->add_cap( $this->get_open_others_threads_capability() );
			$admin->add_cap( $this->get_reopen_others_threads_capability() );
			$admin->add_cap( $this->get_comment_on_others_threads_capability() );
		}

		// Modify the Editor role to include close, open, reopen and comment on thread capabilities
		$editor = get_role( 'editor' );

		if ( null !== $editor ) {
			$editor->add_cap( $this->get_close_threads_capability() );
			$editor->add_cap( $this->get_open_threads_capability() );
			$editor->add_cap( $this->get_reopen_threads_capability() );
			$editor->add_cap( $this->get_comment_on_threads_capability() );

			$admin->add_cap( $this->get_close_others_threads_capability() );
			$admin->add_cap( $this->get_open_others_threads_capability() );
			$admin->add_cap( $this->get_reopen_others_threads_capability() );
			$admin->add_cap( $this->get_comment_on_others_threads_capability() );
		}

		// Modify the Author role to include close, open, and comment on thread capabilities
		$author = get_role( 'author' );

		if ( null !== $author ) {
			$author->add_cap( $this->get_close_threads_capability() );
			$author->add_cap( $this->get_open_threads_capability() );
			$author->add_cap( $this->get_comment_on_threads_capability() );
		}

		// Modify the Contributor role to include the comment on thread capability
		$contributor = get_role( 'contributor' );

		if ( null !== $contributor ) {
			$contributor->add_cap( $this->get_comment_on_threads_capability() );
		}
	}

	/**
	 * Get the value of $_close_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_close_threads_capability() {
		return $this->_close_threads_capability;
	}

	/**
	 * Get the value of $_open_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_open_threads_capability() {
		return $this->_open_threads_capability;
	}

	/**
	 * Get the value of $_reopen_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_reopen_threads_capability() {
		return $this->_reopen_threads_capability;
	}

	/**
	 * Get the value of $_comment_on_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_comment_on_threads_capability() {
		return $this->_comment_on_threads_capability;
	}

	/**
	 * Get the value of $_close_others_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_close_others_threads_capability() {
		return $this->_close_others_threads_capability;
	}

	/**
	 * Get the value of $_open_others_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_open_others_threads_capability() {
		return $this->_open_others_threads_capability;
	}

	/**
	 * Get the value of $_reopen_others_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_reopen_others_threads_capability() {
		return $this->_reopen_others_threads_capability;
	}

	/**
	 * Get the value of $_comment_on_others_threads_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_comment_on_others_threads_capability() {
		return $this->_comment_on_others_threads_capability;
	}
}

SupportFlow()->extend->permissions = new SupportFlow_Permissions();