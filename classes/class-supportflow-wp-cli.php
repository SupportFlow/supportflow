<?php
/**
 * WP-CLI commands for SupportFlow
 */

WP_CLI::add_command( 'supportflow', 'SupportFlow_WPCLI' );

class SupportFlow_WPCLI extends WP_CLI_Command {

	/**
	 * Help function for this command
	 */
	public static function help() {

		WP_CLI::line( <<<EOB
usage: wp supportflow <parameters>
Possible subcommands:
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
	 * Import a remote SupportFlow instance into this instance
	 *
	 * @todo support mapping messages from old instance users to new instance users
	 */
	public function import_remote( $args, $assoc_args ) {

		$defaults = array(
				'db_host'                   => '',
				'db_name'                   => '',
				'db_user'                   => '',
				'db_pass'                   => '',
				'table_prefix'              => 'support_',
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
				'threadmeta',
				'threads',
				'usermeta',
				'users',
			);
		foreach( $sp_tables as $sp_table ) {
			$table_name = $this->args['table_prefix'] . $sp_table;
			if ( !in_array( $table_name, $spdb->tables ) ) {
				$spdb->tables[$sp_table] = $table_name;
				$spdb->$sp_table = $table_name;
			}
		}
		
		/**
		 * Import threads and their messages
		 *
		 * @todo Support for importing priorities. This seems to exist in the schema for old SP, but not in the interface
		 */
		$old_threads = $spdb->get_results( "SELECT * FROM $spdb->threads" );
		$count_threads_created = 0;
		foreach( $old_threads as $old_thread ) {

			// Don't import a thread that's already been imported
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE '_imported_id'=%d", $old_thread->thread_id ) ) ) {
				WP_CLI::line( "Skipping: #{$old_thread->thread_id} '{$old_thread->subject}' already exists" );
				continue;
			}

			// Create the new thread
			$thread_args = array(
					'subject'                => $old_thread->subject,
					'date'                   => $old_thread->dt,
					'status'            => 'sp_' . $old_thread->state,
				);
			$thread_id = SupportFlow()->create_thread( $thread_args );
			if ( is_wp_error( $thread_id ) )
				continue;

			// Add the respondent to the thread
			SupportFlow()->update_thread_respondents( $thread_id, $old_thread->email );

			// Get the thread's messages and import those too
			$old_messages = (array)$spdb->get_results( $spdb->prepare( "SELECT * FROM $spdb->messages WHERE thread_id=%d", $old_thread->thread_id ) );
			$count_comments = 0;
			foreach( $old_messages as $old_message ) {
				$message_args = array(
						'comment_author'              => $old_message->email,
						'comment_author_email'        => $old_message->email,
						'time'                        => $old_message->dt,
						'comment_approved'            => ( 'note' == $old_message->message_type ) ? 'private' : 'public',
					);
				$comment_id = SupportFlow()->add_thread_comment( $thread_id, $old_message->content, $message_args );
				add_comment_meta( $comment_id, '_imported_id', $old_message->message_id );
				$count_comments++;
			}

			// One the thread is created, log the old thread ID
			update_post_meta( $thread_id, '_imported_id', $old_thread->thread_id );

			WP_CLI::line( "Created: #{$old_thread->thread_id} '{$old_thread->subject}' with {$count_comments} comments" );
			$count_threads_created++;
		}

		/**
		 * Import predefined messages
		 *
		 * @todo once we support predefined messages
		 */

		WP_CLI::success( "All done! Imported {$count_threads_created} threads." );

	}


}