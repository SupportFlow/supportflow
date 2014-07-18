<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Generates a table with minimal options
 */
class SupportFlow_Table extends WP_List_Table {
	protected $no_item_message;

	/**
	 * @param string $table_class Class to be used by table
	 */
	function __construct( $table_class = '' ) {
		if ( $table_class ) {
			parent::__construct( array( 'screen' => 'sf_statistics_table' ) );
		} else {
			parent::__construct();
		}
	}

	protected function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	/**
	 * Set message to be display if table contains no items
	 *
	 * @param string $msg
	 */
	public function set_no_items( $msg ) {
		$this->no_item_message = $msg;
	}

	function no_items() {
		$no_item_message = $this->no_item_message;

		if ( $no_item_message ) {
			echo $no_item_message;
		} else {
			parent::no_items();
		}
	}

	/**
	 * Set columns that should be displayed in table
	 *
	 * @param array $columns
	 */
	function set_columns( $columns ) {
		$this->_column_headers = array( $columns, array(), array() );
	}

	/**
	 * Set data that should be displayed in table
	 *
	 * @param array $data
	 */
	function set_data( $data ) {
		$this->items = $data;
	}
}
