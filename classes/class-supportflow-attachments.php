<?php
/**
 * Allow attachments to be securely stored and served
 */

defined( 'ABSPATH' ) or die( "Cheatin' uh?" );

class SupportFlow_Attachments {

	function __construct() {
		add_action( 'init', array( $this, 'action_init_download_attachment' ) );
	}

	public function action_init_download_attachment() {
		if ( empty( $_REQUEST['sf_download_attachment'] ) ) {
			return;
		}

		$attachment_secret = $_REQUEST['sf_download_attachment'];
		$attachment_id = $this->get_attachment_id( $attachment_secret );

		if ( empty( $attachment_id ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! file_exists( $file ) ) {
			return;
		}
		header( 'Content-Description: File Transfer' );
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . basename($file));
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file ) );

		readfile( $file );
		exit;
	}

	public function insert_attachment_secret_key( $attachment_id ) {
		$secret = $this->get_attachment_secret_key( $attachment_id );
		if ( $secret ) {
			return $secret;
		}

		$secret = $this->generate_secret_key();
		update_post_meta( $attachment_id, 'sf_attachment_secret', $secret );

		return $secret;
	}

	public function generate_secret_key() {
		return wp_generate_password( 20, false );
	}

	public function get_attachment_secret_key( $attachment_id ) {
		$secret = get_post_meta( $attachment_id, 'sf_attachment_secret', true );

		return $secret;
	}

	public function get_attachment_id( $attachment_secret ) {
		$post_statuses = SupportFlow()->post_statuses;

		$posts = get_posts( array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => array(
				array(
					'key'   => 'sf_attachment_secret',
					'value' => $attachment_secret,
				),
			)
		) );
		if ( isset( $posts[0] ) ) {
			return $posts[0]->ID;
		} else {
			return 0;
		}
	}

	public function get_attachment_url( $attachment_id ) {
		$attachment_secret = $this->get_attachment_secret_key( $attachment_id );

		return home_url() . '/?sf_download_attachment=' . $attachment_secret;
	}

	/**
	 * Sufffix random characters to attachment to prevent direct access to it by guessing URL
	 *
	 * @param int $attachment_id ID of attachment
	 * @return boolean True on success else false
	 */
	public function secure_attachment_file( $attachment_id ) {

		$file       = get_attached_file( $attachment_id );
		$file_parts = pathinfo( $file );
		$file_new   = $file_parts['dirname'] . '/' . $file_parts['filename'] . '_' . wp_generate_password( 5, false ) . '.' . $file_parts['extension'];

		if ( rename( $file, $file_new ) ) {
			update_attached_file( $attachment_id, $file_new );
			return true;
		} else {
			return false;;
		}
	}
}

SupportFlow()->extend->attachments = new SupportFlow_Attachments();
