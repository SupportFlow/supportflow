<?php
/**
 * Show SupportFlow thread statistics
 *
 * @since    0.1
 */

class SupportFlow_Statistics extends SupportFlow {

	public function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

	}

}

SupportFlow()->extend->statistics = new SupportFlow_Statistics();
