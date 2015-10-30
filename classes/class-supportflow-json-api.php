<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_JSON_API {

	public $action = 'supportflow_json';

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'wp_ajax_' . $this->action, array( $this, 'action_wp_ajax_supportflow_json' ) );
	}

	public function action_wp_ajax_supportflow_json() {
		$response = array(
			'api-action' => ( ! empty( $_REQUEST['api-action'] ) ) ? sanitize_key( $_REQUEST['api-action'] ) : '',
			'status'     => 'error',
			'message'    => '',
			'html'       => '',
		);

		switch ( $response['api-action'] ) {
			case 'get-customers':
				check_ajax_referer( 'get_customers', 'get_customers_nonce' );

				if ( current_user_can( 'sf_get_customers' ) ) {
					$search_for       = sanitize_text_field( isset( $_REQUEST['customers'] ) ? $_REQUEST['customers'] : '' );
					$customer_matches = SupportFlow()->get_customers( array( 'search' => $search_for ) );
				} else {
					$customer_matches = new WP_Error( 'sf_access_denied', __( 'Access denied.', 'supportflow' ) );
				}

				if ( is_wp_error( $customer_matches ) ) {
					$response['message'] = $customer_matches->get_error_message();
				} else {
					$response['query']       = $search_for;
					$response['status']      = "ok";
					$response['customers'] = $customer_matches;
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
