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
        alle_innslag : []
    },
    async mounted() {
        this.updateData();
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

                console.log(this.alle_innslag);
            }
        },
        _alleInnslags : function() {
            return this.alle_innslag[0][1].concat(this.alle_innslag[1][1]);
        },
        // Is innslag(s) empty
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
        continueLastInnslag : function() {
            if(!this.isEmpty()) {
                if(this.alle_innslag[1][1].length > 0) {
                    this.redirectToInnslagOverview(this.alle_innslag[1][1][0].innslag);
                }
            }
        },
        getCurrentDomain : function() {
            return getCurrentDomain();
        },
        redirectToInnslagOverview : function(innslag) {
            window.addEventListener( "pageshow", function ( event ) {
                var historyTraversal = event.persisted || 
                                       ( typeof window.performance != "undefined" && 
                                            window.performance.navigation.type === 2 );
                if ( historyTraversal ) {
                  // Handle page restore.
                  window.location.reload();
                }
            });

            var link = '/ukmid/pamelding/' + innslag.kommune_id + '-' + innslag.home.id + '/' + innslag.type.key + '/' + innslag.id + '/';
            window.location.href = link;
        }
    }
})