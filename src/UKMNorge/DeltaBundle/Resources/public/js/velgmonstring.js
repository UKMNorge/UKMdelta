jQuery(document).ready(function() {
    jQuery('#filterMonstringer').fastLiveFilter('#lokalmonstringer ul', {
        callback: function(numShown) {
            if (jQuery('#filterMonstringer').val().length == 0) {
                jQuery(document).trigger('monstring_not_searching');
            } else if (numShown == 0) {
                jQuery(document).trigger('monstring_none_found');
            } else {
                jQuery(document).trigger('monstring_some_found');
            }
        }
    });
    jQuery('#filterMonstringer').change();
});

jQuery(document).on('monstring_none_found', function() {
    jQuery('#plNoneFound').show();
    jQuery('.fylkeHeader').hide();
});

jQuery(document).on('monstring_some_found', function() {
    //jQuery('#plStartSearch').hide();
    jQuery('#plNoneFound').hide();
    jQuery('#lokalmonstringer ul').each(function() {
        if (jQuery(this).find('li.kommune:visible').length == 0) {
            jQuery('#header_' + jQuery(this).attr('data-fylke')).hide();
        } else {
            jQuery('#header_' + jQuery(this).attr('data-fylke')).show();
        }
    })
});

jQuery(document).on('monstring_not_searching', function() {
    jQuery('#plNoneFound').hide();
});