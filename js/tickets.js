jQuery(document).ready(function () {
	jQuery('#supportflow-details .meta-item-ok-button').click(function (event) {
		var dropdown = jQuery(this).siblings('.meta-item-dropdown').children('option:selected');
		var meta_item = jQuery(this).closest('.meta-item');
		var form_input = meta_item.children('.meta-item-name');
		var label = meta_item.children('.meta-item-label');

		form_input.val(dropdown.val());
		label.text(dropdown.text());

		event.preventDefault();
	});

	jQuery('#supportflow-details .meta-item-toggle-button').click(function (event) {
		jQuery(this).closest('.meta-item').children('.meta-item-toggle-content').toggle(250);
		event.preventDefault();
	});

	// Require title and atleast respondent before saving a ticket
	jQuery('.save-button').click(function (event) {
		if ('' == jQuery('#subject').val()) {
			alert(SFTickets.no_title_msg);
			event.preventDefault();
			return;
		}
		if ('' == jQuery('#respondents').val()) {
			alert(SFTickets.no_respondent_msg);
			event.preventDefault();
			return;
		}
	});

	// Submit post if user pressed Ctrl+Enter in reply content box
	jQuery('#reply').keypress(function (event) {
		if (event.ctrlKey && event.keyCode == 10 && $(this).val() != '') {
			$('#post').submit();
		}
	});
});
