jQuery(document).on('click', '.triggerLink', function() {
	document.getElementById( jQuery(this).find('a.actionLink').attr('id') ).click();
});

jQuery(document).on('click', '.triggerClick:not( input, label)', function() {
	document.getElementById( jQuery(this).find('.clickMe').attr('id') ).click();
})