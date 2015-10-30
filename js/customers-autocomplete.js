jQuery(document).ready(function ($) {
	if ('sf_ticket' == pagenow) {
		var getSearchTerm = function () {

			// Input of the Customers field can contain multiple addresses,
			// we only want to search on the last address
			var resps = $('#customers').val().replace(" ", '').split(",");

			if ($.isArray(resps) && resps.length > 1) {
				var search_for = resps.pop();
			}
			else {
				search_for = resps[0];
			}

			return {
				"customers"           : search_for,
				"api-action"          : 'get-customers',
				"get_customers_nonce" : SFCustomersAc.get_customers_nonce
			};
		};

		$('#customers').autocomplete({

			source   : function (req, response) {

				$.ajax({
					url     : SFCustomersAc.ajax_url,
					dataType: 'json',
					data    : getSearchTerm(),
					success : function (data) {
						if (data.query == "") {
							response(false);
							return false;
						}
						response($.map(data.customers, function (item) {

							// normaliz input
							var resps = $('#customers').val().split(' ').join('').split(",");

							// remove partial customer
							resps.pop();

							// Check if e-mail id already exists
							if (-1 == $.inArray(item.name, resps)) {

								// replace with full email
								resps.push(item.name);

								// make it a string
								var retval = resps.join(", ") + ", ";

								// return those customers!
								return {
									label: item.name,
									value: retval
								}
							}
						}));
					}
				});

			},
			minLength: 2
		});
	}
});