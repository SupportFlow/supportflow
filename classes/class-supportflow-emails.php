<?php
/**
 * Primary class for determining which emails are sent and what goes in the email
 */

class SupportFlow_Emails extends SupportFlow {

	private $from_name = false;
	private $from_email = false;

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Register the notifications to happen on which actions
	 */
	public function setup_actions() {

		$this->from_name  = apply_filters( 'supportflow_emails_from_name', $this->from_name );
		$this->from_email = apply_filters( 'supportflow_emails_from_address', $this->from_email );

		// Don't send out any notifications when importing or using WP-CLI
		if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// When a new reply is added to a thread, notify the respondents and the agents
		add_action( 'supportflow_thread_reply_added', array( $this, 'notify_agents_thread_replies' ) );
		add_action( 'supportflow_thread_reply_added', array( $this, 'notify_respondents_thread_replies' ) );
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
		$agent_ids = apply_filters( 'supportflow_emails_notify_agent_ids', array( $thread->post_author ), $thread, 'reply' );

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
			$key = array_search( get_post_meta( $reply->ID, 'reply_author_email' ), $agent_emails );
			if ( false !== $key ) {
				unset( $agent_emails[$key] );
			}
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $thread->ID, 'agent' );

		$message = stripslashes( $reply->post_content );
		if ( $attachment_ids = get_post_meta( $reply->ID, 'attachment_ids', true ) ) {
			$message .= "\n";
			foreach ( $attachment_ids as $attachment_id ) {
				$message .= "\n" . wp_get_attachment_url( $attachment_id );
			}
		}
		// Ticket details that are relevant to the agent
		$message .= "\n\n-------";
		// Thread status
		$post_status = SupportFlow()->post_statuses[$thread->post_status]['label'];
		$message .= "\n" . sprintf( __( "Status: %s", 'supportflow' ), $post_status );
		// Assigned agent
		$assigned_agent = ( $thread->post_author ) ? get_user_by( 'id', $thread->post_author )->display_name : __( 'None assigned', 'supportflow' );
		$message .= "\n" . sprintf( __( "Agent: %s", 'supportflow' ), $assigned_agent );

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $thread->ID, 'agent' );

		foreach ( $agent_emails as $agent_email ) {
			self::mail( $agent_email, $subject, $message );
		}
	}

	/**
	 * When a new reply is added to the thread, notify all of the respondents on the thread
	 */
	public function notify_respondents_thread_replies( $reply_id ) {

		// Respondents shouldn't receive private replies
		$reply = get_post( $reply_id );
		if ( ! $reply || 'private' == $reply->post_status ) {
			return;
		}

		$thread      = SupportFlow()->get_thread( $reply->post_parent );
		$respondents = SupportFlow()->get_thread_respondents( $thread->ID, array( 'fields' => 'emails' ) );

		// Don't email the person creating the reply, unless that's desired behavior
		if ( ! apply_filters( 'supportflow_emails_notify_creator', false, 'reply' ) ) {
			$key = array_search( get_post_meta( $reply->ID, 'reply_author_email' ), $respondents );
			if ( false !== $key ) {
				unset( $respondents[$key] );
			}
		}

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . get_the_title( $thread->ID );
		$subject = apply_filters( 'supportflow_emails_reply_notify_subject', $subject, $reply_id, $thread->ID, 'respondent' );

		$message = stripslashes( $reply->post_content );
		if ( $attachment_ids = get_post_meta( $reply->ID, 'attachment_ids', true ) ) {
			$message .= "\n";
			foreach ( $attachment_ids as $attachment_id ) {
				$message .= "\n" . wp_get_attachment_url( $attachment_id );
			}
		}

		$message = apply_filters( 'supportflow_emails_reply_notify_message', $message, $reply_id, $thread->ID, 'respondent' );

		foreach ( $respondents as $respondent_email ) {
			self::mail( $respondent_email, $subject, $message );
		}
	}

	/**
	 * Send an email from SupportFlow
	 */
	public function mail( $respondent_email, $subject, $message ) {

		add_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_wp_mail_from_name' ) );
		wp_mail( $respondent_email, $subject, $message );
		remove_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'filter_wp_mail_from_name' ) );
	}

	/**
	 * Filter the 'from address' value used by wp_mail
	 */
	public function filter_wp_mail_from( $from_email ) {
		if ( $this->from_email ) {
			return $this->from_email;
		} else {
			$from_email;
		}
	}

	/**
	 * Filter the 'from name' value used by wp_mail
	 */
	public function filter_wp_mail_from_name( $from_name ) {
		if ( $this->from_name ) {
			return $this->from_name;
		} else {
			$from_name;
		}
	}
}

SupportFlow()->extend->emails = new SupportFlow_Emails();