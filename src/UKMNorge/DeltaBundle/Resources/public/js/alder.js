jQuery(document).on('change', '#myageis', function() {
    var current_age = jQuery(this).val();
    console.log('changeForesatt', current_age);
    if( current_age < 15 && current_age > 0 ) {
        jQuery(document).trigger('showForesatt');
    } else {
        jQuery(document).trigger('hideForesatt');
    }
});

jQuery(document).on('showForesatt', function() {
    console.log('showForesatt');
    jQuery('#foresatt').slideDown(150);
    jQuery('#foresatt').find('input').each( function() {
        jQuery(this).prop('required', true);
    });
});
jQuery(document).on('hideForesatt', function() {
    console.log('hideForesatt');
    jQuery('#foresatt').slideUp(100);
    jQuery('#foresatt').find('input').each( function() {
        jQuery(this).prop('required',false);
    });
});


$(document).ready(function(){
    $('#myageis').trigger('change');
});