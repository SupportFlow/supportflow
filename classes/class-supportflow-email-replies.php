<?php
/**
 * Primary class for ingesting emails from an IMAP email box, parsing, and adding to appropriate threads
 */
class SupportFlow_Email_Replies extends SupportFlow {

	var $imap_connection;

	const email_id_key = 'orig_email_id';

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		add_filter( 'supportflow_emails_comment_notify_subject', array( $this, 'filter_comment_notify_subject' ), 10, 3 );
	}

	public function filter_comment_notify_subject( $subject, $comment_id, $thread_id ) {

		$subject = rtrim( $subject ) . ' [' . SupportFlow()->get_secret_for_thread( $thread_id ) . ']';
		return $subject;
	}

	/**
	 * Primary method for downloading and processing email replies
	 */
	public function download_and_process_email_replies( $connection_details ) {

		$inbox = $connection_details['inbox'];
		$archive = $connection_details['archive'];

		$this->imap_connection = imap_open( $connection_details['host'], $connection_details['username'], $connection_details['password'] );
		if ( ! $this->imap_connection )
			return new WP_Error( 'connection-error', __( 'Error connecting to mailbox', 'supportflow' ) );

		// Check to see if the archive mailbox exists, and create it if it doesn't
		$mailboxes = imap_getmailboxes( $this->imap_connection, $connection_details['host'], '*' );
		if ( ! wp_filter_object_list( $mailboxes, array( 'name' => $connection_details['host'] . $archive ) ) )
			imap_createmailbox( $this->imap_connection, $connection_details['host'] . $archive );

		// Make sure here are new emails to process
		$email_count = imap_num_msg( $this->imap_connection );
		if ( $email_count < 1 )
			return false;

		// Process each new email and put it in the archive mailbox when done
		$success = 0;
		for( $i = 1; $i <= $email_count; $i++ ) {
			$email = new stdClass;
			$email->headers = imap_headerinfo( $this->imap_connection, $i );
			$email->body = $this->get_body_from_connection( $this->imap_connection, $i );

			// @todo Confirm this a message we want to process
			$ret = $this->process_email( $email );
			// If it was successful, move the email to the archive
			if ( $ret ) {
				imap_mail_move( $this->imap_connection, $i, $archive );
				$success++;
			}
		}
		return sprintf( __( 'Processed %d emails', 'supportflow' ), $success );
	}

	/**
	 * Given an email object, maybe create a new ticket
	 *
	 * @todo upload the attachment if there is one
	 */
	public function process_email( $email ) {

		if ( ! empty( $email->headers->subject ) )
			$subject = $email->headers->subject;
		else
			$subject = sprintf( __( 'New thread from %s', 'supportflow' ), $email->headers->fromaddress );

		$respondent_name = $email->headers->from[0]->personal;
		$respondent_email = $email->headers->from[0]->mailbox . '@' . $email->headers->from[0]->host;
		$message = $this->get_message_from_body( $email->body );

		// Check to see if this message was in response to an existing thread
		$thread_id = false;
		if ( preg_match( '#\[([a-zA-Z0-9]{8})\]$#', $subject, $matches ) )
			$thread_id = (int)SupportFlow()->get_thread_from_secret( $matches[1] );

		if ( $thread_id ) {
			$message_args = array(
					'comment_author'        => $respondent_name,
					'comment_author_email'  => $respondent_email,
				);
			SupportFlow()->add_thread_comment( $thread_id, $message, $message_args );
		} else {
			// If this wasn't in reply to an existing message, create a new thread
			$new_thread_args = array(
					'subject'               => $subject,
					'respondent_name'       => $respondent_name,
					'respondent_email'      => $respondent_email,
					'message'               => $message,
				);
			$thread_id = SupportFlow()->create_thread( $new_thread_args );
		}
		$new_comment = array_pop( SupportFlow()->get_thread_comments( $thread_id ) );

		// Add anyone else that was in the 'to' or 'cc' fields as respondents
		$respondents = array();
		$fields = array( 'to', 'cc' );
		foreach( $fields as $field ) {
			if ( ! empty( $email->headers->$field ) ) {
				foreach( $email->headers->$field as $recipient ) {
					$email_address = $recipient->mailbox . '@' . $recipient->host;
					if ( is_email( $email_address ) && $email_address != SupportFlow()->extend->emails->from_address )
						$respondents[] = $email_address;
				}
			}
		}
		SupportFlow()->update_thread_respondents( $thread_id, $respondents, true );

		// Store the original email ID so we don't accidentally dupe it
		$email_id = trim( $email->headers->message_id, '<>' );
		if ( is_object( $new_comment ) )
			update_comment_meta( $new_comment->comment_ID, self::email_id_key, $email_id );

		return true;
	}

	/**
	 * Get the email text body and/or attachments given an IMAP resource
	 */
	public function get_body_from_connection( $connection, $num, $type = 'text/plain' ) {
		// Hacky way to get the email body. We should support more MIME types in the future
		$body = imap_fetchbody( $connection, $num, 1.1 );
		if ( empty( $body ) )
			$body = imap_fetchbody( $connection, $num, 1 );
		return $body;
	}


	/**
	 * Parse the actual user text from a given email body
	 *
	 * @todo Plug in Gimme The Message email parsing to strip out the crap
	 */
	public function get_message_from_body( $body ) {

		$message = trim( $body );

		return $message;
	}

}

SupportFlow()->extend->email_replies = new SupportFlow_Email_Replies();