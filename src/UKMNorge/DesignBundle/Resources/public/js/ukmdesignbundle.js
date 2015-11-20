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

$(document).on('submit', 'form', function(){
    $(this).find(':submit').attr('disabled', 'disabled').val('Vennligst vent...').html('Vennligst vent..');
    return true;
});