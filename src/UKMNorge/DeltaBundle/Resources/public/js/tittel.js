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
	
	console.debug(Valg);
	console.debug(Rad);
	console.debug(melodiforfatter);
	console.debug(tekstforfatter);
	console.debug(innslagsnavn);
	console.debug(selvlaget);

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


$( document ).ready( function() { 
	melodiValg();
	tekstValg();
});

jQuery(document).on('click', '#tekst-valg', function() {
	tekstValg();
});

jQuery(document).on('click', '#melodi-valg', function() {
	melodiValg();
});