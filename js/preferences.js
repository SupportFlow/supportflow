jQuery(document).ready(function ($) {

	// Update E-Mail notification settings on checkbox toggle
	$(document).on('change', '.sf_email_accounts_table .toggle_privilege', function () {
		var checkbox = $(this);
		var checkbox_label = checkbox.siblings('.privilege_status');
		var email_notfication_identifier = checkbox.data('email-notfication-identifier');

		var allowed = checkbox.prop('checked');
		var privilege_type = email_notfication_identifier.privilege_type;
		var privilege_id = email_notfication_identifier.privilege_id;

		checkbox_label.html(SFPreferences.changing_state);
		checkbox.prop('disabled', true);

		$.ajax(ajaxurl, {
			type    : 'post',
			data    : {
				action                      : 'set_email_notfication',
				privilege_type              : privilege_type,
				privilege_id                : privilege_id,
				allowed                     : allowed,
				_set_email_notfication_nonce: SFPreferences.set_email_notfication_nonce,
			},
			success : function (content) {
				if (1 != content) {
					checkbox.prop('checked', !checkbox.prop('checked'));
					alert(SFPreferences.failed_changing_state);
				}
			},
			error   : function () {
				checkbox.prop('checked', !checkbox.prop('checked'));
				alert(SFPreferences.failed_changing_state);
			},
			complete: function () {
				var allowed = checkbox.prop('checked');
				if (true == allowed) {
					checkbox_label.html(SFPreferences.subscribed);
				} else {
					checkbox_label.html(SFPreferences.unsubscribed);
				}
				checkbox.prop('disabled', false);
			},
		});
	});

});