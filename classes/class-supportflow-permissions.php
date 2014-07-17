<?php
/**
 * Sets up the permissions for SupportFlow
 *
 * @since    0.1
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Table to show existing E-Mail accounts with a option to remove existing
 */
class SupportFlow_User_Permissions_Table extends WP_List_Table {
	protected $_data;

	function __construct( $user_permissions ) {
		parent::__construct( array( 'screen' => 'sf_user_permissions_table' ) );

		$this->_data = array();
		foreach ( $user_permissions as $id => $user_permission ) {
			$identfier = json_encode( array(
				'user_id'        => $user_permission['user_id'],
				'privilege_type' => $user_permission['privilege_type'],
				'privilege_id'   => $user_permission['privilege_id'],
			) );
			$status    = "<input type='checkbox' id='permission_$id' class='toggle_privilege' data-permission-identifier='" . $identfier . "' " . checked( $user_permission['allowed'], true, false ) . '>';
			$status .= " <label for='permission_$id' class='privilege_status'>" . __( $user_permission['allowed'] ? 'Allowed' : 'Not allowed', 'supportflow' ) . "</label>";
			$this->_data[] = array(
				'status'    => $status,
				'privilege' => esc_html( $user_permission['privilege'] ),
				'type'      => $user_permission['type'],
				'user'      => esc_html( $user_permission['user'] ),
			);
		}
	}

	function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	function no_items() {
		$message = __('No tag/e-mail accounts found. <b>%s</b> before setting user permissions.<br><b>Note: </b>Administrator accounts automatically have full access in SupportFlow.', 'supportflow');
		$link = '<a href="">' . __('Please add them', 'supportflow') . '</a>';
		printf($message, $link);
	}

	function get_columns() {
		return array(
			'status'    => __( 'Status', 'supportflow' ),
			'privilege' => __( 'Privilege', 'supportflow' ),
			'type'      => __( 'Type', 'supportflow' ),
			'user'      => __( 'User', 'supportflow' ),
		);
	}

	function prepare_items() {
		$columns               = $this->get_columns();
		$data                  = $this->_data;
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}
}

