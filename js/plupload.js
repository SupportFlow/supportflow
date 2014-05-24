jQuery(document).ready(function ($) {
	new wp.Uploader({

		/* Selectors */
		browser : '#upload-indicator',
		dropzone: '#thread-reply-box',

		/* Callbacks */
		success : function (attachment) {
			$('#reply-attachments').val($('#reply-attachments').val() + ',' + attachment.id);
			$('#upload-messages').hide();
			$('<li>', {
				html: '<a target="_blank" href="' + attachment.url + '">' + attachment.title + '</a>',
			}).appendTo($('ul#replies-attachments-list'));
			$('#upload-messages').removeClass('uploading');
		},

		error: function (reason) {
			$('#upload-indicator').html(reason).addClass('error');
		},

		added: function () {
			$('#upload-indicator').addClass('uploading');
		},

		// TypeError: file is undefined
		// progress : function( up, file ) {
		// 	$( '#upload-indicator' ).html( "Uploading: " + file.name + ' ' + file.percent + '%' );
		// },

		complete: function () {
			$('#upload-indicator').html('All done!');
		}
	});
});
