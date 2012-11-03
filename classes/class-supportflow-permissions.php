<?php
/**
 * Sets up the permissions for SupportFlow
 *
 * @since	0.1
 */
class SupportFlow_Permissions extends SupportFlow {

	/**
	 * Value of the "close thread" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_close_thread_capability = 'sf_close_thread';

	/**
	 * Value of the "open thread" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_open_thread_capability = 'sf_open_thread';

	/**
	 * Value of the "reopen thread" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_reopen_thread_capability = 'sf_reopen_thread';

	/**
	 * Value of the "comment on thread" capability.
	 *
	 * @since	0.1
	 * @var		string
	 */
	private $_comment_on_thread_capability = 'sf_comment_on_thread';

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
		$admin->add_cap( $this->get_close_thread_capability() );
		$admin->add_cap( $this->get_open_thread_capability() );
		$admin->add_cap( $this->get_reopen_thread_capability() );
		$admin->add_cap( $this->get_comment_on_thread_capability() );

		// Modify the Editor role to include close, open, reopen and comment on thread capabilities
		$editor = get_role( 'editor' );
		$editor->add_cap( $this->get_close_thread_capability() );
		$editor->add_cap( $this->get_open_thread_capability() );
		$editor->add_cap( $this->get_reopen_thread_capability() );
		$editor->add_cap( $this->get_comment_on_thread_capability() );

		// Modify the Author role to include close, open, and comment on thread capabilities
		$author = get_role( 'author' );
		$author->add_cap( $this->get_close_thread_capability() );
		$author->add_cap( $this->get_open_thread_capability() );
		$author->add_cap( $this->get_comment_on_thread_capability() );

		// Modify the Contributor role to include the comment on thread capability
		$contributor = get_role( 'contributor' );
		$contributor->add_cap( $this->get_comment_on_thread_capability() );
	}

	/**
	 * Get the value of $_close_thread_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_close_thread_capability() {
		return $this->_close_thread_capability;
	}

	/**
	 * Get the value of $_open_thread_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_open_thread_capability() {
		return $this->_open_thread_capability;
	}

	/**
	 * Get the value of $_reopen_thread_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_reopen_thread_capability() {
		return $this->_reopen_thread_capability;
	}

	/**
	 * Get the value of $_comment_on_thread_capability.
	 *
	 * @since	0.1
	 *
	 * @return	string
	 */
	public function get_comment_on_thread_capability() {
		return $this->_comment_on_thread_capability;
	}
}

SupportFlow()->extend->permissions = new SupportFlow_Permissions();