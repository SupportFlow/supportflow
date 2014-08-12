jQuery(document).ready(function ($) {

	// Send elements with class sf_autosave to the server for autosave
	$('.sf_autosave').keyup(function () {

		var autosave_data = {};
		autosave_data['ticket_id'] = SFAutoSave.ticket_id;

		$('.sf_autosave').each(function () {
			element_key = $(this).attr('name');
			element_value = $(this).val();
			autosave_data[element_key] = element_value
		});

		wp.heartbeat.enqueue(
			'supportflow-autosave',
			autosave_data,
			false
		);

	});

});