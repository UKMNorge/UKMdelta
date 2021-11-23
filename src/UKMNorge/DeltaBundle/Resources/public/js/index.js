// Initialization after the document is ready

var removeInnslag = async (e, response, ukmOnePage, otherData) => {
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

var addInnslag = async (e, response, ukmOnePage, otherData) => {
    var el = $('#paameldingerFullforte');

    try{
        var res = await response;
        ukmOnePage.appendHTML(el, innslagTemplate(res.navn));
    }catch(err) {
        // Error
        console.error(err);
    }
};


var alleFylkerOgKommuner = async (e, response, ukmOnePage, otherData) => {
    var el = $('#alleFylkerOgKommuner');

    try{
        var res = await response;
        el.append(fylkerOgKommunerTemplate(res));
        
    }catch(err) {
        // Error
        console.error(err);
    }
};
// console.log(1711);
// console.log(EventElement);

var eventElements = [];

eventElements.push(
    removeInnslag = new EventElement('.fjern-innslag-btn', 'click', removeInnslag, 'remove_innslag', 'POST', ['pl_id', 'b_id'], [])
);

eventElements.push(
    removeInnslag = new EventElement('#testBtnMeldPaa', 'click', addInnslag, 'new_innslag', 'POST', ['k_id', 'pl_id', 'type'], [])
);

// Hent alle fylker og kommuner
eventElements.push(
    removeInnslag = new EventElement(window, 'load', alleFylkerOgKommuner, 'get_all_fylker_og_kommuner', 'GET', [], [])
);


const deltaOnePage = new DeltaOnePage('/app_dev.php/api/', eventElements);