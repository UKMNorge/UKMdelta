function waitAMinute() {
	// Disable link
	//href = link.removeAttr('href');
	//console.log(href);
	// Start timer
	console.log('Starting timer for "Ikke fått SMS".');
	//console.log(link);
	tick();
}

function tick () {
	if (tid > 0) {
		tid = tid-1;
		printTime(tid);
		//console.log('Tid som gjenstår: '+tid);
		setTimeout(tick, 1000);
	}
	else {
		// Fjern tekst om "Linken aktiveres om xx sekunder."
		$('#no_sms_all_text').text('');
	}
}

function printTime(time) {
	$('#no_sms_text').text(time);
}

var tid = 60;
var link = $('#no_sms_link');
var href = '';
var tekst = $('#no_sms_text');
$( document ).ready(function() {
	waitAMinute();
});
$(document).on('click', '#no_sms_link', function(e) {
 	if( tid > 0 ) {
 		e.preventDefault();
 		alert('Du må vente i ' + tid + ' sekunder.');
 	}
});

