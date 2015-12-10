<?php
/**
 * Primary class for ingesting emails from an IMAP email box, parsing, and adding to appropriate tickets
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Email_Replies {

	const email_id_key = 'orig_email_id';

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'sf_cron_retrieve_email_replies', array( $this, 'retrieve_email_replies' ) );
	}

	function retrieve_email_replies() {
		SupportFlow()->extend->logger->log( 'email_retrieve', __METHOD__, __( 'Starting to retrieve new e-mail.', 'supportflow' ) );
		$start_time = time();

		// Opens a temp file
		$tmp_file = sys_get_temp_dir() . '/sf_cron_lock.txt';
		$fp       = fopen( $tmp_file, 'c+' );

		// Unlock file if created 2+ hours earlier
		$time      = time();
		$file_time = (int) fgets( $fp );
		if ( 0 != $file_time && $time > $file_time + 2 * HOUR_IN_SECONDS ) {
			flock( $fp, LOCK_UN );

			SupportFlow()->extend->logger->log(
				'email_retrieve',
				__METHOD__,
				__( "Forcibly released the lock because it's been locked for 2+ hours.", 'supportflow' ),
				compact( 'time', 'file_time' )
			);
		}

		// Die if temp file is locked. i.e. cron is running
		if ( 6 != fwrite( $fp, 'length' ) ) {
			SupportFlow()->extend->logger->log( 'email_retrieve', __METHOD__, __( 'Exiting early because another job is already running.', 'supportflow' ) );
			die;
		}

		// Save current time to file
		ftruncate( $fp, 0 );
		rewind( $fp );
		fwrite( $fp, (string) $time );

		// Lock the file while running cron
		$lock_acquired = flock( $fp, LOCK_EX );
		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			$lock_acquired ? __( 'Successfully acquired the lock.', 'supportflow' ) : __( 'Failed to acquire the lock.', 'supportflow' )
		);

		$email_accounts = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		foreach ( $email_accounts as $id => $email_account ) {
			$imap_account = array_merge( $email_account,
				apply_filters( 'supportflow_imap_folders', array(
					'inbox'      => 'INBOX',
					'archive'    => 'ARCHIVE',
					'account_id' => $id,
				), $email_account )
			);

			$this->download_and_process_email_replies( $imap_account );
		}

		// Unlock the file and close it
		$lock_released = flock( $fp, LOCK_UN );
		fclose( $fp );
		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			$lock_released ? __( 'Successfully released the lock.', 'supportflow' ) : __( 'Failed to release the lock.', 'supportflow' )
		);

		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			sprintf( __( 'Finished retrieving new e-mail after %d seconds', 'supportflow' ), time() - $start_time ) );
	}

	/**
	 * Primary method for downloading and processing email replies
	 */
	public function download_and_process_email_replies( $connection_details ) {
		imap_timeout( IMAP_OPENTIMEOUT, apply_filters( 'supportflow_imap_open_timeout', 5 ) );

		$ssl = $connection_details['imap_ssl'] ? '/ssl' : '';
		$ssl = apply_filters( 'supportflow_imap_ssl', $ssl, $connection_details['imap_host'] );

		$mailbox     = "{{$connection_details['imap_host']}:{$connection_details['imap_port']}{$ssl}}";
		$inbox       = "{$mailbox}{$connection_details['inbox']}";
		$archive_box = "{$mailbox}{$connection_details['archive']}";

		$imap_connection = imap_open( $mailbox, $connection_details['username'], $connection_details['password'] );

		$redacted_connection_details = $connection_details;
		$redacted_connection_details['password'] = '[redacted]';  // redact the password to avoid unnecessarily exposing it in logs
		$imap_errors = imap_errors();
		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			$imap_connection ? __( 'Successfully opened IMAP connection.', 'supportflow' ) : __( 'Failed to open IMAP connection.', 'supportflow' ),
			compact( 'redacted_connection_details', 'mailbox', 'imap_errors' )
		);

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
			SupportFlow()->extend->logger->log(
				'email_retrieve',
				__METHOD__,
				__( 'No new messages to process.', 'supportflow' ),
				compact( 'mailboxes' )
			);

			return false;
		}

		$emails        = imap_search( $imap_connection, 'ALL', SE_UID );
		$email_count   = min( $email_count, apply_filters( 'supportflow_max_email_process_count', 20 ) );
		$emails        = array_slice( $emails, 0, $email_count );
		$processed     = 0;

		// Process each new email and put it in the archive mailbox when done.
		foreach ( $emails as $uid ) {
			$email            = new stdClass;
			$email->uid       = $uid;
			$email->msgno     = imap_msgno( $imap_connection, $email->uid );

			$email->headers   = imap_headerinfo( $imap_connection, $email->msgno );
			$email->structure = imap_fetchstructure( $imap_connection, $email->msgno );
			$email->body      = $this->get_body_from_connection( $imap_connection, $email->msgno );

			if ( 0 === strcasecmp( $connection_details['username'], $email->headers->from[0]->mailbox . '@' . $email->headers->from[0]->host ) ) {
				$connection_details['password'] = '[redacted]';  // redact the password to avoid unnecessarily exposing it in logs
				SupportFlow()->extend->logger->log(
					'email_retrieve',
					__METHOD__,
					__( 'Skipping message because it was sent from a SupportFlow account.', 'supportflow' ),
					compact( 'email' )
				);

				continue;
			}

			// @todo Confirm this a message we want to process
			$result = $this->process_email( $imap_connection, $email, $email->msgno, $connection_details['username'], $connection_details['account_id'] );

			// If it was successful, move the email to the archive
			if ( $result ) {
				imap_mail_move( $imap_connection, $email->uid, $connection_details['archive'], CP_UID );
				$processed++;
			}
		}

		imap_close( $imap_connection, CL_EXPUNGE );

		$status_message = sprintf( __( 'Processed %d emails', 'supportflow' ), $processed );
		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			$status_message
		);
		return $status_message;
	}

	/**
	 * Given an email object, maybe create a new ticket
	 */
	public function process_email( $imap_connection, $email, $i, $to, $email_account_id ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';

		$new_attachment_ids = array();
		$k                  = 0;

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
						SupportFlow()->extend->attachments->secure_attachment_file( $upload_result );
						$new_attachment_ids[] = $upload_result;
					}

				}
				$k ++;
			}
		}

		if ( ! empty( $email->headers->subject ) ) {
			$subject = imap_utf8( $email->headers->subject );
		} else {
			$subject = sprintf( __( 'New ticket from %s', 'supportflow' ), $email->headers->fromaddress );
		}

		$reply_author       = isset( $email->headers->from[0]->personal ) ? $email->headers->from[0]->personal : '';

		if ( empty( $email->headers->reply_to ) ) {
			$reply_author_email = $email->headers->from[0]->mailbox     . '@' . $email->headers->from[0]->host;
		} else {
			$reply_author_email = $email->headers->reply_to[0]->mailbox . '@' . $email->headers->reply_to[0]->host;
		}

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
					SupportFlow()->extend->logger->log(
						'email_retrieve',
						__METHOD__,
						__( 'Skipping e-mail because sender is a robot.', 'supportflow' ),
						compact( 'email' )
					);

					return true;
				}
			}
		}

		// Check to see if this message was in response to an existing ticket
		$ticket_id = false;

		if ( preg_match( '/#(\d+):/', $subject, $matches ) ) {
			$ticket_id = $matches[1];
			$ticket = get_post( $ticket_id );

			// Make sure the ticket ID is a SupportFlow ticket with a valid status.
			if ( $ticket->post_type != SupportFlow()->post_type
				 || ! array_key_exists( $ticket->post_status, SupportFlow()->post_statuses ) ) {
				$ticket_id = false;
			}
		}

		// Back-compat with old-style secrets.
		if ( ! $ticket_id && preg_match( '#\[([a-zA-Z0-9]{8})\]$#', $subject, $matches ) ) {
			$ticket_id = SupportFlow()->get_ticket_from_secret( $matches[1] );
		}

		// Add anyone else that was in the 'to' or 'cc' fields as customers
		$customers = array();
		$fields      = array( 'to', 'cc' );
		foreach ( $fields as $field ) {
			if ( ! empty( $email->headers->$field ) ) {
				foreach ( $email->headers->$field as $recipient ) {
					$email_address = $recipient->mailbox . '@' . $recipient->host;
					if ( is_email( $email_address ) && $email_address != SupportFlow()->extend->emails->from_address && strcasecmp( $email_address, $to ) != 0 ) {
						$customers[] = $email_address;
					}
				}
			}
		}
		$customers[] = $reply_author_email;
		$customers = apply_filters( 'supportflow_new_email_customers', $customers, $email, $ticket_id, $email_account_id );

		$message = SupportFlow()->sanitize_ticket_reply( $message );

		if ( $ticket_id ) {
			$reply_args = array(
				'reply_author'       => $reply_author,
				'reply_author_email' => $reply_author_email,
			);
			SupportFlow()->update_ticket_customers( $ticket_id, $customers, true );
			SupportFlow()->add_ticket_reply( $ticket_id, $message, $reply_args );

		} else {
			// If this wasn't in reply to an existing message, create a new ticket
			$new_ticket_args = array(
				'subject'            => $subject,
				'reply_author'       => $reply_author,
				'reply_author_email' => $reply_author_email,
				'message'            => $message,
				'customer_email'   => $customers,
				'email_account'      => $email_account_id,
			);

			$ticket_id = SupportFlow()->create_ticket( $new_ticket_args );
		}

		$all_replies = SupportFlow()->get_ticket_replies( $ticket_id );
		$new_reply   = $all_replies[count( $all_replies ) - 1];

		foreach ( $new_attachment_ids as $new_attachment_id ) {
			// Save the attachment ID as post meta of reply
			add_post_meta( $new_reply->ID, 'sf_attachments', $new_attachment_id );
			SupportFlow()->extend->attachments->insert_attachment_secret_key( $new_attachment_id ) ;

			// It doesn't do anything special other than making sure file is not shown as unattached in media page
			wp_update_post( array( 'ID' => $new_attachment_id, 'post_parent' => $ticket_id ) );
		}

		// Store the original email ID so we don't accidentally dupe it
		$email_id = trim( $email->headers->message_id, '<>' );
		if ( is_object( $new_reply ) ) {
			update_post_meta( $new_reply->ID, self::email_id_key, $email_id );
		}

		SupportFlow()->extend->logger->log(
			'email_retrieve',
			__METHOD__,
			// translators: %s is the sender's e-mail address
			sprintf( __( 'Successfully imported e-mail from %s.', 'supportflow' ), $reply_author_email )
		);

		return true;
	}

	/**
	 * Get the email text body and/or attachments given an IMAP resource
	 */
	public function get_body_from_connection( $connection, $num, $type = 'text/plain' ) {
		// Hacky way to get the email body. We should support more MIME types in the future
		$body = imap_fetchbody( $connection, $num, '1.1' );
		if ( empty( $body ) ) {
			$body = imap_fetchbody( $connection, $num, '1' );
		}

		$body = imap_qprint( $body );
		if ( imap_base64( $body ) )
			$body = imap_base64( $body );

		$body = imap_utf8( $body );
		return $body;
	}

}

SupportFlow()->extend->email_replies = new SupportFlow_Email_Replies();
