jQuery(document).ready(function($) {
	
	var getSearchTerm = function() {

		// Input of the Respondents field can contain multiple addresses,
		// we only want to search on the last address
		var resps = $('#respondents').val().replace(" ", '').split(",");
		var search_for = resps.pop();

		return { "respondents":search_for, "api-action":'get-respondents' };
		
	}	

	$('#respondents').autocomplete({

		source: function( req, response ) {
			
			$.ajax({
				url: SFRespondentsAc.ajax_url,
				dataType: 'json',
				data: getSearchTerm(),
				success: function(data) {
					console.log(data);
				}
			});

		},
		select: function( event, ui ) {
			console.log(ui);
		},
		minLength: 2,
	});

});