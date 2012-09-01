// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportpress = {};

( function( $ ) {

	supportpress.init = function() {
		supportpress.widget_title = $('h1#widget_title');

		// Creating a new thread
		supportpress.new_thread_form = $('#supportpress-newthread-form');
		supportpress.new_thread_subject = supportpress.new_thread_form.find('#new-thread-subject' );
		supportpress.new_thread_message = supportpress.new_thread_form.find('#new-thread-message' );
		supportpress.new_thread_submit = supportpress.new_thread_form.find('#new-thread-submit' );

		// Viewing all threads
		supportpress.all_threads_view = $('#supportpress-all-threads');

		// Viewing an existing thread
		supportpress.single_thread_view = $('#supportpress-single-thread');
		supportpress.single_thread_body = supportpress.single_thread_view.find('#supportpress-thread-body');

		$('#supportpress-newthread'     ).click( supportpress.show_new_thread_form );
		$('#supportpress-newthread-form').submit( supportpress.submit_new_thread_form );
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

	supportpress.submit_new_thread_form = function( e ) {
		e.preventDefault();

		supportpress.disable_new_thread_form( SupportPressUserWidgetVars.starting_thread_text );

		var data = {
			subject:                 supportpress.new_thread_subject.val(),
			message:                 supportpress.new_thread_message.val(),
			supportpress_widget:     true,
		}
		$.post( supportpress.get_ajax_url( 'create-thread' ), data, supportpress.handle_new_thread_response );
	};

	supportpress.enable_new_thread_form = function( clear_form, submit_text ) {
		supportpress.new_thread_subject.removeAttr('disabled');
		supportpress.new_thread_message.removeAttr('disabled');
		supportpress.new_thread_submit.removeAttr('disabled').val(submit_text).removeClass('disabled');
		if ( clear_form ) {
			supportpress.new_thread_subject.val('');
			supportpress.new_thread_message.val('');
		}
	}

	supportpress.disable_new_thread_form = function( submit_text ) {
		supportpress.new_thread_subject.attr('disabled', 'disabled');
		supportpress.new_thread_message.attr('disabled', 'disabled');
		supportpress.new_thread_submit.attr('disabled', 'disabled').val(submit_text).addClass('disabled');
	}

	supportpress.handle_new_thread_response = function( response ) {

		// If there was an error in the process, re-enable the form and show the error message
		if ( 'error' == response.status ) {
			supportpress.enable_new_thread_form( false, SupportPressUserWidgetVars.start_thread_text );
			return;
		}

		// Restore and hide the form
		supportpress.enable_new_thread_form( true, SupportPressUserWidgetVars.start_thread_text );
		supportpress.new_thread_form.hide();
		supportpress.all_threads_view.hide();

		// We're good to render the single thread
		supportpress.widget_title.html(response.title);
		supportpress.single_thread_body.html( response.html );
		supportpress.single_thread_view.show();

	}

	// Initialize everything!
	$( document ).ready( supportpress.init );

} )( jQuery );