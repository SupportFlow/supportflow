jQuery(document).ready(function ($) {

	attachment_uploader = {
		// Initialization
		init    : function () {
			$(document).on('click', '#reply-attachment-browse-button', {}, attachment_uploader.uploader);
			$(document).on('click', '.reply-attachment-remove', attachment_uploader.remove);
		},

		// Call this from the upload button to initiate the upload frame.
		uploader: function (event) {
			var frame = wp.media({
				title   : SFTicketAttachments.frame_title,
				multiple: true,
				button  : { text: SFTicketAttachments.button_title },
			});

			// Handle results from media manager.
			frame.on('close', function () {
				var attachments = frame.state().get('selection').toJSON();
				$.each(attachments, function () {
					var attachment_download_link = '<a class="reply-attachment-link" target="_blank" href="' + _.escape(this.url) + '">' + _.escape(this.filename) + '</a>';
					var attachment_remove_link = '<a class="reply-attachment-remove delete" href="#" data-attachment-id=' + _.escape(this.id) + ' >' + SFTicketAttachments.remove_attachment + '</a>';
					$('#replies-attachments-list').append('<li id="media-items" class="reply-attachment">' + attachment_download_link + '&nbsp;' + attachment_remove_link + '</li>');
					$('#reply-attachments').val($('#reply-attachments').val() + _.escape(this.id) + ',');
				});
			});

			frame.open();
			return false;
		},

		remove: function (event) {
			if (confirm(SFTicketAttachments.sure_remove)) {
				var attachment_id = $(this).data('attachment-id');
				var reply_attachments = $('#reply-attachments').val();
				reply_attachments = reply_attachments.replace(',' + attachment_id + ',', ',');

				// Remove attachment ID from hidden input box
				$('#reply-attachments').val(reply_attachments)

				// Remove attachment from attachment list
				$(this).closest('.reply-attachment').remove();
			}
			event.preventDefault();
		},

	};

	attachment_uploader.init();

});