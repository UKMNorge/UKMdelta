// Components
var allePersoner = Vue.component('innslag-persons', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            personer : [],
        }
    },
    async mounted() {
        var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        var personer = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
        this.personer = personer;
    },
    methods : {
        showRemoveButton : (e) => {
            console.log('aaee');
            $(e.currentTarget).parent().parent().toggleClass('remove-mode');
        },
        removePerson : function(person) {
            console.log(this.personer);
            this.personer.splice(this.personer.indexOf(person), 1);
        }
    },
    template : `
    <div>
    <div v-for="person in personer">
        
        <div class="person">
            <div class="avatar">
                <img class="avatar" src="https://assets.ukm.dev/img/delta-nytt/avatar-female.png">
            </div>
            <div class="user-info">
                <p class="rolle">#{ !person.rolle ? person.rolle : 'Ukjent rolle' }</p>
                <p class="name">#{ person.fornavn }</p>
            </div>
            <div class="buttons">
                <button @click="showRemoveButton" class="small-button-style hover-button-delta mini remove-person-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="5 1 25 25" style="fill: #fff; transform: ;msFilter:;"><path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path></svg>
                </button>
            </div>
            <div class="remove-button-show-hide">
                <button @click="removePerson(person)" innslag-id="94100" class="round-style-button mini-size slett-paamelding">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 32 32" style="fill: #fff; transform: ;msFilter:;">
                    <path d="M6 7H5v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H6zm10.618-3L15 2H9L7.382 4H3v2h18V4z"></path>
                </svg>
                    <span>Bekreft sletting</span>
                </button>
            </div>
        </div>

    </div>
    </div>
    `
})


// The app
var oversiktInnslag = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageOversiktInnslag',
    data: {
        innslag_id : null,
        innslag : {navn : '2'}
    },
    created() {
        console.log('created')
    },
    async mounted() {
        this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        var innslag = await spaInteraction.runAjaxCall('get_innslag/'+this.innslag_id, 'GET', {});
        this.innslag = innslag;
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