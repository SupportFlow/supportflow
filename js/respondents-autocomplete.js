jQuery(document).ready(function($) {
	
	var getSearchTerm = function() {

		// Input of the Respondents field can contain multiple addresses,
		// we only want to search on the last address
		var resps = $('#respondents').val().replace(" ", '').split(",");

		if( $.isArray(resps) && resps.length > 1 ) {
			var search_for = resps.pop();
		}
		else {
			search_for = resps[0];
		}

		return { "respondents":search_for, "api-action":'get-respondents' };
		
	}	

	$('#respondents').autocomplete({

		source: function( req, response ) {
			
			$.ajax({
				url: SFRespondentsAc.ajax_url,
				dataType: 'json',
				data: getSearchTerm(),
				success: function(data) {
					if(data.query == "") {
						response(false);
						return false;
					}
					response( $.map( data.respondents, function (item) {

						// normaliz input
						var resps = $('#respondents').val().replace(" ", '').split(",");

						// remove partial respondent
						resps.pop();

						// replace with full email
						resps.push(item.name);

						// make it a string
						var retval = resps.join(", ");

						// return those respondents!
						return {
							label: item.name,
							value: retval
						}
					}));
				}
			});

		},
		minLength: 2
	});

});