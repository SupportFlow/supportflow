<?php

class SupportFlow_JSON_API extends SupportFlow {

	public $action = 'supportflow_json';

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'wp_ajax_' . $this->action, array( $this, 'action_wp_ajax_supportflow_json' ) );
	}

	public function action_wp_ajax_supportflow_json() {

		$current_user = wp_get_current_user();

		$response = array(
			'api-action' => ( ! empty( $_REQUEST['api-action'] ) ) ? sanitize_key( $_REQUEST['api-action'] ) : '',
			'status'     => 'error',
			'message'    => '',
			'html'       => '',
		);
		switch ( $response['api-action'] ) {

			case 'create-thread':

				$thread_args = array(
					'subject' => sanitize_text_field( $_REQUEST['subject'] ),
					'message' => wp_filter_nohtml_kses( $_REQUEST['message'] ),
				);
				if ( ! empty( $_REQUEST['respondent_email'] ) && is_email( $_REQUEST['respondent_email'] ) ) {
					$thread_args['respondent_email'] = sanitize_email( $_REQUEST['respondent_email'] );
					if ( ! empty( $_REQUEST['respondent_name'] ) ) {
						$thread_args['respondent_name'] = sanitize_text_field( $_REQUEST['respondent_name'] );
					}
				} else {
					$thread_args['respondent_email'] = $current_user->user_email;
					$thread_args['respondent_name']  = $current_user->display_name;
					$thread_args['respondent_id']    = $current_user->ID;
				}

				$thread_id = SupportFlow()->create_thread( $thread_args );
				if ( is_wp_error( $thread_id ) ) {
					$response['message'] = $thread_id->get_error_message();
				} else {
					$response['status']    = 'ok';
					$response['thread_id'] = $thread_id;
				}
				break;

			case 'get-respondent-threads':

				break;

			case 'get-respondents':
				$search_for         = sanitize_text_field( $_REQUEST['respondents'] );
				$respondent_matches = SupportFlow()->get_respondents( array( 'search' => $search_for ) );
				if ( is_wp_error( $respondent_matches ) ) {
					$response['message'] = $respondent_matches->get_error_message();
				} else {
					$response['query']       = $search_for;
					$response['status']      = "ok";
					$response['respondents'] = $respondent_matches;
				}

				break;

			case 'get-thread':
				$thread_id             = (int) $_REQUEST['thread_id'];
				$response['status']    = 'ok';
				$response['thread_id'] = $thread_id;
				break;
			case 'add-thread-reply':
				$thread_id  = (int) $_REQUEST['thread_id'];
				$message    = wp_filter_nohtml_kses( $_REQUEST['message'] );
				$reply_args = array();
				if ( ! empty( $_REQUEST['reply_author_email'] ) && is_email( $_REQUEST['reply_author_email'] ) ) {
					$reply_args['reply_author_email'] = sanitize_email( $_REQUEST['reply_author_email'] );
					if ( ! empty( $_REQUEST['reply_author'] ) ) {
						$reply_args['reply_author'] = sanitize_text_field( $_REQUEST['reply_author'] );
					}
				} else {
					$reply_args['reply_author_email'] = $current_user->user_email;
					$reply_args['reply_author']       = $current_user->display_name;
					$reply_args['user_id']            = $current_user->ID;
				}
				$reply_id = SupportFlow()->add_thread_reply( $thread_id, $message );
				if ( is_wp_error( $reply_id ) ) {
					$response['message'] = $reply_id->get_error_message();
				} else {
					$response['status']    = 'ok';
					$response['thread_id'] = $thread_id;
					$response['reply_id']  = $reply_id;
				}
				break;
			default:
				$response['message'] = __( "There's no API method registered under that action.", 'supportflow' );
				break;
		}

		$response = apply_filters( 'supportflow_json_api_response', $response );
		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo json_encode( $response );
		die();
	}
}

SupportFlow()->extend->jsonapi = new SupportFlow_JSON_API();