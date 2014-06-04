jQuery(document).ready(function () {

	var sf_thread_attachment_uploader = new plupload.Uploader(sf_thread_attachment_plupload_settings);
	sf_thread_attachment_uploader.init();

	sf_thread_attachment_uploader.bind('FilesAdded', function (uploader, files) {
		plupload.each(files, function (file) {
			jQuery('#replies-attachments-list').append('<li id="reply-attachment-' + file.id + '">' + SFThreadAttachments.uploading + file.name + '</li>');
		});

		uploader.refresh();
		uploader.start();
	});

	sf_thread_attachment_uploader.bind('FileUploaded', function (uploader, file, response) {
		if (null == response.response || 0 == response.response) {
			jQuery('#reply-attachment-' + file.id).html('<li>' + SFThreadAttachments.failed_uploading + file.name + '</li>');
		} else {
			var attachment = jQuery.parseJSON(response.response);
			jQuery('#reply-attachment-' + file.id).html('<li>' + SFThreadAttachments.uploaded + '<a target="_blank" href="' + attachment.url + '">' + file.name + '</a></li>');
			jQuery('#reply-attachments').val(jQuery('#reply-attachments').val() + attachment.id + ',');
		}
	});

});
