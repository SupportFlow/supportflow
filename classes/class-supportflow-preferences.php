<?php
/**
 *
 * @since    0.1
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Preferences {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'wp_ajax_set_email_notfication', array( $this, 'action_wp_ajax_set_email_notfication' ) );
	}

	public function action_admin_menu() {
		$this->slug = 'sf_email_notifications';

		add_submenu_page(
			'edit.php?post_type=' . SupportFlow()->post_type,
			__( 'Preferences', 'supportflow' ),
			__( 'Preferences', 'supportflow' ),
			'read',
			$this->slug,
			array( $this, 'preferences_page' )
		);


	}

	public function insert_scripts() {
		$handle = SupportFlow()->enqueue_script( 'supportflow-preferences', 'preferences.js' );

		wp_localize_script( $handle, 'SFPreferences', array(
			'changing_state'              => __( 'Changing status, please wait.', 'supportflow' ),
			'set_email_notfication_nonce' => wp_create_nonce( 'set_email_notfication' ),
			'failed_changing_state'       => __( 'Failed changing state. Old state is reverted', 'supportflow' ),
			'subscribed'                  => __( 'Subscribed', 'supportflow' ),
			'unsubscribed'                => __( 'Unsubscribed', 'supportflow' ),
		) );
	}

	public function preferences_page() {
		echo '<div class="wrap">';

		$this->insert_scripts();

		$this->user_ticket_signature_page();
		echo '<br />';
		$this->notification_setting_page();

		echo '</div>';
	}

	public function user_ticket_signature_page() {
		if (
			isset( $_POST['action'], $_POST['_wpnonce'], $_POST['update_signature_value'] ) &&
			'update_signature' == $_POST['action'] &&
			wp_verify_nonce( $_POST['_wpnonce'], 'update_signature' )
		) {
			$signature = wp_kses( $_POST['update_signature_value'], array() );
			update_user_meta( get_current_user_id(), 'sf_user_signature', $signature );

			$insert_signature_default = isset( $_REQUEST['insert_signature_default'] ) && 'on' == $_REQUEST['insert_signature_default'];
			update_user_meta( get_current_user_id(), 'sf_insert_signature_default', $insert_signature_default );

			$sign_updated = true;
		} else {
			$signature                = get_user_meta( get_current_user_id(), 'sf_user_signature', true );
			$insert_signature_default = (boolean) get_user_meta( get_current_user_id(), 'sf_insert_signature_default', true );
		}

		?>
		<h1><?php _e( 'My signature', 'supportflow' ) ?></h1>
		<p><?php _e( 'Please enter your signature you wish to add to your ticket reply. To remove signature clear the text box and then update it. Note: It will be automatically appended at the end of your ticket replies', 'supportflow' ) ?></p>
		<?php if ( ! empty( $sign_updated ) ) {
			echo '<h3>' . __( 'Signature updated successfully.', 'supportflow' ) . '</h3>';
		} ?>
		<form id="update_signature" method="POST">
			<?php wp_nonce_field( 'update_signature' ) ?>
			<input type="hidden" name="action" value="update_signature" />
			<textarea id="update_signature" name="update_signature_value" rows="7"><?php esc_html_e( $signature ) ?></textarea>
			<input type="checkbox" name="insert_signature_default" id="insert_signature_default" <?php checked( $insert_signature_default ) ?> />
			<label for="insert_signature_default" title="<?php _e( 'This will toggle on/off the insert signature checkbox in ticket page by default', 'supportflow' ) ?>">
				<?php _e( 'Insert my signature by default', 'supportflow' ) ?>
			</label>
			<?php submit_button( __( 'Update my signature', 'supportflow' ) ); ?>
		</form>
	<?php
	}

	/**
	 * Loads the page to change E-Mail notfication settings
	 */
	public function notification_setting_page() {
		$columns = array(
			'privilege' => __( 'Privilege', 'supportflow' ),
			'type'      => __( 'Type', 'supportflow' ),
			'status'    => __( 'Status', 'supportflow' ),
		);

		$no_items = __( "You don't have <b>permission</b> to any tag/e-mail account, or maybe no tag/e-mail account exists yet. Please ask your administrator to give you permission to an e-mail account or tag.", 'supportflow' );

		$data                  = array();
		$notification_settings = SupportFlow()->extend->email_notifications->get_notifications_settings( get_current_user_id() );
		foreach ( $notification_settings as $id => $notification_setting ) {
			$identfier = json_encode( array(
				'privilege_type' => $notification_setting['privilege_type'],
				'privilege_id'   => $notification_setting['privilege_id'],
			) );
			$status    = "<input type='checkbox' id='permission_$id' class='toggle_privilege' data-email-notfication-identifier='" . $identfier . "' " . checked( $notification_setting['allowed'], true, false ) . '>';
			$status .= " <label for='permission_$id' class='privilege_status'> " . __( $notification_setting['allowed'] ? 'Subscribed' : 'Unsubscribed', 'supportflow' ) . "</label>";
			$data[] = array(
				'status'    => $status,
				'privilege' => esc_html( $notification_setting['privilege'] ),
				'type'      => $notification_setting['type'],
			);
		}

		$email_notifications_table = new SupportFlow_Table( 'sf_email_accounts_table' );
		$email_notifications_table->set_columns( $columns );
		$email_notifications_table->set_no_items( $no_items );
		$email_notifications_table->set_data( $data );

		echo '<h1>' . __( 'E-Mail Notifications', 'supportflow' ) . '</h1>';
		echo '<p>' . __( 'Please check the tags/E-Mail accounts for which you want to receive E-Mail notifications of replies. You will be able to override E-Mail notifications settings for individual tickets.', 'supportflow' ) . '</p>';
		echo '<div id="email_notification_table">';
		$email_notifications_table->display();
		echo '</div>';
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

		echo absint( SupportFlow()->extend->email_notifications->set_notfication_settings( $privilege_type, $privilege_id, $allowed ) );
		exit;
	}


}

SupportFlow()->extend->preferences = new SupportFlow_Preferences();
