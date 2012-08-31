jQuery(document).ready(function($) {
	new wp.Uploader({

		/* Selectors */
		browser:   '#upload-indicator',
		dropzone:  '#comment',

		/* Callbacks */
		success  : function( attachment ) {
			$( '#comment-attachments' ).val( $( '#comment-attachments' ).val() + ',' + attachment.id );
			$( '#upload-messages' ).hide();
			$( '<li>', {
				html: attachment.filename,
			 } ).appendTo( $( 'ul#comment-attachments-list' ) );
			$( '#upload-messages' ).removeClass( 'uploading' );
		},

		error    : function ( reason ) {
			$( '#upload-indicator' ).html( reason ).addClass('error');
		},

		added    : function() {
			$( '#upload-indicator' ).addClass( 'uploading' );
		},

		// TypeError: file is undefined
		// progress : function( up, file ) {
		// 	$( '#upload-indicator' ).html( "Uploading: " + file.name + ' ' + file.percent + '%' );
		// },

		complete : function() {
			$( '#upload-indicator' ).html( 'All done!' );
		}
	});
});
