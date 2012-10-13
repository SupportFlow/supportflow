<?php

class SupportFlow_UI_SubmissionForm extends SupportFlow {

	public $messages = array();

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init_handle_form_submission' ) );

		add_shortcode( 'supportflow_submissionform', array( $this, 'shortcode_submissionform' ) );
	}

	// TODO: Nonce, l10n, etc.
	public function shortcode_submissionform() {
		$html = '';

		$html .= '<div class="supportflow-submissionform">';
			$html .= '<form action="" method="POST">';

				if ( ! empty( $this->messages ) ) {
					foreach ( $this->messages as $message ) {
						$html .= '<p class="supportflow-message" style="font-weight:bold;color:red;">' . $message . '</p>';
					}
				}

				$html .= "<p>This is just an ugly form that's meant for testing. We'll probably want something like this in the final version but we can worry about that later.</p>";
				$html .= '<p>Your Name: <input type="text" name="supportflow[name]" size="30" /></p>';
				$html .= '<p>Your Email: <input type="text" name="supportflow[email]" size="30" /></p>';
				$html .= '<p>Subject: <input type="text" name="supportflow[subject]" size="60" /></p>';
				$html .= '<p>Message:</p>';
				$html .= '<p><textarea name="supportflow[message]" cols="50" rows="10"></textarea></p>';
				$html .= '<p><input type="submit" value="Submit" /></p>';
			$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	public function action_init_handle_form_submission() {
		if ( empty( $_POST['supportflow'] ) || ! is_array( $_POST['supportflow'] ) )
			return;

		$_POST['supportflow'] = array_map( 'stripslashes', $_POST['supportflow'] );

		if ( empty( $_POST['supportflow']['name'] ) )
			$this->messages[] = 'The name field is required.';

		if ( empty( $_POST['supportflow']['email'] ) )
			$this->messages[] = 'The email field is required.';
		elseif ( ! is_email( $_POST['supportflow']['email'] ) )
			$this->messages[] = 'Please enter a valid e-mail address.';

		if ( empty( $_POST['supportflow']['subject'] ) )
			$this->messages[] = 'The subject field is required.';

		if ( empty( $_POST['supportflow']['message'] ) )
			$this->messages[] = 'You must enter a message.';

		if ( ! empty( $this->messages ) )
			return;

		$thread_id = SupportFlow()->create_thread( array(
			'subject'         => $_POST['supportflow']['subject'],
			'message'         => $_POST['supportflow']['message'],
			'requester_name'  => $_POST['supportflow']['name'],
			'requester_email' => $_POST['supportflow']['email'],
		) );

		if ( is_wp_error( $thread_id ) ) {
			$this->messages[] = 'There was an error creating the thread: ' . $thread_id->get_error_message();
			return;
		}

		$this->messages[] = 'Thread (ticket) number ' . $thread_id . ' created.';
	}
}

SupportFlow()->extend->ui->submissionform = new SupportFlow_UI_SubmissionForm();