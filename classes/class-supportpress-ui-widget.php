<?php

class SupportPress_UI_Widget extends SupportPress {

	public $script_slug = 'supportpress-user-widget';

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );

		if ( ! empty( $_GET['supportpress_widget'] ) )
			show_admin_bar( false );
	}

	public function setup_actions() {
		add_action( 'template_redirect', array( $this, 'action_template_redirect' ) );
	}

	// @todo: Pretty URLs
	public function action_template_redirect() {
		global $current_user;

		if ( empty( $_GET['supportpress_widget'] ) )
			return;

		wp_enqueue_script(
			$this->script_slug,
			plugins_url( 'js/supportpress-user-widget.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			mt_rand() // For cache busting during development
		);

		wp_localize_script(
			$this->script_slug,
			'SupportPressUserWidgetVars',
			array(
				'ajaxurl' => add_query_arg( 'action', SupportPress()->extend->jsonapi->action, admin_url( 'admin-ajax.php' ) ),
			)
		);

		wp_enqueue_style(
			$this->script_slug,
			plugins_url( 'css/widget.css', dirname( __FILE__ ) ),
			array(),
			mt_rand() // For cache busting during development
		);

		// @todo: Templating or something I guess
?>
<html>
<head>
	<title><?php _e( 'Support', 'supportpress' ); ?></title>

	<?php wp_head(); ?>
</head>
<body>

<div id="supportpress-widget">
	<h1 class="title"><?php _e( 'Support', 'supportpress' ); ?></h1>

	<div id="supportpress-newthread-box">
		<button id="supportpress-newthread"><?php _e( 'Start a new thread', 'supportpress' ); ?></button>
		<form id="supportpress-newthread-form">
			<input type="text" name="subject" placeholder="<?php esc_attr_e( 'What can we help with?', 'supportpress' ); ?>" />
			<textarea name="message" cols="25" rows="10" placeholder="<?php esc_attr_e( 'Tell us a bit more...', 'supportpress' ); ?>"></textarea>
			<input type="submit" name="submit" value="<?php esc_attr_e( 'Send reply', 'supportpress' ); ?>" />
		</form>
	</div>

	<div id="supportpress-open-tickets">
<?php
		$threads = SupportPress()->get_threads_for_respondent( $current_user->user_email );

		if ( $threads->have_posts() ) {
			while ( $threads->have_posts() ) {
				$threads->the_post();
?>
		<div class="thread">
			<h3><?php the_title(); ?></h3>

			<p>Output ticket details here. I'll leave this to Daniel -- running out of time.</p>

			<span class="time"><?php printf( __( '%s @ %s', 'supportpress' ), get_the_date(), get_the_time() ); ?></span>
		</div>
<?php
			} // while
			wp_reset_postdata();
		} else {
			echo '<div class="thread nothreads">' . __( 'No open threads found.', 'supportpress' ) . '</div>';
		}
?>
	</div>
</div>

</body>
</html>
<?php

		exit();
	}
}

SupportPress()->extend->ui->widget = new SupportPress_UI_Widget();