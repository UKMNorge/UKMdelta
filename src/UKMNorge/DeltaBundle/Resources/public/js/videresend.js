jQuery(document).on('click', '#overview_mainform a:not(.this-is-js)', function( e ) {
	e.preventDefault();
	jQuery('#deltapath').val( jQuery(this).attr('href') );
	jQuery('#overview_mainform').submit();
} );