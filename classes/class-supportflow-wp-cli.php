<?php
/**
 * WP-CLI commands for SupportFlow
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

WP_CLI::add_command( 'supportflow', 'SupportFlow_WPCLI' );

class SupportFlow_WPCLI extends WP_CLI_Command {

	/**
	 * Help function for this command
	 */
	public static function help() {

		WP_CLI::line(
			<<<EOB
			usage: wp supportflow <parameters>
Possible subcommands:
					download_and_process_email_replies
					import_remote               Import from a remote SupportFlow
					--db_host=                  Hostname for the remote database
					--db_name=                  Name of the database to connect to
					--db_user=                  Remote database user
					--db_pass=                  Remote database password
					--table_prefix=             Prefix for the SupportFlow tables
EOB
		);
	}

	/**
	 * Download and process email replies from a remote IMAP inbox
	 */
	public function download_and_process_email_replies( $args, $assoc_args ) {

		$defaults           = array(
			'host'     => '', // '{imap.gmail.com:993/imap/ssl/novalidate-cert}' for Gmail
			'username' => '', // Full email address for Gmail
			'password' => '', // Whatever the password is
			'inbox'    => 'INBOX', // Where the new emails will go
			'archive'  => 'SF_ARCHIVE', // Where you'd like emails put after they've been processed
		);
		$connection_details = wp_parse_args( $assoc_args, $defaults );

		// Allow the connection details to be stored in a secret config file or similar
		$connection_details = apply_filters( 'supportflow_imap_connection_details', $connection_details );
		$retval             = SupportFlow()->extend->email_replies->download_and_process_email_replies( $connection_details );
		if ( is_wp_error( $retval ) ) {
			WP_CLI::error( $retval->get_error_message() );
		} else {
			WP_CLI::success( $retval );
		}
	}

	/**
	 * Import a remote SupportFlow instance into this instance
	 *
	 * @todo support mapping messages from old instance users to new instance users
	 */
	public function import_remote( $args, $assoc_args ) {

		$defaults = array(
			'db_host'      => '',
			'db_name'      => '',
			'db_user'      => '',
			'db_pass'      => '',
			'table_prefix' => 'support_',
		);

		$this->args = wp_parse_args( $assoc_args, $defaults );

		// Our WP connection
		global $wpdb;

		// Don't do stuff like send email notifications when importing
		define( 'WP_IMPORTING', true );

		// Make the connection
		$spdb = new wpdb( $this->args['db_user'], $this->args['db_pass'], $this->args['db_name'], $this->args['db_host'] );

		// Register our tables
		$sp_tables = array(
			'messagemeta',
			'messages',
			'predefined_messages',
			'tags',
			'ticketmeta',
			'tickets',
			'usermeta',
			'users',
		);
		foreach ( $sp_tables as $sp_table ) {
			$table_name = $this->args['table_prefix'] . $sp_table;
			if ( ! in_array( $table_name, $spdb->tables ) ) {
				$spdb->tables[$sp_table] = $table_name;
				$spdb->$sp_table         = $table_name;
			}
		}

		/**
		 * Import tickets and their messages
		 *
		 * @todo Support for importing priorities. This seems to exist in the schema for old SP, but not in the interface
		 */
		$old_tickets           = $spdb->get_results( "SELECT * FROM $spdb->tickets" );
		$count_tickets_created = 0;
		foreach ( $old_tickets as $old_ticket ) {

			// Don't import a ticket that's already been imported
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE '_imported_id'=%d", $old_ticket->ticket_id ) ) ) {
				WP_CLI::line( "Skipping: #{$old_ticket->ticket_id} '{$old_ticket->subject}' already exists" );
				continue;
			}

			// Create the new ticket
			$ticket_args = array(
				'subject' => $old_ticket->subject,
				'date'    => $old_ticket->dt,
				'status'  => 'sf_' . $old_ticket->state,
			);
			$ticket_id   = SupportFlow()->create_ticket( $ticket_args );
			if ( is_wp_error( $ticket_id ) ) {
				continue;
			}

			// Add the customer to the ticket
			SupportFlow()->update_ticket_customers( $ticket_id, $old_ticket->email );

			// Get the ticket's messages and import those too
			$old_messages  = (array) $spdb->get_results( $spdb->prepare( "SELECT * FROM $spdb->messages WHERE ticket_id=%d", $old_ticket->ticket_id ) );
			$count_replies = 0;
			foreach ( $old_messages as $old_message ) {
				$message_args = array(
					'reply_author'       => $old_message->email,
					'reply_author_email' => $old_message->email,
					'time'               => $old_message->dt,
					'post_status'        => ( 'note' == $old_message->message_type ) ? 'private' : 'public',
				);
				if ( function_exists( 'What_The_Email' ) ) {
					$old_message->content = What_The_Email()->get_message( $old_message->content );
				}
				$reply_id = SupportFlow()->add_ticket_reply( $ticket_id, $old_message->content, $message_args );
				add_post_meta( $reply_id, '_imported_id', $old_message->message_id );
				$count_replies ++;
			}

			// One the ticket is created, log the old ticket ID
			update_post_meta( $ticket_id, '_imported_id', $old_ticket->ticket_id );

			WP_CLI::line( "Created: #{$old_ticket->ticket_id} '{$old_ticket->subject}' with {$count_replies} replies" );
			$count_tickets_created ++;
		}

		/**
		 * Import predefined messages
		 *
		 * @todo once we support predefined messages
		 */

		WP_CLI::success( "All done! Imported {$count_tickets_created} tickets." );

	}


}