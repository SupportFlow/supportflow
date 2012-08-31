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

		// @todo: Templating or something I guess
?>
<html>
<head>
	<title>SupportPress</title>

	<?php wp_head(); ?>
</head>
<body>

<div id="supportpress-widget">

</div>

</body>
</html>
<?php

		exit();
	}
}

SupportPress()->extend->ui->widget = new SupportPress_UI_Widget();