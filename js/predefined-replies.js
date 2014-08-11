jQuery(document).ready(function ($) {

	$('#predefs').change(function () {
		if (this.old_child == undefined) {
			this.old_child = 1;
		}

		// New selection of combo box
		new_content = $('#predefs').find('option:selected').data('content');

		// Previous selection of combo box
		old_content = $('.predef:nth-child(' + this.old_child + ')').data('content');

		// Current value of reply text box
		current_content = $('#reply').val();

		if (0 == new_content.length) {
			return;
		}

		// Show confirmation message if reply box content is manually changed by user
		if (current_content != old_content) {
			if (false == window.confirm(SFPredefinedReplies.message)) {
				$('.predef:nth-child(' + this.old_child + ')').prop('selected', true);
				return;
			}
		}

		$('#reply').val(new_content);
		this.old_child = $('#predefs').find('option:selected').index() + 1;
	});
});