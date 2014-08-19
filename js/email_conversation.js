jQuery(document).ready(function ($) {
	// Send conversation to E-Mail ID's

	var email_conversion = function (event) {
		event.preventDefault();
		var email_ids = $('#email_conversation_to').val();

		$.ajax(ajaxurl, {
			type      : 'post',
			data      : {
				action                   : 'sf_forward_conversation',
				email_ids                : email_ids,
				post_id                  : SFEmailConversation.post_id,
				_email_conversation_nonce: SFEmailConversation._email_conversation_nonce,
			},
			beforeSend: function () {
				$('#email_conversation_to').prop('disabled', true);
				$('#email_conversation_submit').prop('disabled', true);
				$('#email_conversation_status').text(SFEmailConversation.sending_emails);
			},
			success   : function (content) {
				$('#email_conversation_status').html(content);
			},
			error     : function () {
				$('#email_conversation_status').text(SFEmailConversation.failed_sending);
			},
			complete  : function () {
				$('#email_conversation_to').prop('disabled', false);
				$('#email_conversation_submit').prop('disabled', false);
			},
		});

	}

	$('#email_conversation_submit').click(email_conversion);
	$('#email_conversation_to').keyup(function () {
		if (event.keyCode == 10 || event.keyCode == 13) {
			email_conversion();
		}
	});

});