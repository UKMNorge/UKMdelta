// General functions

var getMonthNorwegian = (monthInt) => {
    let month = [];
    month[0]="Januar";
    month[1]="Februar";
    month[2]="Mars";
    month[3]="April";
    month[4]="Mai";
    month[5]="Juni";
    month[6]="Juli";
    month[7]="August";
    month[8]="September";
    month[9]="Oktober";
    month[10]="November";
    month[11]="Desember";

    return month[monthInt];
}

var getDayNorwegian = (dayInt) => {
    let day = [];
    day[0]="Søndag";
    day[1]="Mandag";
    day[2]="Tirsdag";
    day[3]="Onsdag";
    day[4]="Torsdag";
    day[5]="Fredag";
    day[6]="Lørdag";

    return day[dayInt];
}

var refreshOnBack = () => {
    window.addEventListener( "pageshow", function ( event ) {
        var historyTraversal = event.persisted || 
                               ( typeof window.performance != "undefined" && 
                                    window.performance.navigation.type === 2 );
        if ( historyTraversal ) {
          // Handle page restore.
          window.location.reload();
        }
    });
}

var getCurrentDomain = () => {
    var hostname = window.location.hostname;
    return hostname.split('.')[1] + '.' + hostname.split('.')[2];
}

var deltaStyleShowRemoveButton = (e) => {
    var cTarget = $(e.currentTarget).parent().parent();
    cTarget.toggleClass('remove-mode').addClass('moving');
    
    setTimeout(function() {
        cTarget.removeClass('moving');
    }, 200);
}

// Input fields
var inputDeltaFix = function() {
    $('.input-delta .overlay').off('click').click((e) => {
        var el = $(e.currentTarget).parent();
        el.addClass('open');
        el.children('.input').focus();

        if(el.hasClass('validation-inactive-click')) {
            el.removeClass('validation-failed-active validation-inactive-click');
        }
    });

    $('.input-delta .input').off('blur').blur((ev) => {
        var inputDelta = $(ev.currentTarget).parent();
        if($(ev.currentTarget).val().length < 1) {
            inputDelta.removeClass('open');
        }
        if(inputDelta.hasClass('validation-failed')) {
            inputDelta.removeClass('validation-failed').addClass('validation-failed-active');
        }
    })
    

    $('.input-delta .close-btn').off('click').click((e) => {
        var el = $(e.currentTarget);
        
        $(el.parent()).find('.input').val('');
        $('.input-delta .input').blur();
        $('#searchInput').trigger('change');
    });

    // Do not allow varighet under 1 second
    $('.varighet-limit input').blur((e) => {
        var el = $(e.currentTarget);
        var sec = el.parent().find('input[name="sekunder"]');
        var min = parseInt(el.parent().find('input[name="minutter"]').val());
        if(parseInt(sec.val()) < 1) {
            if(min < 1) {
                sec.val(1);
            }
        }
    })
}
inputDeltaFix();