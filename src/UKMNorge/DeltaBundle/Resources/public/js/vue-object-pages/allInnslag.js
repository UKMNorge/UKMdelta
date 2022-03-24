// Components
var innslagComponent = Vue.component('innslag-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            alle_innslag : []
        }
    },
    async mounted() {
        this.updateData();
    },
    methods : {
        forceRerender() {
            // Removing my-component from the DOM
            this.renderComponent = false;

            this.$nextTick(() => {
                // Adding the component back in
                this.renderComponent = true;
            });
        },
        updateData : async function() {
            var innslags = await spaInteraction.runAjaxCall('get_all_innslag', 'GET', {});
    
            if(innslags) {
                this.alle_innslag = [
                    ['Fullførte påmeldinger', innslags.fullforte], 
                    ['Ufullstendige påmeldinger', innslags.ikke_fullforte]
                ]

                for(var innslag of this._alleInnslags()) {
                    console.log(innslag);
                    innslag.innslag.deleted = false;
                    innslag.innslag.showDelete = false;
                }
            }
        },
        _alleInnslags : function() {
            return this.alle_innslag[0][1].concat(this.alle_innslag[1][1]);
        },
        isEmpty : function(arg) {
            if(!this.alle_innslag || this.alle_innslag.length < 1) return false;
            return this.alle_innslag[0][1].length < 1 && this.alle_innslag[1][1].length < 1;
        },
        showDelete : function(innslag) {
            innslag.showDelete = !innslag.showDelete ? true : false;
            innslag.__ob__.dep.notify();
        },
        deleteInnslag : async function(innslag) {
            var mainDiv = $('.slett-beskjed[innslag-id="' + innslag.id + '"]');
            $(mainDiv).removeClass('hide');

            // var innslagId = $(e.currentTarget).attr('innslag-id');

            try{
                var res = await spaInteraction.runAjaxCall('remove_innslag/', 'POST', {pl_id : innslag.context.monstring.id, b_id : innslag.id})
                
                if(res) {
                    innslag.deleted = true;
                    innslag.__ob__.dep.notify();

                    setTimeout(() => {
                        this.updateData();
                    }, 3000);
                }
            }catch(err) {
                // Error
                console.error(err);
            }
        },
        openInnslag : function(innslag) {
            var link = '/ukmid/pamelding/' + innslag.kommune_id + '-' + innslag.home.id + '/' + innslag.type.key + '/' + innslag.id + '/';
            window.location.href = link;
            
            // Refresh after revisitong (back button)
            window.addEventListener( "pageshow", function ( event ) {
                var historyTraversal = event.persisted || 
                                       ( typeof window.performance != "undefined" && 
                                            window.performance.navigation.type === 2 );
                if ( historyTraversal ) {
                  // Handle page restore.
                  window.location.reload();
                }
            });
            
        }
    },
    template : /*html*/`
    <div>
        <!-- ingen innslag -->
        <div v-if="isEmpty(1)">
            <div class="no-innslag-div">
                <img src="https://assets.${ getCurrentDomain() }/img/delta-nytt/not_found_events.png" class="{{ isPaameldingNull ? '' : 'hide' }}" />
            </div>
        </div>
        <div v-else v-for="innslagCategory in alle_innslag">
            <h3 v-if="innslagCategory[1].length > 0" class="tittle">#{ innslagCategory[0] }</h3>

            <div v-for="innslag in innslagCategory[1]" class="paamelding-box" :class="{ 'hide-up-gently' : innslag.innslag.deleted }">
                <div class="slett-beskjed" :class="{ 'hide' : !innslag.innslag.deleted }" v-bind:innslag-id="innslag.innslag.id">
                    <div class="beskjed-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm-1.999 14.413-3.713-3.705L7.7 11.292l2.299 2.295 5.294-5.294 1.414 1.414-6.706 6.706z"></path></svg>
                        <p class="text">Bidraget ble slettet</p>
                    </div>
                </div>

                <div class="header-top">
                    <div class="category-tittel">
                        <div class="mini-label-style label">
                            <span>#{innslag.innslag.type.name}</span>
                        </div>
                    </div>
                    <div class="buttons">
                        <!-- /ukmid/pamelding/{k_id}-{pl_id}/{type}/{b_id}/ -->
                        <button @click="openInnslag(innslag.innslag)" class="small-button-style hover-button-delta mini">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="fill: #fff; transform: ;msFilter:;"><path d="m18.988 2.012 3 3L19.701 7.3l-3-3zM8 16h3l7.287-7.287-3-3L8 13z"></path><path d="M19 19H8.158c-.026 0-.053.01-.079.01-.033 0-.066-.009-.1-.01H5V5h6.847l2-2H5c-1.103 0-2 .896-2 2v14c0 1.104.897 2 2 2h14a2 2 0 0 0 2-2v-8.668l-2 2V19z"></path></svg>
                        </button>
                        <button @click="showDelete(innslag.innslag)" class="small-button-style hover-button-delta mini go-to-meld-av">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="2.5 0 25 25" style="fill: #fff; transform: ;msFilter:;"><path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="inner" :class="{ 'make-space' : innslag.innslag.showDelete }">
                    <div class="row">
                        <div class="col-6 innslag-info">
                            <p class="description">Navn på gruppe</p>
                            <h5 class="name nop">#{innslag.innslag.navn}</h5>
                        </div>
                        <div class="col-6 meld-av-bilde">
                            <img :class="{ 'opacity-hidden' : !innslag.innslag.showDelete }" src="https://assets.${ getCurrentDomain() }/img/delta-nytt/meld-av.png" />
                        </div>
                    </div>
                    <div class="members">
                        <div class="avatars-div" :class="innslag.innslag.showDelete ? 'hide-gently' : 'show-gently'">
                            <div class="avatars">
                                <img v-for="innslag in innslag.personer" class="avatar" src="https://assets.${ getCurrentDomain() }/img/delta-nytt/avatar-female.png" />
                            </div>
                            
                            <span class="total">#{ innslag.personer.length } medlem#{ innslag.personer.length > 1 ? 'er' : '' }</span>
                        </div>
                        <div class="slett-paamelding-div" :class="innslag.innslag.showDelete ? 'show-gently' : 'hide-gently'">
                            <button @click="deleteInnslag(innslag.innslag)" v-bind:innslag-id="innslag.innslag.id" class="round-style-button slett-paamelding">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewbox="0 0 24 24" style="fill: #FFF; margin-right: 0.5rem">
                                    <path d="M6 7H5v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H6zm10.618-3L15 2H9L7.382 4H3v2h18V4z"></path>
                                </svg>
                                <span>Meld av</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `
})

// The app
var allInnslag = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageAllInnslag',
    data: {
        
    },
    methods : {
        addNew : function() {
            // this.$refs.innslagsType.initTypes();
            this.items.push({text : 'four'})
        },
        update : function() {
            allInnslag.$children[0].updateData();
        }
    },
    components : {
        innslagComponent
    }
})