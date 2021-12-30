// Mixin super object
var skjema = {
    mounted : function() {
        this.hello = "hello";
    },
    provide() { return {child: this}},
    components: {
        super: {
            inject: ['child'],
            template: `
            <div>
                <p>{{child.hello}}</p>
                <p>Yo Yo!</p>
            </div>`
        }
    }
}



// Component
var musikkComponent = Vue.component('musikk-component', { 
    mixins : [skjema], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            titler : [],
            tekstOrInstrumental : null,
        }
    },
    async mounted() {
        setTimeout(() => {
            this.hello ='Helloooo!99';
            var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        }, 5000);
        // var titler = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
        // this.titler = titler;
    },
    methods : {
    
    },
    template : `
    <div>

    <div class="radio-input-delta">
        <p class="description">Har låten tekst, eller er det en instrumental?</p>
        <div class="inputs">
			<div class="input-div">
                <input type="radio" name="sangtype" value="sang" v-model="tekstOrInstrumental" required="" data-form-type="other">
                <span>Tekst</span>
            </div>
			<div class="input-div">
                <input type="radio" name="sangtype" value="instrumental" v-model="tekstOrInstrumental" required="" data-form-type="other">
                <span>Instrumental</span>
            </div>
		</div>
	</div>

    <!-- TEKST ELLER INSTRUMENTAL -->
    <div class="radio-input-delta">
        <p class="description">Har du/dere laget låten selv?</p>
        <div class="inputs">
			<div class="input-div">
                <input type="radio" name="selvlaget" value="1" required="" data-form-type="other">
                <span>Ja</span>
            </div>
			<div class="input-div">
                <input type="radio" name="selvlaget" value="0" required="" data-form-type="other">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- TESKTEN SKREVET AV -->
    <div v-if="tekstOrInstrumental != 'instrumental'" class="input-delta">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har skrevet teksten?</span>
            </div>
        </div>
        <input type="text" class="input" name="tekst_av">
    </div> 

    <!-- MELODI LAGET AV -->
    <div class="input-delta">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har laget melodien?</span>
            </div>
        </div>
        <input type="text" class="input" name="tekst_av">
    </div> 

    </div>
    `
});

// Component
var dansComponent = Vue.component('dans-component', { 
    mixins : [skjema], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            titler : [],
            tekstOrInstrumental : null,
        }
    },
    async mounted() {
        setTimeout(() => {
            this.hello ='Helloooo!99';
            var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        }, 5000);
        // var titler = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
        // this.titler = titler;
    },
    methods : {
    
    },
    template : `
    <div>

    <div class="radio-input-delta">
        <p class="description">Har du/dere laget koreografien / dansen selv?</p>
        <div class="inputs">
			<div class="input-div">
                <input type="radio" name="danstype" value="ja" v-model="tekstOrInstrumental" required="" data-form-type="other">
                <span>Ja</span>
            </div>
			<div class="input-div">
                <input type="radio" name="danstype" value="nei" v-model="tekstOrInstrumental" required="" data-form-type="other">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- Hvem har koreografert dansen? -->
    <div  class="input-delta">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har koreografert dansen?</span>
            </div>
        </div>
        <input type="text" class="input" name="koreografert_dansen?">
    </div> 

    </div>
    `
});


