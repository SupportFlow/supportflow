<?php

class SupportFlow_UI_Widget extends SupportFlow {

	public $script_slug = 'supportflow-user-widget';

	function __construct() {
		if ( ! empty( $_REQUEST['supportflow_widget'] ) ) {
			add_action( 'supportflow_after_setup_actions', array( $this, 'setup_actions' ) );
		}
	}

	public function setup_actions() {
		add_action( 'template_redirect', array( $this, 'action_template_redirect' ) );
		add_filter( 'supportflow_json_api_response', array( $this, 'filter_json_api_response' ) );
	}

	/**
	 * Hook into the response for the JSON API to add
	 * our HTML to render the widget areas
	 */
	public function filter_json_api_response( $response ) {

		if ( 'error' == $response['status'] ) {
			return $response;
		}

		switch ( $response['api-action'] ) {
			case 'create-thread':
			case 'get-thread':
				$response['widget_title'] = get_the_title( (int) $response['thread_id'] );
				$response['html']         = $this->render_single_thread_replies_html( (int) $response['thread_id'] );
				break;
			case 'add-thread-reply':
				$reply            = get_post( $response['comment_id'] );
				$response['html'] = '<li>' . $this->render_single_reply_html( $reply ) . '</li>';
				break;
		}

		return $response;
	}

	public function render_single_thread_replies_html( $thread_id ) {

		$replies = SupportFlow()->get_thread_replies( $thread_id, array( 'status' => 'public', 'order' => 'ASC' ) );

		$output = '<ul class="thread-replies">';
		foreach ( $replies as $reply ) {
			$output .= '<li>' . $this->render_single_reply_html( $reply ) . '</li>';
		}
		$output .= '</ul>';

		return $output;
	}

	public function render_single_reply_html( $reply ) {
		$reply_timestamp = mysql2date( 'M. n', $reply->post_date_gmt );
		$reply_author    = get_post_meta( $reply->ID, 'reply_author' );
		$output          = '<div class="thread-reply-body">'
			. wpautop( stripslashes( $reply->post_content ) )
			. '</div>'
			. '<div class="thread-reply-meta">'
			. '<span class="thread-reply-author">' . esc_html( $reply_author ) . '</span>'
			. '<span class="thread-reply-timestamp">' . esc_html( $reply_timestamp ) . '</span>'
			. '</div>';

		return $output;
	}

	public function render_all_threads_html() {
		$user = wp_get_current_user();

		$threads = SupportFlow()->get_threads( array( 'respondent_email' => $user->user_email ) );

		if ( empty( $threads ) ) {
			$output = '<div class="thread nothreads">' . __( 'No open threads.', 'supportflow' ) . '</div>';
		} else {
			$output = '<ul id="respondent-threads">';
			foreach ( $threads as $thread ) {
				$output .= '<li id="thread-' . $thread->ID . '">';
				$output .= '<h4 class="thread-title">' . get_the_title( $thread->ID ) . '</h4>';
				$output .= '<div class="thread-replies">';
				$replies    = SupportFlow()->get_thread_replies( $thread->ID, array( 'status' => 'public' ) );
				$last_reply = array_shift( $replies );
				$output .= $this->render_single_reply_html( $last_reply );
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
			plugins_url( 'js/supportflow-user-widget.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			mt_rand() // For cache busting during development
		);

		$ajaxurl = add_query_arg( 'action', SupportFlow()->extend->jsonapi->action, admin_url( 'admin-ajax.php' ) );

		$widget_title = __( 'Support', 'supportflow' );

		$start_thread_text    = __( 'Start thread', 'supportflow' );
		$starting_thread_text = __( 'Starting thread...', 'supportflow' );
		$send_reply_text      = __( 'Send reply', 'supportflow' );
		$sending_reply_text   = __( 'Sending reply...', 'supportflow' );
		wp_localize_script(
			$this->script_slug,
			'SupportFlowUserWidgetVars',
			array(
				'ajaxurl'              => $ajaxurl,
				'widget_title'         => $widget_title,
				'start_thread_text'    => $start_thread_text,
				'starting_thread_text' => $starting_thread_text,
				'send_reply_text'      => $send_reply_text,
				'sending_reply_text'   => $sending_reply_text,
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
			<title><?php _e( 'Support', 'supportflow' ); ?></title>

			<?php wp_head(); ?>
		</head>
		<body>

		<div id="supportflow-widget">
			<button id="supportflow-back"><?php _e( 'All Threads', 'supportflow' ); ?></button>
			<h1 id="widget-title"><?php echo $widget_title; ?></h1>

			<div id="supportflow-newthread-box">
				<button id="supportflow-newthread"><?php _e( 'Start a new thread', 'supportflow' ); ?></button>
				<form id="supportflow-newthread-form">
					<input type="text" id="new-thread-subject" name="new-thread-subject" class="thread-subject" placeholder="<?php esc_attr_e( 'What can we help with?', 'supportflow' ); ?>" autocomplete="off" />
					<textarea id="new-thread-message" name="new-thread-message" class="thread-message" cols="25" rows="6" placeholder="<?php esc_attr_e( 'Tell us a bit more...', 'supportflow' ); ?>" autocomplete="off"></textarea>
					<input id="new-thread-submit" type="submit" name="new-thread-submit" class="submit-button" value="<?php echo esc_attr( $start_thread_text ); ?>" />
				</form>
			</div>

			<div id="supportflow-all-threads">
				<?php echo $this->render_all_threads_html(); ?>
			</div>

			<div id="supportflow-single-thread">
				<div id="supportflow-thread-body">
				</div>
				<form id="supportflow-existing-thread-form">
					<textarea id="existing-thread-message" name="existing-thread-message" class="thread-message" cols="25" rows="6" autocomplete="off"></textarea>
					<input id="existing-thread-submit" type="submit" name="existing-thread-submit" class="submit-button" value="<?php echo esc_attr( $send_reply_text ); ?>" />
					<input id="existing-thread-id" name="thread-id" type="hidden" />
				</form>
			</div>
		</div>

		</body>
		</html>
		<?php

		exit();
	}
}

SupportFlow()->extend->ui->widget = new SupportFlow_UI_Widget();