class SupportFlow_Permissions extends SupportFlow {

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
		?>
		<div class="wrap">
		<h2><?php _e( 'Permissions', 'supportflow' ) ?></h2>

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
								echo "<option data-user-id=$user_id>$user_nicename ($user_email)</option>";
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
			<?php
			$user_permissions_table = new SupportFlow_User_Permissions_Table( $this->get_user_permissions( 0 ) );
			$user_permissions_table->prepare_items();
			$user_permissions_table->display();
			?>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function () {
				jQuery('.permission_filters').change(function () {
					var user_id = jQuery('#change_user option:selected').data('user-id');
					var status = jQuery('#change_status option:selected').data('status');
					jQuery.ajax(ajaxurl, {
						type   : 'post',
						data   : {
							action                     : 'get_user_permissions',
							user_id                    : user_id,
							status                     : status,
							_get_user_permissions_nonce: '<?php echo wp_create_nonce( 'get_user_permissions' ) ?>',
						},
						success: function (content) {
							jQuery('#user_permissions_table').html(content);
						},
					});
				});

				jQuery(document).on('change', '.toggle_privilege', function () {
					var checkbox = jQuery(this);
					var checkbox_label = checkbox.siblings('.privilege_status');
					var permission_identifier = checkbox.data('permission-identifier');

					var allowed = checkbox.prop('checked');
					var user_id = permission_identifier.user_id;
					var privilege_type = permission_identifier.privilege_type;
					var privilege_id = permission_identifier.privilege_id;

					checkbox_label.html('<?php _e( 'Changing status, please wait.', 'supportflow' ) ?>');
					checkbox.prop('disabled', true);

					jQuery.ajax(ajaxurl, {
						type    : 'post',
						data    : {
							action                    : 'set_user_permission',
							user_id                   : user_id,
							privilege_type            : privilege_type,
							privilege_id              : privilege_id,
							allowed                   : allowed,
							_set_user_permission_nonce: '<?php echo wp_create_nonce( 'set_user_permission' ) ?>',
						},
						success : function (content) {
							if (1 != content) {
								checkbox.prop('checked', !checkbox.prop('checked'));
								alert('<?php _e( 'Failed changing state. Old state is reverted', 'supportflow' ) ?>');
							}
						},
						error   : function () {
							checkbox.prop('checked', !checkbox.prop('checked'));
							alert('<?php _e( 'Failed changing state. Old state is reverted', 'supportflow' ) ?>');
						},
						complete: function () {
							var allowed = checkbox.prop('checked');
							if (true == allowed) {
								checkbox_label.html('<?php _e( 'Allowed', 'supportflow' ) ?>');
							} else {
								checkbox_label.html('<?php _e( 'Not Allowed', 'supportflow' ) ?>');
							}
							checkbox.prop('disabled', false);
						},
					});
				});
			});
		</script>
	<?php

	}

	public function action_wp_ajax_get_user_permissions() {
		check_ajax_referer( 'get_user_permissions', '_get_user_permissions_nonce' );

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

		$user_permissions_table = new SupportFlow_User_Permissions_Table( $this->get_user_permissions( $user_id, $get_allowed, $get_disallowed ) );
		$user_permissions_table->prepare_items();
		$user_permissions_table->display();

		exit;
	}

	public function get_user_permissions( $user_id, $return_allowed = true, $return_disallowed = true ) {
		$tags             = get_terms( 'sf_tags', 'hide_empty=0' );
		$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts( true);
		$permissions      = array();
		$user_permissions = array();

		if ( 0 == $user_id ) {
			$users = get_users();
		} else {
			$users = array( get_userdata( $user_id ) );
		}

		foreach ( $users as $user ) {
			if ( ! $user->has_cap( 'manage_options' ) ) {
				$permission = $this->get_user_permissions_data( $user->ID );
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

		echo $this->set_user_permission( $user_id, $privilege_type, $privilege_id, $allowed );
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
	 * Limited under-privileged user acces to only allowed E-Mail accounts
	 */
	function limit_user_permissions( $allcaps, $cap, $args ) {
		global $pagenow, $post_type;

		// Check if atleast one E-Mail account exists before showing thread creation page
		if (
			'post-new.php' == $pagenow &&
			SupportFlow()->post_type == $post_type
		) {
			$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
			if ( empty( $email_accounts ) ) {
				$link = "edit.php?post_type=$post_type&page=sf_accounts";
				$msg  = 'Please add atleast one E-Mail account before creating a thread.';

				wp_die( "<a href='$link'>" . __( $msg, 'supportflow' ) . "</a>" );
			}
		}

		if (
			// Return if required capability is not one of them
			! in_array( $args[0], array( 'edit_post', 'edit_posts', 'delete_post' ) ) ||

			// Return if user is admin
			( ! empty( $allcaps['manage_options'] ) && true == $allcaps['manage_options'] ) ||

			// Return if posts are not supportflow threads
			SupportFlow()->post_type != $post_type
		) {
			return $allcaps;
		}

		// Get all supportflow permissions granted to the current user
		$user_permissions = $this->get_user_permissions_data( $args[1] );

		// This capability is requested if user is viewing/modifying existing ticket
		if ( 'edit_post' == $args[0] ) {

			// Allow if user have access to the tag/E-Mail account used by the thread
			if ( $this->is_user_allowed_post( $args[1], $args[2] ) ) {
				$allcaps["edit_others_posts"] = true;
				$allcaps["edit_posts"]        = true;

			// Allow if user is creating new thread with permitted E-Mail account
			} elseif (
				'post.php' == $pagenow &&
				isset ( $_REQUEST['action'], $_REQUEST['post_email_account'] ) &&
				'editpost' == $_REQUEST['action'] &&
				in_array( $_REQUEST['post_email_account'], $user_permissions['email_accounts'] )
			) {
				$allcaps["edit_others_posts"] = true;
				$allcaps["edit_posts"]        = true;

			// Disallow user access to thread in other cases
			} else {
				$allcaps["edit_others_posts"] = false;
				$allcaps["edit_posts"]        = false;
			}
		}

		// Allow deleting thread if user have access to the tag/E-Mail account used by the thread
		if ( 'delete_post' == $args[0] && $this->is_user_allowed_post( $args[1], $args[2] ) ) {
			$allcaps["delete_others_posts"] = true;
		} else {
			$allcaps["delete_others_posts"] = false;
		}

		// This capability is requested if user is creating new thread or listing all threads
		if ( 'edit_posts' == $args[0] ) {

			// List All threads if user have access to atleast one E-Mail account or tag
			if ( 'post-new.php' != $pagenow && ( ! empty( $user_permissions['email_accounts'] ) || ! empty( $user_permissions['tags'] ) ) ) {
				$allcaps["edit_posts"] = true;

			// Allow creating new thread if user have access to atleast one E-Mail account
			} elseif ( 'post-new.php' == $pagenow && ! empty( $user_permissions['email_accounts'] ) ) {
				$allcaps["edit_posts"] = true;

			// Disallow in other cases
			} else {
				$allcaps["edit_posts"] = false;
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
		$post_tags          = wp_get_post_terms( $post_id, 'sf_tags' );

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
				'close_others_threads'    => 'sf_close_others_threads',
				'open_others_threads'     => 'sf_open_others_threads',
				'reopen_others_threads'   => 'sf_reopen_others_threads',
				'reply_on_others_threads' => 'sf_reply_on_others_threads',
				'close_threads'           => 'sf_close_threads',
				'open_threads'            => 'sf_open_threads',
				'reopen_threads'          => 'sf_reopen_threads',
				'reply_on_threads'        => 'sf_reply_on_threads',
			)
		);

		// Map the default caps onto WordPress roles
		$this->_role_cap_map = apply_filters(
			'supportflow_role_cap_map', array(
				'administrator' => $this->get_caps(), // Apply all caps
				'editor'        => $this->get_caps(), // Apply all caps
				'author'        => array(
					'close_threads'    => $this->get_cap( 'close_threads' ),
					'open_threads'     => $this->get_cap( 'open_threads' ),
					'reply_on_threads' => $this->get_cap( 'reply_on_threads' ),
				),
				'contributor'   => array(
					'reply_on_threads' => $this->get_cap( 'reply_on_threads' ),
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
