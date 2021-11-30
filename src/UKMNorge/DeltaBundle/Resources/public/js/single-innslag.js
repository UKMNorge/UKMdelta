$('.go-to-meld-av').off('click').click((e) => {
    var el = $(e.currentTarget).parent().parent().parent();

    $(el).find('.slett-paamelding-div').toggleClass('hide-gently').toggleClass('show-gently');
    $(el).find('.avatars-div').toggleClass('hide-gently').toggleClass('show-gently');
    $(el).find('.meld-av-bilde img').toggleClass('opacity-hidden');

    $(el).find('.inner').toggleClass('make-space');
});

var removeInnslag = async (e, response, ukmOnePage, otherData) => {
    var el = $(e.currentTarget);
    var mainDiv = $($(el).parent().parent().parent());
    $(mainDiv).parent().find('.slett-beskjed').removeClass('hide');

    try{
        var res = await response;
        setTimeout(() => {
            ukmOnePage.removeElementFromDOMSlideUp($(mainDiv).parent());
        }, 1000)
    }catch(err) {
        // Error
        console.error(err);
    }
};

var addNewInnslag = async (e, response, ukmOnePage, otherData) =>{
    alert('Yo Yo, nytt innslag!');
}

var eventElements = [];

eventElements.push(
    new EventElement('.slett-paamelding', 'click', removeInnslag, 'remove_innslag', 'POST', ['pl_id', 'b_id']),
    new EventElement('#testBtnMeldPaa', 'click', addNewInnslag, 'new_innslag', 'POST', ['k_id', 'pl_id', 'type'])
);

if(typeof deltaOnePage === 'undefined') {
    const deltaOnePage = new DeltaOnePage('/app_dev.php/api/', eventElements);
}
else {
    deltaOnePage.addEventElements(eventElements);
}



