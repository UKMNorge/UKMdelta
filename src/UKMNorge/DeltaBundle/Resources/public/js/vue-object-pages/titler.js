// MAIN - Component
var mainComponent = Vue.component('titler-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            innslag : null,
            titler : [],
            newTittel : this._nullTittel()
        }
    },
    async mounted() {
        this.getData();
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        getData : async function() {
            this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');

            var innslag = await spaInteraction.runAjaxCall('get_innslag/'+this.innslag_id, 'GET', {});
            var titler = await spaInteraction.runAjaxCall('get_all_titler/'+this.innslag_id, 'GET', {});
            
            this.innslag = innslag;
            
            for(var t of titler) {
                t.phantom = false;
                t.isOpen = false;
                t.saving = false;
                t.savingStatus = 0; // 0 saved, 1 saving, -1 error
            }
            this.titler = titler;
        },
        showRemoveButton : function(e) {
            deltaStyleShowRemoveButton(e);
            this.closeAllOpenForms();
        },
        closeAllOpenForms() {
            $('.edit-tittel-form, .new-user-form').collapse('hide');
            for(var t of this.titler) {
                t.isOpen = false;
            }
        },
        closeAllDeleteButtons() {
            $('.accordion-body-root .items-oversikt .item').removeClass('remove-mode')
        },
        // Is attribute defined in DOM
        _isTitleAttributeDefined(attribute) {
            var ret = $('#newTittleForm').find('input[name="' + attribute + '"]').length > 0;
            return ret;
        },
        verifyNewTittel() {
            var newTittel = this.newTittel;
            console.log('aaa');

            if(this._isTitleAttributeDefined('melodi_av') && (!newTittel.melodi_av || newTittel.melodi_av.length < 1)
            || this._isTitleAttributeDefined('tittel') && (!newTittel.tittel || newTittel.tittel.length < 1)
            || this._isTitleAttributeDefined('tekst_av') && (!newTittel.tekst_av || newTittel.tekst_av.length < 1)
            || this._isTitleAttributeDefined('koreografi_av') && (!newTittel.koreografi_av || newTittel.koreografi_av.length < 1)
            || this._isTitleAttributeDefined('type') && (!newTittel.type || (newTittel.koreografi_av && newTittel.koreografi_av.type < 1))
            ){
                $('#newTittleForm').find('.validation-failed').addClass('validation-failed-active').removeClass('validation-failed');
                return false;
            }

            return true;
        },
        addNewTittel : async function() {
            if(this.verifyNewTittel() == false) {
                return;
            }

            $('.edit-tittel-form').collapse('hide');
            var phantomTittel = this._nullTittel('phantom', true);
            phantomTittel.savingStatus = 1;

            this.titler.push(phantomTittel);

            var newT = await this.saveChanges(this.newTittel);
            newT[0].phantom = false;

            
            // remove phantom Tittel and add new Tittel
            this.titler.splice(this.titler.indexOf(phantomTittel), 1);
            this.titler.push(newT[0]);
            
            newT[0].saving = false;
            newT[0].savingStatus = 0;

            // Empty newTittel
            this.newTittel = this._nullTittel();

            // Empty varighet
            $('#newtittelMin, #newtittelSec').val('');
        },
        saveChanges : async function(tittel) {
            console.log('ppppp');
            tittel.saving = true;
            tittel.savingStatus = 1;

            var data = {
                b_id : this.innslag_id,
                t_id : tittel.id, // 'new' for ny tittel
                tittel : tittel.tittel,
            };

            if(typeof tittel.selvlaget !== 'undefined') {
                tittel.selvlaget = (tittel.selvlaget === 'true' || tittel.selvlaget === true);
                data.selvlaget = tittel.selvlaget ? 1 : 0;
            }

            if(typeof tittel.sekunder !== 'undefined') {
                var min = parseInt($('#' + tittel.id + 'tittelMin').val());
                var sec = parseInt($('#' + tittel.id + 'tittelSec').val());
                if(!isNaN(min) && !isNaN(sec)) {
                    tittel.sekunder = (min * 60) + sec;
                }

                data.lengde = String(tittel.sekunder);
            }

            if(typeof tittel.melodi_av !== 'undefined') {
                data.melodiforfatter = tittel.melodi_av;
            }

            
            if(typeof tittel.instrumental !== 'undefined') {
                tittel.instrumental = (tittel.instrumental === 'true' || tittel.instrumental === true);
                data.sangtype = tittel.instrumental || tittel.instrumental == 'true' ? 'instrumental' : 'tekst';
            }

            if(typeof tittel.tekst_av !== 'undefined') {
                data.tekstforfatter = tittel.tekst_av;
            }

            if(typeof tittel.koreografi_av !== 'undefined') {
                data.koreografi = tittel.koreografi_av;
            }

            if(typeof tittel.litteratur_read !== 'undefined') {
                tittel.litteratur_read = (tittel.litteratur_read === 'true' || tittel.litteratur_read === true);
                data.leseopp = tittel.litteratur_read ? 1 : 0;
            }

            if(typeof tittel.type !== 'undefined') {
                data.type = tittel.type;
            }

            try{
                var editTittel = await spaInteraction.runAjaxCall(
                    'create_or_edit_tittel/', 
                    'POST', 
                    data
                );
                if(editTittel != null) {
                    tittel.savingStatus = 0;
                    tittel.saving = false;
                }
                return editTittel;
            } catch(e) {
                console.error('here');
                tittel.savingStatus = -1;
                tittel.saving = false;
                console.log(e);
            }
        },
        deleteTittel : async function(tittel) {
            this.closeAllDeleteButtons();
            tittel.phantom = true;
            
            // Bruker timeout fordi da blir vanskelig på brukergrensesnitt å forstå hvilke brukere ble slettet
            setTimeout(async () => {
                var res = await spaInteraction.runAjaxCall(
                    'delete_tittel/', 
                    'DELETE', 
                    {
                        b_id : this.innslag.id,
                        t_id : tittel.id,
                    }
                );
                
                if(res == true) {
                    this.titler.splice(this.titler.indexOf(tittel), 1);
                }
            }, 1000);


        },
        _nullTittel : function(id = 'new', phantom = false) {
            return {
                id : id,
                instrumental : false,
                melodi_av : null,
                sekunder : 1,
                selvlaget : true,
                tekst_av : null,
                tittel : '',
                koreografi_av : null,
                litteratur_read : false,
                type : null,
                phantom : phantom,
                context : {
                    innslag : {
                        id : -1
                    }
                }
            };
        }
    },
    template : /*html*/`
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
             <div :class="{ 'open-item' : tittel.isOpen }" class="item titel">
                
                <div class="user-info varighet">
                    <div v-if="!innslag.erKunstgalleri">
                        <p class="rolle">Varighet</p>
                        <p class="name">
                           <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 30 30" style="fill: #CBD5E0; transform: ;msFilter:;"><path d="M12.25 2c-5.514 0-10 4.486-10 10s4.486 10 10 10 10-4.486 10-10-4.486-10-10-10zM18 13h-6.75V6h2v5H18v2z"></path></svg>
                            <span v-if="tittel.sekunder" :class="{ 'phantom-loading' : tittel.phantom }" class="varighet">#{ Math.floor(tittel.sekunder/60) > 0 ? Math.floor(tittel.sekunder/60) + 'm ' : ''} #{ tittel.sekunder % 60 > 0 ? tittel.sekunder % 60 + 's' : ''}</span>
                            <!-- No varighet -->
                            <span v-else class="varighet inactive">- -</span>
                        </p>
                        <p class="rolle status" :class="{ 'lagring': tittel.savingStatus == 1, 'feilet': tittel.savingStatus == -1, 'opacity-hidden' : tittel.saving == false }">#{tittel.savingStatus == 0 ? 'lagret!' : (tittel.savingStatus == 1 ? 'lagring...' : 'lagring feilet!')}</p>
                    </div>
                    <!-- Kunstgalleri -->
                    <div v-else>
                        <p class="rolle">Kunstverk</p>

                        <p class="name">
                            <svg v-if="tittel.bilde != null" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 23 23" style="fill: #CBD5E0; transform: ;msFilter:;"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm-7.933 13.481-3.774-3.774 1.414-1.414 2.226 2.226 4.299-5.159 1.537 1.28-5.702 6.841z"></path></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 23 23"  style="fill: #CBD5E0; transform: ;msFilter:;"><path d="m10.933 13.519-2.226-2.226-1.414 1.414 3.774 3.774 5.702-6.84-1.538-1.282z"></path><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zM5 19V5h14l.002 14H5z"></path></svg>
                            
                            <span :class="[ tittel.bilde ? 'godkjent' : (tittel.playback ? 'registrert' : 'ingen') ]" class="varighet kunstgalleri">#{tittel.bilde ? 'godkjent' : (tittel.playback ? 'registrert' : 'ingen')}</span>
                            <!-- No varighet -->
                        </p>
                        <p class="rolle status" :class="{ 'lagring': tittel.savingStatus == 1, 'feilet': tittel.savingStatus == -1, 'opacity-hidden' : tittel.saving == false }">#{tittel.savingStatus == 0 ? 'lagret!' : (tittel.savingStatus == 1 ? 'lagring...' : 'lagring feilet!')}</p>
                    </div>
                </div>
                
                <p class="title-name" :class="{ 'phantom-loading' : tittel.phantom }">#{ tittel.tittel }</p>
                <div :class="{ 'hide' : tittel.isOpen }" class="buttons">
                   <button @click="closeAllOpenForms(); tittel.isOpen = true;" :class="{ 'phantom-loading' : tittel.phantom }" data-toggle="collapse" :href="[ '#edittittel' + tittel.id ]" onclick="$('.edit-tittel-form').collapse('hide');" aria-expanded="true" class="small-button-style hover-button-delta mini edit-user-info collapsed" data-form-type="other">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="-3 -1 30 30" style="fill: rgb(255, 255, 255);">
                         <path d="m18.988 2.012 3 3L19.701 7.3l-3-3zM8 16h3l7.287-7.287-3-3L8 13z"></path>
                         <path d="M19 19H8.158c-.026 0-.053.01-.079.01-.033 0-.066-.009-.1-.01H5V5h6.847l2-2H5c-1.103 0-2 .896-2 2v14c0 1.104.897 2 2 2h14a2 2 0 0 0 2-2v-8.668l-2 2V19z"></path>
                      </svg>
                   </button>
                   <button :class="{ 'phantom-loading' : tittel.phantom }" @click="showRemoveButton" class="small-button-style hover-button-delta mini remove-person-button" data-form-type="other">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 25 25" style="fill: rgb(255, 255, 255);">
                         <path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path>
                      </svg>
                   </button>
                </div>
                <div class="remove-button-show-hide">
                   <button :class="{ 'phantom-loading' : tittel.phantom }" @click="deleteTittel(tittel)" class="round-style-button mini-size slett-paamelding">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 32 32" style="fill: rgb(255, 255, 255);">
                         <path d="M6 7H5v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H6zm10.618-3L15 2H9L7.382 4H3v2h18V4z"></path>
                      </svg>
                      <span>Bekreft sletting</span>
                   </button>
                </div>
             </div>

             <div :id="[ 'edittittel' + tittel.id ]" class="collapse edit-user-form edit-tittel-form">
                <div class="item new-person">
                   <div class="user-not-empty">
                      <div class="buttons">
                         <button @click="tittel.isOpen = false;" data-toggle="collapse" :href="[ '#edittittel' + tittel.id ]" aria-expanded="true" class="small-button-style hover-button-delta mini go-to-meld-av">
                         <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 2 25 25" style="fill: rgb(255, 255, 255);"><path d="m12 6.879-7.061 7.06 2.122 2.122L12 11.121l4.939 4.94 2.122-2.122z"></path></svg>
                         </button>
                      </div>
                   </div>
                   <div class="form-new-user">

                        <!-- Title (Title name) -->
                        <div class="input-delta" :class="{ open: tittel.tittel, 'validation-failed' : !tittel.tittel || !tittel.tittel.length }" >
                            <div class="overlay">
                                <div class="info">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                    <span class="text">Navn</span>
                                </div>
                            </div>
                            <input @blur="saveChanges(tittel)" v-model:value="tittel.tittel" type="text" class="input" name="tittel">
                        </div>

                        <!-- Varighet -->
                        <div v-if="(tittel.sekunder || tittel.sekunder == 0) && tittel.context.innslag.type != 'litteratur' && tittel.context.innslag.type != 'utstilling' && (innslag.erKunstgalleri == false)" class="input-delta open varighet" mangler="tittel.varighet">
                            <div class="overlay">
                                <div class="info">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                    <span class="text">Varighet på fremføringen</span>
                                </div>
                            </div>

                            <div class="input-group-horizontal varighet-limit">
                                <input @blur="saveChanges(tittel)" :id="[ tittel.id + 'tittelMin' ]" :value="Math.floor(tittel.sekunder/60)" type="number" min="0" class="input" name="minutter">
                                <span class="input-info">minutter</span>
                                <input @blur="saveChanges(tittel)" :id="[ tittel.id + 'tittelSec' ]" :value="tittel.sekunder % 60" type="number" max="59" min="0" class="input" name="sekunder">
                                <span class="input-info">sekunder</span>
                            </div>

                        </div>

                        <!-- musikk type -->
                        <musikk-component v-if="tittel.context.innslag.type == 'musikk'" :tittel="tittel" ></musikk-component>

                        <!-- dans type -->
                        <dans-component v-if="tittel.context.innslag.type == 'dans'" :tittel="tittel"></dans-component>
                        
                        <!-- litteratur type -->
                        <litteratur-component v-if="tittel.context.innslag.type == 'litteratur'" :tittel="tittel"></litteratur-component>
                        
                        <!-- teater type -->
                        <teater-component v-if="tittel.context.innslag.type == 'teater'" :tittel="tittel"></teater-component>
                        
                        <!-- utstilling type -->
                        <utstilling-component v-if="tittel.context.innslag.type == 'utstilling' && innslag.erKunstgalleri == false" :tittel="tittel" ></utstilling-component>

                        <!-- utstilling type - virtueltkunstgalleri -->
                        <virtueltkunstgalleri-component v-if="tittel.context.innslag.type == 'utstilling' && innslag.erKunstgalleri == true" :tittel="tittel" ></virtueltkunstgalleri-component>
                        
                        
                   </div>
                </div>
             </div>
          </div>
          
          
    </div>
    </div>


    <!-- NY FREMFØRING -->
    <div v-if="innslag" class="panel-body accordion-body-root">
    <div class="accordion-header-sub card-body items-oversikt">

             
    <div id="newTittleForm" class="edit-user-form edit-tittel-form collapse">
        <div class="item new-person">
        <div class="user-empty">
            <div class="user-info">
                <p class="name">Legger til #{ innslag.type.key == 'utstilling' && innslag.erKunstgalleri == true ? 'nytt kunstgalleri' : 'ny fremføring' }</p>
            </div>
            <div class="buttons">
                <button data-toggle="collapse" href="#newTittleForm" aria-expanded="true" class="small-button-style hover-button-delta mini go-to-meld-av">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 2 25 25" style="fill: rgb(255, 255, 255);"><path d="m12 6.879-7.061 7.06 2.122 2.122L12 11.121l4.939 4.94 2.122-2.122z"></path></svg>
                </button>
            </div>
        </div>
        <div class="form-new-user">
                <!-- tittel navn ny Tittel -->

                <div class="input-delta" :class="{ open: newTittel.tittel, 'validation-failed' : !newTittel.tittel || newTittel.tittel.length < 1}">
                    <div class="overlay">
                        <div class="info">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                            <span class="text">Navn</span>
                        </div>
                    </div>
                    <input v-model="newTittel.tittel" type="text" class="input" name="tittel">
                </div>

                <!-- varighet ny Tittel -->
                <div 
                   v-if="
                    innslag.type.key != 'litteratur' && 
                    innslag.type.key != 'matkultur' &&
                    innslag.type.key != 'utstilling'" 
                   class="input-delta open">
                    <div class="overlay">
                        <div class="info">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                            <span class="text">Varighet på fremføringen</span>
                        </div>
                    </div>

                    <div class="input-group-horizontal varighet-limit">
                        <input :id="[ newTittel.id + 'tittelMin' ]" value="0" type="number" min="0" class="input" name="minutter">
                        <span class="input-info">minutter</span>
                        <input :id="[ newTittel.id + 'tittelSec' ]" value="1" type="number" max="59" min="0" class="input" name="sekunder">
                        <span class="input-info">sekunder</span>
                    </div>
                </div>

                <!-- ALLE TYPER -->

                <!-- musikk type -->
                <musikk-component v-if="innslag.type.key == 'musikk'" :tittel="newTittel"></musikk-component>

                <!-- dans type -->
                <dans-component v-if="innslag.type.key == 'dans'" :tittel="newTittel"></dans-component>
                
                <!-- litteratur type -->
                <litteratur-component v-if="innslag.type.key == 'litteratur'" :tittel="newTittel"></litteratur-component>
                
                <!-- teater type -->
                <teater-component v-if="innslag.type.key == 'teater'" :tittel="newTittel"></teater-component>

                <!-- utstilling type -->
                <utstilling-component v-if="innslag.type.key == 'utstilling' && innslag.erKunstgalleri == false" :tittel="newTittel" ></utstilling-component>

                <!-- utstilling type - virtueltkunstgalleri -->
                <virtueltkunstgalleri-component v-if="innslag.type.key == 'utstilling' && innslag.erKunstgalleri == true" :tittel="newTittel" ></virtueltkunstgalleri-component>

        </div>
    </div>

       
        <div class="new-member-div">
            <button @click="addNewTittel" class="small-button-style new-member hover-button-delta">
                Fullfør og legg til
            </button>
        </div>
    </div>
  </div>
</div>


    <div class="new-member-div">
        <button @click="closeAllOpenForms();" data-toggle="collapse" onclick="$('.edit-tittel-form').collapse('hide');" href="#newTittleForm" aria-expanded="false" class="small-button-style new-member add-new hover-button-delta collapsed" data-form-type="other">
            Legg til fremføring
            <svg xmlns="http://www.w3.org/2000/svg" wwidth="18" height="18" viewBox="0 0 24 24" style="fill: rgb(113, 128, 150); transform: ;msFilter:;">
                <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"></path>
            </svg>
        </button>
    </div>
    </div>
    </div>
    </div>
    `
});


