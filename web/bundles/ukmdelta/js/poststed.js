jQuery(document).on("keyup", '#postNumber', function() {
	
	// Finner poststed ut fra postnummer
	var postnummer = $("#postNumber").val();
	var api_url = 'https://delta.ukm.dev/web/app_dev.php/api/poststed/' + postnummer;
	var api_url2 = 'https://api.ukm.no/post:sted/' + postnummer;
	// Sjekk om det er 4 characters
	if (postnummer.length != 4) {
		// Empty poststed?
		$("#postPlace").val('');
		return;
	}
	console.log("AJAX Poststed started.");
	// Utfør AJAX mot API
	$.ajax({
		url: api_url2,
		dataType: 'json'}
		).done(function( data ) {
		if (data.sted != false) {
			console.log(data);
			console.log(data.sted);
			$("#postPlace").val(data.sted);
		}
		else {
			$("#postPlace").val('');
		}
		console.log( "AJAX done.");
	});
});