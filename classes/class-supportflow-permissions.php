<?php
/**
 * Sets up the permissions for SupportFlow
 *
 * @since    0.1
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Permissions {

	/**
	 * Holds all of the capabilities managed by SupportFlow.
	 *
	 * @access    private
	 * @var        array
	 */
	private $_caps = array();

	/**
	 * Array of user roles and associated capabilities.
	 *
	 * @access    private
	 * @var    array
	 */
	private $_role_cap_map = array();

	/**
	 * Construct the object and initiate actions to setup permissions.
	 *
	 * @access    public
	 * @since     0.1
	 * @uses      add_action
	 *
	 * @return    SupportFlow_Permissions
	 */
	public function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'wp_ajax_get_user_permissions', array( $this, 'action_wp_ajax_get_user_permissions' ) );
		add_action( 'wp_ajax_set_user_permission', array( $this, 'action_wp_ajax_set_user_permission' ) );
	}

	public function action_admin_menu() {
		$this->slug = 'sf_permissions';
		add_submenu_page(
			'edit.php?post_type=' . SupportFlow()->post_type,
			__( 'Permissions', 'supportflow' ),
			__( 'Permissions', 'supportflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'permissions_page' )
		);

	}

	public function action_admin_init() {
		add_filter( 'user_has_cap', array( $this, 'limit_user_permissions' ), 10, 3 );
	}

	/*
	 * Return an array containing all the tags and E-Mail accounts user have access to
	 */
	public function get_user_permissions_data( $user_id ) {
		$user_permissions = get_user_meta( $user_id, 'sf_permissions', true );
		if ( ! is_array( $user_permissions ) ) {
			$user_permissions = array( 'tags' => array(), 'email_accounts' => array() );
		}

		return $user_permissions;
	}

	public function permissions_page() {
		$this->insert_script();
		?>
		<div class="wrap">
		<h1><?php _e( 'Permissions', 'supportflow' ) ?></h1>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="change_user"><?php _e( 'User', 'supportflow' ) ?></label></th>
				<td>
					<select name="change_user" id="change_user" class="permission_filters">
						<option data-user-id=0><?php _e( 'All', 'supportflow' ) ?></option>
						<?php
						foreach ( get_users() as $user ) {
							if ( ! $user->has_cap( 'manage_options' ) ) {
								$user_id       = $user->data->ID;
								$user_nicename = esc_html( $user->data->user_nicename );
								$user_email    = esc_html( $user->data->user_email );
								?>

								<option data-user-id="<?php echo esc_attr( $user_id ); ?>">
									<?php echo esc_html( $user_nicename ); ?>
									(<?php echo esc_html( $user_email ); ?>)
								</option>

								<?php
							}
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="change_status"><?php _e( 'Status', 'supportflow' ) ?></label></th>
				<td>
					<select name="change_status" id="change_status" class="permission_filters">
						<option data-status="any"><?php _e( 'Any', 'supportflow' ) ?></option>
						<option data-status="allowed_only"><?php _e( 'Allowed only', 'supportflow' ) ?></option>
						<option data-status="disallowed_only"><?php _e( 'Disallowed only', 'supportflow' ) ?></option>
					</select>
				</td>
			</tr>
		</table>

		<div id="user_permissions_table">
			<?php $this->show_permissions_table( $this->get_user_permissions( 0 ) ) ?>
		</div>
	<?php
	}

	/*
	 * Enqueue JS code required by class
	 */
	public function insert_script() {
		$handle = SupportFlow()->enqueue_script( 'supportflow-permissions', 'permissions.js' );

		wp_localize_script( $handle, 'SFPermissions', array(
			'_get_user_permissions_nonce' => wp_create_nonce( 'get_user_permissions' ),
			'changing_status'             => __( 'Changing status, please wait.', 'supportflow' ),
			'_set_user_permission_nonce'  => wp_create_nonce( 'set_user_permission' ),
			'failed_changing_status'      => __( 'Failed changing state. Old state is reverted.', 'supportflow' ),
			'allowed'                     => __( 'Allowed', 'supportflow' ),
			'not_allowed'                 => __( 'Not allowed', 'supportflow' ),
		) );
	}

	public function action_wp_ajax_get_user_permissions() {
		check_ajax_referer( 'get_user_permissions', '_get_user_permissions_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Access denied.', 'supportflow' ) );
		}

		if (
			! isset( $_POST['user_id'], $_POST['status'] ) ||
			! is_numeric( $_POST['user_id'] ) ||
			! in_array( $_POST['status'], array( 'any', 'allowed_only', 'disallowed_only' ) )
		) {
			exit;
		}

		$user_id = (int) $_POST['user_id'];
		$status  = $_POST['status'];

		if ( 'allowed_only' == $status ) {
			$get_allowed    = true;
			$get_disallowed = false;
		} elseif ( 'disallowed_only' == $status ) {
			$get_allowed    = false;
			$get_disallowed = true;
		} else {
			$get_allowed    = true;
			$get_disallowed = true;
		}
		$this->show_permissions_table( $this->get_user_permissions( $user_id, $get_allowed, $get_disallowed ) );
		exit;
	}

	public function show_permissions_table( $user_permissions ) {
		$message  = __( 'No tag/e-mail accounts found. <b>%s</b> before setting user permissions.<br><b>Note: </b>Administrator accounts automatically have full access in SupportFlow.', 'supportflow' );
		$link     = '<a href="">' . __( 'Please add them', 'supportflow' ) . '</a>';
		$no_items = sprintf( $message, $link );

		$columns = array(
			'status'    => __( 'Status', 'supportflow' ),
			'privilege' => __( 'Privilege', 'supportflow' ),
			'type'      => __( 'Type', 'supportflow' ),
			'user'      => __( 'User', 'supportflow' ),
		);

		$data = array();
		foreach ( $user_permissions as $id => $user_permission ) {
			$identfier = json_encode( array(
				'user_id'        => $user_permission['user_id'],
				'privilege_type' => $user_permission['privilege_type'],
				'privilege_id'   => $user_permission['privilege_id'],
			) );
			$status    = "<input type='checkbox' id='permission_$id' class='toggle_privilege' data-permission-identifier='" . $identfier . "' " . checked( $user_permission['allowed'], true, false ) . '>';
			$status .= " <label for='permission_$id' class='privilege_status'>" . __( $user_permission['allowed'] ? 'Allowed' : 'Not allowed', 'supportflow' ) . "</label>";
			$data[] = array(
				'status'    => $status,
				'privilege' => esc_html( $user_permission['privilege'] ),
				'type'      => $user_permission['type'],
				'user'      => esc_html( $user_permission['user'] ),
			);
		}

		$permissions_table = new SupportFlow_Table( 'sf_user_permissions_table' );
		$permissions_table->set_columns( $columns );
		$permissions_table->set_no_items( $no_items );
		$permissions_table->set_data( $data );
		$permissions_table->display();

	}

	public function get_user_permissions( $user_id, $return_allowed = true, $return_disallowed = true ) {
		$tags             = get_terms( SupportFlow()->tags_tax, 'hide_empty=0' );
		$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		$permissions      = array();
		$user_permissions = array();

		if ( 0 == $user_id ) {
			$users = get_users();
		} else {
			$users = array( get_userdata( $user_id ) );
		}

		foreach ( $users as $user ) {
			if ( ! $user->has_cap( 'manage_options' ) ) {
				$permission             = $this->get_user_permissions_data( $user->ID );
				$permissions[$user->ID] = $permission;
			}
		}

		foreach ( $permissions as $user_id => $permission ) {
			$user_data = get_userdata( $user_id );

			foreach ( $email_accounts as $id => $email_account ) {
				$allowed = in_array( $id, $permission['email_accounts'] );
				if ( ( $allowed && ! $return_allowed ) || ( ! $allowed && ! $return_disallowed ) ) {
					continue;
				}
				$user_permissions[] = array(
					'user_id'        => $user_id,
					'user'           => $user_data->data->user_nicename . ' (' . $user_data->data->user_email . ')',
					'privilege_type' => 'email_accounts',
					'type'           => 'E-Mail Account',
					'privilege_id'   => $id,
					'privilege'      => $email_account['username'],
					'allowed'        => $allowed,
				);
			}
			foreach ( $tags as $tag ) {
				$allowed = in_array( $tag->slug, $permission['tags'] );
				if ( ( $allowed && ! $return_allowed ) || ( ! $allowed && ! $return_disallowed ) ) {
					continue;
				}
				$user_permissions[] = array(
					'user_id'        => $user_id,
					'user'           => $user_data->data->user_nicename . ' (' . $user_data->data->user_email . ')',
					'privilege_type' => 'tags',
					'type'           => 'Tag',
					'privilege_id'   => $tag->slug,
					'privilege'      => $tag->name . ' (' . $tag->slug . ')',
					'allowed'        => $allowed,
				);
			}
		}

		return $user_permissions;
	}

	public function action_wp_ajax_set_user_permission() {
		check_ajax_referer( 'set_user_permission', '_set_user_permission_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Access denied.', 'supportflow' ) );
		}

		if ( ! isset( $_POST['user_id'] )
			|| ! isset( $_POST['privilege_type'] )
			|| ! isset( $_POST['privilege_id'] )
			|| ! isset( $_POST['allowed'] )
			|| ! in_array( $_POST['privilege_type'], array( 'email_accounts', 'tags' ) )
		) {
			exit;
		}

		$user_id        = (int) $_POST['user_id'];
		$privilege_type = $_POST['privilege_type'];
		$privilege_id   = $_POST['privilege_id'];
		$allowed        = 'true' == $_POST['allowed'] ? true : false;

		echo absint( $this->set_user_permission( $user_id, $privilege_type, $privilege_id, $allowed ) );
		exit;
	}


	public function set_user_permission( $user_id, $privilege_type, $privilege_id, $allowed ) {
		$permission = $this->get_user_permissions_data( $user_id );

		if ( true == $allowed ) {
			if ( ! in_array( $privilege_id, $permission[$privilege_type] ) ) {
				$permission[$privilege_type][] = (string) $privilege_id;
				update_user_meta( $user_id, 'sf_permissions', $permission );

				return true;
			}
		} else {
			if ( false !== ( $key = array_search( $privilege_id, $permission[$privilege_type] ) ) ) {
				unset( $permission[$privilege_type][$key] );
				update_user_meta( $user_id, 'sf_permissions', $permission );

				return true;
			}
		}

		return false;
	}

	/*
	 * Limited under-privileged user access to only allowed E-Mail accounts
	 */
	function limit_user_permissions( $allcaps, $cap, $args ) {
		global $pagenow, $post_type;

		// Check if at least one E-Mail account exists before showing ticket creation page
		if (
			'post-new.php' == $pagenow &&
			SupportFlow()->post_type == $post_type
		) {
			$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
			if ( empty( $email_accounts ) ) {
				$link = "edit.php?post_type=$post_type&page=sf_accounts";

				wp_die( sprintf(
					"<a href='%s'>%s</a>",
					esc_html( $link ),
					__( 'Please add at least one E-Mail account before creating a ticket.', 'supportflow' )
				) );
			}
		}

		// Return early in some cases
		$relevant_capability   = in_array( $args[0], array( 'edit_post', 'edit_posts', 'delete_post', 'sf_get_customers' ), true );
		$user_is_admin         = ! empty( $allcaps['manage_options'] ) && true === $allcaps['manage_options'];
		$is_supportflow_ticket = SupportFlow()->post_type === $post_type;
		$is_api_call           = isset( $_REQUEST['action'] ) && SupportFlow()->extend->jsonapi->action === $_REQUEST['action'];

		if ( ! $relevant_capability || $user_is_admin || ( ! $is_supportflow_ticket && ! $is_api_call ) ) {
			return $allcaps;
		}

		// Get all supportflow permissions granted to the current user
		$user_permissions = $this->get_user_permissions_data( $args[1] );

		// This capability is requested if user is viewing/modifying existing ticket
		if ( 'edit_post' == $args[0] ) {

			// Allow if user have access to the tag/E-Mail account used by the ticket
			if ( $this->is_user_allowed_post( $args[1], $args[2] ) ) {
				$allcaps["edit_others_posts"] = true;
				$allcaps["edit_posts"]        = true;

				// Allow if user is creating new ticket with permitted E-Mail account
			} elseif (
				'post.php' == $pagenow &&
				isset ( $_REQUEST['action'], $_REQUEST['post_email_account'] ) &&
				'editpost' == $_REQUEST['action'] &&
				in_array( $_REQUEST['post_email_account'], $user_permissions['email_accounts'] )
			) {
				$allcaps["edit_others_posts"] = true;
				$allcaps["edit_posts"]        = true;

				// Disallow user access to ticket in other cases
			} else {
				$allcaps["edit_others_posts"] = false;
				$allcaps["edit_posts"]        = false;
			}
		}

		// Allow deleting ticket if user have access to the tag/E-Mail account used by the ticket
		if ( 'delete_post' == $args[0] && $this->is_user_allowed_post( $args[1], $args[2] ) ) {
			$allcaps["delete_others_posts"] = true;
		} else {
			$allcaps["delete_others_posts"] = false;
		}

		// This capability is requested if user is creating new ticket or listing all tickets
		if ( 'edit_posts' == $args[0] ) {

			// List All tickets if user have access to atleast one E-Mail account or tag
			if ( 'post-new.php' != $pagenow && ( ! empty( $user_permissions['email_accounts'] ) || ! empty( $user_permissions['tags'] ) ) ) {
				$allcaps["edit_posts"] = true;

				// Allow creating new ticket if user have access to atleast one E-Mail account
			} elseif ( 'post-new.php' == $pagenow && ! empty( $user_permissions['email_accounts'] ) ) {
				$allcaps["edit_posts"] = true;

				// Disallow in other cases
			} else {
				$allcaps["edit_posts"] = false;
			}
		}

		// Allow users with access to at least one e-mail account or tag to get the full customer list
		if ( 'sf_get_customers' == $args[0] ) {
			if ( ! empty ( $user_permissions['email_accounts'] ) || ! empty ( $user_permissions['tags'] ) ) {
				$allcaps['sf_get_customers'] = true;
			}
		}

		return $allcaps;
	}

	/*
	 * Check if user has access to a particular post
	 */
	public function is_user_allowed_post( $user_id, $post_id ) {
		$user_permissions   = $this->get_user_permissions_data( $user_id );
		$post_email_account = get_post_meta( $post_id, 'email_account', true );
		$post_tags          = wp_get_post_terms( $post_id, SupportFlow()->tags_tax );

		if ( in_array( $post_email_account, $user_permissions['email_accounts'] ) ) {
			return true;
		}

		foreach ( $post_tags as $post_tag ) {
			$post_tag_slug = $post_tag->slug;
			if ( in_array( $post_tag_slug, $user_permissions['tags'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Initiates the actions for the permissions class.
	 *
	 * @access    public
	 * @since     0.1
	 * @uses      add_action
	 *
	 * @return    void
	 */
	public function setup_actions() {
		$this->_setup_caps();
		add_action( 'init', array( $this, 'add_capabilities' ) );
	}

	/**
	 * Setup the mapping of roles to capabilities.
	 *
	 * @access    public
	 * @since     0.1
	 * @uses      apply_filters
	 *
	 * @return    void
	 */
	private function _setup_caps() {
		// Setup the default caps for SupportFlow
		$this->_caps = apply_filters(
			'supportflow_caps', array(
				'close_others_tickets'    => 'sf_close_others_tickets',
				'open_others_tickets'     => 'sf_open_others_tickets',
				'reopen_others_tickets'   => 'sf_reopen_others_tickets',
				'reply_on_others_tickets' => 'sf_reply_on_others_tickets',
				'close_tickets'           => 'sf_close_tickets',
				'open_tickets'            => 'sf_open_tickets',
				'reopen_tickets'          => 'sf_reopen_tickets',
				'reply_on_tickets'        => 'sf_reply_on_tickets',
			)
		);

		// Map the default caps onto WordPress roles
		$this->_role_cap_map = apply_filters(
			'supportflow_role_cap_map', array(
				'administrator' => $this->get_caps(), // Apply all caps
				'editor'        => $this->get_caps(), // Apply all caps
				'author'        => array(
					'close_tickets'    => $this->get_cap( 'close_tickets' ),
					'open_tickets'     => $this->get_cap( 'open_tickets' ),
					'reply_on_tickets' => $this->get_cap( 'reply_on_tickets' ),
				),
				'contributor'   => array(
					'reply_on_tickets' => $this->get_cap( 'reply_on_tickets' ),
				),
			)
		);
	}

	/**
	 * Adds the standard SupportFlow capabilities to built-in WordPress roles.
	 *
	 * @access    public
	 * @since     0.1
	 * @uses      get_role, WP_Roles::add_cap
	 *
	 * @return    void
	 */
	public function add_capabilities() {
		$role_cap_map = $this->get_role_cap_map();

		if ( empty( $role_cap_map ) ) {
			return;
		}

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
	 * @access    public
	 * @since     0.1
	 *
	 * @return    array    Array of SF capabilities.
	 */
	public function get_caps() {
		return $this->_caps;
	}

	/**
	 * Get the mapping of roles to capabilities.
	 *
	 * @access    public
	 * @since     0.1
	 *
	 * @return    array    Array roles and caps.
	 */
	public function get_role_cap_map() {
		return $this->_role_cap_map;
	}

	/**
	 * Get the name of an individual capability.
	 *
	 * @access    public
	 * @since     0.1
	 *
	 * @param    string $cap Capability to get.
	 *
	 * @return    string|bool                    Capability name on success; False on failure.
	 */
	public function get_cap( $cap ) {
		$all_caps = $this->get_caps();

		if ( array_key_exists( $cap, $all_caps ) ) {
			return $all_caps[$cap];
		} else {
			return false;
		}
	}
}

SupportFlow()->extend->permissions = new SupportFlow_Permissions();
