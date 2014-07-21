// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportflow = {};

(function ($) {

	supportflow.init = function () {
		supportflow.widget_title = $('h1#widget-title');
		supportflow.original_widget_title = supportflow.widget_title.html();
		supportflow.back_button = $('#supportflow-back');
		supportflow.back_button.click(supportflow.hide_single_ticket_view);

		// Creating a new ticket
		supportflow.new_ticket_button = $('#supportflow-newticket');
		supportflow.new_ticket_form = $('#supportflow-newticket-form');
		supportflow.new_ticket_subject = supportflow.new_ticket_form.find('#new-ticket-subject');
		supportflow.new_ticket_message = supportflow.new_ticket_form.find('#new-ticket-message');
		supportflow.new_ticket_submit = supportflow.new_ticket_form.find('#new-ticket-submit');

		supportflow.new_ticket_button.click(supportflow.show_new_ticket_form);
		supportflow.new_ticket_form.submit(supportflow.submit_new_ticket_form);

		// Viewing all tickets
		supportflow.all_tickets_view = $('#supportflow-all-tickets');
		supportflow.all_tickets_view.find('li').click(supportflow.show_single_ticket_view);

		// Viewing an existing ticket
		supportflow.single_ticket_view = $('#supportflow-single-ticket');
		supportflow.single_ticket_body = supportflow.single_ticket_view.find('#supportflow-ticket-body');
		supportflow.single_ticket_form = supportflow.single_ticket_view.find('#supportflow-existing-ticket-form');
		supportflow.single_ticket_id = supportflow.single_ticket_form.find('#existing-ticket-id');
		supportflow.single_ticket_message = supportflow.single_ticket_view.find('#existing-ticket-message');
		supportflow.single_ticket_submit = supportflow.single_ticket_form.find('#existing-ticket-submit');

		supportflow.single_ticket_form.submit(supportflow.submit_new_message_form);

	};

	supportflow.get_ajax_url = function (action) {
		return SupportFlowUserWidgetVars.ajaxurl + '&api-action=' + action;
	};

	supportflow.show_new_ticket_form = function () {
		$(this).hide();
		$('#supportflow-newticket-form').slideDown();
	};

	supportflow.hide_new_ticket_form = function () {

	}

	supportflow.hide_single_ticket_view = function () {
		supportflow.single_ticket_view.hide();
		supportflow.widget_title.html(supportflow.original_widget_title);
		supportflow.all_tickets_view.attr('opacity', '1').show();
		supportflow.new_ticket_button.show();
		supportflow.back_button.hide();
	}

	supportflow.show_single_ticket_view = function (e) {
		e.preventDefault();

		supportflow.back_button.show();

		supportflow.new_ticket_button.hide();
		supportflow.all_tickets_view.attr('opacity', '0.5');
		supportflow.widget_title.html($(this).find('.ticket-title').html());
		var ticket_id = $(this).attr('id').replace('ticket-', '');
		var data = {
			ticket_id         : ticket_id,
			supportflow_widget: true,
		}
		$.get(supportflow.get_ajax_url('get-ticket'), data, supportflow.handle_new_ticket_response);

	}

	supportflow.submit_new_ticket_form = function (e) {
		e.preventDefault();

		supportflow.disable_ticket_form(supportflow.new_ticket_form, SupportFlowUserWidgetVars.starting_ticket_text);

		var data = {
			subject           : supportflow.new_ticket_subject.val(),
			message           : supportflow.new_ticket_message.val(),
			supportflow_widget: true,
		}
		$.post(supportflow.get_ajax_url('create-ticket'), data, supportflow.handle_new_ticket_response);
	}

	supportflow.submit_new_message_form = function (e) {
		e.preventDefault();

		supportflow.disable_ticket_form(supportflow.single_ticket_form, SupportFlowUserWidgetVars.sending_reply_text);

		var data = {
			ticket_id         : supportflow.single_ticket_id.val(),
			message           : supportflow.single_ticket_message.val(),
			supportflow_widget: true,
		}
		$.post(supportflow.get_ajax_url('add-ticket-reply'), data, supportflow.handle_new_message_response);
	}

	supportflow.enable_ticket_form = function (form, clear_form, submit_text) {
		form.find('input.ticket-subject').removeAttr('disabled');
		form.find('textarea.ticket-message').removeAttr('disabled');
		form.find('input.submit-button').removeAttr('disabled').val(submit_text).removeClass('disabled');
		if (clear_form) {
			form.find('input.ticket-subject').val('');
			form.find('textarea.ticket-message').val('');
		}
	}

	supportflow.disable_ticket_form = function (form, submit_text) {
		form.find('input.ticket-subject').attr('disabled', 'disabled');
		form.find('textarea.ticket-message').attr('disabled', 'disabled');
		form.find('input.submit-form').attr('disabled', 'disabled').val(submit_text).addClass('disabled');
	}

	supportflow.handle_new_ticket_response = function (response) {

		// If there was an error in the process, re-enable the form and show the error message
		if ('error' == response.status) {
			supportflow.enable_ticket_form(supportflow.new_ticket_form, false, SupportFlowUserWidgetVars.start_ticket_text);
			return;
		}

		// Restore and hide the form
		supportflow.enable_ticket_form(supportflow.new_ticket_form, true, SupportFlowUserWidgetVars.start_ticket_text);
		supportflow.new_ticket_form.hide();
		supportflow.all_tickets_view.hide();

		// We're good to render the single ticket
		supportflow.widget_title.html(response.title);
		supportflow.single_ticket_body.html(response.html);
		supportflow.single_ticket_view.show();
		supportflow.single_ticket_id.val(response.ticket_id);

	}

	supportflow.handle_new_message_response = function (response) {

		// If there was an error in the process, re-enable the form and show the error message
		if ('error' == response.status) {
			// @todo display the error message
			supportflow.enable_ticket_form(supportflow.single_ticket_form, false, SupportFlowUserWidgetVars.send_reply_text);
			return;
		}

		// Renable the form and append the response
		supportflow.enable_ticket_form(supportflow.single_ticket_form, true, SupportFlowUserWidgetVars.send_reply_text);
		supportflow.single_ticket_body.find('ul').append(response.html);

	}

	// Initialize everything!
	$(document).ready(supportflow.init);

})(jQuery);