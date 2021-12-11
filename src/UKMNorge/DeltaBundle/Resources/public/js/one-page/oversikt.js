var eventElements = [];


// ALLE PERSONER -----
var getAllPersons = async (e, response, ukmOnePage, doAfter) => {
    try{
        var persons = await response;
        
        for(var person of persons) {
            $(singlePersonPreviewTemplate(person)).append($('#allPersons'));
        }
        
    }catch(err) {
        // Error
        console.error(err);
    }
}

var b_id = $('#allPersons').attr('b_id');
eventElements.push(
    new EventElement(window, 'load', getAllPersons, 'get_all_persons/'+b_id, 'GET', [])
);




// NY PERSON -----
var createNewPerson = async (e, response, ukmOnePage, doAfter) => {

    // Adding phantom
    $(singlePersonPreviewTemplate({person : null}, true)).insertBefore($('#allPersons #newUserCollapse'));

    console.log('here');

    try{
        var person = await response;
        
        // Remove phantom
        $('#phantomloadingNewPerson').detach();
        $(singlePersonPreviewTemplate(person)).insertBefore($('#allPersons #newUserCollapse'));
        
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