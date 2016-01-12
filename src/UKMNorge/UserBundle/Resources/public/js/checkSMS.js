function checkSMS() {
	console.log('Ajax fired.');

	var phone = $('#phone').val();
	var ajax_url = $('#url').val();

	console.log('Opening ' + ajax_url);
	// Stop timeout
	clearInterval(interval);
	$.ajax({
        url: ajax_url,
        async: true,
        dataType: "json"
	}).done(function( data ) {
		console.log(data);
		console.log(data[0].validated);
		if(data[0].validated == '1') {
			console.log('Mottatt og godkjent SMS, alt vel');
			// Redirect her
			window.location.reload();
		} 
		else if (data[0].validated == 0) {
			console.log('Ikke mottatt enda.');
			interval = setInterval(checkSMS, timeout);
		}
		else {
			interval = setInterval(checkSMS, timeout);
			console.log('Some error or something occurred');
		}
	});
};
// 3 sekunder
var timeout = 3000;
var interval = setInterval(checkSMS, timeout);