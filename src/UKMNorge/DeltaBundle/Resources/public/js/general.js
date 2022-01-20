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