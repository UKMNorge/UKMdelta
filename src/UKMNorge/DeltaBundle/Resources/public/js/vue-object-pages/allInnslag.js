// // Components
// var innslagComponent = Vue.component('innslag-component', { 
//     delimiters: ['#{', '}'], // For å bruke det på Twig
//     props: {
//         alleInnslag : []
//     },
//     data : function() {
//         return {
//             alle_innslag : this.alleInnslag
//         }
//     },
//     async mounted() {
//         // this.updateData();
//         var parent = this.$parent.alle_innslag;
//         console.log('aaa');
//     },
//     methods : {
        
//     },
//     template : /*html*/`
    
//     `
// })

// The app
var allInnslag = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageAllInnslag',
    data: {
        alle_innslag : [],
        venteliste_innslag : [],
        loaded : false
    },
    async mounted() {
        this.updateData();
    },
    updated() {
        $('button.tooltip-btn').tooltip();
    },
    methods : {
        update : function() {
            // allInnslag.$children[0].updateData();
        },
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
            var ventelisteArrangementer = await spaInteraction.runAjaxCall('venteliste_arrangementer/', 'GET', {});
    
            if(innslags) {
                this.alle_innslag = [
                    ['Fullførte påmeldinger', innslags.fullforte], 
                    ['Ufullstendige påmeldinger', innslags.ikke_fullforte]
                ]

                if(ventelisteArrangementer.length > 0) {
                    for(var v_arrangement of ventelisteArrangementer) {
                        var innslagObj = {innslag: v_arrangement}
                        innslagObj.innslag.isVenteliste = true;
                        innslagObj.personer = [];

                        this.alle_innslag[1][1].push(innslagObj);
                    }
                }

                for(var innslag of this._alleInnslags()) {
                    innslag.innslag.isVenteliste = innslag.innslag.isVenteliste == true ? true : false;
                    innslag.innslag.deleted = false;
                    innslag.innslag.showDelete = false;
                }
                this.loaded = true;
            }

        },
        _alleInnslags : function() {
            return this.alle_innslag[0][1].concat(this.alle_innslag[1][1]);
        },
        // Is innslag(s) empty
        isEmpty : function() {
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

            try{
                if(innslag.isVenteliste == true) {
                    var res = await spaInteraction.runAjaxCall('venteliste_remove/', 'POST', {pl_id : innslag.id})
                }
                else {
                    var res = await spaInteraction.runAjaxCall('remove_innslag/', 'POST', {pl_id : innslag.context.monstring.id, b_id : innslag.id})
                }
                
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
        continueLastInnslag : function() {
            if(!this.isEmpty()) {
                if(this.alle_innslag[1][1].length > 0) {
                    this.redirectToInnslagOverview(this.alle_innslag[1][1][0].innslag);
                    // Check if arrangement frist is available
                }
            }
        },
        getCurrentDomain : function() {
            return getCurrentDomain();
        },
        redirectToInnslagOverview : function(innslag) {
            refreshOnBack(() => {
                allInnslag.updateData();
            });
            var link = '/ukmid/pamelding/' + innslag.kommune_id + '-' + innslag.home.id + '/' + innslag.type.key + '/' + innslag.id + '/';
            window.location.href = link;
        },
        startNyPaamelding : async function() {
            // redirecting first because it is unlikely that the check will return false
            // user provides data about age and other just one time, therefor waiting for the call everytime is bad idea
            director.openPage("pageMeldPaaArrangement");

            // If the user check will return false, the user will be redirected to the page to provide the missing data.
            var checkInfo = await spaInteraction.runAjaxCall('check_info', 'GET', {});
            if(checkInfo.validation == false) {
                window.location.href = checkInfo.path;
                director.openPage("pageMeldPaaArrangement");
            }
        }
    }
})