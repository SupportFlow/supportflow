<?php

class SupportPress_UI_Widget extends SupportPress {

	public $script_slug = 'supportpress-user-widget';

	function __construct() {
		if ( ! empty( $_REQUEST['supportpress_widget'] ) )
			add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {
		add_action( 'template_redirect', array( $this, 'action_template_redirect' ) );
		add_filter( 'supportpress_json_api_response', array( $this, 'filter_json_api_response' ) );
	}

	/**
	 * Hook into the response for the JSON API to add 
	 * our HTML to render the widget areas
	 */
	public function filter_json_api_response( $response ) {

		if ( 'error' == $response['status'] )
			return $response;

		switch( $response['api-action'] ) {
			case 'create-thread':
				$response['widget_title'] = get_the_title( (int)$response['thread_id'] );
				$response['html'] = $this->render_single_thread_comments_html( (int)$response['thread_id'] );
				break;
		}
		return $response;
	}

	public function render_single_thread_comments_html( $thread_id ) {

		// @todo there's no get_thread() method right now
		$thread = get_post( $thread_id );
		$comments = SupportPress()->get_thread_comments( $thread_id, array( 'comment_type' => 'public' ) );
		setup_postdata( $thread );
		ob_start();
?><ul class="thread-comments">
<?php foreach( $comments as $comment ):
	$comment_timestamp = get_comment_date( 'M. n', $comment->comment_ID );
?><li>
		<div class="thread-comment-meta"><span class="thread-comment-author"><?php echo esc_html( $comment->comment_author ); ?></span><span class="thread-comment-timestamp"><?php echo esc_html( $comment_timestamp ); ?></span></div>
		<div class="thread-comment-body"><?php echo wpautop( stripslashes( $comment->comment_content ) ); ?></div>
	</li><?php endforeach; ?>
</ul><?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	// @todo: Pretty URLs
	public function action_template_redirect() {
		global $current_user;

		wp_enqueue_script(
			$this->script_slug,
			plugins_url( 'js/supportpress-user-widget.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			mt_rand() // For cache busting during development
		);

		$ajaxurl = add_query_arg( 'action', SupportPress()->extend->jsonapi->action, admin_url( 'admin-ajax.php' ) );

		$widget_title = __( 'Support', 'supportpress' );

		$start_thread_text = __( 'Start thread', 'supportpress' );
		$starting_thread_text = __( 'Starting thread...', 'supportpress' );
		$send_reply_text = __( 'Send reply', 'supportpress' );
		$sending_reply_text = __( 'Sending reply...', 'supportpress' );
		wp_localize_script(
			$this->script_slug,
			'SupportPressUserWidgetVars',
			array(
				'ajaxurl'                       => $ajaxurl,
				'widget_title'                  => $widget_title,
				'start_thread_text'             => $start_thread_text,
				'starting_thread_text'          => $starting_thread_text,
				'send_reply_text'               => $send_reply_text,
				'sending_reply_text'            => $sending_reply_text,
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
	<h1 id="widget-title"><?php echo $widget_title; ?></h1>

	<div id="supportpress-newthread-box">
		<button id="supportpress-newthread"><?php _e( 'Start a new thread', 'supportpress' ); ?></button>
		<form id="supportpress-newthread-form">
			<input type="text" id="new-thread-subject" name="new-thread-subject" placeholder="<?php esc_attr_e( 'What can we help with?', 'supportpress' ); ?>" autocomplete="off" />
			<textarea id="new-thread-message" name="new-thread-message" cols="25" rows="6" placeholder="<?php esc_attr_e( 'Tell us a bit more...', 'supportpress' ); ?>" autocomplete="off"></textarea>
			<input id="new-thread-submit" type="submit" name="new-thread-submit" value="<?php echo esc_attr( $start_thread_text ); ?>" />
		</form>
	</div>

	<div id="supportpress-all-threads">
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
			echo '<div class="thread nothreads">' . __( 'No open threads.', 'supportpress' ) . '</div>';
		}
?>
	</div>

	<div id="supportpress-single-thread">
		<div id="supportpress-thread-body">
		</div>
		<form id="supportpress-existing-thread-form">
			<textarea id="existing-thread-message" name="existing-thread-message" cols="25" rows="6" autocomplete="off"></textarea>
			<input id="existing-thread-submit" type="submit" name="existing-thread-submit" value="<?php echo esc_attr( $send_reply_text ); ?>" />
			<input id="thread-id" name="thread-id" type="hidden" />
		</form>
	</div>
</div>

</body>
</html>
<?php

		exit();
	}
}

SupportPress()->extend->ui->widget = new SupportPress_UI_Widget();