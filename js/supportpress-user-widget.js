// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportpress = {};

( function( $ ) {

	supportpress.init = function() {
		supportpress.widget_title = $('h1#widget-title');

		// Creating a new thread
		supportpress.new_thread_button = $('#supportpress-newthread');
		supportpress.new_thread_form = $('#supportpress-newthread-form');
		supportpress.new_thread_subject = supportpress.new_thread_form.find('#new-thread-subject' );
		supportpress.new_thread_message = supportpress.new_thread_form.find('#new-thread-message' );
		supportpress.new_thread_submit = supportpress.new_thread_form.find('#new-thread-submit' );

		supportpress.new_thread_button.click( supportpress.show_new_thread_form );
		supportpress.new_thread_form.submit( supportpress.submit_new_thread_form );

		// Viewing all threads
		supportpress.all_threads_view = $('#supportpress-all-threads');
		supportpress.all_threads_view.find('li').click( supportpress.show_single_thread_view );

		// Viewing an existing thread
		supportpress.single_thread_view = $('#supportpress-single-thread');
		supportpress.single_thread_body = supportpress.single_thread_view.find('#supportpress-thread-body');
		supportpress.single_thread_form = supportpress.single_thread_view.find('#supportpress-existing-thread-form');
		supportpress.single_thread_id = supportpress.single_thread_form.find('#existing-thread-id' );
		supportpress.single_thread_message = supportpress.single_thread_view.find('#existing-thread-message');
		supportpress.single_thread_submit = supportpress.single_thread_form.find('#existing-thread-submit' );

		supportpress.single_thread_form.submit( supportpress.submit_new_message_form );

	};

	supportpress.get_ajax_url = function( action ) {
		return SupportPressUserWidgetVars.ajaxurl + '&api-action=' + action;
	};

	supportpress.show_new_thread_form = function() {
		$(this).hide();
		$('#supportpress-newthread-form').slideDown();
	};

	supportpress.hide_new_thread_form = function() {

	}

	supportpress.show_single_thread_view = function( e ) {
		e.preventDefault();

		supportpress.new_thread_button.hide();
		supportpress.all_threads_view.attr('opacity', '0.5');
		var thread_id = $(this).attr('id').replace('thread-','');
		var data = {
			thread_id:               thread_id,
			supportpress_widget:     true,
		}
		$.get( supportpress.get_ajax_url( 'get-thread' ), data, supportpress.handle_new_thread_response );

	}

	supportpress.submit_new_thread_form = function( e ) {
		e.preventDefault();

		supportpress.disable_thread_form( supportpress.new_thread_form, SupportPressUserWidgetVars.starting_thread_text );

		var data = {
			subject:                 supportpress.new_thread_subject.val(),
			message:                 supportpress.new_thread_message.val(),
			supportpress_widget:     true,
		}
		$.post( supportpress.get_ajax_url( 'create-thread' ), data, supportpress.handle_new_thread_response );
	}

	supportpress.submit_new_message_form = function( e ) {
		e.preventDefault();

		supportpress.disable_thread_form( supportpress.single_thread_form, SupportPressUserWidgetVars.sending_reply_text );

		var data = {
			thread_id:               supportpress.single_thread_id.val(),
			message:                 supportpress.single_thread_message.val(),
			supportpress_widget:     true,
		}
		$.post( supportpress.get_ajax_url( 'add-thread-comment' ), data, supportpress.handle_new_message_response );
	}

	supportpress.enable_thread_form = function( form, clear_form, submit_text ) {
		form.find('input.thread-subject').removeAttr('disabled');
		form.find('textarea.thread-message').removeAttr('disabled');
		form.find('input.submit-button').removeAttr('disabled').val(submit_text).removeClass('disabled');
		if ( clear_form ) {
			form.find('input.thread-subject').val('');
			form.find('textarea.thread-message').val('');
		}
	}

	supportpress.disable_thread_form = function( form, submit_text ) {
		form.find('input.thread-subject').attr('disabled', 'disabled');
		form.find('textarea.thread-message').attr('disabled', 'disabled');
		form.find('input.submit-form').attr('disabled', 'disabled').val(submit_text).addClass('disabled');
	}

	supportpress.handle_new_thread_response = function( response ) {

		// If there was an error in the process, re-enable the form and show the error message
		if ( 'error' == response.status ) {
			supportpress.enable_thread_form( supportpress.new_thread_form, false, SupportPressUserWidgetVars.start_thread_text );
			return;
		}

		// Restore and hide the form
		supportpress.enable_thread_form( supportpress.new_thread_form, true, SupportPressUserWidgetVars.start_thread_text );
		supportpress.new_thread_form.hide();
		supportpress.all_threads_view.hide();

		// We're good to render the single thread
		supportpress.widget_title.html(response.title);
		supportpress.single_thread_body.html( response.html );
		supportpress.single_thread_view.show();
		supportpress.single_thread_id.val(response.thread_id);

	}

	supportpress.handle_new_message_response = function( response ) {

		// If there was an error in the process, re-enable the form and show the error message
		if ( 'error' == response.status ) {
			// @todo display the error message
			supportpress.enable_thread_form( supportpress.single_thread_form, false, SupportPressUserWidgetVars.start_thread_text );
			return;
		}

		// Renable the form and append the response
		supportpress.enable_thread_form( supportpress.single_thread_form, true, SupportPressUserWidgetVars.start_thread_text );
		supportpress.single_thread_body.find( 'ul' ).append( response.html );

	}

	// Initialize everything!
	$( document ).ready( supportpress.init );

} )( jQuery );