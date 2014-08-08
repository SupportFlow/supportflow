jQuery(document).ready(function () {
	jQuery('.delete_email_account').click(function (e) {
		e.preventDefault();

		if (!confirm(SFEmailAccounts.sure_delete_account)) {
			return;
		}

		var account_key = jQuery(this).data('account-id');
		var form_action = "edit.php?post_type=" + SFEmailAccounts.post_type + "&page=" + SFEmailAccounts.slug;

		var form = '';
		form += '<form method="POST" id="remove_email_account" action="' + form_action + '">';
		form += '<input type="hidden" name="action" value="delete" />';
		form += SFEmailAccounts.delete_email_account_nonce;
		form += '<input type="hidden" name="account_id" value=' + account_key + ' />';
		form += '</form>';

		jQuery('body').append(form);
		jQuery('#remove_email_account').submit();
	});


	jQuery('#add_new_email_account #imap_ssl').change(function () {
		if (this.checked) {
			// Change to default IMAP SSL port on enabling SSL
			jQuery('#add_new_email_account #imap_port').val('993');
		} else {
			// Change to default IMAP non-SSL port on disabling SSL
			jQuery('#add_new_email_account #imap_port').val('143');
		}
	});

	jQuery('#add_new_email_account #smtp_ssl').change(function () {
		if (this.checked) {
			// Change to default SMTP SSL port on enabling SSL
			jQuery('#add_new_email_account #smtp_port').val('465');
		} else {
			// Change to default SMTP non-SSL port on disabling SSL
			jQuery('#add_new_email_account #smtp_port').val('25');
		}
	});
});
