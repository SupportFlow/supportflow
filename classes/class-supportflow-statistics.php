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
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
	}

	public function action_admin_menu() {
		$this->slug = 'sf_statistics';

		// Creates a submenu in SupportFlow menu
		add_submenu_page(
			'edit.php?post_type=' . SupportFlow()->post_type,
			__( 'Statistics', 'supportflow' ),
			__( 'Statistics', 'supportflow' ),
			'manage_options',
			$this->slug,
			array( $this, 'statistics_page' )
		);
	}

	/**
	 * Display whole statistics page
	 */
	public function statistics_page() {
		// Add JS and CSS code required by page
		$this->insert_css_code();
		$this->insert_js_code()
		?>
		<div class="wrap">
			<h2><?php _e( 'Statistics', 'supportflow' ) ?></h2>
			<br />

			<div class="stat-box">
				<a class="toggle-link" href="#">
					<h3><?php _e( 'Overall statistics', 'supportflow' ) ?></h3>
				</a>

				<div class="toggle-content">
					<?php $this->show_overall_stats() ?>
				</div>
			</div>

			<div class="stat-box">
				<a class="toggle-link" href="#">
					<h3><?php _e( 'Tickets distribution by tag', 'supportflow' ) ?></h3>
				</a>

				<div class="toggle-content">
					<?php $this->show_tag_stats() ?>
				</div>
			</div>

			<div class="stat-box">
				<a class="toggle-link" href="#">
					<h3><?php _e( 'Daily statistics for last 30 days', 'supportflow' ) ?></h3>
				</a>

				<div class="toggle-content">
					<?php $this->show_30_days_stats() ?>
				</div>
			</div>

			<div class="stat-box">
				<a class="toggle-link" href="#">
					<h3><?php _e( 'Month statistics for last 12 months', 'supportflow' ) ?></h3>
				</a>

				<div class="toggle-content">
					<?php $this->show_12_month_stats() ?>
				</div>
			</div>

			<div class="stat-box">
				<a class="toggle-link" href="#">
					<h3><?php _e( 'Yearly statistics for last 5 years', 'supportflow' ) ?></h3>
				</a>

				<div class="toggle-content">
					<?php $this->show_5_year_stats() ?>
				</div>
			</div>

		</div>
	<?php
	}

	/*
	 * Add CSS code required by statistics page
	 */
	function insert_css_code() {
		?>
		<style type="text/css">
			.stat-box .toggle-content {
				display: none;
			}
		</style>
	<?php
	}

	/*
	 * Add JS code required by statistics page
	 */
	function insert_js_code() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function () {
				// Close all the different stat tables and toggle the status of clicked stat table
				jQuery('.toggle-link').click(function (event) {
					var current = jQuery(this).siblings('.toggle-content').css('display');
					jQuery('.toggle-content').hide(500);
					jQuery('.toggle-link').prop('title', 'Expand');
					if ('none' == current) {
						jQuery(this).siblings('.toggle-content').show(500);
						jQuery(this).prop('title', 'Collapse');
					}
					event.preventDefault();
				});

				// Set different type of statistics link title at page load
				jQuery(jQuery('.toggle-content')).each(function () {
					if ('none' == jQuery(this).css('display')) {
						jQuery(this).siblings('.toggle-link').prop('title', 'Expand');
					} else {
						jQuery(this).siblings('.toggle-link').prop('title', 'Collapse');
					}

					jQuery('.toggle-content').first().show(500);
				});
			});
		</script>
	<?php
	}

	/*
	 * Show overall stats table.
	 * Currently shows today, yesterday and overall (whole life) ticket stats
	 */
	public function show_overall_stats() {
		$statistics_table = new SupportFlow_Statistics_Table();

		$statistics_table->set_columns( array(
			'table_date'   => __( 'Date', 'supportflow' ),
			'table_new'    => __( 'New threads', 'supportflow' ),
			'table_open'   => __( 'Open threads', 'supportflow' ),
			'table_closed' => __( 'Closed threads', 'supportflow' ),
		) );

		$labels = array( 'Today', 'Yesterday' );
		for ( $i = 0; $i < 2; $i ++ ) {
			$date_time = strtotime( '-' . $i . ' days' );

			$link_date  = date( "Ymd", $date_time );
			$link_value = date( "j-M-Y", $date_time );
			$post_date  = array(
				'year'  => (int) date( "Y", $date_time ),
				'month' => (int) date( "m", $date_time ),
				'day'   => (int) date( "j", $date_time ),
			);

			$items[] = $this->get_post_data_by_date( $post_date, $link_date, __( $labels[$i], 'supportflow' ) );
		}

		$items[] = $this->get_post_data_by_date( null, null, __( 'Total tickets', 'supportflow' ) );

		$statistics_table->set_data( $items );
		$statistics_table->display();
	}


	/*
	 * Show tickets status per tag
	 */
	public function show_tag_stats() {
		$statistics_table = new SupportFlow_Statistics_Table();

		$statistics_table->set_columns( array(
			'table_tag'    => __( 'Tag', 'supportflow' ),
			'table_new'    => __( 'New threads', 'supportflow' ),
			'table_open'   => __( 'Open threads', 'supportflow' ),
			'table_closed' => __( 'Closed threads', 'supportflow' ),
		) );

		foreach ( get_terms(SupportFlow()->tags_tax, 'hide_empty=0' ) as $tag ) {
			$items[] = $this->get_post_data_by_tag( $tag->slug, $tag->slug, $tag->name );
		}

		$statistics_table->set_data( $items );
		$statistics_table->display();
	}


	/*
	 * Shows stats of ticket created in last 30 days
	 */
	public function show_30_days_stats() {
		$statistics_table = new SupportFlow_Statistics_Table();

		$statistics_table->set_columns( array(
			'table_date'   => __( 'Date', 'supportflow' ),
			'table_new'    => __( 'New threads', 'supportflow' ),
			'table_open'   => __( 'Open threads', 'supportflow' ),
			'table_closed' => __( 'Closed threads', 'supportflow' ),
		) );

		for ( $i = 0; $i < 30; $i ++ ) {
			$date_time  = strtotime( '-' . $i . ' days' );
			$post_date  = array(
				'year'  => (int) date( "Y", $date_time ),
				'month' => (int) date( "m", $date_time ),
				'day'   => (int) date( "j", $date_time ),
			);
			$link_date  = date( "Ymd", $date_time );
			$link_value = date( "j-M-Y", $date_time );
			$items[]    = $this->get_post_data_by_date( $post_date, $link_date, $link_value );
		}

		$statistics_table->set_data( $items );
		$statistics_table->display();
	}


	/*
	 * Shows stats of ticket created in last 12 months
	 */
	public function show_12_month_stats() {
		$statistics_table = new SupportFlow_Statistics_Table();

		$statistics_table->set_columns( array(
			'table_date'   => __( 'Date', 'supportflow' ),
			'table_new'    => __( 'New threads', 'supportflow' ),
			'table_open'   => __( 'Open threads', 'supportflow' ),
			'table_closed' => __( 'Closed threads', 'supportflow' ),
		) );

		for ( $i = 0; $i < 12; $i ++ ) {
			$date_time  = strtotime( '-' . $i . 'month' );
			$post_date  = array(
				'year'  => (int) date( "Y", $date_time ),
				'month' => (int) date( "m", $date_time ),
			);
			$link_date  = date( "Ym", $date_time );
			$link_value = date( "M-Y", $date_time );
			$items[]    = $this->get_post_data_by_date( $post_date, $link_date, $link_value );
		}

		$statistics_table->set_data( $items );
		$statistics_table->display();
	}


	/*
	 * Shows stats of ticket created in last 5 years
	 */
	public function show_5_year_stats() {
		$statistics_table = new SupportFlow_Statistics_Table();

		$statistics_table->set_columns( array(
			'table_date'   => __( 'Date', 'supportflow' ),
			'table_new'    => __( 'New threads', 'supportflow' ),
			'table_open'   => __( 'Open threads', 'supportflow' ),
			'table_closed' => __( 'Closed threads', 'supportflow' ),
		) );

		for ( $i = 0; $i < 5; $i ++ ) {
			$date_time  = strtotime( '-' . $i . 'year' );
			$post_date  = array(
				'year' => (int) date( "Y", $date_time ),
			);
			$link_date  = date( "Y", $date_time );
			$link_value = date( "Y", $date_time );
			$items[]    = $this->get_post_data_by_date( $post_date, $link_date, $link_value );
		}

		$statistics_table->set_data( $items );
		$statistics_table->display();
	}


	/**
	 * Stats of post created on a particular date
	 * @param array $post_date Date in format like array('year'=>1994, 'month'=>12, 'day'=>5)
	 * @param integer $link_date Date in format used in filtering by WP in all threads page. e.g. 19941205
	 * @param string $link_value Value to show user of link in table
	 * @return array Tickets stats created on a particular date. This array is directly usable in table
	 */
	function get_post_data_by_date( $post_date = null, $link_date = null, $link_value = null ) {

		return array(
			'table_date'   => $link_value,
			'table_new'    => $this->get_post_link_by_date( $link_date, 'sf_new', $this->get_posts_count_by_date( 'sf_new', $post_date ) ),
			'table_open'   => $this->get_post_link_by_date( $link_date, 'sf_open', $this->get_posts_count_by_date( 'sf_open', $post_date ) ),
			'table_closed' => $this->get_post_link_by_date( $link_date, 'sf_closed', $this->get_posts_count_by_date( 'sf_closed', $post_date ) ),
		);
	}


	/**
	 * Stats of post created of a particular tag
	 * @param string $post_tag Slug of tag for which you want to get stats for
	 * @param integer $link_tag Tag in format used in filtering by WP in all threads page.
	 * @param string $link_value Value to show user of link in table
	 * @return array Tickets stats created for a particular tag. This array is directly usable in table
	 */
	function get_post_data_by_tag( $post_tag = null, $link_tag = null, $link_value = null ) {

		return array(
			'table_tag'    => $link_value,
			'table_new'    => $this->get_post_link_by_tag( $link_tag, 'sf_new', $this->get_posts_count_by_tag( 'sf_new', $post_tag ) ),
			'table_open'   => $this->get_post_link_by_tag( $link_tag, 'sf_open', $this->get_posts_count_by_tag( 'sf_open', $post_tag ) ),
			'table_closed' => $this->get_post_link_by_tag( $link_tag, 'sf_closed', $this->get_posts_count_by_tag( 'sf_closed', $post_tag ) ),
		);
	}


	/**
	 * Get count of post created on a particular date with particular post status.
	 * @param string $post_status Post status you want to get count. Using `*` to get count of all post statuses
	 * @param type $date Date in format like array('year'=>1994, 'month'=>12, 'day'=>5)
	 * @return integer Count of posts
	 */
	public function get_posts_count_by_date( $post_status = '*', $date = null ) {
		$args = array(
			'post_type'      => SupportFlow()->post_type,
			'posts_per_page' => 1,
			'post_parent'    => 0,
			'post_status'    => $post_status,
		);

		if ( ! is_null( $date ) ) {
			$args['date_query'] = $date;
		}

		$wp_query = new WP_Query( $args );

		return (int) $wp_query->found_posts;
	}


	/**
	 * Get count of post created of a particular tag with particular post status.
	 * @param string $post_status Post status you want to get count. Using `*` to get count of all post statuses
	 * @param type $tag Slug of tag you want to get count of
	 * @return integer Count of posts
	 */
	public function get_posts_count_by_tag( $post_status = '*', $tag = null ) {
		$args = array(
			'post_type'      => SupportFlow()->post_type,
			'posts_per_page' => 1,
			'post_parent'    => 0,
			'post_status'    => $post_status,
			'taxonomy'       => SupportFlow()->tags_tax,
		);

		if ( ! is_null( $tag ) ) {
			$args['term'] = $tag;
		}

		$wp_query = new WP_Query( $args );

		return (int) $wp_query->found_posts;
	}


	/**
	 * Generate a link that shows matching tickets in all thread page
	 * @param integer $link_date Date in format used in filtering by WP in all threads page. e.g. 19941205
	 * @param string $post_status Post statuses that should be shown in all threads page
	 * @param string $link_value Value of hyperlink that should be shown to user
	 * @return string A hyperlink
	 */
	function get_post_link_by_date( $link_date = null, $post_status = null, $link_value = null ) {
		$link = '<a href="edit.php?%s%s%s">%s</a>';

		$post_type   = 'post_type=' . SupportFlow()->post_type;
		$post_status = is_null( $post_status ) ? '' : "&post_status=$post_status";
		$date        = is_null( $link_date ) ? '' : "&m=$link_date";
		$value       = is_null( $link_value ) ? '' : "$link_value";

		return sprintf( $link, $post_type, $post_status, $date, $value );
	}


	/**
	 * Generate a link that shows matching tickets in all thread page
	 * @param string $link_tag Slug of tag
	 * @param string $post_status Post statuses that should be shown in all threads page
	 * @param string $link_value Value of hyperlink that should be shown to user
	 * @return string A hyperlink
	 */
	function get_post_link_by_tag( $link_tag = null, $post_status = null, $link_value = null ) {
		$link = '<a href="edit.php?%s%s%s">%s</a>';

		$post_type   = 'post_type=' . SupportFlow()->post_type;
		$post_status = is_null( $post_status ) ? '' : "&post_status=$post_status";
		$date        = is_null( $link_tag ) ? '' : '&' . SupportFlow()->tags_tax . "=$link_tag";
		$value       = is_null( $link_value ) ? '' : "$link_value";

		return sprintf( $link, $post_type, $post_status, $date, $value );
	}

}


/**
 * Table to show statistics
 */
class SupportFlow_Statistics_Table extends WP_List_Table {

	function __construct() {
		parent::__construct( array( 'screen' => 'sf_statistics_table' ) );
	}

	protected function column_default( $item, $column_name ) {
		return $item[$column_name];
	}

	/**
	 * Set columns that should be displayed in table
	 * @param array $columns
	 */
	function set_columns( $columns ) {
		$this->_column_headers = array( $columns, array(), array() );
	}

	/**
	 * Set data that should be displayed in table
	 * @param array $data
	 */
	function set_data( $data ) {
		$this->items = $data;
	}
}

SupportFlow()->extend->statistics = new SupportFlow_Statistics();
