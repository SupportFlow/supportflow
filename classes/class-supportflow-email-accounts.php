<?php
/**
 * Setting page to add/remove new E-Mail accounts to get replies
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Table to show existing E-Mail accounts with a option to remove existing
 */
class SupportFlow_Email_Accounts_Table extends WP_List_Table {
	protected $_data;

	function __construct( $accounts ) {
		parent::__construct();

		$this->_data = array();

		foreach ( $accounts as $account_id => $account ) {
			// Account is deleted
			if ( empty( $account ) ) {
				continue;
			}

			$this->_data[] = array(
				'table_username'  => esc_html( $account['username'] ),
				'table_imap_host' => esc_html( $account['imap_host'] ),
				'table_imap_port' => esc_html( $account['imap_port'] ),
				'table_imap_ssl'  => esc_html( $account['imap_ssl'] ? 'True' : 'False' ),
				'table_smtp_host' => esc_html( $account['smtp_host'] ),
				'table_smtp_port' => esc_html( $account['smtp_port'] ),
				'table_smtp_ssl'  => esc_html( $account['smtp_ssl'] ? 'True' : 'False' ),
				'table_action'    => "<a href='#' data-account-id='" . esc_attr( $account_id ) . "' class='delete_email_account'>" . __( 'Delete', 'supportflow' ) . "</a>",
			);
		}
	}

