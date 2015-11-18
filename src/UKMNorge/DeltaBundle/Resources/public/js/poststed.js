jQuery(document).on("keyup", '#postNumber', function() {
	
	// Finner poststed ut fra postnummer
	var postnummer = $("#postNumber").val();
	var api_url = 'http://delta.ukm.dev/web/app_dev.php/api/poststed/' + postnummer;
	// Sjekk om det er 4 characters
	if (postnummer.length != 4) {
		return;
	}
	console.log("AJAX Poststed started.");
	// Utfør AJAX mot API
	$.ajax(api_url).done(function( data ) {
		if (data.sted != false) {
			$("#postPlace").val(data.sted);
		}
		console.log( "AJAX done.");
	});
});