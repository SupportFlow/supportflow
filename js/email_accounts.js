jQuery(document).ready(function ($) {
	$('.delete_email_account').click(function (e) {
		e.preventDefault();

		if (!confirm(SFEmailAccounts.sure_delete_account)) {
			return;
		}

		var account_key = $(this).data('account-id');
		var form_action = "edit.php?post_type=" + SFEmailAccounts.post_type + "&page=" + SFEmailAccounts.slug;

		var form = '';
		form += '<form method="POST" id="remove_email_account" action="' + form_action + '">';
		form += '<input type="hidden" name="action" value="delete" />';
		form += SFEmailAccounts.delete_email_account_nonce;
		form += '<input type="hidden" name="account_id" value=' + account_key + ' />';
		form += '</form>';

		$('body').append(form);
		$('#remove_email_account').submit();
	});


	$('#add_new_email_account #imap_ssl').change(function () {
		if (this.checked) {
			// Change to default IMAP SSL port on enabling SSL
			$('#add_new_email_account #imap_port').val('993');
		} else {
			// Change to default IMAP non-SSL port on disabling SSL
			$('#add_new_email_account #imap_port').val('143');
		}
	});

	$('#add_new_email_account #smtp_ssl').change(function () {
		if (this.checked) {
			// Change to default SMTP SSL port on enabling SSL
			$('#add_new_email_account #smtp_port').val('465');
		} else {
			// Change to default SMTP non-SSL port on disabling SSL
			$('#add_new_email_account #smtp_port').val('25');
		}
	});
});
