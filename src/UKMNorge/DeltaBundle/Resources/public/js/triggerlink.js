jQuery(document).on('click', '.triggerLink', function() {
	document.getElementById( jQuery(this).find('a.actionLink').attr('id') ).click();
});