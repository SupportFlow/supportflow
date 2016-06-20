<?php

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_UI_Widget {

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
			case 'create-ticket':
			case 'get-ticket':
				$response['widget_title'] = get_the_title( (int) $response['ticket_id'] );
				$response['html']         = $this->render_single_ticket_replies_html( (int) $response['ticket_id'] );
				break;
			case 'add-ticket-reply':
				$reply            = get_post( $response['comment_id'] );
				$response['html'] = '<li>' . $this->render_single_reply_html( $reply ) . '</li>';
				break;
		}

		return $response;
	}

	public function render_single_ticket_replies_html( $ticket_id ) {

		$replies = SupportFlow()->get_ticket_replies( $ticket_id, array( 'status' => 'public', 'order' => 'ASC' ) );

		$output = '<ul class="ticket-replies">';
		foreach ( $replies as $reply ) {
			$output .= '<li>' . $this->render_single_reply_html( $reply ) . '</li>';
		}
		$output .= '</ul>';

		return $output;
	}

	public function render_single_reply_html( $reply ) {
		$reply_timestamp = mysql2date( 'M. n', $reply->post_date_gmt );
		$reply_author    = get_post_meta( $reply->ID, 'reply_author', true );

		?>

		<div class="ticket-reply-body">
			<?php echo wp_kses( wpautop( stripslashes( $reply->post_content ) ), 'post' ); ?>
		</div>

		<div class="ticket-reply-meta">
			<span class="ticket-reply-author"><?php echo esc_html( $reply_author ); ?></span>
			<span class="ticket-reply-timestamp"><?php echo esc_html( $reply_timestamp ); ?></span>
		</div>

		<?php
	}

	public function render_all_tickets_html() {
		$user = wp_get_current_user();
		$tickets = SupportFlow()->get_tickets( array( 'customer_email' => $user->user_email ) );

		?>

		<?php if ( empty( $tickets ) ) : ?>

			<div class="ticket notickets">
				<?php esc_html_e( 'No open tickets.', 'supportflow' ); ?>
			</div>

		<?php else : ?>

			<ul id="customer-tickets">
				<?php foreach ( $tickets as $ticket ) : ?>
					<?php
						$replies    = SupportFlow()->get_ticket_replies( $ticket->ID, array( 'status' => 'public' ) );
						$last_reply = array_shift( $replies );
					?>

					<li id="ticket-<?php echo esc_attr( $ticket->ID ); ?>">
						<h4 class="ticket-title">
							<?php echo esc_html( get_the_title( $ticket->ID ) ); ?>
						</h4>

						<div class="ticket-replies">
							<?php $this->render_single_reply_html( $last_reply ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>

		<?php
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

		$start_ticket_text    = __( 'Start ticket', 'supportflow' );
		$starting_ticket_text = __( 'Starting ticket...', 'supportflow' );
		$send_reply_text      = __( 'Send reply', 'supportflow' );
		$sending_reply_text   = __( 'Sending reply...', 'supportflow' );
		wp_localize_script(
			$this->script_slug,
			'SupportFlowUserWidgetVars',
			array(
				'ajaxurl'              => $ajaxurl,
				'widget_title'         => $widget_title,
				'start_ticket_text'    => $start_ticket_text,
				'starting_ticket_text' => $starting_ticket_text,
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
			<button id="supportflow-back"><?php _e( 'All Tickets', 'supportflow' ); ?></button>
			<h1 id="widget-title"><?php echo esc_html( $widget_title ); ?></h1>

			<div id="supportflow-newticket-box">
				<button id="supportflow-newticket"><?php _e( 'Start a new ticket', 'supportflow' ); ?></button>
				<form id="supportflow-newticket-form">
					<input type="text" id="new-ticket-subject" name="new-ticket-subject" class="ticket-subject" placeholder="<?php esc_attr_e( 'What can we help with?', 'supportflow' ); ?>" autocomplete="off" />
					<textarea id="new-ticket-message" name="new-ticket-message" class="ticket-message" cols="25" rows="6" placeholder="<?php esc_attr_e( 'Tell us a bit more...', 'supportflow' ); ?>" autocomplete="off"></textarea>
					<input id="new-ticket-submit" type="submit" name="new-ticket-submit" class="submit-button" value="<?php echo esc_attr( $start_ticket_text ); ?>" />
				</form>
			</div>

			<div id="supportflow-all-tickets">
				<?php $this->render_all_tickets_html(); ?>
			</div>

			<div id="supportflow-single-ticket">
				<div id="supportflow-ticket-body">
				</div>
				<form id="supportflow-existing-ticket-form">
					<textarea id="existing-ticket-message" name="existing-ticket-message" class="ticket-message" cols="25" rows="6" autocomplete="off"></textarea>
					<input id="existing-ticket-submit" type="submit" name="existing-ticket-submit" class="submit-button" value="<?php echo esc_attr( $send_reply_text ); ?>" />
					<input id="existing-ticket-id" name="ticket-id" type="hidden" />
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
