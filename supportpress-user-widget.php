<?php

/**
 * Plugin Name: SupportPress: User Widget
 * Plugin URI:  http://supportpress.com/
 * Description: Displays a widget on the front end of any blog allowing interaction with a local or remote SupportPress install.
 * Author:      Daniel Bachhuber, Alex Mills, Andrew Spittle, Automattic
 * Author URI:  http://automattic.com/
 * Version:     0.1
 *
 * Text Domain: supportpress
 * Domain Path: /languages/
 */

add_action( 'wp_footer', function() {
	$query_arg = 'supportpress_widget';

	// If being run on the same site as SupportPress, prevent infinite widgets
	if ( ! empty( $_GET[$query_arg] ) )
		return;

	/**
	 * This variable will need to be replaced with a UI or whatnot
	 * so that this plugin here can run on a different blog than
	 * the blog that SupportPress is running on.
	 */
	$supportpress_install_url = home_url();

	echo '<div id="supportpress-widget" style="width:400px;height:500px;position:fixed;bottom:0px;right:0px;background-color:white;z-index:10000;border:10px solid black;">';
	echo '<iframe width="100%" height="100%" src="' . esc_url( add_query_arg( $query_arg, 1, $supportpress_install_url ) ) . '"></iframe>';
	echo '</div>';
} );