// Component
var musikkComponent = Vue.component('musikk-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på 
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
        
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        }
    },
    template : /*html*/`
    <div>

    <!-- TEKST ELLER INSTRUMENTAL -->
    <div class="radio-input-delta">
        <p class="description">Har låten tekst, eller er det en instrumental?</p>
        <div class="inputs">
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="false"
                v-model="tittel.instrumental"
                checked>

                <span>Tekst</span>
            </div>
            <div class="input-div">
                 <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="true"
                v-model="tittel.instrumental"
                :checked="!tittel.instrumental">
                <span>Instrumental</span>
            </div>
        </div>
    </div>


    <div class="radio-input-delta">
        <p class="description">Har du/dere laget låten selv?</p>
        <div class="inputs">
            <div class="input-div">                
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="true"
                v-model="tittel.selvlaget"
                checked>
                
                <span>Ja</span>
            </div>
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="false"
                v-model="tittel.selvlaget"
                :checked="!tittel.selvlaget">
                <span>Nei</span>
            </div>
        </div>
    </div>

    <!-- TESKTEN SKREVET AV -->
    <div v-if="tittel.instrumental == false || tittel.instrumental == 'false'" class="input-delta" :class="{ open: tittel.tekst_av, 'validation-failed' : !tittel.tekst_av || !tittel.tekst_av.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har skrevet teksten?</span>
            </div>
        </div>
        
        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.tekst_av" class="input" name="tekst_av">
    </div> 

    <!-- MELODI LAGET AV -->
    <div class="input-delta" :class="{ open: tittel.melodi_av, 'validation-failed' : !tittel.melodi_av || !tittel.melodi_av.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har laget melodien?</span>
            </div>
        </div>

        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.melodi_av" class="input" name="melodi_av">
    </div> 

    </div>
    </div>
    `
});

