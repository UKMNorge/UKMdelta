jQuery(document).on('click', '.triggerLink', function() {
	document.getElementById( jQuery(this).find('a.actionLink').attr('id') ).click();
});

jQuery(document).on('click', '.triggerClick', function() {
	document.getElementById( jQuery(this).find('.clickMe').attr('id') ).click();
})