// Component
var litteraturComponent = Vue.component('litteratur-component', { 
    mixins : [skjema], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            titler : [],
            leseOpp : null,
        }
    },
    async mounted() {
        setTimeout(() => {
            this.hello ='Helloooo!99';
            var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        }, 5000);
        // var titler = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
        // this.titler = titler;
    },
    methods : {
    
    },
    template : `
    <div>

    <!-- Medforfater -->
    <div class="input-delta open">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Medforfater</span>
            </div>
        </div>
        <input type="text" class="input" name="tittel">
    </div>

    <div class="radio-input-delta">
        <p class="description">Har du/dere laget koreografien / dansen selv?</p>
        <div class="inputs">
			<div class="input-div">
                <input type="radio" v-model="leseOpp" name="danstype" value="ja" required="" data-form-type="other">
                <span>Ja</span>
            </div>
			<div class="input-div">
                <input type="radio" v-model="leseOpp" name="danstype" value="nei" required="" data-form-type="other">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- Tid for å lese opp -->
    <div v-if="leseOpp != 'nei'" class="input-delta open">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Varighet på fremføringen</span>
            </div>
        </div>

        <div class="input-group-horizontal">
            <input value="1" type="text" class="input" name="minutter">
            <span class="input-info">minutter</span>
            <input value="1" type="text" class="input" name="sekunder">
            <span class="input-info">sekunder</span>
        </div>
    </div>

    </div>
    `
});



