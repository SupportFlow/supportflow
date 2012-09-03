<?php

class SupportPress_JSON_API extends SupportPress {

	public $action = 'supportpress_json';

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'wp_ajax_' . $this->action, array( $this, 'action_wp_ajax_supportpress_json' ) );
	}

	public function action_wp_ajax_supportpress_json() {

		$current_user = wp_get_current_user();

		$response = array(
				'api-action'           => ( ! empty( $_REQUEST['api-action'] ) ) ? sanitize_key( $_REQUEST['api-action'] ) : '',
				'status'              => 'error',
				'message'             => '',
				'html'                => '',
			);
		switch ( $response['api-action'] ) {

			case 'create-thread':

				$thread_args = array(
						'subject'                    => sanitize_text_field( $_REQUEST['subject'] ),
						'message'                    => wp_filter_nohtml_kses( $_REQUEST['message'] ),
					);
				if ( !empty( $_REQUEST['respondent_email'] ) && is_email( $_REQUEST['respondent_email'] ) ) {
					$thread_args['respondent_email'] = sanitize_email( $_REQUEST['respondent_email'] );
					if ( !empty( $_REQUEST['respondent_name'] ) )
						$thread_args['respondent_name'] = sanitize_text_field( $_REQUEST['respondent_name'] );
				} else {
					$thread_args['respondent_email'] = $current_user->user_email;
					$thread_args['respondent_name'] = $current_user->display_name;
					$thread_args['respondent_id'] = $current_user->ID;
				}

				$thread_id = SupportPress()->create_thread( $thread_args );
				if ( is_wp_error( $thread_id ) ) {
					$response['message'] = $thread_id->get_error_message();
				} else {
					$response['status'] = 'ok';
					$response['thread_id'] = $thread_id;
				}
				break;

			case 'get-respondent-threads':

				break;

			case 'get-thread':
				$thread_id = (int)$_REQUEST['thread_id'];
				$response['status'] = 'ok';
				$response['thread_id'] = $thread_id;
				break;
			case 'add-thread-comment':
				$thread_id = (int)$_REQUEST['thread_id'];
				$message = wp_filter_nohtml_kses( $_REQUEST['message'] );
				$comment_args = array();
				if ( !empty( $_REQUEST['comment_author_email'] ) && is_email( $_REQUEST['comment_author_email'] ) ) {
					$comment_args['comment_author_email'] = sanitize_email( $_REQUEST['comment_author_email'] );
					if ( !empty( $_REQUEST['comment_author'] ) )
						$comment_args['comment_author'] = sanitize_text_field( $_REQUEST['comment_author'] );
				} else {
					$comment_args['comment_author_email'] = $current_user->user_email;
					$comment_args['comment_author'] = $current_user->display_name;
					$comment_args['user_id'] = $current_user->ID;
				}
				$comment_id = SupportPress()->add_thread_comment( $thread_id, $message);
				if ( is_wp_error( $comment_id ) ) {
					$response['message'] = $comment_id->get_error_message();
				} else {
					$response['status'] = 'ok';
					$response['thread_id'] = $thread_id;
					$response['comment_id'] = $comment_id;
				}
				break;
			default:
				$response['message'] = __( "There's no API method registered under that action.", 'supportpress' );
				break;
		}

		$response = apply_filters( 'supportpress_json_api_response', $response );
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo json_encode( $response );
		die();
	}
}

SupportPress()->extend->jsonapi = new SupportPress_JSON_API();