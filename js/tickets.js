jQuery(document).ready(function ($) {
	$('#supportflow-details .meta-item-ok-button').click(function (event) {
		var dropdown = $(this).siblings('.meta-item-dropdown').children('option:selected');
		var meta_item = $(this).closest('.meta-item');
		var form_input = meta_item.children('.meta-item-name');
		var label = meta_item.children('.meta-item-label');

		form_input.val(dropdown.val());
		label.text(dropdown.text());

		event.preventDefault();
	});

	$('#supportflow-details .meta-item-toggle-button').click(function (event) {
		$(this).closest('.meta-item').children('.meta-item-toggle-content').toggle(250);
		event.preventDefault();
	});

	// Require title and atleast customer before saving a ticket
	$('.save-button').click(function (event) {
		if ('' == $('#subject').val()) {
			$('#subject').focus();
			alert(SFTickets.no_title_msg);
			event.preventDefault();
			return;
		}
		if ('' == $('#customers').val()) {
			$('#customers').focus();
			alert(SFTickets.no_customer_msg);
			event.preventDefault();
			return;
		}
	});

	// Submit post if user pressed Ctrl+Enter in reply content box
	$('#reply').keypress(function (event) {
		if (event.ctrlKey && ( event.keyCode == 10 || event.keyCode == 13 ) && $(this).val() != '') {
			$('.save-button').first().click();
		}
	});

	// Toggle submit button text (Send Message/Add private note)
	$('#mark-private').change(function () {
		if ('post.php' == SFTickets.pagenow) {
			if ($('#mark-private').prop('checked')) {
				$('#insert-reply').val(SFTickets.add_private_note);
			} else {
				$('#insert-reply').val(SFTickets.send_msg);
			}
		}
	});

	// Close ticket wehen close ticket button is submitted
	$('#close-ticket-submit').click(function (event) {
		$("#post .meta-item input[name='post_status']").val('sf_closed');
	});

	// Show quoted text	
	$('.sf_toggle_quoted_text').click(function (event) {
		event.preventDefault();
		$(this).parent().html($(this).data('quoted_text'))
	});

});
