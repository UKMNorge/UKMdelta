jQuery(document).on('change', "input[name='personvern']", function() {
    var current_setting = jQuery(this).val();
    if( current_setting == 'ja' ) {
        jQuery(document).trigger('hideMediaForklaring');
    } else {
        jQuery(document).trigger('showMediaForklaring');
    }
});

jQuery(document).on('showMediaForklaring', function() {
    jQuery('#forklaring_personvern_nei').slideDown(150);
});
jQuery(document).on('hideMediaForklaring', function() {
    jQuery('#forklaring_personvern_nei').slideUp(100);
});