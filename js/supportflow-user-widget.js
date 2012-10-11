// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportflow = {};

( function( $ ) {

	supportflow.init = function() {
		supportflow.widget_title = $('h1#widget-title');

		// Creating a new thread
		supportflow.new_thread_button = $('#supportflow-newthread');
		supportflow.new_thread_form = $('#supportflow-newthread-form');
		supportflow.new_thread_subject = supportflow.new_thread_form.find('#new-thread-subject' );
		supportflow.new_thread_message = supportflow.new_thread_form.find('#new-thread-message' );
		supportflow.new_thread_submit = supportflow.new_thread_form.find('#new-thread-submit' );

		supportflow.new_thread_button.click( supportflow.show_new_thread_form );
		supportflow.new_thread_form.submit( supportflow.submit_new_thread_form );

		// Viewing all threads
		supportflow.all_threads_view = $('#supportflow-all-threads');
		supportflow.all_threads_view.find('li').click( supportflow.show_single_thread_view );

		// Viewing an existing thread
		supportflow.single_thread_view = $('#supportflow-single-thread');
		supportflow.single_thread_body = supportflow.single_thread_view.find('#supportflow-thread-body');
		supportflow.single_thread_form = supportflow.single_thread_view.find('#supportflow-existing-thread-form');
		supportflow.single_thread_id = supportflow.single_thread_form.find('#existing-thread-id' );
		supportflow.single_thread_message = supportflow.single_thread_view.find('#existing-thread-message');
		supportflow.single_thread_submit = supportflow.single_thread_form.find('#existing-thread-submit' );

		supportflow.single_thread_form.submit( supportflow.submit_new_message_form );

	};

	supportflow.get_ajax_url = function( action ) {
		return supportflowUserWidgetVars.ajaxurl + '&api-action=' + action;
	};

	supportflow.show_new_thread_form = function() {
		$(this).hide();
		$('#supportflow-newthread-form').slideDown();
	};

	supportflow.hide_new_thread_form = function() {

	}

	supportflow.show_single_thread_view = function( e ) {
		e.preventDefault();

		supportflow.new_thread_button.hide();
		supportflow.all_threads_view.attr('opacity', '0.5');
		var thread_id = $(this).attr('id').replace('thread-','');
		var data = {
			thread_id:               thread_id,
			supportflow_widget:     true,
		}
		$.get( supportflow.get_ajax_url( 'get-thread' ), data, supportflow.handle_new_thread_response );

	}

	supportflow.submit_new_thread_form = function( e ) {
		e.preventDefault();

		supportflow.disable_thread_form( supportflow.new_thread_form, supportflowUserWidgetVars.starting_thread_text );

		var data = {
			subject:                 supportflow.new_thread_subject.val(),
			message:                 supportflow.new_thread_message.val(),
			supportflow_widget:     true,
		}
		$.post( supportflow.get_ajax_url( 'create-thread' ), data, supportflow.handle_new_thread_response );
	}

	supportflow.submit_new_message_form = function( e ) {
		e.preventDefault();

		supportflow.disable_thread_form( supportflow.single_thread_form, supportflowUserWidgetVars.sending_reply_text );

		var data = {
			thread_id:               supportflow.single_thread_id.val(),
			message:                 supportflow.single_thread_message.val(),
			supportflow_widget:     true,
		}
		$.post( supportflow.get_ajax_url( 'add-thread-comment' ), data, supportflow.handle_new_message_response );
	}

	supportflow.enable_thread_form = function( form, clear_form, submit_text ) {
		form.find('input.thread-subject').removeAttr('disabled');
		form.find('textarea.thread-message').removeAttr('disabled');
		form.find('input.submit-button').removeAttr('disabled').val(submit_text).removeClass('disabled');
		if ( clear_form ) {
			form.find('input.thread-subject').val('');
			form.find('textarea.thread-message').val('');
		}
	}

	supportflow.disable_thread_form = function( form, submit_text ) {
		form.find('input.thread-subject').attr('disabled', 'disabled');
		form.find('textarea.thread-message').attr('disabled', 'disabled');
		form.find('input.submit-form').attr('disabled', 'disabled').val(submit_text).addClass('disabled');
	}

	supportflow.handle_new_thread_response = function( response ) {

		// If there was an error in the process, re-enable the form and show the error message
		if ( 'error' == response.status ) {
			supportflow.enable_thread_form( supportflow.new_thread_form, false, supportflowUserWidgetVars.start_thread_text );
			return;
		}

		// Restore and hide the form
		supportflow.enable_thread_form( supportflow.new_thread_form, true, supportflowUserWidgetVars.start_thread_text );
		supportflow.new_thread_form.hide();
		supportflow.all_threads_view.hide();

		// We're good to render the single thread
		supportflow.widget_title.html(response.title);
		supportflow.single_thread_body.html( response.html );
		supportflow.single_thread_view.show();
		supportflow.single_thread_id.val(response.thread_id);

	}

	supportflow.handle_new_message_response = function( response ) {

		// If there was an error in the process, re-enable the form and show the error message
		if ( 'error' == response.status ) {
			// @todo display the error message
			supportflow.enable_thread_form( supportflow.single_thread_form, false, supportflowUserWidgetVars.send_reply_text );
			return;
		}

		// Renable the form and append the response
		supportflow.enable_thread_form( supportflow.single_thread_form, true, supportflowUserWidgetVars.send_reply_text );
		supportflow.single_thread_body.find( 'ul' ).append( response.html );

	}

	// Initialize everything!
	$( document ).ready( supportflow.init );

} )( jQuery );