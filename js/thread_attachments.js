jQuery(document).ready(function ($) {

	attachment_uploader = {
		// Initialization
		init    : function () {
			$(document).on('click', '#reply-attachment-browse-button', {}, attachment_uploader.uploader);
		},

		// Call this from the upload button to initiate the upload frame.
		uploader: function (event) {
			var frame = wp.media({
				title   : SFThreadAttachments.frame_title,
				multiple: true,
				button  : { text: SFThreadAttachments.button_title },
			});

			// Handle results from media manager.
			frame.on('close', function () {
				var attachments = frame.state().get('selection').toJSON();
				jQuery.each(attachments, function () {
					jQuery('#replies-attachments-list').append('<li>' + '<a target="_blank" href="' + this.url + '">' + this.filename + '</a></li>');
					jQuery('#reply-attachments').val(jQuery('#reply-attachments').val() + this.id + ',');
				});
			});

			frame.open();
			return false;
		},


	};

	attachment_uploader.init();

});