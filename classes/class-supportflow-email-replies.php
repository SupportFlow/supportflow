<?php
/**
 * Primary class for ingesting emails from an IMAP email box, parsing, and adding to appropriate tickets
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Email_Replies extends SupportFlow {

	const email_id_key = 'orig_email_id';

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		add_filter( 'supportflow_emails_reply_notify_subject', array( $this, 'filter_reply_notify_subject' ), 10, 3 );
		add_action( 'sf_cron_retrieve_email_replies', array( $this, 'retrieve_email_replies' ) );

	}

	public function filter_reply_notify_subject( $subject, $reply_id, $ticket_id ) {

		$subject = rtrim( $subject ) . ' [' . SupportFlow()->get_secret_for_ticket( $ticket_id ) . ']';

		return $subject;
	}


	function retrieve_email_replies() {
		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		foreach ( $email_accounts as $id => $email_account ) {
			$imap_account = array_merge( $email_account, array(
				'inbox'      => 'INBOX',
				'archive'    => 'ARCHIVE',
				'account_id' => $id,
			) );

			$this->download_and_process_email_replies( $imap_account );
		}
	}

	/**
	 * Primary method for downloading and processing email replies
	 */
	public function download_and_process_email_replies( $connection_details ) {
		imap_timeout( IMAP_OPENTIMEOUT, apply_filters( 'supportflow_imap_open_timeout', 5 ) );

		$ssl = $connection_details['imap_ssl'] ? '/ssl' : '';

		$mailbox     = "{{$connection_details['imap_host']}:{$connection_details['imap_port']}{$ssl}}";
		$inbox       = "{$mailbox}{$connection_details['inbox']}";
		$archive_box = "{$mailbox}{$connection_details['archive']}";

		$imap_connection = imap_open( $mailbox, $connection_details['username'], $connection_details['password'] );
		if ( ! $imap_connection ) {
			return new WP_Error( 'connection-error', __( 'Error connecting to mailbox', 'supportflow' ) );
		}

		// Check to see if the archive mailbox exists, and create it if it doesn't
		$mailboxes = imap_getmailboxes( $imap_connection, $mailbox, '*' );

		if ( ! wp_filter_object_list( $mailboxes, array( 'name' => $archive_box ) ) ) {
			imap_createmailbox( $imap_connection, $archive_box );
		}
		// Make sure here are new emails to process
		$email_count = imap_num_msg( $imap_connection );
		if ( $email_count < 1 ) {
			return false;
		}

		$emails        = imap_search( $imap_connection, 'ALL', SE_UID );
		$archive_mails = array();

		// Process each new email and put it in the archive mailbox when done
		$success = 0;
		for ( $i = 1; $i <= $email_count; $i ++ ) {
			$email            = new stdClass;
			$email->headers   = imap_headerinfo( $imap_connection, $i );
			$email->structure = imap_fetchstructure( $imap_connection, $i );
			$email->body      = $this->get_body_from_connection( $imap_connection, $i );

			if ( 0 === strcasecmp( $connection_details['username'], $email->headers->from[0]->mailbox . '@' . $email->headers->from[0]->host ) ) {
				continue;
			}

			// @todo Confirm this a message we want to process
			$ret = $this->process_email( $imap_connection, $email, $i, $connection_details['username'], $connection_details['account_id'] );
			// If it was successful, move the email to the archive
			if ( $ret ) {
				$archive_mails[] = $i;
			}
		}

		foreach ( $archive_mails as $msg_number ) {
			imap_mail_move( $imap_connection, $emails[$msg_number - 1], $connection_details['archive'], CP_UID );
			$success ++;
		}

		imap_close( $imap_connection, CL_EXPUNGE );

		return sprintf( __( 'Processed %d emails', 'supportflow' ), $success );
	}

	/**
	 * Given an email object, maybe create a new ticket
	 */
	public function process_email( $imap_connection, $email, $i, $to, $email_account_id ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';

		$new_attachment_ids = array();
		$k = 0;

		if ( isset( $email->structure->parts ) ) {
			foreach ( $email->structure->parts as $email_part ) {

				// We should at least be dealing with something that resembles an email object at this point
				if ( ! isset( $email_part->disposition, $email_part->subtype, $email_part->dparameters[0]->value ) ) {
					continue;
				}

				if ( 'ATTACHMENT' == $email_part->disposition ) {
					// We need to add 2 to our array key each time to get the correct email part
					//@todo this needs more testing with different emails, should be smarter about which parts
					$raw_attachment_data = imap_fetchbody( $imap_connection, $i, $k + 2 );

					// The raw data from imap is base64 encoded, but php-imap has us covered!
					$attachment_data = imap_base64( $raw_attachment_data );

					$temp_file = get_temp_dir() . time() . '_supportflow_temp.tmp';
					touch( $temp_file );
					$temp_handle = fopen( $temp_file, 'w+' );
					fwrite( $temp_handle, $attachment_data );
					fclose( $temp_handle );

					$file_array = array(
						'tmp_name' => $temp_file,
						'name'     => $email_part->dparameters[0]->value,
					);

					$upload_result = media_handle_sideload( $file_array, null );

					if ( is_wp_error( $upload_result ) ) {
						WP_CLI::warning( $upload_result->get_error_message() );
					} else {
						$new_attachment_ids[] = $upload_result;
					}

				}
				$k ++;
			}
		}

		if ( ! empty( $email->headers->subject ) ) {
			$subject = $email->headers->subject;
		} else {
			$subject = sprintf( __( 'New ticket from %s', 'supportflow' ), $email->headers->fromaddress );
		}

		$reply_author       = isset( $email->headers->from[0]->personal ) ? $email->headers->from[0]->personal : '';
		$reply_author_email = $email->headers->from[0]->mailbox . '@' . $email->headers->from[0]->host;

		// Parse out the reply body
		if ( function_exists( 'What_The_Email' ) ) {
			$message = What_The_Email()->get_message( $email->body );
		} else {
			$message = $email->body;
		}

		// Check if this email should be blocked
		if ( function_exists( 'What_The_Email' ) ) {
			$check_strings = array(
				'subject' => $subject,
				'sender'  => $reply_author_email,
				'message' => $message,
			);
			foreach ( $check_strings as $key => $value ) {
				if ( What_The_Email()->is_robot( $key, $value ) ) {
					return true;
				}
			}
		}

		// Check to see if this message was in response to an existing ticket
		$ticket_id = false;
		if ( preg_match( '#\[([a-zA-Z0-9]{8})\]$#', $subject, $matches ) ) {
			$ticket_id = SupportFlow()->get_ticket_from_secret( $matches[1] );
		}

		// Add anyone else that was in the 'to' or 'cc' fields as respondents
		$respondents = array();
		$fields      = array( 'to', 'cc' );
		foreach ( $fields as $field ) {
			if ( ! empty( $email->headers->$field ) ) {
				foreach ( $email->headers->$field as $recipient ) {
					$email_address = $recipient->mailbox . '@' . $recipient->host;
					if ( is_email( $email_address ) && $email_address != SupportFlow()->extend->emails->from_address && strcasecmp( $email_address, $to ) != 0 ) {
						$respondents[] = $email_address;
					}
				}
			}
		}
		$respondents[] = $reply_author_email;

		if ( $ticket_id ) {
			$reply_args = array(
				'reply_author'       => $reply_author,
				'reply_author_email' => $reply_author_email,
			);
			SupportFlow()->update_ticket_respondents( $ticket_id, $respondents, true );
			SupportFlow()->add_ticket_reply( $ticket_id, $message, $reply_args );

		} else {
			if ( method_exists( 'Akismet', 'http_post' ) ) {
				$akismet_request = array(
					'blog'                 => get_site_url(),
					'user_ip'              => '127.0.0.1',
					'comment_content'      => $message,
					'comment_author'       => $reply_author,
					'comment_author_email' => $reply_author_email,
				);
				$akismet_request = http_build_query( $akismet_request );

				$akismet_response = Akismet::http_post( $akismet_request, 'comment-check' );
				$is_spam          = isset( $akismet_response[1] ) && 'true' == trim( $akismet_response[1] );

			}

			// If this wasn't in reply to an existing message, create a new ticket
			$new_ticket_args = array(
				'subject'            => $subject,
				'reply_author'       => $reply_author,
				'reply_author_email' => $reply_author_email,
				'message'            => $message,
				'respondent_email'   => $respondents,
				'email_account'      => $email_account_id,
			);

			if ( isset( $is_spam ) && $is_spam ) {
				$new_ticket_args['status'] = 'sf_spam';
			}

			$ticket_id = SupportFlow()->create_ticket( $new_ticket_args );
		}

		$all_replies = SupportFlow()->get_ticket_replies( $ticket_id );
		$new_reply   = $all_replies[count( $all_replies ) - 1];

		foreach ( $new_attachment_ids as $new_attachment_id ) {
			// Associate the ticket ID as the parent to our new attachment
			wp_update_post( array( 'ID' => $new_attachment_id, 'post_parent' => $new_reply->ID, 'post_status' => 'inherit' ) );
		}

		// Store the original email ID so we don't accidentally dupe it
		$email_id = trim( $email->headers->message_id, '<>' );
		if ( is_object( $new_reply ) ) {
			update_post_meta( $new_reply->ID, self::email_id_key, $email_id );
		}

		return true;
	}

	/**
	 * Get the email text body and/or attachments given an IMAP resource
	 */
	public function get_body_from_connection( $connection, $num, $type = 'text/plain' ) {
		// Hacky way to get the email body. We should support more MIME types in the future
		$body = imap_fetchbody( $connection, $num, 1.1 );
		if ( empty( $body ) ) {
			$body = imap_fetchbody( $connection, $num, 1 );
		}

		return $body;
	}

}

SupportFlow()->extend->email_replies = new SupportFlow_Email_Replies();