// Component
var dansComponent = Vue.component('dans-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
        
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        }
    },
    template : /*html*/`
    <div>

    <div class="radio-input-delta">
        <p class="description">Har du/dere laget koreografien / dansen selv?</p>
        <div class="inputs">
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="true"
                v-model="tittel.selvlaget"
                checked>
                <span>Ja</span>
            </div>
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="false"
                v-model="tittel.selvlaget"
                :checked="!tittel.selvlaget">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- Hvem har koreografert dansen? -->
    <div  class="input-delta" :class="{ open: tittel.koreografi_av, 'validation-failed' : !tittel.koreografi_av || !tittel.koreografi_av.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har koreografert dansen?</span>
            </div>
        </div>
        <input @blur="saveChangesLocal(tittel)" type="text" class="input" v-model="tittel.koreografi_av" name="koreografi_av">
    </div> 

    </div>
    `
});


// Component
var litteraturComponent = Vue.component('litteratur-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
       
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        }
    },
    template : /*html*/`
    <div>

    <!-- Medforfater -->
    <div class="input-delta" :class="{ open: tittel.tekst_av, 'validation-failed' : !tittel.tekst_av || !tittel.tekst_av.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Medforfatter</span>
            </div>
        </div>
        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.tekst_av" class="input" name="tekst_av">
    </div>

    <div class="radio-input-delta">
        <p class="description">Ønsker du å lese opp denne?</p>
        <div class="inputs">
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="true"
                v-model="tittel.litteratur_read"
                checked>
                <span>Ja</span>
            </div>
            <div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="false"
                v-model="tittel.litteratur_read"
                :checked="!tittel.litteratur_read">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- Tid for å lese opp -->
    <div v-if="tittel.litteratur_read == 'true' || tittel.litteratur_read == true" class="input-delta open">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Varighet på fremføringen</span>
            </div>
        </div>

        <div class="input-group-horizontal varighet-limit">
            <input @blur="saveChangesLocal(tittel)" :id="[ tittel.id + 'tittelMin' ]" :value="Math.floor(tittel.sekunder/60)" type="number" min="0" class="input" name="minutter">
            <span class="input-info">minutter</span>
            <input @blur="saveChangesLocal(tittel)" :id="[ tittel.id + 'tittelSec' ]" :value="tittel.sekunder % 60" type="number" max="59" min="0" class="input" name="sekunder">
            <span class="input-info">sekunder</span>
        </div>
    </div>

    </div>
    `
});


