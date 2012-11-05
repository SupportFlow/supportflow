<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

class SupportFlow_Emails extends SupportFlow {

	var $from_name;
	var $from_address;

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Register the notifications to happen on which actions
	 */
	public function setup_actions() {

		$this->from_name = apply_filters( 'supportflow_emails_from_name', $this->from_name );
		$this->from_address = apply_filters( 'supportflow_emails_from_address', $this->from_address );

		// Don't send out any notifications when importing or using WP-CLI
		if ( ( defined('WP_IMPORTING') && WP_IMPORTING ) || ( defined('WP_CLI') && WP_CLI ) )
			return;

		// When a new comment is added to a thread, notify the respondents and the agents
		add_action( 'supportflow_thread_comment_added', array( $this, 'notify_agents_thread_comment' ) );
		add_action( 'supportflow_thread_comment_added', array( $this, 'notify_respondents_thread_comment' ) );
	}

	/**
	 * When a new comment is added to the thread, notify the agent on the thread if there is one
	 */
	public function notify_agents_thread_comment( $comment_id ) {

		$comment = get_comment( $comment_id );
		if ( ! $comment )
			return;

		$thread = SupportFlow()->get_thread( $comment->comment_post_ID );
		// One agent by default, but easily allow notifications to a triage team
		$agent_ids = apply_filters( 'supportflow_emails_notify_agent_ids', array( $thread->post_author ), $thread, 'comment' );

		if ( empty( $agent_ids ) )
			return;

		$agent_emails = array();
		foreach( $agent_ids as $user_id ) {
			if ( $user = get_user_by( 'id', $user_id ) )
				$agent_emails[] = $user->user_email;
		}

		// Don't email the person creating the comment, unless that's desired behavior
		if ( !apply_filters( 'supportflow_emails_notify_creator', false, 'comment' ) ) {
			$key = array_search( $comment->comment_author_email, $agent_emails );
			if ( false !== $key )
				unset( $agent_emails[$key] );
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_comment_notify_subject', $subject, $comment_id, $thread->ID, 'agent' );

		$message = stripslashes( $comment->comment_content );
		if ( $attachment_ids = get_comment_meta( $comment->comment_ID, 'attachment_ids', true ) ) {
			$message .= "\n";
			foreach( $attachment_ids as $attachment_id ) {
				$message .= "\n" . wp_get_attachment_url( $attachment_id );
			}
		}
		foreach( $agent_emails as $agent_email ) {
			wp_mail( $agent_email, $subject, $message );
		}
	}

	/**
	 * When a new comment is added to the thread, notify all of the respondents on the thread
	 */
	public function notify_respondents_thread_comment( $comment_id ) {

		// Respondents shouldn't receive private comments
		$comment = get_comment( $comment_id );
		if ( ! $comment || 'private' == $comment->comment_approved )
			return;

		$thread = SupportFlow()->get_thread( $comment->comment_post_ID );
		$respondents = SupportFlow()->get_thread_respondents( $thread->ID, array( 'fields' => 'emails' ) );

		// Don't email the person creating the comment, unless that's desired behavior
		if ( !apply_filters( 'supportflow_emails_notify_creator', false, 'comment' ) ) {
			$key = array_search( $comment->comment_author_email, $respondents );
			if ( false !== $key )
				unset( $respondents[$key] );
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_comment_notify_subject', $subject, $comment_id, $thread->ID, 'respondent' );

		$message = stripslashes( $comment->comment_content );
		if ( $attachment_ids = get_comment_meta( $comment->comment_ID, 'attachment_ids', true ) ) {
			$message .= "\n";
			foreach( $attachment_ids as $attachment_id ) {
				$message .= "\n" . wp_get_attachment_url( $attachment_id );
			}
		}
		foreach( $respondents as $respondent_email ) {
			self::mail( $respondent_email, $subject, $message );
		}
	}

	/**
	 * Send an email from SupportFlow
	 */
	public function mail( $respondent_email, $subject, $message ) {
		$headers = array();
		if ( ! empty( $this->from_name ) && is_email( $this->from_address ) )
			$headers[] = 'From: ' . $this->from_name  . ' <' . $this->from_address . '>';
		wp_mail( $respondent_email, $subject, $message, $headers );
	}
}

SupportFlow()->extend->emails = new SupportFlow_Emails();