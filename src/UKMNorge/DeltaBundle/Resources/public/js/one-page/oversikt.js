var eventElements = [];

var createNewPerson = async (e, response, ukmOnePage, doAfter) => {

    // Adding phantom
    $(singlePersonPreviewTemplate('loadingNavn', 'etternavn', 'rolle', true)).insertBefore($('#allPersons #newUserCollapse'));

    try{
        var res = await response;
        
        // Remove phantom
        $('#phantomloadingNavn').detach();
        $(singlePersonPreviewTemplate(res.fornavn, res.etternavn, res.rolle)).insertBefore($('#allPersons #newUserCollapse'));
        
    }catch(err) {
        // Error
        console.error(err);
    }
}

var createNewPersonBefore = () => {
    var inputs = $('#newUserCollapse').find('.input-delta .input');
    var btn = $('#createNewPerson');

    // Add attributes to button to be used/sendt from EventElement
    for(var input of inputs) {
        btn.attr($(input).attr('name'), $(input).val());
        $(input).val('');
    }
    
    // Close the form and close all open inputs
    $('#newUserCollapse').collapse('hide').find('.input-delta').removeClass('open');
}

var createNewPersonAfter = () => {
    
}



eventElements.push(
    new EventElement(
        '#createNewPerson', 
        'click', 
        createNewPerson, 
        'new_person', 
        'POST', 
        [
            'k_id', 
            'pl_id', 
            'type',
            'b_id',
            'fornavn',
            'etternavn',
            'alder',
            'mobil',
            'rolle',
        ], 
        createNewPersonAfter, // doAfter
        createNewPersonBefore, // doAfter
        
    )
);


eventElements.push(
    new EventElement('.remove-person-button', 'click', () => {alert('aaa')}, 'remove_person', 'POST', ['k_id', 'pl_id', 'type', 'b_id', 'p_id'])
);

const deltaOnePage = new DeltaOnePage('/app_dev.php/api/', eventElements);