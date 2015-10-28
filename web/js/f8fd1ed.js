/**
 * fastLiveFilter jQuery plugin 1.0.3
 * 
 * Copyright (c) 2011, Anthony Bush
 * License: <http://www.opensource.org/licenses/bsd-license.php>
 * Project Website: http://anthonybush.com/projects/jquery_fast_live_filter/
 **/

jQuery.fn.fastLiveFilter = function(list, options) {
	// Options: input, list, timeout, callback
	options = options || {};
	list = jQuery(list);
	var input = this;
	var timeout = options.timeout || 0;
	var callback = options.callback || function() {};
	
	var keyTimeout;
	
	// NOTE: because we cache lis & len here, users would need to re-init the plugin
	// if they modify the list in the DOM later.  This doesn't give us that much speed
	// boost, so perhaps it's not worth putting it here.
	var lis = list.children();
	var len = lis.length;
	var oldDisplay = len > 0 ? lis[0].style.display : "block";
	callback(len); // do a one-time callback on initialization to make sure everything's in sync
	
	input.change(function() {
		// var startTime = new Date().getTime();
		var filter = input.val().toLowerCase();
		var li;
		var numShown = 0;
		for (var i = 0; i < len; i++) {
			li = lis[i];
			if (( jQuery(li).attr('data-filter') || li.textContent || li.innerText ||  "").toLowerCase().indexOf(filter) >= 0) {
				if (li.style.display == "none") {
					li.style.display = oldDisplay;
				}
				numShown++;
			} else {
				if (li.style.display != "none") {
					li.style.display = "none";
				}
			}
		}
		callback(numShown);
		// var endTime = new Date().getTime();
		// console.log('Search for ' + filter + ' took: ' + (endTime - startTime) + ' (' + numShown + ' results)');
		return false;
	}).keydown(function() {
		// TODO: one point of improvement could be in here: currently the change event is
		// invoked even if a change does not occur (e.g. by pressing a modifier key or
		// something)
		clearTimeout(keyTimeout);
		keyTimeout = setTimeout(function() { input.change(); }, timeout);
	});
	return this; // maintain jQuery chainability
}
jQuery('.transformList .click').on('click', function() {
	var transformList = jQuery(this).parents('.transformList');
	// Detaljert liste er synlig, vis komprimert tekst-liste
	if( transformList.find('.list-detailed').is(':visible') ) {
		transformList.find('.list-detailed').slideUp();
		transformList.find('.list-short').fadeIn();
		transformList.find('.edit').fadeIn();
	} else {
		transformList.find('.edit').fadeOut();
		transformList.find('.list-short').fadeOut();
		transformList.find('.list-detailed').slideDown();
	}
	
});
jQuery(document).ready(function() {
	jQuery('#filterMonstringer').fastLiveFilter('#lokalmonstringer ul', 
											{callback:
												function(numShown) {
													if( jQuery('#filterMonstringer').val().length == 0 ) {
														jQuery(document).trigger('monstring_not_searching');
													} else if(numShown == 0) {
														jQuery(document).trigger('monstring_none_found');
													} else {
														jQuery(document).trigger('monstring_some_found');
													}
												}
											});
	jQuery('#filterMonstringer').change();
	//jQuery('.monstringSok').hide();
});

jQuery(document).on('monstring_none_found', function() {
	//jQuery('#plStartSearch').hide();
	jQuery('#plNoneFound').show();
});

jQuery(document).on('monstring_some_found', function() {
	//jQuery('#plStartSearch').hide();
	jQuery('#plNoneFound').hide();
	jQuery('#lokalmonstringer ul').each(function() {
											if(jQuery(this).find('li:visible').length == 0) {
												jQuery('#header_'+jQuery(this).attr('data-fylke')).hide();
											}
											else {
												jQuery('#header_'+jQuery(this).attr('data-fylke')).show();
											}
										})
});

jQuery(document).on('monstring_not_searching', function() {
	//jQuery('.monstringSok').hide();

	//jQuery('#plStartSearch').show();
	jQuery('#plNoneFound').hide();
});