	function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	function get_columns() {
		return array(
			'table_username'  => __( 'Username', 'supportflow' ),
			'table_imap_host' => __( 'IMAP Host', 'supportflow' ),
			'table_imap_port' => __( 'IMAP Port', 'supportflow' ),
			'table_imap_ssl'  => __( 'IMAP use SSL', 'supportflow' ),
			'table_smtp_host' => __( 'SMTP Host', 'supportflow' ),
			'table_smtp_port' => __( 'SMTP Port', 'supportflow' ),
			'table_smtp_ssl'  => __( 'SMTP use SSL', 'supportflow' ),
			'table_action'    => __( 'Action', 'supportflow' ),
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

/**
 * Setting page to add/remove new E-Mail accounts to get replies
 */
class SupportFlow_Email_Accounts extends SupportFlow {
	var $email_accounts;
	var $existing_email_accounts;

	const SUCCESS                  = 0;
	const ACCOUNT_EXISTS           = 1;
	const NO_ACCOUNT_EXISTS        = 2;
	const IMAP_HOST_NOT_FOUND      = 3;
	const IMAP_INVALID_CREDENTIALS = 4;
	const IMAP_TIME_OUT            = 5;
	const IMAP_CONNECTION_FAILED   = 6;
	const SMTP_AUTHENTICATION_FAILED = 7;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		// Get existing E-Mail accounts from database
		$email_accounts = & $this->email_accounts;
		$email_accounts = get_option( 'sf_email_accounts', array() );

		$existing_email_accounts = & $this->existing_email_accounts;
		$existing_email_accounts = array();
		foreach ($email_accounts as $uid => $email_account) {
			if ( ! empty( $email_account ) ) {
				$existing_email_accounts[ $uid ] = $email_account;
			}
		}
	}

	function action_admin_menu() {
		$post_type  = SupportFlow()->post_type;
		$this->slug = 'sf_accounts';
		// Create a menu (SupportFlow->E-Mail Accounts)
		add_submenu_page(
			"edit.php?post_type=$post_type",
			__( 'SupportFlow E-Mail Accounts', 'supportflow' ),
			__( 'E-Mail Accounts', 'supportflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'settings_page' )
		);

	}

	/*
	 * Return an array containing E-Mail accounts
	 */
	function get_email_accounts( $existing_only = false ) {
		if ( $existing_only ) {
			return $this->existing_email_accounts;
		} else {
			return $this->email_accounts;
		}
	}

	/*
	 * Loads the setting page to add/remove E-Mail accounts
	 */
	function settings_page() {
		echo '<div class="wrap">
			<h2>' . __( 'E-Mail Accounts', 'supportflow' ) . '</h2>';

		// Add/remove E-Mail accounts if submitted by user
		$this->process_form_submission();

		$this->list_email_accounts();

		$this->insert_add_new_account_form();

		echo '</div>';

		$this->insert_js_code();
	}

	/*
	 * Add/remove E-Mail accounts if submitted by user
	 */
	function process_form_submission() {
		$action   = isset( $_POST['action'] ) ? $_POST['action'] : '';
		$imap_ssl = isset( $_POST['imap_ssl'] ) && 'on' == $_POST['imap_ssl'] ? true : false;
		$smtp_ssl = isset( $_POST['smtp_ssl'] ) && 'on' == $_POST['smtp_ssl'] ? true : false;

		// Create new account
		if ( 'add' == $action && isset( $_POST['imap_host'], $_POST['imap_port'], $_POST['smtp_host'], $_POST['smtp_port'], $_POST['username'], $_POST['password'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'add_email_account' ) ) {

			$res = $this->add_email_account( $_POST['imap_host'], $_POST['imap_port'], $imap_ssl, $_POST['smtp_host'], $_POST['smtp_port'], $smtp_ssl, $_POST['username'], $_POST['password'] );

			switch ( $res ) {

				case self::SUCCESS:
					echo '<h3>' . __( 'Account added successfully', 'supportflow' ) . '</h3>';
					unset( $_POST['imap_host'], $_POST['action'], $_POST['imap_port'], $_POST['imap_ssl'], $_POST['smtp_host'], $_POST['smtp_port'], $_POST['smtp_ssl'], $_POST['username'], $_POST['password'] );
					break;
				case self::ACCOUNT_EXISTS:
					echo '<h3>' . __( 'There is an account already exists with same host name and user name. Please create a new account with different settings.', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_HOST_NOT_FOUND:
					echo '<h3>' . sprintf( __( 'Unable to connect to %s. Please check your IMAP host name.', 'supportflow' ), esc_html( $_POST['imap_host'] ) ) . '</h3>';
					break;
				case self::IMAP_TIME_OUT:
					echo '<h3>' . sprintf( __( 'Time out while connecting to %s. Please check your IMAP port number and host name.', 'supportflow' ), esc_html( $_POST['imap_host'] ) ) . '</h3>';
					break;
				case self::IMAP_INVALID_CREDENTIALS:
					echo '<h3>' . __( 'Unable to connect with given username/password combination. Please re-check your username and password', 'supportflow' ) . '</h3>';
					break;
				case self::IMAP_CONNECTION_FAILED:
					echo '<h3>' . __( 'Unknown error while connecting to the IMAP server. Please check your IMAP settings and try again.', 'supportflow' ) . '</h3>';
					break;
				case self::SMTP_AUTHENTICATION_FAILED:
					echo '<h3>' . __( 'Unable to authenticate SMTP account. Please check your SMTP setting and try again.', 'supportflow' ) . '</h3>';
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

	}

	/**
	 * List all the existing E-Mail accounts in a table
	 */
	public function list_email_accounts() {
		$email_accounts_table = new SupportFlow_Email_Accounts_Table( $this->email_accounts );
		$email_accounts_table->prepare_items();
		$email_accounts_table->display();

	}

	/*
	 * Create a form to enter new E-Mail account details
	 */
	public function insert_add_new_account_form() {
		$form_action = "edit.php?post_type=" . SupportFlow()->post_type . "&page=" . $this->slug;

		$imap_ssl_enabled =
			! isset( $_POST['action'] )
			|| 'add' != $_POST['action']
			|| ( isset( $_POST['imap_ssl'] ) && 'on' == $_POST['imap_ssl'] );

		$smtp_ssl_enabled =
			! isset( $_POST['action'] )
			|| 'add' != $_POST['action']
			|| ( isset( $_POST['smtp_ssl'] ) && 'on' == $_POST['smtp_ssl'] );
		?>
		<h3><?php _e( 'Add New Account', 'supportflow' ) ?></h3>
		<?php _e( 'Please enter IMAP Server Settings', 'supportflow' ) ?><br />
		<form method="POST" id="add_new_email_account" action="<?php echo esc_attr( $form_action ) ?>">
			<input type="hidden" name="action" value="add" />
			<?php wp_nonce_field( 'add_email_account' ) ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="imap_host"><?php _e( 'IMAP Host:', 'supportflow' ) ?></label></th>
					<td>
						<input type="text" required id="imap_host" name="imap_host" value="<?php echo esc_attr( isset( $_POST['imap_host'] ) ? $_POST['imap_host'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="imap_ssl"><?php _e( 'IMAP Server supports SSL: ', 'supportflow' ) ?></label></th>
					<td>
						<input type="checkbox" id="imap_ssl" name="imap_ssl" <?php echo checked( $imap_ssl_enabled ) ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="imap_port"><?php _e( 'IMAP Port Number: ', 'supportflow' ) ?></label></th>
					<td>
						<input type="number" required id="imap_port" name="imap_port" value="<?php echo esc_attr( isset( $_POST['imap_port'] ) ? $_POST['imap_port'] : '993' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="smtp_host"><?php _e( 'SMTP Host:', 'supportflow' ) ?></label></th>
					<td>
						<input type="text" required id="smtp_host" name="smtp_host" value="<?php echo esc_attr( isset( $_POST['smtp_host'] ) ? $_POST['smtp_host'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="smtp_ssl"><?php _e( 'SMTP Server supports SSL: ', 'supportflow' ) ?></label></th>
					<td>
						<input type="checkbox" id="smtp_ssl" name="smtp_ssl" <?php checked( $smtp_ssl_enabled ) ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="smtp_port"><?php _e( 'SMTP Port Number: ', 'supportflow' ) ?></label></th>
					<td>
						<input type="number" required id="smtp_port" name="smtp_port" value="<?php echo esc_attr( isset( $_POST['smtp_port'] ) ? $_POST['smtp_port'] : '465' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="username"><?php _e( 'Username:', 'supportflow' ) ?></label></th>
					<td>
						<input type="text" required id="username" name="username" value="<?php echo esc_attr( isset( $_POST['username'] ) ? $_POST['username'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="password"><?php _e( 'Password:', 'supportflow' ) ?></label></th>
					<td><input type="password" required id="password" name="password" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add New Server', 'supportflow' ) ); ?>
		</form>
	<?php
	}

	/*
	 * Insert JS code required for form submission
	 */
	public function insert_js_code() {
		$msg_title   = __( 'Are you sure want to delete this account?', 'supportflow' );
		$form_action = "edit.php?post_type=" . SupportFlow()->post_type . "&page=" . $this->slug;
		?>
		<script type="text/javascript">
			jQuery('.delete_email_account').click(function (e) {
				e.preventDefault();
				if (!confirm('<?php echo $msg_title ?>')) {
					return;
				}

				var account_key = jQuery(this).data('account-id');
				var form = '<form method="POST" id="remove_email_account" action="<?php echo $form_action ?>">' +
					'<input type="hidden" name="action" value="delete" />' +
					'<?php wp_nonce_field( 'delete_email_account'  ) ?>' +
					'<input type="hidden" name="account_id" value=' + account_key + ' />' +
					'</form>';

				jQuery('body').append(form);
				jQuery('#remove_email_account').submit();
			});


			jQuery('#add_new_email_account #imap_ssl').change(function () {
				if (this.checked) {
					// Change to default IMAP SSL port on enabling SSL
					jQuery('#add_new_email_account #imap_port').val('993');
				} else {
					// Change to default IMAP non-SSL port on disabling SSL
					jQuery('#add_new_email_account #imap_port').val('143');
				}
			});

			jQuery('#add_new_email_account #smtp_ssl').change(function () {
				if (this.checked) {
					// Change to default SMTP SSL port on enabling SSL
					jQuery('#add_new_email_account #smtp_port').val('465');
				} else {
					// Change to default SMTP non-SSL port on disabling SSL
					jQuery('#add_new_email_account #smtp_port').val('25');
				}
			});
		</script>
	<?php
	}

	/**
	 * Add a new E-Mail account to database
	 */
	function add_email_account( $imap_host, $imap_port, $imap_ssl, $smtp_host, $smtp_port, $smtp_ssl, $username, $password, $test_login = true ) {
		global $phpmailer;

		$email_accounts = & $this->email_accounts;
		$imap_host      = sanitize_text_field( $imap_host );
		$imap_port      = intval( $imap_port );
		$imap_ssl       = (boolean) $imap_ssl;
		$smtp_host      = sanitize_text_field( $smtp_host );
		$smtp_port      = intval( $smtp_port );
		$smtp_ssl       = (boolean) $smtp_ssl;
		$username       = sanitize_text_field( $username );
		$password       = sanitize_text_field( $password );

		if ( $this->email_account_exists( $imap_host, $smtp_host, $username ) ) {
			return self::ACCOUNT_EXISTS;
		}

		if ( $test_login ) {
			imap_timeout( IMAP_OPENTIMEOUT, apply_filters( 'supportflow_imap_open_timeout', 5 ) );
			$ssl     = $imap_ssl ? '/ssl' : '';
			$mailbox = '{' . $imap_host . ':' . $imap_port . $ssl . '}';
			if ( $imap_stream = imap_open( $mailbox, $username, $password, 0, 0 ) ) {
				imap_close( $imap_stream );
			} else {
				$error = imap_errors();
				$error = $error[0];
				if ( (string) strpos( $error, 'Host not found' ) != '' ) {
					return self::IMAP_HOST_NOT_FOUND;
				} elseif ( (string) strpos( $error, 'Timed out' ) != '' ) {
					return self::IMAP_TIME_OUT;
				} elseif ( (string) strpos( $error, 'Invalid credentials' ) != '' ) {
					return self::IMAP_INVALID_CREDENTIALS;
				} else {
					return self::IMAP_CONNECTION_FAILED;
				}
			}

			// Initialize PHPMailer
			wp_mail( '', '', '' );

			// Set PHPMailer SMTP settings
			$phpmailer->IsSMTP();
			$phpmailer->Host       = $smtp_host;
			$phpmailer->Port       = $smtp_port;
			$phpmailer->SMTPSecure = $smtp_ssl ? 'ssl' : '';
			$phpmailer->Username   = $username;
			$phpmailer->Password   = $password;
			$phpmailer->SMTPAuth   = true;

			// $phpmail raise fatal error on SMTP connect failure
			try {
				$smtp_authentication = $phpmailer->smtpConnect();
			} catch ( Exception $e ) {

			}

			if ( ! isset( $smtp_authentication ) || ! $smtp_authentication ) {
				return self::SMTP_AUTHENTICATION_FAILED;
			}
		}

		$email_accounts[] = array(
			'imap_host' => $imap_host,
			'imap_port' => $imap_port,
			'imap_ssl'  => $imap_ssl,
			'smtp_host' => $smtp_host,
			'smtp_port' => $smtp_port,
			'smtp_ssl'  => $smtp_ssl,
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
		$email_accounts = & $this->email_accounts;
		$account_id     = intval( $account_id );
		if ( ! isset( $email_accounts[$account_id] ) ) {
			return self::NO_ACCOUNT_EXISTS;
		} else {
			$email_accounts[$account_id] = null;
			update_option( 'sf_email_accounts', $email_accounts );

			return self::SUCCESS;
		}
	}

	/**
	 * Check if E-Mail account exists in database
	 * @return boolean
	 */
	function email_account_exists( $imap_host, $smtp_host, $username ) {
		$email_accounts = & $this->email_accounts;

		foreach ( $email_accounts as $email_account ) {
			// Account is deleted
			if ( empty( $email_account ) ) {
				continue;
			}

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

SupportFlow()->extend->email_accounts = new SupportFlow_Email_Accounts();