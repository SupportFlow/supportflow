<?php
/**
 * Primary class for ingesting emails from an IMAP email box, parsing, and adding to appropriate threads
 */
class SupportFlow_Email_Replies extends SupportFlow {

	var $imap_connection;

	/**
	 * Primary method for downloading and processing email replies
	 */
	public function download_and_process_email_replies() {

		// Get any new emails

		// Parse the comment replies

		// Insert new comments as threads or comments

	}

	public function download_new_emails( $connection_details = array() ) {

	}

	/**
	 * Parse the actual user text from a given email body
	 */
	public function get_comment_from_email( $email_body ) {

		return $email_body;
	}

}

SupportFlow()->extend->email_replies = new SupportFlow_Email_Replies();