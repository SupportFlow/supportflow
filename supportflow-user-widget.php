<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

add_action( 'wp_enqueue_scripts', 'sf_user_widget_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'sf_user_widget_enqueue_scripts' );
function sf_user_widget_enqueue_scripts() {
	wp_enqueue_script( 'jquery' );
}

add_action( 'wp_footer', 'sf_user_widget_load_footer' );
add_action( 'admin_footer', 'sf_user_widget_load_footer' );
function sf_user_widget_load_footer() {
	$query_arg = 'supportflow_widget';

	// If being run on the same site as SupportFlow, prevent infinite widgets
	if ( ! empty( $_GET[$query_arg] ) ) {
		return;
	}

	/**
	 * This variable will need to be replaced with a UI or whatnot
	 * so that this plugin here can run on a different blog than
	 * the blog that SupportFlow is running on.
	 */
	$supportflow_install_url = home_url();

	?>

	<div id="supportflow-widget" style="display:none;  position:fixed;bottom:0px;right:0px;z-index:10000; width:350px;height:85%;background-color:#f9f9f9;box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);-webkit-box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);-moz-box-shadow: -2px 0px 6px rgba(102, 102, 102, .6);border:1px solid #989898; border-right:none;border-bottom:none;">
		<button id="supportflow-widget-close" style="position:absolute;top:0;right:10px;cursor:pointer;color: #666;margin: -37px 25px -1px 0;z-index: 10000;font: bold 14px Helvetica, Arial, sans-serif;padding: 10px;background-color: #f9f9f9;border: 1px solid #989898;border-bottom: none;-webkit-border-top-left-radius: 10px;-webkit-border-top-right-radius: 10px;-moz-border-radius-topleft: 10px;-moz-border-radius-topright: 10px;border-top-left-radius: 10px;border-top-right-radius: 10px;" onclick="jQuery('#supportflow-widget').slideUp(function(){jQuery('#supportflow-help').fadeIn();});"><?php _e( 'Close', 'supportflow-user-widget' ); ?></button>
		<iframe width="100%" height="100%" src="<?php echo esc_url( add_query_arg( $query_arg, 1, $supportflow_install_url ) ); ?>"></iframe>
	</div>

	<button id="supportflow-help" style="cursor:pointer;color: #fff;position: fixed;bottom: 0;right: 0;margin: 0 25px -1px 0;z-index: 10000;font: bold 14px Helvetica, Arial, sans-serif;padding: 10px;background-color: #21759b;border: 1px solid #fff;border-bottom: none;-moz-box-shadow: 0px 0px 6px rgba(102, 102, 102, .6);-webkit-box-shadow: 0px 0px 6px rgba(102, 102, 102, .6);box-shadow: 0px 0px 2px rgba(102, 102, 102, .6);-webkit-border-top-left-radius: 10px;-webkit-border-top-right-radius: 10px;-moz-border-radius-topleft: 10px;-moz-border-radius-topright: 10px;border-top-left-radius: 10px;border-top-right-radius: 10px;" onclick="jQuery(this).hide();jQuery('#supportflow-widget').slideDown();">Need help?</button>

<?php
}