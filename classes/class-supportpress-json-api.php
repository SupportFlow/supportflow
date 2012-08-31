<?php
/**
 *
 */

class SupportPressJSONAPI extends SupportPress {

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'wp_ajax_supportpress', array( $this, 'action_wp_ajax_supportpress' ) );
	}

	public function action_wp_ajax_supportpress() {
		global $current_user;

		if ( empty( $_REQUEST['spaction'] ) )
			die( 'invalid sp action' ); // @todo: change back to 0

		switch ( $_REQUEST['spaction'] ) {

			case 'mythreads':
				$threads = SupportPress()->get_threads_for_respondent( $current_user->user_email );

				foreach ( $threads as $key => $thread ) {
					$threads[$key]->permalink = get_permalink( $thread->ID );
				}

				//var_dump( $threads ); exit();

				// @todo: Strip out some unnecessary data for size reasons
				wp_send_json( $threads );
		}
	}
}

SupportPress()->extend->jsonapi = new SupportPressJSONAPI();