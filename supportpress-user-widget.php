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

add_action( 'wp_enqueue_scripts',      'sp_user_widget_enqueue_scripts' );
add_action( 'admin_enqueue_scripts',   'sp_user_widget_enqueue_scripts' );
function sp_user_widget_enqueue_scripts() {
	wp_enqueue_script( 'jquery' );
}

add_action( 'wp_footer',        'sp_user_widget_load_footer' );
add_action( 'admin_footer',     'sp_user_widget_load_footer' );
function sp_user_widget_load_footer() {
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

<div id="supportpress-widget" style="display:none;  position:fixed;bottom:0px;right:0px;z-index:10000; width:350px;height:96%;background-color:#f9f9f9;box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);-webkit-box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);-moz-box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);">
	<button id="supportpress-widget-close" style="position:absolute;top:12px;right:10px;" onclick="jQuery('#supportpress-widget').slideUp(function(){jQuery('#supportpress-help').fadeIn();});"><?php _e( 'Close', 'supportpress-user-widget' ); ?></button>
	<iframe width="100%" height="100%" src="<?php echo esc_url( add_query_arg( $query_arg, 1, $supportpress_install_url ) ); ?>"></iframe>
</div>

<button id="supportpress-help" style="color: #fff;position: fixed;bottom: 0;right: 0;margin: 0 25px -1px 0;z-index: 10000;font: bold 14px Helvetica, Arial, sans-serif;padding: 10px;background-color: #21759b;border: 1px solid #fff;border-bottom: none;-moz-box-shadow: 0px 0px 6px rgba(102, 102, 102, .6);-webkit-box-shadow: 0px 0px 6px rgba(102, 102, 102, .6);box-shadow: 0px 0px 2px rgba(102, 102, 102, .6);-webkit-border-top-left-radius: 10px;-webkit-border-top-right-radius: 10px;-moz-border-radius-topleft: 10px;-moz-border-radius-topright: 10px;border-top-left-radius: 10px;border-top-right-radius: 10px;" onclick="jQuery(this).hide();jQuery('#supportpress-widget').slideDown();">Support</button>

<?php
}