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
                $('.panel-body.fylke-body.search').removeClass('show');
                $('.accordion-by .card-header-fylke .btn-link').removeClass('halv');
            }
                for(let el of $('#alleFylkerOgKommuner .accordion .card .fylke-body.search')) {
                    if($(el).parent().parent().css('display') != 'none') {
                        var count = 0;
                        for(var kommune of $(el).find('.card-body-kommune.search')) {
                            if($(kommune).css('display') != 'none') {
                                count++;
                            }
                        }
                        if(count == 0) {
                            $(el).find('.card-body-kommune.search').css('display', 'flex');
                        }
                        else if(count > 0 && count < 15) {
                            $(el).collapse('show');
                            console.log(el);
                            $(el).parent().find('.fylke-btn').addClass('halv');
                        }
                    }
                }
            

        };

        // Filter
        $('#searchInput').fastLiveFilter('#alleFylkerOgKommuner, .search-kommune', {
            callback: callbackFilter}
        );
        
        // Click fylke button
        $('.card-header-fylke .fylke-btn').click((e) => {
            var el = $(e.currentTarget);
            if(el.hasClass('halv')) {
                el.removeClass('halv');
                var mainElement = el.parent().parent();
                mainElement.find('.search-kommune .search').css('display', 'flex');
                console.log(mainElement.find('.fylke-body.search'));
                mainElement.find('.fylke-body.search').removeClass(['collapse', 'show']).collapse('show', 1000);
            }
        });
            
        
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