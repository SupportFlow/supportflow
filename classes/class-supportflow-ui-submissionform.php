<?php

class SupportPress_UI_SubmissionForm extends SupportPress {

	public $messages = array();

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init_handle_form_submission' ) );

		add_shortcode( 'supportpress_submissionform', array( $this, 'shortcode_submissionform' ) );
	}

	// TODO: Nonce, l10n, etc.
	public function shortcode_submissionform() {
		$html = '';

		$html .= '<div class="supportpress-submissionform">';
			$html .= '<form action="" method="POST">';

				if ( ! empty( $this->messages ) ) {
					foreach ( $this->messages as $message ) {
						$html .= '<p class="supportpress-message" style="font-weight:bold;color:red;">' . $message . '</p>';
					}
				}

				$html .= "<p>This is just an ugly form that's meant for testing. We'll probably want something like this in the final version but we can worry about that later.</p>";
				$html .= '<p>Your Name: <input type="text" name="supportpress[name]" size="30" /></p>';
				$html .= '<p>Your Email: <input type="text" name="supportpress[email]" size="30" /></p>';
				$html .= '<p>Subject: <input type="text" name="supportpress[subject]" size="60" /></p>';
				$html .= '<p>Message:</p>';
				$html .= '<p><textarea name="supportpress[message]" cols="50" rows="10"></textarea></p>';
				$html .= '<p><input type="submit" value="Submit" /></p>';
			$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	public function action_init_handle_form_submission() {
		if ( empty( $_POST['supportpress'] ) || ! is_array( $_POST['supportpress'] ) )
			return;

		$_POST['supportpress'] = array_map( 'stripslashes', $_POST['supportpress'] );

		if ( empty( $_POST['supportpress']['name'] ) )
			$this->messages[] = 'The name field is required.';

		if ( empty( $_POST['supportpress']['email'] ) )
			$this->messages[] = 'The email field is required.';
		elseif ( ! is_email( $_POST['supportpress']['email'] ) )
			$this->messages[] = 'Please enter a valid e-mail address.';

		if ( empty( $_POST['supportpress']['subject'] ) )
			$this->messages[] = 'The subject field is required.';

		if ( empty( $_POST['supportpress']['message'] ) )
			$this->messages[] = 'You must enter a message.';

		if ( ! empty( $this->messages ) )
			return;

		$thread_id = SupportPress()->create_thread( array(
			'subject'         => $_POST['supportpress']['subject'],
			'message'         => $_POST['supportpress']['message'],
			'requester_name'  => $_POST['supportpress']['name'],
			'requester_email' => $_POST['supportpress']['email'],
		) );

		if ( is_wp_error( $thread_id ) ) {
			$this->messages[] = 'There was an error creating the thread: ' . $thread_id->get_error_message();
			return;
		}

		$this->messages[] = 'Thread (ticket) number ' . $thread_id . ' created.';
	}
}

SupportPress()->extend->ui->submissionform = new SupportPress_UI_SubmissionForm();