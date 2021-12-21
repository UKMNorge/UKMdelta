// Components
var allePersoner = Vue.component('innslag-persons', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            fylker : [],
        }
    },
    async mounted() {
        var fylker = await spaInteraction.runAjaxCall('get_all_fylker_og_kommuner/', 'GET', {});
        this.fylker = fylker;
    },
    updated() {
        this.initFilter();
    },
    methods : {
        async getArrangement(kommune) {
            kommune.arrangementer = await spaInteraction.runAjaxCall('get_arrangementer_i_kommune/' + kommune.id, 'GET', {});
            kommune.arrangementer_loaded = true;            
        }
    },
    template : `
    <div>
        <h1>Persons</h1>
    </div>
    `
})


// The app
var oversiktInnslag = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageOversiktInnslag',
    data: {
        innslag : 'innslagYoYo',
        navn : 'Innslag Navn'
    },
    methods : {
        addNew : function() {
            this.items.push({text : 'four'})
        }
    },
    components : {
        allePersoner
    }
})