jQuery(document).on('click', 'a', function( e ) {
	e.preventDefault();
	jQuery('#deltapath').val( jQuery(this).attr('href') );
	jQuery('#mainform').submit();
} );