<?php
/**
 *
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SupportFlow_Email_Accounts_Table extends WP_List_Table {
	protected $_data;

	function __construct( $accounts ) {
		parent::__construct();

		$data = & $this->_data;
		$data = array();

		foreach ( $accounts as $account_id => $account ) {
			$data[] = array(
				'username'  => $account['username'],
				'imap_host' => $account['imap_host'],
				'imap_port' => $account['imap_port'],
				'smtp_host' => $account['smtp_host'],
				'smtp_port' => $account['smtp_port'],
				'action'    => "<a href='#' data-account-id='$account_id' id='delete_email_account'>Delete</a>",
			);
		}
	}

	function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	function get_columns() {
		return array(
			'username'  => __( 'Username', 'supportflow' ),
			'imap_host' => __( 'IMAP Host', 'supportflow' ),
			'imap_port' => __( 'IMAP Port', 'supportflow' ),
			'smtp_host' => __( 'SMTP Host', 'supportflow' ),
			'smtp_port' => __( 'SMTP Port', 'supportflow' ),
			'action'    => __( 'Action', 'supportflow' ),
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

class SupportFlow_Email_Accounts extends SupportFlow {
	protected $_email_accounts;

	const SUCCESS                  = 0;
	const ACCOUNT_EXISTS           = 1;
	const NO_ACCOUNT_EXISTS        = 2;
	const IMAP_HOST_NOT_FOUND      = 3;
	const IMAP_INVALID_CRIDENTIALS = 4;
	const IMAP_TIME_OUT            = 5;
	const IMAP_CONNECTION_FAILED   = 6;


	function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		// Get existing E-Mail accounts from database
		$email_accounts = & $this->_email_accounts;
		$email_accounts = get_option( 'sf_email_accounts' );
		if ( ! is_array( $email_accounts ) ) {
			$email_accounts = array();
		}
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

	/*
	 * Loads the setting page to add/remove E-Mail accounts
	 */
	function settings_page() {
		$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

		echo '<div class="wrap">
			<h2>' . __( 'E-Mail Accounts', 'supportflow' ) . '</h2>';

		// Create new account
		if ( 'add' == $action && isset( $_POST['imap_host'], $_POST['imap_port'], $_POST['smtp_host'], $_POST['smtp_port'], $_POST['username'], $_POST['password'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'add_email_account' ) ) {
			$res = $this->add_email_account( $_POST['imap_host'], $_POST['imap_port'], $_POST['smtp_host'], $_POST['smtp_port'], $_POST['username'], $_POST['password'] );
			switch ( $res ) {

				case self::SUCCESS:
					echo '<h3>' . __( 'Account added successfully', 'supportflow' ) . '</h3>';
					unset( $_POST['imap_host'], $_POST['imap_port'], $_POST['smtp_host'], $_POST['smtp_port'], $_POST['username'], $_POST['password'] );
					break;
				case self::ACCOUNT_EXISTS:
					echo '<h3>' . __( 'There is an account already exists with same host name and user name. Please create a new account with different settings.', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_HOST_NOT_FOUND:
					echo '<h3>' . __( 'Unable to connect to ' . esc_html( $_POST['imap_host'] ) . '. Please check your IMAP host name.', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_TIME_OUT:
					echo '<h3>' . __( 'Time out while connecting to ' . esc_html( $_POST['imap_host'] ) . '. Please check your IMAP port number and host name.', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_INVALID_CRIDENTIALS:
					echo '<h3>' . __( 'Unable to connect with given username/password combination. Please re-check your username and password', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_CONNECTION_FAILED:
					echo '<h3>' . __( 'Unknown error while connecting to the IMAP server. Please check your IMAP settings and try again.', 'supportflow' ) . '</h3>';
					break;
			}
		}

		// Delete existing account
		if ( 'delete' == $action && isset( $_POST['account_id'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'delete_email_account' ) ) {
			$res = $this->remove_email_account( $_POST['account_id'] );
			switch ( $res ) {

				case self::SUCCESS:
					echo '<h3>' . __( 'Account Deleted successfully', 'supportflow' ) . '</h3>';
					break;
				case self::NO_ACCOUNT_EXISTS:
					echo '<h3>' . __( 'Failed deleting account. There is no such account exists. Please try again.', 'supportflow' ) . '</h3>';
					break;
			}
		}

		$email_accounts_table = new SupportFlow_Email_Accounts_Table( $this->get_email_accounts() );
		$email_accounts_table->prepare_items();
		$email_accounts_table->display();

		?>
		<h3><?php _e( 'Add New Account', 'supportflow' ) ?></h3>
		<?php _e( 'Please enter IMAP Server Settings', 'supportflow' ) ?><br />
		<form method="POST" action="edit.php?post_type=sf_thread&page=sf_accounts">
			<input type="hidden" name="action" value="add" />
			<?php wp_nonce_field( 'add_email_account' ) ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'IMAP Host:', 'supportflow' ) ?></th>
					<td>
						<input type="text" required name="imap_host" value="<?php echo esc_html( isset( $_POST['imap_host'] ) ? $_POST['imap_host'] : '' ) ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'IMAP Port Number: ', 'supportflow' ) ?></th>
					<td>
						<input type="number" required name="imap_port" value="<?php echo esc_html( isset( $_POST['imap_port'] ) ? $_POST['imap_port'] : '993' ) ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'SMTP Host:', 'supportflow' ) ?></th>
					<td>
						<input type="text" required name="smtp_host" value="<?php echo esc_html( isset( $_POST['smtp_host'] ) ? $_POST['smtp_host'] : '' ) ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'SMTP Port Number: ', 'supportflow' ) ?></th>
					<td>
						<input type="number" required name="smtp_port" value="<?php echo esc_html( isset( $_POST['smtp_port'] ) ? $_POST['smtp_port'] : '465' ) ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'Username:', 'supportflow' ) ?></th>
					<td>
						<input type="text" required name="username" value="<?php echo esc_html( isset( $_POST['username'] ) ? $_POST['username'] : '' ) ?>" />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'Password:', 'supportflow' ) ?></th>
					<td><input type="password" required name="password" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add New Server', 'supportflow' ) ); ?>
		</form>

		<script type="text/javascript">
			jQuery('#delete_email_account').click(function (e) {
				e.preventDefault();
				if (!confirm('<?php _e( 'Are you sure want to delete this account.', 'supportflow' ) ?>')) {
					return;
				}

				var account_key = jQuery(this).data('account-id');
				var form = '<form method="POST" action="edit.php?post_type=sf_thread&page=sf_accounts">' +
					'<input type="hidden" name="action" value="delete" />' +
					'<?php wp_nonce_field( 'delete_email_account') ?>' +
					'<input type="hidden" name="account_id" value=' + account_key + ' />' +
					'</form>';

				jQuery(form).submit();
			});
		</script>
	<?php
	}

	/**
	 * Add a new E-Mail account to database
	 */
	function add_email_account( $imap_host, $imap_port, $smtp_host, $smtp_port, $username, $password, $test_login = true ) {
		$email_accounts = & $this->_email_accounts;
		sanitize_text_field( $imap_host, $imap_port, $smtp_port, $username, $password );

		if ( $this->is_exist_email_account( $imap_host, $smtp_host, $username ) ) {
			return self::ACCOUNT_EXISTS;
		}

		if ( true == $test_login ) {
			imap_timeout( IMAP_OPENTIMEOUT, apply_filters( 'supportflow_imap_open_timeout', 5 ) );
			if ( $imap_stream = imap_open( "{{$imap_host}:{$imap_port}/ssl}", $username, $password, 0, 0 ) ) {
				imap_close( $imap_stream );
			} else {
				$error = imap_errors()[0];
				if ( (string) strpos( $error, 'Host not found' ) != '' ) {
					return self::IMAP_HOST_NOT_FOUND;
				} elseif ( (string) strpos( $error, 'Timed out' ) != '' ) {
					return self::IMAP_TIME_OUT;
				} elseif ( (string) strpos( $error, 'Invalid credentials' ) != '' ) {
					return self::IMAP_INVALID_CRIDENTIALS;
				} else {
					return self::IMAP_CONNECTION_FAILED;
				}
			}
		}

		$email_accounts[] = array(
			'imap_host' => $imap_host,
			'imap_port' => $imap_port,
			'smtp_host' => $smtp_host,
			'smtp_port' => $smtp_port,
			'username'  => $username,
			'password'  => $password,
		);
		update_option( 'sf_email_accounts', $email_accounts );

		return self::SUCCESS;

	}

	/**
	 * Remove an E-Mail account from database
	 */
	function remove_email_account( $account_id ) {
		$email_accounts = & $this->_email_accounts;

		if ( ! isset( $email_accounts[$account_id] ) ) {
			return self::NO_ACCOUNT_EXISTS;
		} else {
			unset( $email_accounts[$account_id] );
			update_option( 'sf_email_accounts', $email_accounts );

			return self::SUCCESS;
		}
	}

	/**
	 * Return the list of all existing E-Mail accounts
	 * @return array
	 */
	function get_email_accounts() {
		return $this->_email_accounts;
	}

	/**
	 * Check if E-Mail account exists in database
	 * @return boolean
	 */
	function is_exist_email_account( $imap_host, $smtp_host, $username ) {
		$email_accounts = & $this->_email_accounts;

		foreach ( $email_accounts as $email_account ) {
			if (
				$email_account['imap_host'] == $imap_host &&
				$email_account['smtp_host'] == $smtp_host &&
				$email_account['username'] == $username
			) {
				return true;
			}
		}

		return false;
	}
}

SupportFlow()->extend->settings = new SupportFlow_Email_Accounts();