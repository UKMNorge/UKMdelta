jQuery(document).on('click', 'a:not(.this-is-js)', function( e ) {
	e.preventDefault();
	jQuery('#deltapath').val( jQuery(this).attr('href') );
	jQuery('#mainform').submit();
} );