jQuery(document).ready(function () {
	// Close all the different stat tables and toggle the status of clicked stat table
	jQuery('.toggle-link').click(function (event) {
		var current = jQuery(this).siblings('.toggle-content').css('display');
		jQuery('.toggle-content').hide(500);
		jQuery('.toggle-link').prop('title', SFStatistics.expand);
		if ('none' == current) {
			jQuery(this).siblings('.toggle-content').show(500);
			jQuery(this).prop('title', SFStatistics.collapse);
		}
		event.preventDefault();
	});

	// Set different type of statistics link title at page load
	jQuery(jQuery('.toggle-content')).each(function () {
		if ('none' == jQuery(this).css('display')) {
			jQuery(this).siblings('.toggle-link').prop('title', SFStatistics.expand);
		} else {
			jQuery(this).siblings('.toggle-link').prop('title', SFStatistics.collapse);
		}

		jQuery('.toggle-content').first().show(500);
	});
});