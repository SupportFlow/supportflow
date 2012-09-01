<?php
/**
 * Allow attachments to be securely stored and served
 */
class SupportPress_Attachments extends SupportPress {

	var $file_hash = '';

	function __construct() {
		add_action( 'supportpress_after_setup_actions', array( $this, 'setup_actions' ) );
	}

	public function setup_actions() {

		add_filter( 'wp_get_attachment_url', array( $this, 'filter_wp_get_attachment_url' ), 10, 2 );

		// Handle delivery of the secure file
		add_action( 'template_redirect', array( $this, 'handle_file_delivery' ) );

		// Only apply to files that are uploaded to a thread
		if ( isset( $_REQUEST['post_id'] ) && SupportPress()->is_thread( (int)$_REQUEST['post_id'] ) ) {
			add_filter( 'wp_handle_upload_prefilter', array( $this, 'wp_handle_upload_prefilter' ) );
			add_filter( 'wp_handle_upload', array( $this, 'wp_handle_upload' ) );
		}

	}

	public function filter_wp_get_attachment_url( $url, $attachment_id ) {

		if ( $attachment = get_post( $attachment_id ) ) {
			if ( SupportPress()->is_thread( $attachment->post_parent ) )
				return $attachment->guid;

		}
		return $url;
	}

	public function handle_file_delivery( $template ) {

		// First check to see 
		if ( false === stripos( $_SERVER['REQUEST_URI' ], "secure-files/" ) )
			return;

		// Get the file
		preg_match( '#^\/secure\-files\/(.+)$#', $_SERVER['REQUEST_URI'], $matches );
		if ( empty( $matches ) )
			return;
		
		global $wpdb;
		$file = $matches[1];
		$query = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE guid LIKE %s", '%' . $file );
		$post = $wpdb->get_row( $query );
		if ( empty( $post ) )
			return get_404_template();

		$post_id = $post->ID;

		// @todo do our logged in and permissions checks

		$file = get_attached_file( $post_id );

		if ( !is_file( $file ) )
			return get_404_template();

		// We may override this later.
		status_header( 200 );

		//rest inspired by wp-includes/ms-files.php.
		$mime = wp_check_filetype( $file );
		if ( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
			$mime[ 'type' ] = mime_content_type( $file );

		if ( $mime[ 'type' ] )
			$mimetype = $mime[ 'type' ];
		else
			$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );

		//fake the filename
		$filename = $post->post_name;

		//we want the true attachment URL, not the permalink, so temporarily remove our filter
		$filename .= pathinfo( $file, PATHINFO_EXTENSION );

		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Type: ' . $mimetype ); // always send this
		header( 'Content-Length: ' . filesize( $file ) );
		$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $file ) );
		$etag = '"' . md5( $last_modified ) . '"';
		header( "Last-Modified: $last_modified GMT" );
		header( 'ETag: ' . $etag );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );

		// Support for Conditional GET
		$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

		if ( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
			$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;

		$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

		// If string is empty, return 0. If not, attempt to parse into a timestamp
		$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

		// Make a timestamp for our most recent modification...
		$modified_timestamp = strtotime($last_modified);

		if ( ( $client_last_modified && $client_etag )
			? ( ( $client_modified_timestamp >= $modified_timestamp) && ( $client_etag == $etag ) )
			: ( ( $client_modified_timestamp >= $modified_timestamp) || ( $client_etag == $etag ) )
		) {
			status_header( 304 );
			return;
		}

		//in case this is a large file, remove PHP time limits
		@set_time_limit( 0 );

		// If we made it this far, just serve the file
		readfile( $file );

		exit;
	}

	public function wp_handle_upload_prefilter( $file ) {

		// @todo use a secret key stored in options here too
		$this->private_hash = md5( date( 'Y-m-d' ) . $file['name'] );
		$file['name'] = $this->private_hash . '.' . basename( $file['name'] );
		return $file;
	}

	public function wp_handle_upload( $file ) {

		$secondary_hash = md5( date( 'Y-m-d' ) );
		$file['url'] = site_url( '/secure-files/' ) . str_replace( $this->private_hash . '.', $secondary_hash . '/', basename( $file['file'] ) );
		return $file;
	}

}

SupportPress()->extend->attachments = new SupportPress_Attachments();