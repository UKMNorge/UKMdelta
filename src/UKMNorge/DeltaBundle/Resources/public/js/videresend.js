jQuery(document).on('click', '#pagecontent a:not(.this-is-js)', function( e ) {
	e.preventDefault();
	jQuery('#deltapath').val( jQuery(this).attr('href') );
	jQuery('#mainform').submit();
} );