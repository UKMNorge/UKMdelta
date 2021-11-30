$('.input-delta .overlay').off('click').click((e) => {
    var el = $(e.currentTarget).parent();
    el.addClass('open');
    el.children('.input').focus();
});

$('.input-delta .input').blur((ev) => {
    if($(ev.currentTarget).val().length < 1) {
        $(ev.currentTarget).parent().removeClass('open');
    }
})

$('.input-delta .close-btn').off('click').click((e) => {
    var el = $(e.currentTarget);
    
    $(el.parent()).find('.input').val('');
    $('.input-delta .input').blur();
    $('#searchInput').trigger('change');
});