// Component
var dansComponent = Vue.component('teater-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
       
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        }
    },
    template : /*html*/`
    <div>

    <div class="radio-input-delta">
        <p class="description">Har du/dere laget sketsjen/stykket selv?</p>
        <div class="inputs">
			<div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="true"
                v-model="tittel.selvlaget"
                checked>
                <span>Ja</span>
            </div>
			<div class="input-div">
                <input type="radio"
                @change="saveChangesLocal(tittel)"
                value="false"
                v-model="tittel.selvlaget"
                :checked="!tittel.selvlaget">
                <span>Nei</span>
            </div>
		</div>
	</div>

    <!-- Hvem har skrevet manus? -->
    <div class="input-delta" :class="{ open: tittel.tekst_av, 'validation-failed' : !tittel.tekst_av || !tittel.tekst_av.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Hvem har skrevet manus?</span>
            </div>
        </div>
        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.tekst_av" class="input" name="tekst_av">
    </div>

    </div>
    `
});


// Component
var dansComponent = Vue.component('utstilling-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
       
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        }
    },
    template : /*html*/`
    <div>

    <!-- Type og teknikk -->
    <div class="input-delta" :class="{ open: tittel.type, 'validation-failed' : !tittel.type || !tittel.type.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Type og teknikk</span>
            </div>
        </div>
        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.type" class="input" name="type">
    </div> 

    </div>
    `
});

