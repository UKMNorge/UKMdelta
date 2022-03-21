// Component
var allePersoner = Vue.component('innslag-persons', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            personer : [],
            newPerson : this._nullTittel(),
        }
    },
    async mounted() {
        var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');

        var personer = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
        for(var p of personer) {
            p.phantom = false;
            p.isOpen = false;
            p.saving = false;
            p.savingStatus = 0; // 0 saved, 1 saving, -1 error
        }
        this.personer = personer;
    },
    methods : {
        showRemoveButton : function(e) {
            this.closeAllOpenForms();
            deltaStyleShowRemoveButton(e);
        },
        removePerson : async function(person) {
            this.closeAllDeleteButtons();
            person.phantom = true;
            var innslag = this.$parent.innslag;

            var removedPerson = await spaInteraction.runAjaxCall('remove_person/', 'POST', {
                k_id : innslag.kommune_id,
                pl_id : innslag.context.monstring.id, 
                type : innslag.type.key,
                b_id : innslag.id,
                p_id : person.id
            });
            
            if(removedPerson.p_id == person.id) {
                this.personer.splice(this.personer.indexOf(person), 1);
            }
        },
        toggleShadows : (e) => {
			if($(e.currentTarget).hasClass('collapsed')) {
				$($(e.currentTarget).parent().parent().parent()).addClass('with-shadow');
			}
			else {
				$($(e.currentTarget).parent().parent().parent()).removeClass('with-shadow');
			}
		},
        closeAllDeleteButtons() {
            $('.accordion-body-root .items-oversikt .item').removeClass('remove-mode')
        },
        closeAllOpenForms() {
            for(var p of this.personer) {
                p.isOpen = false;
            }
            $('.edit-user-form.user-only, .new-user-form').collapse('hide');
        },
        createNewPerson : async function() {
            // Show phantom (loading)
            var phantomPerson = this._nullTittel('phantom', true);
            this.personer.push(phantomPerson);

            // Close all open forms
            this.closeAllOpenForms();

            // Innslag from parent
            var innslag = this.$parent.innslag;

            console.log(this.newPerson);

            var p = await spaInteraction.runAjaxCall('new_person/', 'POST', {
                k_id : innslag.kommune_id,
                pl_id : innslag.context.monstring.id, 
                type : innslag.type.key,
                b_id : innslag.id,
                fornavn : this.newPerson.fornavn,
                etternavn : this.newPerson.etternavn,
                alder : this.newPerson.alder,
                mobil : this.newPerson.mobil,
                rolle : this.newPerson.rolle // check if rolle exists
            });
            p.id = p.p_id;
            p.saving = false;

            // Remove phantom person
            this.personer.splice(this.personer.indexOf(phantomPerson), 1);
            this.personer.push(p);

            // Empty new person
            this.newPerson = this._nullTittel();
        },
        editPerson : async function(person) {
            var innslag = this.$parent.innslag;

            person.saving = true;
            person.savingStatus = 1;
            
            try{
                var editPerson = await spaInteraction.runAjaxCall('edit_person/', 'PATCH', {
                    b_id : innslag.id,
                    p_id : person.id,
                    fornavn : person.fornavn,
                    etternavn : person.etternavn,
                    alder : person.fodselsdato,
                    mobil : person.mobil,
                    rolle : person.rolle,
                });
            }catch(e) {
                person.savingStatus = -1;
                person.saving = false;
            }

            if(editPerson == true) {
                person.savingStatus = 0;
                person.saving = false;
            }
        },
        _nullTittel : function(id = 'new', phantom = false) {
            return {
                id : id,
                fornavn : null,
                etternavn : null,
                mobil : null,
                alder : null,
                rolle : null,
                phantom : phantom,
                saving : false,
                savingStatus : 0, // 0 saved, 1 saving, -1 error
            };
        },
    },
    template : /*html*/`
    <div>
    <div class="card-header accordion-header-root">
        <button class="btn btn-link btn-block btn-accordion-root text-left hover-button-delta" @click="toggleShadows" data-toggle="collapse" href="#collapseUsers" aria-expanded="true">
            <svg class="caret-flip" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill:#A0AEC0;">
                <path d="M9 19L17 12 9 5z"></path>
            </svg>
            <span class="accordion-title-root">#{ personer.length } #{ personer.length == 1 ? 'medlem' : 'meldemer' }</span>
        </button>
    </div>
    <div id="collapseUsers" class="panel-body accordion-body-root collapse show">
        <div id="allPersons" class="accordion-header-sub card-body items-oversikt">
            <div v-for="person in personer">
                
                <div :class="{ 'open-item' : person.isOpen }"class="item">
                    <div class="avatar">
                        <img :class="{ 'phantom-loading' : person.phantom }" class="avatar" src="https://assets.ukm.dev/img/delta-nytt/avatar-female.png">
                    </div>
                    <div class="user-info">
                        <p :class="{ 'phantom-loading' : person.phantom }" class="rolle">#{ person.rolle ? person.rolle : 'Ukjent rolle' }</p>
                        <p :class="{ 'phantom-loading' : person.phantom }" class="name">#{person.fornavn + ' ' + person.etternavn}</p>
                        <p class="rolle status" :class="{ 'lagring': person.savingStatus == 1, 'feilet': person.savingStatus == -1, 'opacity-hidden' : person.saving == false }">#{person.savingStatus == 0 ? 'lagret!' : (person.savingStatus == 1 ? 'lagring...' : 'lagring feilet!')}</p>
                    </div>
                    <div :class="{ 'hide' : person.isOpen }" class="buttons">
                        <button :class="{ 'phantom-loading' : person.phantom }" @click="closeAllOpenForms(); person.isOpen = true;" class="small-button-style hover-button-delta mini edit-user-info collapsed" data-toggle="collapse" :href="['#editUser' + person.id ]" aria-expanded="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="2 -1 30 30" style="fill: #fff; transform: ;msFilter:;"><path d="m18.988 2.012 3 3L19.701 7.3l-3-3zM8 16h3l7.287-7.287-3-3L8 13z"></path><path d="M19 19H8.158c-.026 0-.053.01-.079.01-.033 0-.066-.009-.1-.01H5V5h6.847l2-2H5c-1.103 0-2 .896-2 2v14c0 1.104.897 2 2 2h14a2 2 0 0 0 2-2v-8.668l-2 2V19z"></path></svg>
                        </button>
                        
                        <button :class="{ 'phantom-loading' : person.phantom }" @click="showRemoveButton" class="small-button-style hover-button-delta mini remove-person-button">
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
                <div :id="['editUser' + person.id ]" class="collapse edit-user-form user-only">
                    <div class="item new-person">
                        <div class="user-not-empty">
                            <div class="buttons">
                                <button @click="closeAllOpenForms(); person.isOpen = false;" class="small-button-style hover-button-delta mini go-to-meld-av" data-toggle="collapse" :href="['#editUser' + person.id ]" aria-expanded="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="3 2 25 25" style="fill: #fff; transform: ;msFilter:;">
                                        <path d="m12 6.879-7.061 7.06 2.122 2.122L12 11.121l4.939 4.94 2.122-2.122z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    
                        <div class="form-new-user">
                            
                            <!-- Fornavn -->
                            <div class="input-delta open" v-bind:class="{ 'validation-failed' : !person.fornavn || !person.fornavn.length }">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                        <span class="text">Fornavn</span>
                                    </div>
                                </div>
                                <input v-model:value="person.fornavn" @blur="editPerson(person)" type="text" class="input" name="fornavn">
                            </div>

                            <!-- Etternavn -->
                            <div class="input-delta open" v-bind:class="{ 'validation-failed' : !person.etternavn || !person.etternavn.length }">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                        <span class="text">Etternavn</span>
                                    </div>
                                </div>
                                <input v-model:value="person.etternavn" @blur="editPerson(person)" type="text" class="input" name="etternavn">
                            </div>

                            <!-- Alder -->
                            <div class="input-delta open" v-bind:class="{ 'validation-failed' : !person.fodselsdato || !person.fodselsdato.length }">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="m21 2-5 5-4-5-4 5-5-5v13h18zM5 21h14a2 2 0 0 0 2-2v-2H3v2a2 2 0 0 0 2 2z"></path></svg>
                                        <span class="text">Alder</span>
                                    </div>
                                </div>
                                <input v-model:value="person.fodselsdato" @blur="editPerson(person)" maxlength="2" type="number" class="input" name="alder">
                            </div>

                            <!-- Mobilnummer -->
                            <div class="input-delta open">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="m20.487 17.14-4.065-3.696a1.001 1.001 0 0 0-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 0 0 .043-1.391L6.859 3.513a1 1 0 0 0-1.391-.087l-2.17 1.861a1 1 0 0 0-.29.649c-.015.25-.301 6.172 4.291 10.766C11.305 20.707 16.323 21 17.705 21c.202 0 .326-.006.359-.008a.992.992 0 0 0 .648-.291l1.86-2.171a.997.997 0 0 0-.085-1.39z"></path></svg>	
                                        <span class="text">Mobilnummer</span>
                                    </div>
                                </div>
                                <input v-model:value="person.mobil" @blur="editPerson(person)" type="tel" maxlength="8" class="input" name="mobil">
                            </div>

                            <!-- Rolle -->
                            <div class="input-delta open" v-bind:class="{ 'validation-failed' : !person.rolle || !person.rolle.length }">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="M20 6h-3V4c0-1.103-.897-2-2-2H9c-1.103 0-2 .897-2 2v2H4c-1.103 0-2 .897-2 2v4h5v-2h2v2h6v-2h2v2h5V8c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 11h-2v-2H9v2H7v-2H2v6c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2v-6h-5v2z"></path></svg>
                                        <span class="text">Rolle i gruppa</span>
                                    </div>
                                </div>
                                <input v-model:value="person.rolle" @blur="editPerson(person)" type="text" class="input" name="rolle">
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- NEW PERSON -->

            <div id="newUserCollapse" class="collapse edit-user-form new-user-form">
                    <div class="item new-person">
                        <div class="user-empty">
                        <div class="avatar">
                            <img class="avatar" src="https://assets.ukm.dev/img/delta-nytt/avatar-female.png">
                        </div>
                        <div class="user-info">
                            <p class="name">Legger til nytt medlem</p>
                        </div>
                        <div class="buttons">
                            <button class="small-button-style hover-button-delta mini go-to-meld-av" data-toggle="collapse" href="#newUserCollapse" aria-expanded="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="5 1 25 25" style="fill: #fff; transform: ;msFilter:;"><path d="m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z"></path></svg>
                            </button>
                        </div>
                    </div>
                    
                        <div class="form-new-user">
                            
                            <!-- Fornavn -->
                            <div class="input-delta">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                        <span class="text">Fornavn</span>
                                    </div>
                                </div>
                                <input v-model="newPerson.fornavn" id="fornavnNewPerson" type="text" class="input input-new-person" name="fornavn">
                            </div>

                            <!-- Etternavn -->
                            <div class="input-delta">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0;transform: ;msFilter:;"><path d="M7.5 6.5C7.5 8.981 9.519 11 12 11s4.5-2.019 4.5-4.5S14.481 2 12 2 7.5 4.019 7.5 6.5zM20 21h1v-1c0-3.859-3.141-7-7-7h-4c-3.86 0-7 3.141-7 7v1h17z"></path></svg>
                                        <span class="text">Etternavn</span>
                                    </div>
                                </div>
                                <input v-model="newPerson.etternavn" id="etternavnNewPerson" type="text" class="input input-new-person" name="etternavn">
                            </div>

                            <!-- Alder -->
                            <div class="input-delta">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="m21 2-5 5-4-5-4 5-5-5v13h18zM5 21h14a2 2 0 0 0 2-2v-2H3v2a2 2 0 0 0 2 2z"></path></svg>
                                        <span class="text">Alder</span>
                                    </div>
                                </div>
                                <input v-model="newPerson.alder" id="alderNewPerson" type="text" maxlength="2" class="input input-new-person" name="alder">
                            </div>

                            <!-- Mobilnummer -->
                            <div class="input-delta">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="m20.487 17.14-4.065-3.696a1.001 1.001 0 0 0-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 0 0 .043-1.391L6.859 3.513a1 1 0 0 0-1.391-.087l-2.17 1.861a1 1 0 0 0-.29.649c-.015.25-.301 6.172 4.291 10.766C11.305 20.707 16.323 21 17.705 21c.202 0 .326-.006.359-.008a.992.992 0 0 0 .648-.291l1.86-2.171a.997.997 0 0 0-.085-1.39z"></path></svg>	
                                        <span class="text">Mobilnummer</span>
                                    </div>
                                </div>
                                <input v-model="newPerson.mobil" id="mobilNewPerson" type="text" maxlength="8" class="input input-new-person" name="mobil">
                            </div>

                            <!-- Rolle -->
                            <div class="input-delta">
                                <div class="overlay">
                                    <div class="info">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="fill: #A0AEC0; transform: ;msFilter:;"><path d="M20 6h-3V4c0-1.103-.897-2-2-2H9c-1.103 0-2 .897-2 2v2H4c-1.103 0-2 .897-2 2v4h5v-2h2v2h6v-2h2v2h5V8c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 11h-2v-2H9v2H7v-2H2v6c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2v-6h-5v2z"></path></svg>
                                        <span class="text">Rolle i gruppa</span>
                                    </div>
                                </div>
                                <input v-model="newPerson.rolle" id="rolleNewPerson" type="text" class="input input-new-person" name="rolle">
                            </div>

                        </div>
                    </div>

                    <div class="new-member-div">
                        <button @click="createNewPerson" class="small-button-style new-member hover-button-delta">
                            Fullfør og legg til
                        </button>
                    </div>
                </div>


            </div>
        </div>
        
        <div class="new-member-div">
            <button @click="closeAllOpenForms();" onclick="$('.edit-user-form').collapse('hide');" class="small-button-style new-member add-new hover-button-delta collapsed" data-toggle="collapse" href="#newUserCollapse" aria-expanded="false">
                Legg til person
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="fill: #718096; transform: ;msFilter:;"><path d="M4.5 8.552c0 1.995 1.505 3.5 3.5 3.5s3.5-1.505 3.5-3.5-1.505-3.5-3.5-3.5-3.5 1.505-3.5 3.5zM19 8h-2v3h-3v2h3v3h2v-3h3v-2h-3zM4 19h10v-1c0-2.757-2.243-5-5-5H7c-2.757 0-5 2.243-5 5v1h2z"></path></svg>
            </button>
        </div>
    </div>
    </div>
    `
})




