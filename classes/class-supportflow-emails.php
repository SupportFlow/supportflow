<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Emails {

	public $from_address = null;

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

		$email_accounts   = SupportFlow()->extend->email_accounts->get_email_accounts( true );
		$email_account_id = get_post_meta( $ticket->ID, 'email_account', true );
		if ( '' == $email_account_id ) {
			return;
		}
		$smtp_account = $email_accounts[$email_account_id];


		$agent_ids = SupportFlow()->extend->email_notifications->get_notified_user( $ticket->ID );
		$agent_ids = apply_filters( 'supportflow_emails_notify_agent_ids', $agent_ids, $ticket, 'reply' );
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

		if ( empty( $agent_emails ) ) {
			SupportFlow()->extend->logger->log(
				'email_send',
				__METHOD__,
				__( 'Returning early because no customers to notify.', 'supportflow' ),
				compact( 'customers' )
			);

			return;
		}

		$subject = sprintf( '#%d: %s', $ticket->ID, get_the_title( $ticket->ID ) );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $ticket->ID, 'agent' );

		$attachments = $this->get_attached_files( $reply->ID );

		$post_status    = SupportFlow()->post_statuses[$ticket->post_status]['label'];
		$assigned_agent = ( $ticket->post_author ) ? get_user_by( 'id', $ticket->post_author )->display_name : __( 'None assigned', 'supportflow' );

		$message = SupportFlow()->sanitize_ticket_reply( stripslashes( $reply->post_content ) );
		$message .= "\n\n-------";
		$message .= "\n" . __( "Status: ", 'supportflow' ) . $post_status;
		$message .= "\n" . __( "Agent: ", 'supportflow' ) . $assigned_agent;

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $ticket->ID, 'agent' );

		$message .= "\n\n" . $this->get_quoted_text( $ticket );
		$message = wpautop( $message );

		self::mail( $agent_emails, $subject, $message, 'Content-Type: text/html', $attachments, $smtp_account );
	}

	/**
	 * When a new reply is added to the ticket, notify all of the customers on the ticket
	 */
	public function notify_customers_ticket_replies( $reply_id, $cc = array(), $bcc = array() ) {
		// Customers shouldn't receive private replies.
		$reply = get_post( $reply_id );
		if ( ! $reply || 'private' == $reply->post_status ) {
			return;
		}

		// Customers shouldn't get replies triggered by non-agents.
		if ( $reply->post_author < 1 ) {
			return;
		}

		$ticket    = SupportFlow()->get_ticket( $reply->post_parent );
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

		if ( empty( $customers ) ) {
			SupportFlow()->extend->logger->log(
				'email_send',
				__METHOD__,
				__( 'Returning early because no customers to notify.', 'supportflow' ),
				compact( 'customers' )
			);

			return;
		}

		$attachments = $this->get_attached_files( $reply->ID );
		$num_replies = SupportFlow()->get_ticket_replies_count( $ticket->ID );
		$subject = sprintf( '#%d: %s', $ticket->ID, get_the_title( $ticket->ID ) );

		// Prefix with Re: if it's a reply.
		if ( $num_replies > 1 ) {
			$subject = sprintf( _x( 'Re: %s', 'A prefix for outgoing e-mail replies.', 'supportflow' ), $subject );
		}

		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $ticket->ID, 'customer' );

		$message = SupportFlow()->sanitize_ticket_reply( stripslashes( $reply->post_content ) );

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $ticket->ID, 'customer' );
		$message .= "\n\n" . $this->get_quoted_text( $ticket );
		$message = wpautop( $message );

		$headers = "Content-Type: text/html\r\n";
		$headers .= $this->get_cc_header( $cc );
		$headers .= $this->get_bcc_header( $bcc );

		$result = self::mail( $customers, $subject, $message, $headers, $attachments, $smtp_account );
		add_post_meta( $reply->ID, '_sf_mail_status', array( 'result' => $result ) );
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
		$subject     = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $ticket->ID ) . ' ' . __( 'Conversation summary', 'supportflow' );

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
			$msg .= SupportFlow()->sanitize_ticket_reply( $ticket_reply->post_content );
			$msg .= '<br><br>';

			$attachments[] = $this->get_attached_files( $ticket_reply->ID );
		}
		self::mail( $to, $subject, $msg, 'Content-Type: text/html', $attachments );
	}

	/**
	 * Send an email from SupportFlow
	 */
	public function mail( $to, $subject, $message, $headers = '', $attachments = array(), $smtp_account = null ) {
		global $phpmailer;

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = $smtp_account;
			add_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}

		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = null;
			remove_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}

		// Log the result, but redact the password to avoid unnecessarily exposing it
		// translators: %s is the recipients e-mail address
		$log_message = $result ? __( 'Sending mail to %s succeeded', 'supportflow' ) : __( 'Sending mail to %s failed', 'supportflow' );
		$log_message = sprintf( $log_message, is_array( $to ) ? implode( ',', $to ) : $to );
		$redacted_smtp_account = $smtp_account;
		if ( ! empty( $redacted_smtp_account ) ) {
			$redacted_smtp_account['password'] = '[redacted]';
		}
		$errors = $phpmailer->ErrorInfo;

		SupportFlow()->extend->logger->log(
			'email_send',
			__METHOD__,
			$log_message,
			compact( 'to', 'subject', 'message', 'headers', 'attachments', 'redacted_smtp_account', 'result', 'errors' )
		);

		return $result;
	}

	public function action_set_smtp_settings( $phpmailer ) {
		$phpmailer->IsSMTP();
		$phpmailer->Host        = $this->smtp_account['smtp_host'];
		$phpmailer->Port        = (int) $this->smtp_account['smtp_port'];
		$phpmailer->SMTPSecure  = $this->smtp_account['smtp_ssl'] ? 'ssl' : '';
		$phpmailer->SMTPAutoTLS = $this->smtp_account['smtp_ssl'];
		$phpmailer->Username    = $this->smtp_account['username'];
		$phpmailer->Password    = $this->smtp_account['password'];
		$phpmailer->SMTPAuth    = true;
		$phpmailer->FromName    = get_bloginfo( 'name' );
	}

	public function get_cc_header( $cc ) {
		if ( is_array( $cc ) && ! empty( $cc ) ) {
			return "Cc: " . implode( ', ', $cc ) . "\r\n";
		} elseif ( is_string( $cc ) && ! empty( $cc ) ) {
			return "Cc: $cc\r\n";
		}
	}

	public function get_bcc_header( $bcc ) {
		if ( is_array( $bcc ) && ! empty( $bcc ) ) {
			return "Bcc: " . implode( ', ', $bcc ) . "\r\n";
		} elseif ( is_string( $bcc ) && ! empty( $cc ) ) {
			return "Bcc: $bcc\r\n";
		}
	}

	public function get_quoted_text( $ticket ) {
		// Insert last second reply as quoted text
		$replies = SupportFlow()->get_ticket_replies( $ticket->ID, array( 'numberposts' => 2, ) );
		if ( empty( $replies[1] ) ) {
			return '';
		} else {
			$quoted_reply = $replies[1];
			$reply_author = get_post_meta( $quoted_reply->ID, 'reply_author', true );
			if ( empty( $reply_author ) ) {
				$reply_author = get_post_meta( $ticket_reply->ID, 'reply_author_email', true );
			}
			$time_stamp = $ticket->post_date_gmt;
			$heading    = sprintf( "On %s GMT, %s wrote:", $time_stamp, $reply_author );
			$content    = SupportFlow()->sanitize_ticket_reply( stripslashes( $quoted_reply->post_content ) );
			$content    = "> $content";
			$content    = str_replace( "\n", "\n> ", $content );

			return "$heading\n$content";
		}
	}

	public function get_attached_files( $reply_id ) {
		$attachments = array();
		if ( $ticket_attachments = get_post_meta( $reply_id, 'sf_attachments' ) ) {
			foreach ( $ticket_attachments as $attachment ) {
				$attachments[] = get_attached_file( $attachment );
			}
		}

		return $attachments;
	}
}

SupportFlow()->extend->emails = new SupportFlow_Emails();
