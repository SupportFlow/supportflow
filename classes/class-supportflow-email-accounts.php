<?php
/**
 * Setting page to add/remove new E-Mail accounts to get replies
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

/**
 * Setting page to add/remove new E-Mail accounts to get replies
 */
class SupportFlow_Email_Accounts {
	var $email_accounts;
	var $existing_email_accounts;

	const SUCCESS                    = 0;
	const ACCOUNT_EXISTS             = 1;
	const NO_ACCOUNT_EXISTS          = 2;
	const IMAP_HOST_NOT_FOUND        = 3;
	const IMAP_INVALID_CREDENTIALS   = 4;
	const IMAP_TIME_OUT              = 5;
	const IMAP_CONNECTION_FAILED     = 6;
	const SMTP_AUTHENTICATION_FAILED = 7;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		// Get existing E-Mail accounts from database
		$email_accounts = & $this->email_accounts;
		$email_accounts = get_option( 'sf_email_accounts', array() );

		$existing_email_accounts = & $this->existing_email_accounts;
		$existing_email_accounts = array();
		foreach ( $email_accounts as $uid => $email_account ) {
			if ( ! empty( $email_account ) ) {
				$existing_email_accounts[$uid] = $email_account;
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

	/**
	 * Return a single E-Mail account
	 *
	 * @param integer $id ID of E-Mail account
	 *
	 * @return null|array array containing E-Mail account on success else null
	 */
	function get_email_account( $id ) {
		$email_accounts = & $this->email_accounts;
		if ( isset( $email_accounts[$id] ) && ! empty( $email_accounts[$id] ) ) {
			return $email_accounts[$id];
		} else {
			return null;
		}
	}

	/*
	 * Loads the setting page to add/remove E-Mail accounts
	 */
	function settings_page() {
		echo '<div class="wrap">
			<h1>' . __( 'E-Mail Accounts', 'supportflow' ) . '</h1>';

		// Add/remove E-Mail accounts if submitted by user
		$this->process_form_submission();

		$this->list_email_accounts();

		$this->insert_add_new_account_form();

		echo '</div>';

		$this->insert_script();
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
		$no_items = __( 'No E-Mail accounts found. Please <b>add them</b> in the form below.', 'supportflow' );

		$columns = array(
			'table_username'  => __( 'Username', 'supportflow' ),
			'table_imap_host' => __( 'IMAP Host', 'supportflow' ),
			'table_imap_port' => __( 'IMAP Port', 'supportflow' ),
			'table_imap_ssl'  => __( 'IMAP use SSL', 'supportflow' ),
			'table_smtp_host' => __( 'SMTP Host', 'supportflow' ),
			'table_smtp_port' => __( 'SMTP Port', 'supportflow' ),
			'table_smtp_ssl'  => __( 'SMTP use SSL', 'supportflow' ),
			'table_action'    => __( 'Action', 'supportflow' ),
		);

		$data = array();
		foreach ( $this->email_accounts as $account_id => $account ) {
			// Account is deleted
			if ( empty( $account ) ) {
				continue;
			}

			$data[] = array(
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

		$email_accounts_table = new SupportFlow_Table();
		$email_accounts_table->set_columns( $columns );
		$email_accounts_table->set_no_items( $no_items );
		$email_accounts_table->set_data( $data );
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
					<th scope="row">
						<label for="imap_host"><?php _e( 'IMAP Host:', 'supportflow' ) ?></label></th>
					<td>
						<input type="text" required id="imap_host" name="imap_host" value="<?php echo esc_attr( isset( $_POST['imap_host'] ) ? $_POST['imap_host'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="imap_ssl"><?php _e( 'IMAP Server supports SSL: ', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="checkbox" id="imap_ssl" name="imap_ssl" <?php echo checked( $imap_ssl_enabled ) ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="imap_port"><?php _e( 'IMAP Port Number: ', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="number" required id="imap_port" name="imap_port" value="<?php echo esc_attr( isset( $_POST['imap_port'] ) ? $_POST['imap_port'] : '993' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="smtp_host"><?php _e( 'SMTP Host:', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="text" required id="smtp_host" name="smtp_host" value="<?php echo esc_attr( isset( $_POST['smtp_host'] ) ? $_POST['smtp_host'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="smtp_ssl"><?php _e( 'SMTP Server supports SSL: ', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="checkbox" id="smtp_ssl" name="smtp_ssl" <?php checked( $smtp_ssl_enabled ) ?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="smtp_port"><?php _e( 'SMTP Port Number: ', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="number" required id="smtp_port" name="smtp_port" value="<?php echo esc_attr( isset( $_POST['smtp_port'] ) ? $_POST['smtp_port'] : '465' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="username"><?php _e( 'Username:', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="text" required id="username" name="username" value="<?php echo esc_attr( isset( $_POST['username'] ) ? $_POST['username'] : '' ) ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="password"><?php _e( 'Password:', 'supportflow' ) ?></label>
					</th>
					<td>
						<input type="password" required id="password" name="password" />
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add New Server', 'supportflow' ) ); ?>
		</form>
	<?php
	}

	/*
	 * Enqueue JS code required for form submission
	 */
	public function insert_script() {
		$handle = SupportFlow()->enqueue_script( 'supportflow-email_accounts', 'email_accounts.js' );

		wp_localize_script( $handle, 'SFEmailAccounts', array(
			'sure_delete_account'        => __( 'Are you sure want to delete this account?', 'supportflow' ),
			'post_type'                  => SupportFlow()->post_type,
			'slug'                       => $this->slug,
			'delete_email_account_nonce' => wp_nonce_field( 'delete_email_account', '_wpnonce', true, false ),

		) );
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
			SupportFlow()->extend->logger->log(
				'email_accounts',
				__METHOD__,
				__( 'Account already exists.', 'supportflow' ),
				compact( 'imap_host', 'imap_port', 'imap_ssl', 'smtp_host', 'smtp_port', 'smtp_ssl', 'username' )
			);

			return self::ACCOUNT_EXISTS;
		}

		if ( $test_login ) {
			imap_timeout( IMAP_OPENTIMEOUT, apply_filters( 'supportflow_imap_open_timeout', 5 ) );
			$ssl     = $imap_ssl ? '/ssl' : '';
			$ssl     = apply_filters( 'supportflow_imap_ssl', $ssl, $imap_host );
			$mailbox = '{' . $imap_host . ':' . $imap_port . $ssl . '}';
			if ( $imap_stream = imap_open( $mailbox, $username, $password, 0, 0 ) ) {
				SupportFlow()->extend->logger->log(
					'email_accounts',
					__METHOD__,
					__( 'Successfully opened IMAP connection.', 'supportflow' ),
					compact( 'imap_host', 'imap_port', 'imap_ssl', 'smtp_host', 'smtp_port', 'smtp_ssl', 'username', 'mailbox' )
				);

				imap_close( $imap_stream );
			} else {
				$imap_errors = imap_errors();
				$error       = $imap_errors[0];

				SupportFlow()->extend->logger->log(
					'email_accounts',
					__METHOD__,
					__( 'Failed to open IMAP connection.', 'supportflow' ),
					compact( 'imap_host', 'imap_port', 'imap_ssl', 'smtp_host', 'smtp_port', 'smtp_ssl', 'username', 'mailbox', 'imap_errors' )
				);

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
			$phpmailer->Host        = $smtp_host;
			$phpmailer->Port        = $smtp_port;
			$phpmailer->SMTPSecure  = $smtp_ssl ? 'ssl' : '';
			$phpmailer->SMTPAutoTLS = $smtp_ssl;
			$phpmailer->Username    = $username;
			$phpmailer->Password    = $password;
			$phpmailer->SMTPAuth    = true;

			// $phpmail raise fatal error on SMTP connect failure
			try {
				$smtp_authentication = $phpmailer->smtpConnect();
			} catch ( Exception $e ) {
				$smtp_authentication = false;

				SupportFlow()->extend->logger->log(
					'email_accounts',
					__METHOD__,
					sprintf( __( 'PHPMailer exception: %s.', 'supportflow' ), $e->getMessage() ),
					compact( 'smtp_host', 'smtp_port', 'smtp_ssl', 'username' )
				);
			}

			SupportFlow()->extend->logger->log(
				'email_accounts',
				__METHOD__,
				$smtp_authentication ? __( 'Successfully authenticated with SMTP server.', 'supportflow' ) : __( 'Failed to authenticate with SMTP server.', 'supportflow' ),
				compact( 'imap_host', 'imap_port', 'imap_ssl', 'smtp_host', 'smtp_port', 'smtp_ssl', 'username', 'mailbox' )
			);

			if ( ! $smtp_authentication ) {
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

	/**
	 * Determines if any active account is using Gmail
	 *
	 * @return bool
	 */
	public function has_gmail_account() {
		$gmail    = false;
		$accounts = $this->get_email_accounts( true );

		foreach( $accounts as $account ) {
			$gmail_imap = false !== strpos( $account['imap_host'], 'gmail.com' );
			$gmail_smtp = false !== strpos( $account['smtp_host'], 'gmail.com' );

			if ( $gmail_imap || $gmail_smtp ) {
				$gmail = true;
				break;
			}
		}

		return $gmail;
	}
}

SupportFlow()->extend->email_accounts = new SupportFlow_Email_Accounts();