// Component
var virtueltkunstgalleriComponent = Vue.component('virtueltkunstgalleri-component', { 
    mixins : [mainComponent], // Parent
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        tittel : {}
    },
    data : function() {
        return {
            tittelObj: this.tittel
        }
    },
    async mounted() {
    
    },
    updated() {
        inputDeltaFix();
    },
    methods : {
        saveChangesLocal : async function(tittel) {
            if(tittel.id != 'new' ) {
                var nyTittel = await this.saveChanges(tittel);
            }
        },
        openKunstOpplasting : function(tittel) {
            refreshOnBack(() => {
                location.reload();
            });
            window.location.href = '/ukmid/filer/' + tittel.context.innslag.id;
        }
    },
    template : /*html*/`
    <div>

    <!-- Type og teknikk -->
    <div class="input-delta" :class="{ open: tittel.type, 'validation-failed' : !tittel.type || !tittel.type.length }">
        <div class="overlay">
            <div class="info">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                <span class="text">Teknikk</span>
            </div>
        </div>
        <input @blur="saveChangesLocal(tittel)" type="text" v-model="tittel.type" class="input" name="teknikk">
    </div>
    
    <div v-if="tittel.id != 'new'" class="kunstverk-last-opp">
        <div class="input-delta validation-failed open">
            <div class="overlay">
                <div class="info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="icon" style="fill: rgb(160, 174, 192);">
                    <path d="M19.999 4h-16c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V6c0-1.103-.897-2-2-2zm-13.5 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm5.5 10h-7l4-5 1.5 2 3-4 5.5 7h-7z"></path>
                    </svg>
                    <span class="text">Kunstverk</span>
                </div>
            </div> 
           
            <div v-if="tittel.playback != null">
                <img @click="openKunstOpplasting(tittel)" class="kunstverk clickable" :src="[tittel.playback.base_url + tittel.playback.file_path + tittel.playback.fil]"/>
            </div>
            <div v-if="tittel.playback == null" class="button-kunstverk">
                <button @click="openKunstOpplasting(tittel)" class="round-style-button medium hover-button-delta">Last opp kunstverk</button>
            </div>
        </div>
        <div id="innslagNavnInfo" class="beskrivelse-under-input-delta collapse show" style="">
            <div class="space">
                <div class="info-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: #718096;">
                        <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path>
                    </svg>
                </div> 
                <span>
                   #{ tittel.bilde ? 'Kunstverk er godkjent!' : 'Kunstverk ble sendt og venter godkjenning' }
                </span>
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
    updated() {
        inputDeltaFix();
    },
    methods : {
    
    },
    components : {
        musikkComponent,
        dansComponent,
        litteraturComponent,
        virtueltkunstgalleriComponent
    }
})