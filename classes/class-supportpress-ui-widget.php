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
			case 'get-thread':
				$response['widget_title'] = get_the_title( (int)$response['thread_id'] );
				$response['html'] = $this->render_single_thread_comments_html( (int)$response['thread_id'] );
				break;
		}
		return $response;
	}

	public function render_single_thread_comments_html( $thread_id ) {

		$comments = SupportPress()->get_thread_comments( $thread_id, array( 'comment_approved' => 'public', 'order' => 'ASC' ) );

		$output = '<ul class="thread-comments">';
		foreach( $comments as $comment ) {
			$output .= '<li>' . $this->render_single_comment_html( $comment ) . '</li>';
		}
		$output .= '</ul>';
		return $output;
	}

	public function render_single_comment_html( $comment ) {
		$comment_timestamp = get_comment_date( 'M. n', $comment->comment_ID );

		$output = '<div class="thread-comment-meta">'
				. '<span class="thread-comment-author">' . esc_html( $comment->comment_author ) . '</span>'
				. '<span class="thread-comment-timestamp">' . esc_html( $comment_timestamp ) . '</span>'
				. '</div>'
				. '<div class="thread-comment-body">'
				. wpautop( stripslashes( $comment->comment_content ) )
				. '</div>';
		return $output;
	}

	public function render_all_threads_html() {
		$user = wp_get_current_user();

		$threads = SupportPress()->get_threads( array( 'respondent_email' => $user->user_email ) );

		if ( empty( $threads ) ) {
			$output = '<div class="thread nothreads">' . __( 'No open threads.', 'supportpress' ) . '</div>';
		} else {
			$output = '<ul id="respondent-threads">';
			foreach( $threads as $thread ) {
				$output .= '<li id="thread-' . $thread->ID . '">';
				$output .= '<h4 class="thread-title">' . get_the_title( $thread->ID ) . '</h4>';
				$output .= '<div class="thread-comments">';
				$comments = SupportPress()->get_thread_comments( $thread->ID, array( 'comment_approved' => 'public' ) );
				$last_comment = array_shift( $comments );
				$output .= $this->render_single_comment_html( $last_comment );
				$output .= '</div>';
				$output .= '</li>';
			}
			$output .= '</ul>';
		}
		return $output;

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
		); ?>
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
<?php echo $this->render_all_threads_html(); ?>
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