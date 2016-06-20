<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Generates a table with minimal options
 */
class SupportFlow_Table extends WP_List_Table {
	protected $no_item_message = null;
	protected $display_nav;
	protected $display_col_headers;

	/**
	 * @param string  $table_class         Class to be used by table
	 * @param boolean $display_nav         Show navigation area on header and footer of table. It is generally an empty area with some height
	 * @param boolean $display_col_headers Show columns name on header and footer of table
	 */
	function __construct( $table_class = '', $display_nav = true, $display_col_headers = true ) {
		if ( $table_class ) {
			parent::__construct( array( 'screen' => $table_class ) );
		} else {
			parent::__construct();
		}
		$this->display_nav         = $display_nav;
		$this->display_col_headers = $display_col_headers;
	}

	public function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	/**
	 * Set message to be display if table contains no items
	 *
	 * @param string $msg
	 */
	public function set_no_items( $msg ) {
		$allowed_html = array_merge(
			wp_kses_allowed_html( 'data' ),
			array( 'br' => array() )
		);

		$this->no_item_message = wp_kses( $msg, $allowed_html );
	}

	public function no_items() {
		$no_item_message = $this->no_item_message;

		if ( $no_item_message === null ) {
			parent::no_items();
		} else {
			echo esc_html( $no_item_message );
		}
	}

	/**
	 * Get the table's column keys and labels
	 *
	 * Normally the columns would be hardcoded in this function, but since this is a generic table class, the
	 * columns are set dynamically via set_columns().
	 *
	 * @return array
	 */
	public function get_columns() {
		return $this->_column_headers[0];
	}

	public function print_column_headers( $with_id = true ) {
		if ( $this->display_col_headers ) {
			parent::print_column_headers( $with_id );
		}
	}

	protected function display_tablenav( $which ) {
		if ( $this->display_nav ) {
			parent::display_tablenav( $which );
		}
	}

	/**
	 * Set columns that should be displayed in table
	 *
	 * @param array $columns
	 */
	public function set_columns( $columns ) {
		$this->_column_headers = array( $columns, array(), array() );
	}

	/**
	 * Set data that should be displayed in table
	 *
	 * @param array $data
	 */
	public function set_data( $data ) {
		$this->items = $data;
	}
}
