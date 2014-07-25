jQuery(document).ready(function () {
	// Close all the tables and toggle the status of clicked tables
	jQuery('.toggle-link').click(function (event) {
		var current = jQuery(this).siblings('.toggle-content').css('display');
		jQuery('.toggle-content').hide(500);
		jQuery('.toggle-link').prop('title', 'Expand');
		if ('none' == current) {
			jQuery(this).siblings('.toggle-content').show(500);
			jQuery(this).prop('title', 'Collapse');
		}
		event.preventDefault();
	});

	// Set different type of link title at page load
	jQuery(jQuery('.toggle-content')).each(function () {
		if ('none' == jQuery(this).css('display')) {
			jQuery(this).siblings('.toggle-link').prop('title', 'Expand');
		} else {
			jQuery(this).siblings('.toggle-link').prop('title', 'Collapse');
		}

		jQuery('.toggle-content').first().show(500);
	});
});