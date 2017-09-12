jQuery(document).on('click','#show_kontaktpersoner, #show_main_mobile_menu', function(){
	pageFocus( jQuery(this) );
});
jQuery(document).on('click','#pageDeFocus',function(){
	jQuery( '#' + jQuery(this).attr('data-clicker') ).click();
});

function pageFocus(clicked) {
    if (clicked.attr('data-action') == 'show') {
        clicked.attr('data-action', 'hide');

		var title = clicked.attr('data-showJumboUKM');
		if (title == 'true') {
			jQuery('#ukm_page_jumbo_header').hide();
			jQuery('#ukm_page_jumbo_header_temp').show();
		}

        jQuery('#ukm_page_content').hide();
        jQuery('#ukm_page_pre_content').html( jQuery( clicked.attr('data-toggle') ).html() ).slideDown();

		jQuery('#ukm_page_jumbo_content').hide();
		jQuery('#ukm_page_jumbo_temp').html( clicked.attr('data-toggletitle') ).show();
		
		jQuery('#ukm_page_post_content').show();
		jQuery('#pageDeFocus').attr('data-clicker', clicked.attr('id') ).html( clicked.attr('data-toggleclose') );
    } else {
        clicked.attr('data-action', 'show');

		jQuery('#ukm_page_jumbo_temp').html('').hide();
		jQuery('#ukm_page_jumbo_content').show();

		jQuery('#ukm_page_pre_content').slideUp( 400, function(){jQuery('#ukm_page_content').show();} );
		jQuery('#ukm_page_post_content').hide();
		
		jQuery('#ukm_page_jumbo_header_temp').hide();
		jQuery('#ukm_page_jumbo_header').show();
    }
}

window.onbeforeunload = function() {
	//$('#page_load_content').fadeOut(400, function(){$('#page_load_loader').fadeIn();});
};


$(document).on('touchend click', "a.btn:not(.isClicked, .this-is-js)", function(e) {
	e.preventDefault();
    $(this).addClass('isClicked').val('Vennligst vent...').html('Vennligst vent...');
	window.location.href = $(this).attr('href');
});
$(document).on('touchend click', "a.isClicked", function(e) {
	e.preventDefault();
});

$(document).on('submit', 'form', function(){
    $(this).find(':submit').attr('disabled', 'disabled').val('Vennligst vent...').html('Vennligst vent..');
    return true;
});

jQuery(document).ready(function(){
	jQuery('textarea').autogrow();
});