// Component
var titlerComponent = Vue.component('titler-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            titler : [],
        }
    },
    async mounted() {
        this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        var titler = await spaInteraction.runAjaxCall('get_all_titler/'+this.innslag_id, 'GET', {});
        this.titler = titler;
    },
    methods : {
    
    },
    template : `
    <div>
    <div v-if="titler != null" class="accordion-item with-shadow with-radius">
    <div class="panel-default">
    <div class="card-header accordion-header-root">
       <button data-toggle="collapse" href="#collapseTitler" aria-expanded="true" class="btn btn-link btn-block btn-accordion-root text-left hover-button-delta" data-form-type="other">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="caret-flip" style="fill: rgb(160, 174, 192);">
             <path d="M9 19L17 12 9 5z"></path>
          </svg>
          <span class="accordion-title-root">#{titler.length + ' fremføring' + (titler.length > 1 ? 'er' : '')}</span>
       </button>
    </div>
    <div v-for="tittel in titler" id="collapseTitler" class="panel-body accordion-body-root collapse show">
       <div class="accordion-header-sub card-body items-oversikt">
          <div>
             <div class="item titel">
                <div class="user-info varighet">
                   <p class="rolle">Varighet</p>
                   <p class="name">
                       <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 30 30" style="fill: #CBD5E0; transform: ;msFilter:;"><path d="M12.25 2c-5.514 0-10 4.486-10 10s4.486 10 10 10 10-4.486 10-10-4.486-10-10-10zM18 13h-6.75V6h2v5H18v2z"></path></svg>
                       <span class="varighet">#{ Math.floor(tittel.sekunder/60) > 0 ? Math.floor(tittel.sekunder/60) + 'm ' : ''} #{ tittel.sekunder % 60 > 0 ? tittel.sekunder % 60 + 's' : ''}</span>
                   </p>
                   </div>
                   <p class="title-name">#{ tittel.tittel }</p>
                <div class="buttons">
                   <button data-toggle="collapse" :href="[ '#editTittel' + tittel.id ]" aria-expanded="true" class="small-button-style hover-button-delta mini edit-user-info collapsed" data-form-type="other">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="2 -1 30 30" style="fill: rgb(255, 255, 255);">
                         <path d="m18.988 2.012 3 3L19.701 7.3l-3-3zM8 16h3l7.287-7.287-3-3L8 13z"></path>
                         <path d="M19 19H8.158c-.026 0-.053.01-.079.01-.033 0-.066-.009-.1-.01H5V5h6.847l2-2H5c-1.103 0-2 .896-2 2v14c0 1.104.897 2 2 2h14a2 2 0 0 0 2-2v-8.668l-2 2V19z"></path>
                      </svg>
                   </button>
                   <button class="small-button-style hover-button-delta mini remove-person-button" data-form-type="other">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="5 1 25 25" style="fill: rgb(255, 255, 255);">
                         <path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path>
                      </svg>
                   </button>
                </div>
                <div class="remove-button-show-hide">
                   <button innslag-id="94100" class="round-style-button mini-size slett-paamelding">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 32 32" style="fill: rgb(255, 255, 255);">
                         <path d="M6 7H5v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H6zm10.618-3L15 2H9L7.382 4H3v2h18V4z"></path>
                      </svg>
                      <span>Bekreft sletting</span>
                   </button>
                </div>
             </div>

             <div :id="[ 'editTittel' + tittel.id ]" class="collapse edit-user-form">
                <div class="item new-person">
                   <div class="user-empty">
                      <div class="buttons">
                         <button data-toggle="collapse" :href="[ '#editTittel' + tittel.id ]" aria-expanded="true" class="small-button-style hover-button-delta mini go-to-meld-av">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="5 1 25 25" style="fill: rgb(255, 255, 255);">
                               <path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path>
                            </svg>
                         </button>
                      </div>
                   </div>
                   <div class="form-new-user">

                        <!-- Title (Title name) -->
                        <div v-if="tittel.tittel" class="input-delta open">
                            <div class="overlay">
                                <div class="info">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                    <span class="text">Navn</span>
                                </div>
                            </div>
                            <input v-model:value="tittel.tittel" type="text" class="input" name="tittel">
                        </div>

                        <!-- Varighet -->
                        <div v-if="tittel.sekunder && tittel.context.innslag.type != 'litteratur'" class="input-delta open">
                            <div class="overlay">
                                <div class="info">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                    <span class="text">Varighet på fremføringen</span>
                                </div>
                            </div>

                            <div class="input-group-horizontal">
                                <input :value="Math.floor(tittel.sekunder/60)" type="text" class="input" name="minutter">
                                <span class="input-info">minutter</span>
                                <input :value="tittel.sekunder % 60" type="text" class="input" name="sekunder">
                                <span class="input-info">sekunder</span>

                            </div>

                        </div>

                        <!-- musikk type -->
                        <musikk-component v-if="tittel.context.innslag.type == 'musikk'"></musikk-component>

                        <!-- dans type -->
                        <dans-component v-if="tittel.context.innslag.type == 'dans'"></dans-component>
                        
                        <!-- litteratur type -->
                        <litteratur-component v-if="tittel.context.innslag.type == 'litteratur'"></litteratur-component>


                   </div>
                </div>
             </div>
          </div>
          <div id="newTittelCollapse" class="new-user-form collapse" style="">
             <div class="item new-tittel">
               <div class="user-empty">
                   <div class="buttons">
                      <button data-toggle="collapse" href="#newTittelCollapse" aria-expanded="false" class="small-button-style hover-button-delta mini go-to-meld-av collapsed">
                         <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="5 1 25 25" style="fill: rgb(255, 255, 255);">
                            <path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path>
                         </svg>
                      </button>
                   </div>
                </div>
               <div class="form-new-user">
                   <p>TYPE HER</p>
               </div>
               <button class="small-button-style new-member hover-button-delta">
                Fullfør og legg til
                </button>
             </div>
          </div>
       </div>
    </div>
    <div class="new-member-div">
        <button data-toggle="collapse" href="#newTittelCollapse" aria-expanded="false" class="small-button-style new-member add-new hover-button-delta collapsed" data-form-type="other">
        Legg til fremføring
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="fill: rgb(113, 128, 150);">
            <path d="M4.5 8.552c0 1.995 1.505 3.5 3.5 3.5s3.5-1.505 3.5-3.5-1.505-3.5-3.5-3.5-3.5 1.505-3.5 3.5zM19 8h-2v3h-3v2h3v3h2v-3h3v-2h-3zM4 19h10v-1c0-2.757-2.243-5-5-5H7c-2.757 0-5 2.243-5 5v1h2z"></path>
        </svg>
        </button>
    </div>
    </div>
    </div>
    </div>
    `
});



// APP
var titler = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    // el: '#titlerAllVue',
    data : {
        titler : []
    },
    async mounted() {
        // this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        // var titler = await spaInteraction.runAjaxCall('get_all_titler/'+this.innslag_id, 'GET', {});
        // this.titler = titler;
    },
    methods : {
    
    },
    components : {
        skjema,
        musikkComponent,
        dansComponent,
        litteraturComponent
    }
})