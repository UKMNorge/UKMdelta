// Initialization after the document is ready

var removeInnslag = async (e, response, ukmOnePage, doAfter) => {
    var el = $(e.currentTarget);
    var mainDiv = $($(el).parent().parent().parent());

    ukmOnePage.fadeElementDOM(mainDiv);

    try{
        var res = await response;
        ukmOnePage.removeElementFromDOM(mainDiv);
    }catch(err) {
        // Error
        console.error(err);
    }
};

var addInnslag = async (e, response, ukmOnePage, doAfter) => {
    var el = $('#paameldingerFullforte');

    try{
        var res = await response;
        ukmOnePage.appendHTML(el, innslagTemplate(res.navn));
    }catch(err) {
        // Error
        console.error(err);
    }
};


var alleFylkerOgKommuner = async (e, response, ukmOnePage, doAfter) => {
    var el = $('#alleFylkerOgKommuner');

    try{
        var res = await response;
        el.append(fylkerOgKommunerTemplate(res));
        if(res) {
            doAfter();
        }

        var callbackFilter = (numShown) => {
            if($('#searchInput').val().length < 3) {
                $('.panel-body.fylke-body').collapse('hide');
            }

            for(let el of $('#alleFylkerOgKommuner .accordion .card .fylke-body')) {
                if($(el).parent().parent().css('display') != 'none') {
                    var count = 0;
                    for(var kommune of $(el).find('.card-body-kommune')) {
                        if($(kommune).css('display') != 'none') {
                            count++;
                        }
                    }
                    if(count == 0) {
                        $(el).find('.card-body-kommune').css('display', 'flex');
                    }
                    else if(count > 0 && count < 3) {
                        $(el).collapse('show');

                    }
                }
            }
        };

        // Filter
        $('#searchInput').fastLiveFilter('#alleFylkerOgKommuner, .kommune-accordion', {
            callback: callbackFilter});
        
        $('searchInput').blur(callbackFilter)
        
    }catch(err) {
        // Error
        console.error(err);
    }
};


var arrangementerIKommune = async (e, response, ukmOnePage, doAfter) => {
    var el = $('#collapseArrangementer' + $(e.currentTarget).attr('k_id'));

    // Stop fetching data again
    $(e.currentTarget).off('click');
    
    try{
        var res = await response;
        el.addClass('loaded');

        if($.isEmptyObject(res)) {
            el.children('.no-arrangement').removeClass('hide');
            console.log($(e.currentTarget));
            $(e.currentTarget).find('.description.info-label').addClass('no-arrangement-hide');
        }

        for(let key in res) {
            el.append(singleArrangementPreviewTemplate(res[key]));
        }

        doAfter();
        
    }catch(err) {
        // Error
        console.error(err);
    }
}

var goToInnslagTypes = async (e, response, ukmOnePage, doAfter) => {
    director.openPage('pageVelgInnslagType');
    var el = $('#viseNoeFremTyper');
    
    try{
        el.html('');
        $('#viseNoeFremTyperPhantom').removeClass('loaded');
        var res = await response;
        
        $('#viseNoeFremTyperPhantom').addClass('loaded');
        for(type of res) {
            el.append(innslagTypePreviewTemplate(type));
        }
        
        
        
    }catch(err) {
        // Error
        console.error(err);
    }

};

var eventElements = [];

eventElements.push(
    new EventElement('.fjern-innslag-btn', 'click', removeInnslag, 'remove_innslag', 'POST', ['pl_id', 'b_id'])
);

eventElements.push(
    new EventElement('#testBtnMeldPaa', 'click', addInnslag, 'new_innslag', 'POST', ['k_id', 'pl_id', 'type'])
);

var meldpaaClick = () => {
    deltaOnePage.addEventElements([
        new EventElement('.card-body-arrangement.meldpaa', 'click', goToInnslagTypes, 'get_innslag_types', 'GET', ['pl_id'])        
    ]);
}

getArrangementClick = () => {
    deltaOnePage.addEventElements([
        new EventElement('.kommune-accordion.collapsed', 'click', arrangementerIKommune, 'get_arrangementer_i_kommune', 'GET', ['k_id'], meldpaaClick)
    ]);
}

// Hent alle fylker og kommuner
eventElements.push(
    new EventElement(window, 'load', alleFylkerOgKommuner, 'get_all_fylker_og_kommuner', 'GET', [], getArrangementClick)
);


const deltaOnePage = new DeltaOnePage('/app_dev.php/api/', eventElements);