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

	// Require title and atleast customer before saving a ticket
	jQuery('.save-button').click(function (event) {
		if ('' == jQuery('#subject').val()) {
			jQuery('#subject').focus();
			alert(SFTickets.no_title_msg);
			event.preventDefault();
			return;
		}
		if ('' == jQuery('#customers').val()) {
			jQuery('#customers').focus();
			alert(SFTickets.no_customer_msg);
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

	// Close ticket wehen close ticket button is submitted
	jQuery('#close-ticket-submit').click(function (event) {
		jQuery("#post .meta-item input[name='post_status']").val('sf_closed');
	});

	// Show quoted text	
	jQuery('.sf_toggle_quoted_text').click(function (event) {
		event.preventDefault();
		jQuery(this).parent().html(jQuery(this).data('quoted_text'))
	});

	// Auto-resize height of reply text box
	$('#reply').autosize();
});
