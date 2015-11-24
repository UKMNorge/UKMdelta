jQuery(document).on('click', '.summaryDetailsList .trigger, .summaryDetailsList .list-close', function() {
	var transformList = jQuery(this).parents('.summaryDetailsList');
	// Detaljert liste er synlig, vis komprimert tekst-liste
	if( transformList.find('.details').is(':visible') ) {
		transformList.find('.details').slideUp(function() { transformList.find('.summary').fadeIn(250); });
		transformList.find('.edit').fadeIn();
	} else {
		transformList.find('.edit').hide(200);
		transformList.find('.details').slideDown();
		transformList.find('.summary').hide();
	}
});