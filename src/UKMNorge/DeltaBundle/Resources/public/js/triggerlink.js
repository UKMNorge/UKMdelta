jQuery(document).on('click', '.triggerLink', function() {
	document.getElementById( jQuery(this).find('a.actionLink').attr('id') ).click();
});

jQuery(document).on('click', '.triggerClick:not( input, label)', function() {
	document.getElementById( jQuery(this).find('.clickMe').attr('id') ).click();
})


$(document).on('touchend click', '.checkChildCheckbox', function(event) {
	var target = $(event.target);
	var checkbox = $(target).find('input.iAmChildCheckbox');
	
	if( !checkbox.prop('checked') ) {
		checkbox.prop('checked','checked');
	} else {
		checkbox.prop('checked',false);
		checkbox.removeProp('checked');
	}
});