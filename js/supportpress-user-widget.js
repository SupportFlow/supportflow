// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportpress = {};

( function( $ ) {

	supportpress.init = function() {
		$('#supportpress-newthread'     ).click( supportpress.show_new_thread_form );
		$('#supportpress-newthread-form').submit( supportpress.submit_new_thread_form );
	};

	supportpress.get_ajax_url = function( action ) {
		return SupportPressUserWidgetVars.ajaxurl + '&spaction=' + action;
	};

	supportpress.show_new_thread_form = function() {
		$(this).hide();
		$('#supportpress-newthread-form').slideDown();
	};

	supportpress.submit_new_thread_form = function( e ) {
		e.preventDefault();

		alert( "Doesn't work yet :)" );

		// Submit form via AJAX here
		// Use the JSON API to do this
	};

	// Initialize everything!
	$( document ).ready( supportpress.init );

} )( jQuery );