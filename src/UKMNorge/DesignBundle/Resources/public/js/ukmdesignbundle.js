window.onbeforeunload = function() {
	$('#page_load_content').fadeOut(400, function(){$('#page_load_loader').fadeIn();});
};