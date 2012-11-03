<?php
/**
 * Sets up the permissions for SupportFlow
 *
 * @since	0.1
 */
class SupportFlow_Permissions extends SupportFlow {

	/**
	 * Holds all of the capabilities managed by SupportFlow.
	 *
	 * @access	private
	 * @var		array
	 */
	private $_caps = array();

	/**
	 * Array of user roles and associated capabilities.
	 *
	 * @access	private
	 * @var 	array
	 */
	private $_role_cap_map = array();

	/**
	 * Construct the object and initiate actions to setup permissions.
	 *
	 * @access	public
	 * @since	0.1
	 * @uses	add_action
	 *
	 * @return	SupportFlow_Permissions
	 */
	public function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Initiates the actions for the permissions class.
	 *
	 * @access	public
	 * @since 	0.1
	 * @uses	add_action
	 *
	 * @return	void
	 */
	public function setup_actions() {
		$this->_setup_caps();
		add_action( 'init', array( $this, 'add_capabilities' ) );
	}

	/**
	 * Setup the mapping of roles to capabilities.
	 *
	 * @access	public
	 * @since	0.1
	 * @uses	apply_filters
	 *
	 * @return	void
	 */
	private function _setup_caps() {
		// Setup the default caps for SupportFlow
		$this->_caps = apply_filters( 'supportflow_caps', array(
			'close_others_threads' 		=> 'sf_close_others_threads',
			'open_others_threads' 		=> 'sf_open_others_threads',
			'reopen_others_threads' 	=> 'sf_reopen_others_threads',
			'comment_on_others_threads' => 'sf_comment_on_others_threads',
			'close_threads' 			=> 'sf_close_threads',
			'open_threads'			 	=> 'sf_open_threads',
			'reopen_threads' 			=> 'sf_reopen_threads',
			'comment_on_threads' 		=> 'sf_comment_on_threads',
		) );

		// Map the default caps onto WordPress roles
		$this->_role_cap_map = apply_filters( 'supportflow_role_cap_map', array(
			'administrator' => $this->get_caps(), // Apply all caps
			'editor' 		=> $this->get_caps(), // Apply all caps
			'author' 		=> array(
				'close_threads' 	 => $this->get_cap( 'close_threads' ),
				'open_threads' 		 => $this->get_cap( 'open_threads' ),
				'comment_on_threads' => $this->get_cap( 'comment_on_threads' ),
			),
			'contributor' 	=> array(
				'comment_on_threads' => $this->get_cap( 'comment_on_threads' ),
			),
		) );
	}

	/**
	 * Adds the standard SupportFlow capabilities to built-in WordPress roles.
	 *
	 * @access	public
	 * @since	0.1
	 * @uses	get_role, WP_Roles::add_cap
	 *
	 * @return	void
	 */
	public function add_capabilities() {
		$role_cap_map = $this->get_role_cap_map();

		if ( empty( $role_cap_map ) )
			return;

		// Loop through roles, adding the associated caps to each role
		foreach ( $role_cap_map as $role => $caps ) {
			// Get the role object
			$role_obj = get_role( $role );

			// Verify that an appropriate object was returned
			if ( null !== $role_obj ) {
				// Add caps to the role
				foreach ( $caps as $index => $cap ) {
					if ( false === $role_obj->has_cap( $cap ) ) {
						$role_obj->add_cap( $cap );
					}
				}
			}
		}
	}

	/**
	 * Get all SF capabilities.
	 *
	 * @access	public
	 * @since	0.1
	 *
	 * @return	array	Array of SF capabilities.
	 */
	public function get_caps() {
		return $this->_caps;
	}

	/**
	 * Get the mapping of roles to capabilities.
	 *
	 * @access	public
	 * @since	0.1
	 *
	 * @return	array	Array roles and caps.
	 */
	public function get_role_cap_map() {
		return $this->_role_cap_map;
	}

	/**
	 * Get the name of an individual capability.
	 *
	 * @access	public
	 * @since	0.1
	 *
	 * @param 	string			$cap		Capability to get.
	 * @return 	string|bool					Capability name on success; False on failure.
	 */
	public function get_cap( $cap ) {
		$all_caps = $this->get_caps();

		if ( array_key_exists( $cap, $all_caps ) )
			return $all_caps[ $cap ];
		else
			return false;
	}
}

SupportFlow()->extend->permissions = new SupportFlow_Permissions();