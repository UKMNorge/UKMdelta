var innslagsTypeComponent = Vue.component('type-innslag-component', {
    delimiters:  ['#{', '}'],
    data: function() {
        return {
            innslagsTyper: [],
            director : director,
        }
    },
    updated() {
        // this.initTypes();
    },
    methods: {
        initTypes : async function () {
            var el = $('#viseNoeFremTyper');
            var pl_id = 3870; //this.director.getParam('pl_id');
            // if pl_id er null saa gaa tilbake (se tilbake knappen)
            console.log(pl_id);

            try{
                // el.html('');
                $('#viseNoeFremTyperPhantom').removeClass('loaded');
                var res = await spaInteraction.runAjaxCall('get_innslag_types/' + pl_id, 'GET', {});
                
                console.warn(res);

                this.innslagsTyper = res;
                
            }catch(err) {
                // Error
                console.error(err);
            }
        }
    },
    template: /*html*/`
    <div>
        <div class="vise-noe-frem" v-for="type in innslagsTyper">
            <div class="panel-body accordion-body-root item-type">
                <div class="panel panel-default">
                    <div class="accordion-sub" data-toggle="collapse" data-parent="#accordionKommune" href="#collapseArrangementerkommuneid">
                        <div class="panel-heading accordion-header-sub card-body">
                            <div class="type-info-left">

                                <object class="innslag-type-object" :data="['https://assets.${ getCurrentDomain() }/img/delta-nytt/innslag-types-icons/' + type.key + '.svg']"></object>
                                <span class="innslags-type-navn" style="margin-left: 8px;">
                                    #{type.name}
                                </span>
                            </div>
                            <div class="type-info-right">
                                <button class="small-button-style hover-button-delta mini smaller" style="margin-right: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="6 0 24 24" style="fill: #fff;transform: ;msFilter:;"><path d="M12 4C9.243 4 7 6.243 7 9h2c0-1.654 1.346-3 3-3s3 1.346 3 3c0 1.069-.454 1.465-1.481 2.255-.382.294-.813.626-1.226 1.038C10.981 13.604 10.995 14.897 11 15v2h2v-2.009c0-.024.023-.601.707-1.284.32-.32.682-.598 1.031-.867C15.798 12.024 17 11.1 17 9c0-2.757-2.243-5-5-5zm-1 14h2v2h-2z"></path></svg>
                                </button>

                                <svg xmlns="http://www.w3.org/2000/svg" viewbox="5 5 18 18" fill="#718096" class="icon" width="24" height="24">
                                    <path fill-rule="evenod d" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `
});

var innslagType = new Vue({
    delimiters: ['#{', '}'], 
    el: '#pageVelgInnslagType',
    data: {
        message : 'Noenoenoe!',
    },
    methods : {
        initNew : function() {
            this.$refs.innslagsType.initTypes();
        }
    },
    components : {
        innslagsTypeComponent
    }
});