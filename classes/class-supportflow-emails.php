<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

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

		// When a new reply is added to a thread, notify the respondents and the agents
		add_action( 'supportflow_thread_reply_added', array( $this, 'notify_agents_thread_replies' ) );
		add_action( 'supportflow_thread_reply_added', array( $this, 'notify_respondents_thread_replies' ), 10, 3 );
	}

	/**
	 * When a new reply is added to the thread, notify the agent on the thread if there is one
	 */
	public function notify_agents_thread_replies( $reply_id ) {

		$reply = get_post( $reply_id );
		if ( ! $reply ) {
			return;
		}

		$thread = SupportFlow()->get_thread( $reply->post_parent );
		// One agent by default, but easily allow notifications to a triage team
		$agent_ids = SupportFlow()->extend->email_notifications->get_notified_user( get_the_ID() );
		$agent_ids = apply_filters( 'supportflow_emails_notify_agent_ids', $agent_ids, $thread, 'reply' );

		$email_accounts   = get_option( 'sf_email_accounts' );
		$email_account_id = get_post_meta( $thread->ID, 'email_account', true );
		$smtp_account     = $email_accounts[$email_account_id];

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

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $thread->ID, 'agent' );

		$attachments = array();
		if ( $thread_attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => $reply->ID ) ) ) {
			foreach ( $thread_attachments as $attachment ) {
				$attachments[] = get_attached_file( $attachment->ID );
			}
		}

		$message = stripslashes( $reply->post_content );
		// Ticket details that are relevant to the agent
		$message .= "\n\n-------";
		// Thread status
		$post_status = SupportFlow()->post_statuses[$thread->post_status]['label'];
		$message .= "\n" . sprintf( __( "Status: %s", 'supportflow' ), $post_status );
		// Assigned agent
		$assigned_agent = ( $thread->post_author ) ? get_user_by( 'id', $thread->post_author )->display_name : __( 'None assigned', 'supportflow' );
		$message .= "\n" . sprintf( __( "Agent: %s", 'supportflow' ), $assigned_agent );

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $thread->ID, 'agent' );

		self::mail( $agent_emails, $subject, $message, '', $attachments, $smtp_account );
	}

	/**
	 * When a new reply is added to the thread, notify all of the respondents on the thread
	 */
	public function notify_respondents_thread_replies( $reply_id, $cc = array(), $bcc = array() ) {
		// Respondents shouldn't receive private replies
		$reply = get_post( $reply_id );
		if ( ! $reply || 'private' == $reply->post_status ) {
			return;
		}

		$thread      = SupportFlow()->get_thread( $reply->post_parent );
		$respondents = SupportFlow()->get_thread_respondents( $thread->ID, array( 'fields' => 'emails' ) );

		$email_accounts   = get_option( 'sf_email_accounts' );
		$email_account_id = get_post_meta( $thread->ID, 'email_account', true );
		$smtp_account     = $email_accounts[$email_account_id];

		// Don't email the person creating the reply, unless that's desired behavior
		if ( ! apply_filters( 'supportflow_emails_notify_creator', false, 'reply' ) ) {
			$reply_author_email = get_post_meta( $reply->ID, 'reply_author_email', true );
			$key                = array_search( $reply_author_email, $respondents );
			if ( false !== $key ) {
				unset( $respondents[$key] );
			}
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $thread->ID, 'respondent' );

		$attachments = array();
		if ( $thread_attachments = get_posts( array( 'post_type' => 'attachment', 'post_parent' => $reply->ID ) ) ) {
			foreach ( $thread_attachments as $attachment ) {
				$attachments[] = get_attached_file( $attachment->ID );
			}
		}

		$message = stripslashes( $reply->post_content );
		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $thread->ID, 'respondent' );

		$headers = '';

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

		self::mail( $respondents, $subject, $message, $headers, $attachments, $smtp_account );
	}

	/**
	 * Send an email from SupportFlow
	 */
	public function mail( $to, $subject, $message, $headers = '', $attachments = array(), $smtp_account = null ) {

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = $smtp_account;
			add_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}

		wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! empty( $smtp_account ) ) {
			$this->smtp_account = null;
			remove_action( 'phpmailer_init', array( $this, 'action_set_smtp_settings' ) );
		}
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