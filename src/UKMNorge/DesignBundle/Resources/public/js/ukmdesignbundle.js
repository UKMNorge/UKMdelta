window.onbeforeunload = function() {
	//$('#page_load_content').fadeOut(400, function(){$('#page_load_loader').fadeIn();});
};


$(document).on('touchend click', "a.btn:not(.isClicked)", function(e) {
	e.preventDefault();
    $(this).addClass('isClicked').val('Vennligst vent...').html('Vennligst vent...');
	window.location.href = $(this).attr('href');
});
$(document).on('touchend click', "a.isClicked", function(e) {
	e.preventDefault();
});
// jQuery plugin to prevent double submission of forms
jQuery.fn.preventDoubleSubmission = function () {
    $(this).on('submit', function (e) {
        var $form = $(this);

        if ($form.data('submitted') === true) {
            // Previously submitted - don't submit again
            e.preventDefault();
        } else {
            // Mark it so that the next submit can be ignored
            // ADDED requirement that form be valid
            if($form.valid()) {
                $form.data('submitted', true);
                $form.find('button[type=submit]').val('Vennligst vent...').html('Vennligst vent...');
            } else {
	            e.preventDefault();
            }
        }
    });

    // Keep chainability
    return this;
};


$(document).ready(function(){
	$("form").preventDoubleSubmission();
});