// Components
var innslagComponent = Vue.component('innslag-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            alle_innslag : [],
        }
    },
    async mounted() {
        var innslags = await spaInteraction.runAjaxCall('get_all_innslag', 'GET', {});

        this.alle_innslag = [
            ['Fullførte påmeldinger', innslags.fullforte], 
            ['Ufullstendige påmeldinger', innslags.ikke_fullforte]
        ]

        console.log(this.alle_innslag);
    },
    methods : {
        showDelete : function(event) {
            var el = $(event.currentTarget).parent().parent().parent();        
            $(el).find('.slett-paamelding-div').toggleClass('hide-gently').toggleClass('show-gently');
            $(el).find('.avatars-div').toggleClass('hide-gently').toggleClass('show-gently');
            $(el).find('.meld-av-bilde img').toggleClass('opacity-hidden');
            $(el).find('.inner').toggleClass('make-space');
        },
        deleteInnslag : async function(innslag) {
            var mainDiv = $('.slett-beskjed[innslag-id="' + innslag.id + '"]');
            $(mainDiv).removeClass('hide');

            // var innslagId = $(e.currentTarget).attr('innslag-id');

            try{
                var res = await spaInteraction.runAjaxCall('remove_innslag/', 'POST', {pl_id : innslag.context.monstring.id, b_id : innslag.id})
                
                if(res) {
                    setTimeout(() => {
                        spaInteraction.removeElementFromDOMSlideUp($(mainDiv).parent());
                    }, 1000)
                }
            }catch(err) {
                // Error
                console.error(err);
            }
        }
    },
    template : `
    <div>
        <div v-for="innslagCategory in alle_innslag">
            
            <!-- ingen innslag -->
            <div class="no-innslag-div">
                <img v-if="alle_innslag[0][1].length < 1 && alle_innslag[1][1].length < 1 && alle_innslag[0][1] == innslagCategory[1]" src="https://assets.${ getCurrentDomain() }/img/delta-nytt/not_found_events.png" class="{{ isPaameldingNull ? '' : 'hide' }}" />
            </div>
           
            <h3 v-if="innslagCategory[1].length > 0" class="tittle">#{ innslagCategory[0] }</h3>

            <div v-for="innslag in innslagCategory[1]" class="paamelding-box">
                <div class="slett-beskjed hide" v-bind:innslag-id="innslag.innslag.id">
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
                        <a :href="['/ukmid/pamelding/' + innslag.innslag.kommune_id + '-' + innslag.innslag.home.id + '/' + innslag.innslag.type.key + '/' + innslag.innslag.id + '/']">
                            <button class="small-button-style hover-button-delta mini">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="fill: #fff; transform: ;msFilter:;"><path d="m18.988 2.012 3 3L19.701 7.3l-3-3zM8 16h3l7.287-7.287-3-3L8 13z"></path><path d="M19 19H8.158c-.026 0-.053.01-.079.01-.033 0-.066-.009-.1-.01H5V5h6.847l2-2H5c-1.103 0-2 .896-2 2v14c0 1.104.897 2 2 2h14a2 2 0 0 0 2-2v-8.668l-2 2V19z"></path></svg>
                            </button>
                        </a>
                        <button @click="showDelete" class="small-button-style hover-button-delta mini go-to-meld-av">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="2.5 0 25 25" style="fill: #fff; transform: ;msFilter:;"><path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="inner">
                    <div class="row">
                        <div class="col-6 innslag-info">
                            <p class="description">Navn på gruppe</p>
                            <h5 class="name nop">#{innslag.innslag.navn}</h5>
                        </div>
                        <div class="col-6 meld-av-bilde">
                            <img class="opacity-hidden" src="https://assets.${ getCurrentDomain() }/img/delta-nytt/meld-av.png" />
                        </div>
                    </div>
                    <div class="members">
                        <div class="avatars-div show-gently">
                            <div class="avatars">
                                <img v-for="innslag in innslag.personer" class="avatar" src="https://assets.${ getCurrentDomain() }/img/delta-nytt/avatar-female.png" />
                            </div>
                            
                            <span class="total">#{ innslag.personer.length } medlem#{ innslag.personer.length > 1 ? 'er' : '' }</span>
                        </div>
                        <div class="slett-paamelding-div hide-gently">
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
        visible : true,
        message : 'Hello, world!',
        items : [
            {text : 'one'},
            {text : 'two'},
            {text : 'three'}
        ]
    },
    methods : {
        addNew : function() {
            this.items.push({text : 'four'})
        }
    },
    components : {
        innslagComponent
    }
})