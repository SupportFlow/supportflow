jQuery(document).ready(function () {

	// Require title and atleast respondent before saving a thread
	jQuery('.save-button').click(function (event) {
		if ('' == jQuery('#subject').val()) {
			alert(SFThreads.no_title_msg);
			event.preventDefault();
			return;
		}
		if ('' == jQuery('#respondents').val()) {
			alert(SFThreads.no_respondent_msg);
			event.preventDefault();
			return;
		}
	});
});