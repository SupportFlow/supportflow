// Javascript is not my best scripting language
// This will need love from Nikolay or another JS wizard
// -Alex

var supportpress = {};

( function( $ ) {

	supportpress.init = function() {
		supportpress.$widget_container = $( '#supportpress-widget' );

		supportpress.populate_my_threads();
	};

	supportpress.get_ajax_url = function( action ) {
		return SupportPressUserWidgetVars.supportpressurl + '/wp-admin/admin-ajax.php?action=supportpress&spaction=' + action;
	};

	supportpress.populate_my_threads = function() {
		$.getJSON( supportpress.get_ajax_url( 'mythreads' ), function( data ) {
			var items = [];

			$.each( data, function( key, thread ) {
				items.push('<li id="' + thread.ID + '"><a href="' + thread.permalink + '">' + thread.post_title + '</a></li>');
			});

			supportpress.$widget_container.html( '<strong>My Tickets</strong>' );

			$( '<ul/>', {
				'class': 'mythreads',
				html: items.join('')
			}).appendTo( supportpress.$widget_container );
		} );
	};

	// Initialize everything!
	$( document ).ready( supportpress.init );

} )( jQuery );