<?php
/**
 * Sets up the E-Mail notifications preference for users
 *
 * @since    0.1
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Table to show existing preference with a option to change them
 */
class SupportFlow_Email_Notifications_Table extends WP_List_Table {
	protected $_data;

	function __construct( $notification_settings ) {
		parent::__construct( array( 'screen' => 'sf_user_email_notifications_table' ) );

		$this->_data = array();
		foreach ( $notification_settings as $id => $notification_setting ) {
			$identfier = json_encode( array(
				'privilege_type' => $notification_setting['privilege_type'],
				'privilege_id'   => $notification_setting['privilege_id'],
			) );
			$status    = "<input type='checkbox' id='permission_$id' class='toggle_privilege' data-email-notfication-identifier='" . $identfier . "' " . checked( $notification_setting['allowed'], true, false ) . '>';
			$status .= " <label for='permission_$id' class='privilege_status'> " . __( $notification_setting['allowed'] ? 'Subscribed' : 'Unsubscribed', 'supportflow' ) . "</label>";
			$this->_data[] = array(
				'status'    => $status,
				'privilege' => esc_html( $notification_setting['privilege'] ),
				'type'      => $notification_setting['type'],
			);
		}
	}

	function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	function get_columns() {
		return array(
			'status'    => __( 'Status', 'supportflow' ),
			'privilege' => __( 'Privilege', 'supportflow' ),
			'type'      => __( 'Type', 'supportflow' ),
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

class SupportFlow_Email_Notifications extends SupportFlow {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'wp_ajax_set_email_notfication', array( $this, 'action_wp_ajax_set_email_notfication' ) );
	}

	public function action_admin_menu() {
		$this->slug = 'sf_email_notifications';

		add_submenu_page(
			'edit.php?post_type=' . SupportFlow()->post_type,
			__( 'E-Mail Notifications', 'supportflow' ),
			__( 'E-Mail Notifications', 'supportflow' ),
			'read',
			$this->slug,
			array( $this, 'notification_setting_page' )
		);

	}

	/**
	 * Return an array containing tag/E-Mail account user opted to receive E-Mail notifications.
	 */
	public function get_email_notifications( $user_id ) {
		$email_notifications = get_user_meta( $user_id, 'sf_email_notifications', true );
		if ( ! is_array( $email_notifications ) || empty( $email_notifications ) || ! is_array( $email_notifications['tags'] ) || ! is_array( $email_notifications['email_accounts'] ) ) {
			$email_notifications = array( 'tags' => array(), 'email_accounts' => array() );
		}

		return $email_notifications;
	}

	/**
	 * Loads the page to change E-Mail notfication settings
	 */
	public function notification_setting_page() {
		?>
		<div class="wrap">
		<h2><?php _e( 'E-Mail Notifications', 'supportflow' ) ?></h2>
		<p><?php _e( 'Please check the tags/E-Mail accounts for which you want to receive E-Mail notifications of replies. You will be able to override E-Mail notifications settings for individual threads.', 'supportflow' ) ?></p>

		<div id="email_notification_table">
			<?php
			$email_notifications_table = new SupportFlow_Email_Notifications_Table( $this->get_notifications_settings( get_current_user_id() ) );
			$email_notifications_table->prepare_items();
			$email_notifications_table->display();
			?>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function () {

				jQuery(document).on('change', '.toggle_privilege', function () {
					var checkbox = jQuery(this);
					var checkbox_label = checkbox.siblings('.privilege_status');
					var email_notfication_identifier = checkbox.data('email-notfication-identifier');

					var allowed = checkbox.prop('checked');
					var privilege_type = email_notfication_identifier.privilege_type;
					var privilege_id = email_notfication_identifier.privilege_id;

					checkbox_label.html('<?php _e( 'Changing status, please wait.', 'supportflow' ) ?>');
					checkbox.prop('disabled', true);

					jQuery.ajax(ajaxurl, {
						type    : 'post',
						data    : {
							action                      : 'set_email_notfication',
							privilege_type              : privilege_type,
							privilege_id                : privilege_id,
							allowed                     : allowed,
							_set_email_notfication_nonce: '<?php echo wp_create_nonce( 'set_email_notfication' ) ?>',
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
								checkbox_label.html('<?php _e( 'Subscribed', 'supportflow' ) ?>');
							} else {
								checkbox_label.html('<?php _e( 'Unsubscribed', 'supportflow' ) ?>');
							}
							checkbox.prop('disabled', false);
						},
					});
				});
			});
		</script>
	<?php

	}

	/**
	 * Get E-Mail notification setting of a user(s) in an array
	 * @param integer $user_id
	 * @param boolean $allowed_only
	 * @return array
	 */
	public function get_notifications_settings( $user_id = null, $allowed_only = false ) {
		// Get settings for current user if user not specified
		if ( 0 == $user_id || ! is_int( $user_id ) ) {
			$users = get_users();
		} else {
			$users = array( get_userdata( $user_id ) );
		}

		// Load all tags and E-Mail accounts
		$tags                  = get_terms( 'sf_tags', 'hide_empty=0' );
		$email_accounts        = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		$notification_settings = array();

		// User is admin (has complete access to all tags, E-Mail account)
		foreach ( $users as $user ) {
			if ( user_can( $user->ID, 'manage_options' ) ) {

				$permitted_tags = $permitted_email_accounts = array();

				foreach ( $tags as $tag ) {
					$permitted_tags[] = $tag->slug;
				}
				foreach ( $email_accounts as $id => $email_account ) {
					$permitted_email_accounts[] = (string) $id;
				}
				$user_permissions = array( 'tags' => $permitted_tags, 'email_accounts' => $permitted_email_accounts );
				unset( $permitted_tags, $permitted_email_accounts );

				// Allow user to show notifications settings of only tags/E-Mail account he is permitted
			} else {
				$user_permissions = SupportFlow()->extend->permissions->get_user_permissions_data( $user->ID );
				foreach ( $user_permissions['tags'] as $id => $tag ) {
					if ( ! in_array( $tag, $tags ) ) {
						unset ( $user_permissions['tags'][ $id ] );
					}
				}
				foreach ( $user_permissions['email_accounts'] as $index => $email_account_id ) {
					if ( ! isset( $email_accounts[ $email_account_id ] ) ) {
						unset ( $user_permissions['email_accounts'][ $index ] );
					}
				}
			}


			// Get tag/E-Mail account for which user already receive notifications
			$email_notifications = $this->get_email_notifications( $user->ID );

			// Return exiting notifications settings of user
			foreach ( $user_permissions['email_accounts'] as $id ) {
				if (
					( empty( $email_accounts[$id] ) ) ||
					( ! in_array( $id, $email_notifications['email_accounts'] ) && $allowed_only )
				) {
					continue;
				}
				$email_account           = $email_accounts[$id];
				$notification_settings[] = array(
					'user_id'        => $user->ID,
					'privilege_type' => 'email_accounts',
					'type'           => 'E-Mail Account',
					'privilege_id'   => $id,
					'privilege'      => $email_account['username'] . ' (' . $email_account['imap_host'] . ')',
					'allowed'        => in_array( $id, $email_notifications['email_accounts'] ),
				);
			}

			foreach ( $user_permissions['tags'] as $slug ) {
				if ( ! in_array( $slug, $email_notifications['tags'] ) && $allowed_only ) {
					continue;
				}
				$tag                     = get_term_by( 'slug', $slug, 'sf_tags' );
				$notification_settings[] = array(
					'user_id'        => $user->ID,
					'privilege_type' => 'tags',
					'type'           => 'Tag',
					'privilege_id'   => $slug,
					'privilege'      => $tag->name . ' (' . $tag->slug . ')',
					'allowed'        => in_array( $slug, $email_notifications['tags'] ),
				);
			}
		}

		return $notification_settings;
	}

	/**
	 * AJAX request to change user E-Mail notification settings
	 */
	public function action_wp_ajax_set_email_notfication() {
		check_ajax_referer( 'set_email_notfication', '_set_email_notfication_nonce' );

		if ( ! isset( $_POST['privilege_type'] )
			|| ! isset( $_POST['privilege_id'] )
			|| ! isset( $_POST['allowed'] )
			|| ! in_array( $_POST['privilege_type'], array( 'email_accounts', 'tags' ) )
		) {
			exit;
		}

		$privilege_type = $_POST['privilege_type'];
		$privilege_id   = $_POST['privilege_id'];
		$allowed        = 'true' == $_POST['allowed'] ? true : false;

		echo $this->set_notfication_settings( $privilege_type, $privilege_id, $allowed );
		exit;
	}

	/**
	 * Change E-Mail notification of a user for particular tag/E-Mail account
	 * @param string $privilege_type
	 * @param int $privilege_id
	 * @param boolean $allowed
	 * @param int $user_id
	 * @return boolean
	 */
	public function set_notfication_settings( $privilege_type, $privilege_id, $allowed, $user_id = null ) {
		// Get settings for current user if user not specified
		if ( ! is_int( $user_id ) || 0 == $user_id ) {
			$user_id = get_current_user_id();
		}

		// Get tag/E-Mail account for which user already receive notifications
		$email_notifications = $this->get_email_notifications( $user_id );

		if ( true == $allowed ) {
			if ( ! in_array( $privilege_id, $email_notifications[$privilege_type] ) ) {
				$email_notifications[$privilege_type][] = (string) $privilege_id;
				update_user_meta( $user_id, 'sf_email_notifications', $email_notifications );

				return true;
			}
		} else {
			if ( false !== ( $key = array_search( $privilege_id, $email_notifications[$privilege_type] ) ) ) {
				unset( $email_notifications[$privilege_type][$key] );
				update_user_meta( $user_id, 'sf_email_notifications', $email_notifications );

				return true;
			}
		}

		return false;
	}

	/**
	 * Get user id of all the users that will receive e-mail notifications for a thread reply
	 *
	 * @param type $thread_id
	 *
	 * @return type array
	 */
	public function get_notified_user( $thread_id ) {
		$tags                         = wp_get_post_terms( $thread_id, 'sf_tags', array( 'fields' => 'slugs' ) );
		$email_account                = get_post_meta( $thread_id, 'email_account', true );
		$email_notifications_override = get_post_meta( $thread_id, 'email_notifications_override', true );

		if ( ! is_array( $email_notifications_override ) ) {
			$email_notifications_override = array();
		}

		$notifications_settings = $this->get_notifications_settings( null, true );

		$allowed_users = array();
		foreach ( $notifications_settings as $notifications_setting ) {
			if ( in_array( $notifications_setting['user_id'], $allowed_users ) ) {
				continue;
			}
			if ( 'tags' == $notifications_setting['privilege_type'] ) {
				$user_notified = in_array( $notifications_setting['privilege_id'], $tags );
			} elseif ( 'email_accounts' == $notifications_setting['privilege_type'] ) {
				$user_notified = $email_account == $notifications_setting['privilege_id'];
			}

			if ( $user_notified ) {
				$allowed_users[] = $notifications_setting['user_id'];
			}
		}

		foreach ( $email_notifications_override as $user_id => $status ) {
			if ( 'disable' == $status && in_array( $user_id, $allowed_users ) ) {
				unset( $allowed_users[array_search( $user_id, $allowed_users )] );
			}
			if ( 'enable' == $status && ! in_array( $user_id, $allowed_users ) ) {
				$allowed_users[] = $user_id;
			}

		}

		return $allowed_users;
	}

}

SupportFlow()->extend->email_notifications = new SupportFlow_Email_Notifications();
