<?php
/**
 * Provides a way for SupportFlow to log debugging messages, and for the admin to view them
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Logger {

	function __construct() {
		add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	/**
	 * Register the notifications to happen on which actions
	 */
	public function setup_actions() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu_items' ) );
	}

	/**
	 * Log a message to the database to aid in debugging
	 *
	 * @param string $category
	 * @param string $method The calling function, e.g. __METHOD__ or __FUNCTION__
	 * @param string $message
	 * @param array  $data An associative array of relevant variables to provide context and information about the event, e.g. compact( 'foo', 'bar' )
	 */
	public static function log( $category, $method, $message, $data = array() ) {
		$max_number_log_entries = apply_filters( 'supportflow_max_number_log_entries', 30 );
		$log_entries            = get_option( 'supportflow_log' );
		$timestamp              = time();

		if ( ! is_array( $log_entries ) ) {
			$log_entries = array();
		}

		$log_entries[] = compact( 'timestamp', 'method', 'category', 'message', 'data' );

		$offset      = max( count( $log_entries ) - $max_number_log_entries, 0 );    // The count() - max_number_log_entries can potentially be negative, so max() ensures it's always >= 0
		$log_entries = array_slice( $log_entries, $offset, $max_number_log_entries );

		update_option( 'supportflow_log', $log_entries );
		do_action( 'supportflow_log', $timestamp, $method, $category, $message, $data );
	}

	/**
	 * Add a page to display log items
	 *
	 * Disabled by default to avoid cluttering the menu with something that is rarely used
	 */
	public function add_admin_menu_items() {
		if ( apply_filters( 'supportflow_show_log', false ) ) {
			add_submenu_page(
				'edit.php?post_type=' . SupportFlow()->post_type,
				__( 'Log', 'supportflow' ),
				__( 'Log', 'supportflow' ),
				'manage_options',
				'log',
				array( $this, 'markup_log_page' )
			);
		}
	}

	/**
	 * Creates the markup for the Log page
	 */
	public static function markup_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "Cheatin' uh?" );
		}

		$log_entries     = get_option( 'supportflow_log', array() );
		$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		?>

		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h1><?php _e( 'SupportFlow Log', 'supportflow' ); ?></h1>

			<p>
				<strong><?php _e( 'Warning:', 'supportflow' ); ?></strong>
				<?php _e( "These logs contain private data, so be very careful if you share them with anyone. Make sure you redact anything that you don't want to share.", 'supportflow' ); ?>
			</p>

			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e( 'Time', 'supportflow' ); ?></th>
						<th><?php _e( 'Category', 'supportflow' ); ?></th>
						<th><?php _e( 'Method', 'supportflow' ); ?></th>
						<th><?php _e( 'Message', 'supportflow' ); ?></th>
					</tr>
				</thead>

				<tbody>
					<?php foreach ( $log_entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( get_date_from_gmt( date_i18n( 'Y-m-d H:i:s', $entry['timestamp'] ), $datetime_format ) ); ?></td>
							<td><?php echo esc_html( $entry['category'] ); ?></td>
							<td><?php echo esc_html( $entry['method'] ); ?></td>
							<td>
								<h3><?php echo esc_html( $entry['message'] ); ?></h3>

								<?php if ( $entry['data'] ) : ?>
									<div>
										<pre><?php echo esc_html( print_r( $entry['data'], true ) ); ?></pre>
										<?php // Note that any boolean values in $entry['data'] will be output as an empty string because of PHP's bool->string conversion ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div> <!-- .wrap -->

		<?php
	}
}

SupportFlow()->extend->logger = new SupportFlow_Logger();
