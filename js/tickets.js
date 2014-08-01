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

	// Toggle submit button text (Send Message/Add private note)
	jQuery('#mark-private').change(function () {
		if ('post.php' == SFTickets.pagenow) {
			if (jQuery('#mark-private').prop('checked')) {
				jQuery('#insert-reply').val(SFTickets.add_private_note);
			} else {
				jQuery('#insert-reply').val(SFTickets.send_msg);
			}
		}
	});

	// Close ticket wehen close ticket link is clicked
	jQuery('#close-ticket-link').click(function (event) {
		event.preventDefault();

		var form = '';
		form += '<form name="post" action="post.php" method="post" id="close_ticket_form">';

		form += '<input type="hidden" name="_wpnonce" value="' + jQuery('#post #_wpnonce').val() + '" />';
		form += '<input type="hidden" name="_wp_http_referer" value="' + jQuery("#post input[name='_wp_http_referer']").val() + '" />';
		form += '<input type="hidden" name="action" value="editpost" />';
		form += '<input type="hidden" name="post_ID" value="' + jQuery('#post #post_ID').val() + '" />';
		form += '<input type="hidden" name="post_status" value="sf_closed" />';

		form += '</form>';

		jQuery('body').append(form);
		jQuery('#close_ticket_form').submit();
	});
});
