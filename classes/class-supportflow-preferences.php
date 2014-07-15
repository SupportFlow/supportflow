<?php
/**
 *
 * @since    0.1
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Preferences extends SupportFlow {

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

	public function enqueue_scripts() {
		wp_enqueue_script('supportflow-preferences', SupportFlow()->plugin_url . 'js/preferences.js', array( 'jquery' ) );
		wp_localize_script('supportflow-preferences', 'SFPreferences', array (
		    'changing_state' => __( 'Changing status, please wait.', 'supportflow' ),
		    'set_email_notfication_nonce' => wp_create_nonce( 'set_email_notfication' ),
		    'failed_changing_state' => __( 'Failed changing state. Old state is reverted', 'supportflow' ),
		    'subscribed' => __( 'Subscribed', 'supportflow' ),
		    'unsubscribed' => __( 'Unsubscribed', 'supportflow' ),
		));
	}

	public function preferences_page() {
		$this->enqueue_scripts();
		?>

		<div class="wrap">
			<?php $this->notification_setting_page() ?>
		</div>

		<?php
	}

	/**
	 * Loads the page to change E-Mail notfication settings
	 */
	public function notification_setting_page() {
		?>
		<h2><?php _e( 'E-Mail Notifications', 'supportflow' ) ?></h2>
		<p><?php _e( 'Please check the tags/E-Mail accounts for which you want to receive E-Mail notifications of replies. You will be able to override E-Mail notifications settings for individual threads.', 'supportflow' ) ?></p>

		<div id="email_notification_table">
			<?php
			$email_notifications_table = new SupportFlow_Email_Notifications_Table( SupportFlow()->extend->email_notifications->get_notifications_settings( get_current_user_id() ) );
			$email_notifications_table->prepare_items();
			$email_notifications_table->display();
			?>
		</div>
	<?php

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

		echo SupportFlow()->extend->email_notifications->set_notfication_settings( $privilege_type, $privilege_id, $allowed );
		exit;
	}


}

SupportFlow()->extend->preferences = new SupportFlow_Preferences();


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

	function no_items() {
		_e( "You don't have <b>permission</b> to any tag/e-mail account, or maybe no tag/e-mail account exists yet. Please ask your administrator to give you permission to an e-mail account or tag.", 'supportflow' );
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