// // Component
// var tekniskeBehov = Vue.component('innslag-tekniske-behov', { 
//     delimiters: ['#{', '}'], // For å bruke det på Twig
//     data : function() {
//         return {
//             titler : [],
//         }
//     },
//     async mounted() {
//         var innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
//         // var titler = await spaInteraction.runAjaxCall('get_all_persons/' + innslag_id, 'GET', {});
//         // this.titler = titler;
//     },
//     methods : {
    
//     },
//     template : `
//     <div>
//         <h1>Tekniske Behov</h1>
//         <input value="t b" type="text">
//     </div>
//     `
// });


// The app
var oversiktInnslag = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageOversiktInnslag',
    data: {
        innslag : {}
    },
    created() {
        console.log('created')
    },
    updated() {
        inputDeltaFix();
    },
    async mounted() {
        this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        var innslag = await spaInteraction.runAjaxCall('get_innslag/'+this.innslag_id, 'GET', {});
        this.innslag = innslag;
    },
    methods : {
        editInnslag : async function() {
            console.log(this.innslag);
            var newPerson = await spaInteraction.runAjaxCall('edit_innslag/', 'PATCH', {
                b_id : this.innslag.id,
                navn : this.innslag.navn,
                beskrivelse : this.innslag.beskrivelse,
                sjanger : this.innslag.sjanger ? this.innslag.sjanger : null,
                tekniske_behov : typeof this.innslag.tekniske_behov !== 'undefined' ? this.innslag.tekniske_behov : null,
            });
        },
        saveAndFinish : async function() {
            var innslag = this.innslag;

            var saveFinishInnslag = await spaInteraction.runAjaxCall('save_innslag/', 'POST', {
                k_id : this.innslag.kommune_id,
                pl_id : this.innslag.context.monstring.id, 
                type : this.innslag.type.key,
                b_id : this.innslag.id,
                navn : this.innslag.navn,
                beskrivelse : this.innslag.beskrivelse,
                sjanger : this.innslag.sjanger ? this.innslag.sjanger : null,
            });

            var_dump(saveFinishInnslag);
        },
    },
    components : {
        allePersoner
    }
})