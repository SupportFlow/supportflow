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

	/**
	 * This variable will need to be replaced with a UI or whatnot
	 * so that this plugin here can run on a different blog than
	 * the blog that SupportPress is running on.
	 */
	$supportpress_url = site_url();


	$script_slug = 'supportpress-user-widget';

	wp_enqueue_script(
		$script_slug,
		$supportpress_url . '/wp-content/plugins/supportpress/js/supportpress-user-widget.js',
		array( 'jquery' ),
		mt_rand(), // For cache busting during development
		true
	);

	wp_localize_script(
		$script_slug,
		'SupportPressUserWidgetVars',
		array(
			'supportpressurl' => $supportpress_url,
		)
	);
} );

add_action( 'wp_footer', function() {
	echo '<div id="supportpress-widget" style="width:300px;height:500px;position:fixed;bottom:0px;right:0px;background-color:white;padding:25px;z-index:10000;border:10px solid black;">Loading...</div>';
} );