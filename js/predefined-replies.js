jQuery(document).ready(function () {

	jQuery('#predefs').change(function () {
		if (this.old_child == undefined) {
			this.old_child = 1;
		}

		// New selection of combo box
		new_content = jQuery('#predefs').find('option:selected').data('content');

		// Previous selection of combo box
		old_content = jQuery('.predef:nth-child(' + this.old_child + ')').data('content');

		// Current value of reply text box
		current_content = jQuery('#reply').val();

		if (0 == new_content.length) {
			return;
		}

		// Show confirmation message if reply box content is manually changed by user
		if (current_content != old_content) {
			if (false == window.confirm(SFPredefinedReplies.message)) {
				jQuery('.predef:nth-child(' + this.old_child + ')').prop('selected', true);
				return;
			}
		}

		jQuery('#reply').val(new_content);
		this.old_child = jQuery('#predefs').find('option:selected').index() + 1;
	});
});