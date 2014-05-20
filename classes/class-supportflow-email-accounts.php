<?php
/**
 *
 */

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SupportFlow_Email_Accounts_Table extends WP_List_Table {
	protected $_data;

	function __construct( $accounts ){
		parent::__construct();
		$data = array();

		foreach( $accounts as $k=>$y) {
			$email_account = unserialize( $k );
			$imap_host     = $email_account['host'];
			$imap_port     = $email_account['port'];
			$imap_user     = $email_account['user'];
			$data[]  = array(
				'imap_host'   => $imap_host,
				'imap_port'   => $imap_port,
				'imap_user'   => $imap_user,
				'imap_action' => "<a href='#' data-key='$k' id='delete_email_account'>Delete</a>",
			);
		}
		$this->_data = $data;
	}

	function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	function get_columns(){
		return array(
		    'imap_host'   => __( 'Host Name' , 'supportflow' ),
		    'imap_port'   => __( 'Port No'   , 'supportflow' ),
		    'imap_user'   => __( 'User Name' , 'supportflow' ),
		    'imap_action' => __( 'Action'    , 'supportflow' )
		);
	}

	function prepare_items() {
		$columns  = $this->get_columns();
		$data     = $this->_data;
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;;
	}
}

class SupportFlow_Email_Accounts extends SupportFlow {

	function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
	}

	function action_admin_menu() {
		$post_type = SupportFlow()->post_type;

		// Create a menu (SupportFlow->E-Mail Accounts)
		add_submenu_page(
			"edit.php?post_type=$post_type",
			__( 'SupportFlow E-Mail Accounts', 'supportflow' ),
			__( 'E-Mail Accounts', 'supportflow' ),
			'manage_options',
			'sf_accounts',
			array( $this, 'settings_page' )
		);

	}

	function settings_page() {

		$email_accounts = get_option( 'sf_email_accounts' );
		if ( ! is_array( $email_accounts ) ) {
			$email_accounts = array();
		}

		echo '<div class="wrap">
			<h2>' . __( 'E-Mail Accounts', 'supportflow' ) . '</h2>';

		if ( isset( $_POST['action'] ) ) {
			$action = $_POST['action'];
		}

		// Create new account
		if ( 'add' == $action && isset( $_POST['imap_host'], $_POST['imap_user'], $_POST['imap_pass'], $_POST['imap_port'] ) ) {
			$_POST['imap_port'] = is_numeric( $_POST['imap_port'] ) ? (int) $_POST['imap_port'] : 993;
			$account_key = serialize( array( 'host' => $_POST['imap_host'], 'user' => $_POST['imap_user'], 'port' => $_POST['imap_port'] ) );

			if ( isset( $email_accounts[$account_key] ) ) {
				echo '<h3>' . __( 'There is an account already exists with same host name and user name. Please create a new account with different settings.', 'supportflow' ) . '</h3>';
			} elseif  ( ! @imap_open( "{{$_POST['imap_host']}:{$_POST['imap_port']}/ssl}", $_POST['imap_user'], $_POST['imap_pass'] ) ) {
				echo '<h3>' . __( 'Unable to login to the IMAP server. Please check your IMAP details.', 'supportflow' ) . '</h3>';
			} else {
				$email_accounts[$account_key] = $_POST['imap_pass'];
				update_option( 'sf_email_accounts', $email_accounts );
				echo '<h3>' . __( 'Account added successfully', 'supportflow' ) . '</h3>';
			}
		}

		// Delete existing account
		if ( 'delete' == $action && isset( $_POST['key'] ) ) {
			$account_key = stripcslashes( $_POST['key'] );

			if ( ! isset( $email_accounts[$account_key] ) ) {
				echo '<h3>' . __( 'Failed deleting account. There is no such account exists. Please try again.', 'supportflow' ) . '</h3>';
			} else {
				unset( $email_accounts[$account_key] );
				update_option( 'sf_email_accounts', $email_accounts) ;
				echo '<h3>' . __( 'Account Deleted successfully', 'supportflow' ) . '</h3>';
			}
		}


		$email_accounts_table = new SupportFlow_Email_Accounts_Table($email_accounts);
		$email_accounts_table->prepare_items();
		$email_accounts_table->display();

		?>
		<h3><?= __( 'Add New Account', 'supportflow' ) ?></h3>
		<?= __( 'Please enter IMAP Server Settings', 'supportflow' ) ?><br />
		<form method="POST" action="edit.php?post_type=sf_thread&page=sf_accounts">
			<input type="hidden" name="action" value="add" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?= __( 'Host Name:', 'supportflow' ) ?></th>
					<td><input type="text" name="imap_host" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?= __( 'Port No: (default 993)', 'supportflow' ) ?></th>
					<td><input type="text" name="imap_port" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?= __( 'Username:', 'supportflow' ) ?></th>
					<td><input type="text" name="imap_user"/></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?= __( 'Password:', 'supportflow' ) ?></th>
					<td><input type="text" name="imap_pass" /></td>
				</tr>
			</table>
		<?php submit_button( __( 'Add New Server', 'supportflow' ) ); ?>
		</form>

		<script>
			jQuery('#delete_email_account' ).click(function(e) {
				e.preventDefault();
				if ( ! confirm('<?= __( 'Are you sure want to delete this account.', 'supportflow' ) ?>' ) ) {
					return;
				}

				var account_key  = jQuery(this).data('key' );
				var form = "<form method='POST' action='edit.php?post_type=sf_thread&page=sf_accounts'>" +
					"<input type='hidden' name='action' value='delete' />" +
					"<input type='hidden' name='key' value=" + account_key + " />" +
				"</form>";

				jQuery(form).submit();
			});
		</script>
		<?php
	}


}

SupportFlow()->extend->settings = new SupportFlow_Email_Accounts();