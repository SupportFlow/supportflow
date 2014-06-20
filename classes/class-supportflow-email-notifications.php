<?php
/**
 * Sets up the E-Mail notifications preference for users
 *
 * @since    0.1
 */

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
		foreach ( $notification_settings as $notification_setting ) {
			$identfier = json_encode( array(
				'privilege_type' => $notification_setting['privilege_type'],
				'privilege_id'   => $notification_setting['privilege_id'],
			) );
			$status    = "<input type='checkbox' class='toggle_privilege' data-email-notfication-identifier='" . $identfier . "' " . checked( $notification_setting['allowed'], true, false ) . '>';
			$status .= " <span class='privilege_status'> " . ( $notification_setting['allowed'] ? 'Allowed' : 'Not allowed' ) . "</span>";
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
			array( $this, 'tag_setting_page' )
		);

	}

	public function tag_setting_page() {
		?>
		<div class="wrap">
		<h2><?php _e( 'E-Mail Notifications', 'supportflow' ) ?></h2><br />

		<div id="email_notification_table">
			<?php
			$email_notifications_table = new SupportFlow_Email_Notifications_Table( $this->get_notifications_settings() );
			$email_notifications_table->prepare_items();
			$email_notifications_table->display();
			?>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function () {

				jQuery(document).on('change', '.toggle_privilege', function () {
					var checkbox = jQuery(this);
					var email_notfication_identifier = checkbox.data('email-notfication-identifier');

					var allowed = checkbox.prop('checked');
					var privilege_type = email_notfication_identifier.privilege_type;
					var privilege_id = email_notfication_identifier.privilege_id;

					jQuery.ajax(ajaxurl, {
						type   : 'post',
						data   : {
							action                      : 'set_email_notfication',
							privilege_type              : privilege_type,
							privilege_id                : privilege_id,
							allowed                     : allowed,
							_set_email_notfication_nonce: '<?php echo wp_create_nonce() ?>',
						},
						success: function (content) {
							if (1 == content) {
								var allowed = checkbox.prop('checked');
								if (true == allowed) {
									checkbox.siblings('.privilege_status').html('Allowed');
								} else {
									checkbox.siblings('.privilege_status').html('Not allowed');
								}
							} else {
								checkbox.prop('checked', !checkbox.prop('checked'));
								alert('Failed changing state. Old state is reverted');
							}
						},
						error  : function () {
							checkbox.prop('checked', !checkbox.prop('checked'));
							alert('Failed changing state. Old state is reverted');
						},
					});
				});
			});
		</script>
	<?php

	}

	public function get_notifications_settings( $user_id = null ) {
		// Get settings for current user if user not specified
		if ( null == $user_id ) {
			$user_id = get_current_user_id();
		}

		// Load all tags and E-Mail accounts
		$tags           = get_terms( 'sf_tags', 'hide_empty=0' );
		$email_accounts = get_option( 'sf_email_accounts' );

		// User is admin (has complete access to all tags, E-Mail account)
		if ( current_user_can( 'manage_options' ) ) {

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
			$user_permissions = get_user_meta( $user_id, 'sf_permissions', true );
			if ( ! is_array( $user_permissions ) || empty( $user_permissions ) || ! is_array( $user_permissions['tags'] ) || ! is_array( $user_permissions['email_accounts'] ) ) {
				$user_permissions = array( 'tags' => array(), 'email_accounts' => array() );
			}
		}


		// Get tag/E-Mail account for which user already receive notifications
		$email_notifications = get_user_meta( $user_id, 'sf_email_notifications', true );
		if ( ! is_array( $email_notifications ) || empty( $email_notifications ) || ! is_array( $email_notifications['tags'] ) || ! is_array( $email_notifications['email_accounts'] ) ) {
			$email_notifications = array( 'tags' => array(), 'email_accounts' => array() );
		}


		// Return exiting notifications settings of user
		$notification_settings = array();
		foreach ( $user_permissions['email_accounts'] as $id ) {
			if ( empty( $email_accounts[$id] ) ) {
				continue;
			}
			$email_account           = $email_accounts[$id];
			$notification_settings[] = array(
				'privilege_type' => 'email_accounts',
				'type'           => 'E-Mail Account',
				'privilege_id'   => $id,
				'privilege'      => $email_account['username'] . ' (' . $email_account['imap_host'] . ')',
				'allowed'        => in_array( $id, $email_notifications['email_accounts'] ),
			);
		}

		foreach ( $user_permissions['tags'] as $slug ) {
			$tag                     = get_term_by( 'slug', $slug, 'sf_tags' );
			$notification_settings[] = array(
				'privilege_type' => 'tags',
				'type'           => 'Tag',
				'privilege_id'   => $slug,
				'privilege'      => $tag->name . ' (' . $tag->slug . ')',
				'allowed'        => in_array( $slug, $email_notifications['tags'] ),
			);
		}

		return $notification_settings;
	}

	public function action_wp_ajax_set_email_notfication() {
		check_ajax_referer( - 1, '_set_email_notfication_nonce' );

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


	public function set_notfication_settings( $privilege_type, $privilege_id, $allowed, $user_id = null ) {
		// Get settings for current user if user not specified
		if ( null == $user_id ) {
			$user_id = get_current_user_id();
		}

		// Get tag/E-Mail account for which user already receive notifications
		$email_notifications = get_user_meta( $user_id, 'sf_email_notifications', true );
		if ( ! is_array( $email_notifications ) || empty( $email_notifications ) || ! is_array( $email_notifications['tags'] ) || ! is_array( $email_notifications['email_accounts'] ) ) {
			$email_notifications = array( 'tags' => array(), 'email_accounts' => array() );
		}

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


}

SupportFlow()->extend->email_notifications = new SupportFlow_Email_Notifications();