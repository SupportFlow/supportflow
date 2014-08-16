jQuery(document).ready(function ($) {
	// Close all the different stat tables and toggle the status of clicked stat table
	$('.toggle-link').click(function (event) {
		var current = $(this).siblings('.toggle-content').css('display');
		$('.toggle-content').hide(500);
		$('.toggle-link').prop('title', SFToggleLinks.expand);
		if ('none' == current) {
			$(this).siblings('.toggle-content').show(500);
			$(this).prop('title', SFToggleLinks.collapse);
		}
		event.preventDefault();
	});

	// Set different type of statistics link title at page load
	$($('.toggle-content')).each(function () {
		if ('none' == $(this).css('display')) {
			$(this).siblings('.toggle-link').prop('title', SFToggleLinks.expand);
		} else {
			$(this).siblings('.toggle-link').prop('title', SFToggleLinks.collapse);
		}

		$('.toggle-content').first().show(500);
	});
});