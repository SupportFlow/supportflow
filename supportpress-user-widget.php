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

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script( 'jquery' );
} );

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

?>

<div id="supportpress-widget" style="display:none;  position:fixed;bottom:0px;right:0px;z-index:10000; width:400px;height:90%;  border: 5px solid black;">
	<iframe width="100%" height="100%" src="<?php echo esc_url( add_query_arg( $query_arg, 1, $supportpress_install_url ) ); ?>"></iframe>
</div>

<button style="position:fixed;bottom:10px;right:10px;z-index:10000;" onclick="jQuery(this).hide();jQuery('#supportpress-widget').slideDown();">Support</button>

<?php
} );