jQuery(document).ready(function() {
	jQuery('#filterKommuner').fastLiveFilter('#kommuner ul', 
		{callback:
			function(numShown) {
				if( jQuery('#filterKommuner').val().length == 0 ) {
					jQuery(document).trigger('monstring_not_searching');
				} else if(numShown == 0) {
					jQuery(document).trigger('monstring_none_found');
				} else {
					jQuery(document).trigger('monstring_some_found');
				}
			}
		});
	jQuery('#filterKommuner').change();
	//jQuery('.monstringSok').hide();
});

jQuery(document).on('monstring_none_found', function() {
	//jQuery('#plStartSearch').hide();
	jQuery('#plNoneFound').show();
});

jQuery(document).on('monstring_some_found', function() {
	//jQuery('#plStartSearch').hide();
	jQuery('#plNoneFound').hide();
	jQuery('#kommuner button').each(function() {
		if(jQuery(this).find('button:visible').length == 0) {
			jQuery(this).closest('h3').hide();
		}
		else {
			jQuery(this).closest('h3').show();
		}
	})
});

jQuery(document).on('monstring_not_searching', function() {
	//jQuery('.monstringSok').hide();

	//jQuery('#plStartSearch').show();
	jQuery('#plNoneFound').hide();
});