<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_UI_SubmissionForm {

	public $messages = array();

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init_handle_form_submission' ) );

		add_shortcode( 'supportflow_submissionform', array( $this, 'shortcode_submissionform' ) );
	}

	public function shortcode_submissionform() {
		?>
		<style type="text/css">
			.supportflow-message {
				font-weight: bold;
				color:       red;
			}
		</style>

		<div class="supportflow-submissionform">

			<?php if ( ! empty( $this->messages ) ) : ?>
				<p class="supportflow-message">
					<?php foreach ( $this->messages as $message ) : ?>
						<?php esc_html_e( $message ) ?>
						<br />
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<form action="" method="POST">
				<input type="hidden" name="action" value="sf_create_ticket" />
				<?php wp_nonce_field( '_sf_create_ticket' ) ?>

				<p>
					<label for="fullname"><?php _e( 'Your Name', 'supportflow' ) ?>:</label>
					<br />
					<input type="text" required id="fullname" name="fullname" />
				</p>

				<p>
					<label for="email"><?php _e( 'Your E-Mail', 'supportflow' ) ?>:</label>
					<br />
					<input type="email" required id="email" name="email" />
				</p>

				<p>
					<label for="subject"><?php _e( 'Subject', 'supportflow' ) ?>:</label>
					<br />
					<input type="text" required id="subject" name="subject" />
				</p>

				<p>
					<label for="message"><?php _e( 'Message', 'supportflow' ) ?>:</label>
					<br />
					<textarea required id="message" rows=5 name="message"></textarea>
				</p>

				<p>
					<input type="submit" value="<?php _e( 'Submit', 'supportflow' ) ?>" />
				</p>

			</form>
		</div>
	<?php
	}

	public function action_init_handle_form_submission() {

		if (
			! isset( $_POST['action'], $_POST['_wpnonce'] ) ||
			! 'sf_create_ticket' == $_POST['action'] ||
			! wp_verify_nonce( $_POST['_wpnonce'], '_sf_create_ticket' )
		) {
			return;
		}

		if ( empty( $_POST['fullname'] ) ) {
			$this->messages[] = __( 'The name field is required.', 'supportflow' );
		}

		if ( empty( $_POST['email'] ) ) {
			$this->messages[] = __( 'The email field is required.', 'supportflow' );

		} elseif ( ! is_email( $_POST['email'] ) ) {
			$this->messages[] = __( 'Please enter a valid e-mail address.', 'supportflow' );
		}

		if ( empty( $_POST['subject'] ) ) {
			$this->messages[] = __( 'The subject field is required.', 'supportflow' );
		}

		if ( empty( $_POST['message'] ) ) {
			$this->messages[] = __( 'You must enter a message.', 'supportflow' );
		}

		if ( ! empty( $this->messages ) ) {
			return;
		}

		// Load required file
		require_once( SupportFlow()->plugin_dir . 'classes/class-supportflow-admin.php' );

		$ticket_id = SupportFlow()->create_ticket(
			array(
				'subject'            => $_POST['subject'],
				'message'            => $_POST['message'],
				'reply_author'       => $_POST['fullname'],
				'reply_author_email' => $_POST['email'],
				'customer_email'   => array( $_POST['email'] ),
			)
		);

		if ( is_wp_error( $ticket_id ) ) {
			$this->messages[] = __( 'There is an unknown error while submitting the form. Please try again later.', 'supportflow' );

			return;
		}

		$this->messages[] = __( 'Form submitted successfully', 'supportflow' );
	}
}

SupportFlow()->extend->ui->submissionform = new SupportFlow_UI_SubmissionForm();
