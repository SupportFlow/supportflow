<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

class SupportPress_Emails extends SupportPress {

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Register the notifications to happen on which actions
	 */
	public function setup_actions() {
		
		// When a new comment is added to a thread, notify all of the respondents on the thread
		add_action( 'supportpress_thread_comment_added', array( $this, 'notify_respondents_thread_comment' ) );
	}

	/**
	 * When a new comment is added to the thread, notify all of the respondents on the thread
	 */
	public function notify_respondents_thread_comment( $comment_id ) {

		// Respondents shouldn't receive private comments
		$comment = get_comment( $comment_id );
		if ( ! $comment || 'private' == $comment->comment_approved )
			return;

		$thread = SupportPress()->get_thread( $comment->comment_post_ID );
		$respondents = SupportPress()->get_thread_respondents( $thread->ID, array( 'fields' => 'emails' ) );

		// Don't email the person creating the comment, unless that's desired behavior
		if ( !apply_filters( 'supportpress_emails_notify_creator', false, 'comment' ) ) {
			$key = array_search( $comment->comment_author_email, $respondents );
			if ( false !== $key )
				unset( $respondents[$key] );
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$message = stripslashes( $comment->comment_content );
		if ( $attachment_ids = get_comment_meta( $comment->comment_ID, 'attachment_ids', true ) ) {
			$message .= "\n";
			foreach( $attachment_ids as $attachment_id ) {
				$message .= "\n" . wp_get_attachment_url( $attachment_id );
			}
		}
		foreach( $respondents as $respondent_email ) {
			wp_mail( $respondent_email, $subject, $message );
		}
	}
}

SupportPress()->extend->emails = new SupportPress_Emails();