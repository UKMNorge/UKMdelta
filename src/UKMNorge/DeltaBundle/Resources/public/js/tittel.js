function tekstValg() {
	var tekstValg = $("#tekst-valg");
	var tekstRad = $("#tekst-rad");
	var sangtype = $(":radio:checked", tekstValg);
	if (sangtype.val() == 'sang') {
		tekstRad.show(400);
		//tekstRad.slideDown();
		//tekstRad.fadeIn(400);
	}
	else if (sangtype.val() == 'instrumental') {
		tekstRad.hide(400);
	}

	//console.debug(sangtype);
}
function melodiValg() {
	var Valg = $("#melodi-valg");
	var Rad = $("#melodi-rad");
	var melodiforfatter = $(":input[name='melodiforfatter']", Rad);
	var tekstforfatter = $(":input[name='tekstforfatter']");
	var innslagsnavn = $(":input[name='innslagsnavn']").val();
	var selvlaget = $(":radio:checked", Valg);
	
	if (selvlaget.val() == '0') {
		if (melodiforfatter.val() == innslagsnavn) {
			melodiforfatter.val('');	
		}
		if (tekstforfatter.val() == innslagsnavn) {
			tekstforfatter.val('');
		}
		//tekstRad.slideDown();
		//tekstRad.fadeIn(400);
	}
	else if (selvlaget.val() == '1') {
		if (melodiforfatter.val() == '') {
			melodiforfatter.val(innslagsnavn);	
		}

		if (tekstforfatter.val() == '') {
			tekstforfatter.val(innslagsnavn);	
		}
		
	}
}

jQuery(document).on('click', '#koreografi-valg', function() {
	var Valg = $("#koreografi-valg");
	var Rad = $("#koreografi-rad");
	var koreografi = $(":input[name='koreografi']", Rad);
	var selvlaget = $(":radio:checked", Valg);
	var innslagsnavn = $(":input[name='innslagsnavn']").val();
	
	if (selvlaget.val() == '0') {
		if (koreografi.val() == innslagsnavn) {
			koreografi.val('');	
		}
		if (koreografi.val() == innslagsnavn) {
			koreografi.val('');
		}
	}
	else if (selvlaget.val() == '1') {
		if (koreografi.val() == '') {
			koreografi.val(innslagsnavn);	
		}

		if (koreografi.val() == '') {
			koreografi.val(innslagsnavn);	
		}		
	}
});

$(document).on('click', '#leseopp-valg', function(){
	if( $("#leseopp-valg").find(':radio:checked').val() == 0 ) {
		$('.leseopp-true').slideUp();
	} else {
		$('.leseopp-true').slideDown();
	}
});

$( document ).ready( function() { 
	melodiValg();
	tekstValg();
	$('#leseopp-valg').click();
});

jQuery(document).on('click', '#tekst-valg', function() {
	tekstValg();
});

jQuery(document).on('click', '#melodi-valg', function() {
	melodiValg();
});