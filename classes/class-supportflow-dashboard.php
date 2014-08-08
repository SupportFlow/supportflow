<?php
/**
 * Loads dashboard widgets
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Dashboard extends SupportFlow {

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'wp_dashboard_setup', array( $this, 'action_wp_dashboard_setup' ) );
	}

	function action_wp_dashboard_setup() {
		if ( current_user_can( 'edit_posts' ) ) {
			if ( SupportFlow()->script_dev ) {
				$handle = SupportFlow()->enqueue_script( 'supportflow-statistics', 'toggle-links.js' );
				SupportFlow()->enqueue_style( 'supportflow-dashboard', 'dashboard.css' );
			} else {
				$handle = SupportFlow()->enqueue_scripts();
				SupportFlow()->enqueue_styles();
			}

			wp_localize_script( $handle, 'SFToggleLinks', array(
				'expand'   => __( 'Expand', 'supportflow' ),
				'collapse' => __( 'Collapse', 'supportflow' ),
			) );

			wp_add_dashboard_widget(
				'sf_recent_tickets',
				__( "Recent tickets", 'supportflow' ),
				array( $this, 'action_sf_recent_tickets' )
			);

			wp_add_dashboard_widget(
				'sf_my_tickets',
				__( "My assigned tickets", 'supportflow' ),
				array( $this, 'action_sf_my_tickets' )
			);
		}

	}

	/*
	 * Show unassigned tickets when query author is 0
	 */
	public function filter_author_clause( $clauses, $query ) {
		if ( isset( $query->query['author'] ) && 0 === $query->query['author'] ) {
			$clauses['where'] .= ' AND post_author = 0 ';
		}

		return $clauses;
	}


	function action_sf_recent_tickets() {
		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_tickets'] ) {
				$status_slugs[] = $status;
			}
		}

		$args = array(
			'post_type'   => SupportFlow()->post_type,
			'post_parent' => 0,
			'post_status' => $status_slugs,
			'author'      => 0,
			'numberposts' => 10,
		);

		add_filter( 'posts_clauses', array( $this, 'filter_author_clause' ), 10, 2 );
		$wp_query = new WP_Query( $args );
		remove_filter( 'posts_clauses', array( $this, 'filter_author_clause' ) );

		$tickets = $wp_query->posts;

		$table = new SupportFlow_Table;

		$no_items = '<a href="post-new.php?post_type=' . SupportFlow()->post_type . '">' . __( '<b>Click here</b>' ) . '</a>';
		$no_items = sprintf( __( 'No matching ticket exists. %s to create new.', 'supportflow' ), $no_items );
		$table->set_no_items( $no_items );

		$table->set_columns( array(
			'title'    => __( 'Subject', 'supportflow' ),
			'status'   => __( 'Status', 'supportflow' ),
			'datetime' => __( 'Created', 'supportflow' ),
		) );

		$data = array();
		foreach ( $tickets as $ticket ) {
			$post_date    = strtotime( $ticket->post_date );
			$time_created = time() - strtotime( $ticket->post_date );
			if ( $time_created > 2 * DAY_IN_SECONDS ) {
				$class = 'two_day_old ';
			} elseif ( $time_created > DAY_IN_SECONDS ) {
				$class = 'one_two_day_old';
			} else {
				$class = 'one_day_old';
			}
			$title  = '<b>' . esc_html( $ticket->post_title ) . '</b>';
			$title  = "<a class='$class' href='post.php?post=" . $ticket->ID . "&action=edit'>" . $title . "</a>";
			$data[] = array(
				'title'    => $title,
				'status'   => esc_html( $statuses[$ticket->post_status]['label'] ),
				'datetime' => sprintf( __( '%s ago', 'supportflow' ), human_time_diff( time(), $post_date ) ),
			);
		}

		$table->set_data( $data );
		$table->display();
	}


	function action_sf_my_tickets() {
		$statuses     = SupportFlow()->post_statuses;
		$status_slugs = array();

		foreach ( $statuses as $status => $status_data ) {
			if ( true == $status_data['show_tickets'] ) {
				$status_slugs[] = $status;
			}
		}

		$table   = new SupportFlow_Table;
		$user_id = get_current_user_id();

		foreach ( $status_slugs as $status_slug ) {

			$args = array(
				'post_type'   => SupportFlow()->post_type,
				'post_parent' => 0,
				'post_status' => $status_slug,
				'author'      => $user_id,
			);

			$wp_query = new WP_Query( $args );
			$tickets  = $wp_query->posts;

			$no_items = '<a href="post-new.php?post_type=' . SupportFlow()->post_type . '">' . __( '<b>Click here</b>' ) . '</a>';
			$no_items = sprintf( __( 'No matching ticket exists. %s to create new.', 'supportflow' ), $no_items );
			$table->set_no_items( $no_items );

			$table->set_columns( array(
				'title'    => __( 'Subject', 'supportflow' ),
				'modified' => __( 'Last modified', 'supportflow' ),
				'datetime' => __( 'Created', 'supportflow' ),
			) );

			$data = array();
			foreach ( $tickets as $ticket ) {
				$post_date     = strtotime( $ticket->post_date );
				$post_modified = strtotime( $ticket->post_modified );
				$title         = '<b>' . esc_html( $ticket->post_title ) . '</b>';
				$title         = "<a href='post.php?post=" . $ticket->ID . "&action=edit'>" . $title . "</a>";
				$data[]        = array(
					'title'    => $title,
					'modified' => sprintf( __( '%s ago', 'supportflow' ), human_time_diff( time(), $post_modified ) ),
					'datetime' => sprintf( __( '%s ago', 'supportflow' ), human_time_diff( time(), $post_date ) ),
				);
			}
			$table->set_data( $data );

			echo '<div class="container">';
			echo "<h3 class='toggle-link'>" . $statuses[$status_slug]['label'] . "</h3>";

			echo "<div class='toggle-content'>";
			$table->display();
			echo "</div>";

			echo '</div>';
		}

	}

}

SupportFlow()->extend->dashboard = new SupportFlow_Dashboard();
