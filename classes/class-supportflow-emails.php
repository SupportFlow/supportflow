<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Emails extends SupportFlow {

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Register the notifications to happen on which actions
	 */
	public function setup_actions() {

		// Don't send out any notifications when importing or using WP-CLI
		if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// When a new reply is added to a ticket, notify the customers and the agents
		add_action( 'supportflow_ticket_reply_added', array( $this, 'notify_agents_ticket_replies' ) );
		add_action( 'supportflow_ticket_reply_added', array( $this, 'notify_customers_ticket_replies' ), 10, 3 );
	}

	/**
	 * When a new reply is added to the ticket, notify the agent on the ticket if there is one
	 */
	public function notify_agents_ticket_replies( $reply_id ) {

		$reply = get_post( $reply_id );
		if ( ! $reply ) {
			return;
		}

		$ticket = SupportFlow()->get_ticket( $reply->post_parent );
		// One agent by default, but easily allow notifications to a triage team
		$agent_ids = SupportFlow()->extend->email_notifications->get_notified_user( $ticket->ID );
		$agent_ids = apply_filters( 'supportflow_emails_notify_agent_ids', $agent_ids, $ticket, 'reply' );

		$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		$email_account_id = get_post_meta( $ticket->ID, 'email_account', true );
		if ( '' == $email_account_id ) {
			return;
		}
		$smtp_account = $email_accounts[$email_account_id];

		if ( empty( $agent_ids ) ) {
			return;
		}

		$agent_emails = array();
		foreach ( $agent_ids as $user_id ) {
			if ( $user = get_user_by( 'id', $user_id ) ) {
				$agent_emails[] = $user->user_email;
			}
		}

		// Don't email the person adding the reply, unless that's desired behavior
		if ( ! apply_filters( 'supportflow_emails_notify_creator', false, 'reply' ) ) {
			$key = array_search( get_post_meta( $reply->ID, 'reply_author_email', true ), $agent_emails );
			if ( false !== $key ) {
				unset( $agent_emails[$key] );
			}
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $ticket->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $ticket->ID, 'agent' );

		$attachments = array();
		if ( $ticket_attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => $reply->ID ) ) ) {
			foreach ( $ticket_attachments as $attachment ) {
				$attachments[] = get_attached_file( $attachment->ID );
			}
		}

		$message = stripslashes( $reply->post_content );
		// Ticket details that are relevant to the agent
		$message .= "\n\n-------";
		// Ticket status
		$post_status = SupportFlow()->post_statuses[$ticket->post_status]['label'];
		$message .= "\n" . sprintf( __( "Status: %s", 'supportflow' ), $post_status );
		// Assigned agent
		$assigned_agent = ( $ticket->post_author ) ? get_user_by( 'id', $ticket->post_author )->display_name : __( 'None assigned', 'supportflow' );
		$message .= "\n" . sprintf( __( "Agent: %s", 'supportflow' ), $assigned_agent );

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $ticket->ID, 'agent' );

		// Insert last second reply as quoted text
		$replies = SupportFlow()->get_ticket_replies( $ticket->ID, array( 'numberposts' => 2, ) );
		if ( ! empty( $replies[1] ) ) {
			$quoted_reply = $replies[1];
			$reply_author = get_post_meta( $quoted_reply->ID, 'reply_author', true );
			if ( empty( $reply_author ) ) {
				$reply_author = get_post_meta( $ticket_reply->ID, 'reply_author_email', true );
			}
			$time_stamp   = $ticket->post_date_gmt;
			$heading      = sprintf( "On %s GMT, %s wrote:", $time_stamp, $reply_author );
			$content      = '> ' . stripslashes( $quoted_reply->post_content );
			$content      = str_replace( "\n", "\n> ", $content );
			$message .= "\n\n$heading\n>$content";
		}
		$message = wpautop( $message );

		self::mail( $agent_emails, $subject, $message, 'Content-Type: text/html', $attachments, $smtp_account );
	}

	/**
	 * When a new reply is added to the ticket, notify all of the customers on the ticket
	 */
	public function notify_customers_ticket_replies( $reply_id, $cc = array(), $bcc = array() ) {
		// Customers shouldn't receive private replies
		$reply = get_post( $reply_id );
		if ( ! $reply || 'private' == $reply->post_status ) {
			return;
		}

		$ticket      = SupportFlow()->get_ticket( $reply->post_parent );
		$customers = SupportFlow()->get_ticket_customers( $ticket->ID, array( 'fields' => 'emails' ) );

		$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		$email_account_id = get_post_meta( $ticket->ID, 'email_account', true );
		if ( '' == $email_account_id ) {
			return;
		}
		$smtp_account = $email_accounts[$email_account_id];

		// Don't email the person creating the reply, unless that's desired behavior
		if ( ! apply_filters( 'supportflow_emails_notify_creator', false, 'reply' ) ) {
			$reply_author_email = get_post_meta( $reply->ID, 'reply_author_email', true );
			$key                = array_search( $reply_author_email, $customers );
			if ( false !== $key ) {
				unset( $customers[$key] );
			}
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $ticket->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $ticket->ID, 'customer' );

		$attachments = array();
		if ( $ticket_attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => $reply->ID ) ) ) {
			foreach ( $ticket_attachments as $attachment ) {
				$attachments[] = get_attached_file( $attachment->ID );
			}
		}

		$message = stripslashes( $reply->post_content );
		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $ticket->ID, 'customer' );
		$message = wpautop( $message );

		// Insert last second reply as quoted text
		$replies = SupportFlow()->get_ticket_replies( $ticket->ID, array( 'numberposts' => 2, ) );
		if ( ! empty( $replies[1] ) ) {
			$quoted_reply = $replies[1];
			$reply_author = get_post_meta( $quoted_reply->ID, 'reply_author', true );
			if ( empty( $reply_author ) ) {
				$reply_author = get_post_meta( $ticket_reply->ID, 'reply_author_email', true );
			}
			$time_stamp   = $ticket->post_date_gmt;
			$heading      = sprintf( "On %s GMT, %s wrote:", $time_stamp, $reply_author );
			$content      = '> ' . stripslashes( $quoted_reply->post_content );
			$content      = str_replace( "\n", "\n> ", $content );
			$message .= "\n\n$heading\n>$content";
		}

		$headers = "Content-Type: text/html\r\n";

		if ( is_array( $cc ) && ! empty( $cc ) ) {
			$headers .= "Cc: " . implode( ', ', $cc ) . "\r\n";
		} elseif ( is_string( $cc ) && ! empty( $cc ) ) {
			$headers .= "Cc: $cc\r\n";
		}

		if ( is_array( $bcc ) && ! empty( $bcc ) ) {
			$headers .= "Bcc: " . implode( ', ', $bcc ) . "\r\n";
		} elseif ( is_string( $bcc ) && ! empty( $cc ) ) {
			$headers .= "Bcc: $bcc\r\n";
		}

		self::mail( $customers, $subject, $message, $headers, $attachments, $smtp_account );
	}

	/**
	 * E-Mail a supportflow conversation to users
	 *
	 * @param integer      $ticket_id
	 * @param array|string $to
	 */
	public function email_conversation( $ticket_id, $to ) {

		$ticket         = SupportFlow()->get_ticket( $ticket_id );
		$ticket_replies = SupportFlow()->get_ticket_replies( $ticket_id );
		$ticket_owner   = $ticket->post_author > 0 ? get_user_by( 'id', $ticket->post_author )->data->user_nicename : __( 'Unassigned', 'supportflow' );

		$attachments = array();
		$msg         = '';
		$subject     = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $ticket->ID ) . ' ' . __( 'Conversation summery', 'supportflow' );

		$msg .= '<b>' . __( 'Title', 'supportflow' ) . ':</b> ' . esc_html( $ticket->post_title ) . '<br>';
		$msg .= '<b>' . __( 'Status', 'supportflow' ) . ':</b> ' . SupportFlow()->post_statuses[$ticket->post_status]['label'] . '<br>';
		$msg .= '<b>' . __( 'Owner', 'supportflow' ) . ':</b> ' . esc_html( $ticket_owner ) . '<br>';
		$msg .= '<b>' . __( 'Created on', 'supportflow' ) . ':</b> ' . $ticket->post_date_gmt . ' GMT<br>';
		$msg .= '<b>' . __( 'Last Updated on', 'supportflow' ) . ':</b> ' . $ticket->post_modified_gmt . ' GMT<br>';
		$msg .= '<br><br>';

		foreach ( array_reverse( $ticket_replies ) as $ticket_reply ) {
			$date_time    = $ticket_reply->post_date_gmt . ' GMT';
			$reply_author = get_post_meta( $ticket_reply->ID, 'reply_author', true );
			if ( empty( $reply_author ) ) {
				$reply_author = get_post_meta( $ticket_reply->ID, 'reply_author_email', true );
			}

			$msg .= '<b>' . sprintf( __( 'On %s, %s wrote:', 'supportflow' ), $date_time, esc_html( $reply_author ) ) . '</b>';
			$msg .= '<br>';
			$msg .= esc_html( $ticket_reply->post_content );
			$msg .= '<br><br>';

			if ( $ticket_attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => $ticket_reply->ID ) ) ) {
				foreach ( $ticket_attachments as $attachment ) {
					$attachments[] = get_attached_file( $attachment->ID );
				}
			}
		}
		self::mail( $to, $subject, $msg, 'Content-Type: text/html', $attachments );
	}

	/**
	 * Send an email from SupportFlow
	 */
	public function mail( $to, $subject, $message, $headers = '', $attachments = array(), $smtp_account = null ) {

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = $smtp_account;
			add_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}

		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = null;
			remove_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}

		return $result;
	}

	public function action_set_smtp_settings( $phpmailer ) {
		$phpmailer->IsSMTP();
		$phpmailer->Host       = $this->smtp_account['smtp_host'];
		$phpmailer->Port       = (int) $this->smtp_account['smtp_port'];
		$phpmailer->SMTPSecure = $this->smtp_account['smtp_ssl'] ? 'ssl' : '';
		$phpmailer->Username   = $this->smtp_account['username'];
		$phpmailer->Password   = $this->smtp_account['password'];
		$phpmailer->SMTPAuth   = true;
	}

}

SupportFlow()->extend->emails = new SupportFlow_Emails();