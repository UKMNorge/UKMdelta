// Components
var fylkerKommunerComponent = Vue.component('fylker-kommuner-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            fylker : [],
            director : director,
        }
    },
    async mounted() {
        var fylker = await spaInteraction.runAjaxCall('get_all_fylker_og_kommuner/', 'GET', {});
        this.fylker = fylker;
    },
    updated() {
        this.initFilter();
        inputDeltaFix();
    },
    methods : {
        getDato : (dateint) => {
            var date = new Date(dateint * 1000)
            var day = getDayNorwegian(date.getDay());
            var month = getMonthNorwegian(date.getMonth());

            return day + ' ' + date.getDate() + '. ' + month + ', ' + date.getFullYear();
        },
        chooseArrangement : (arrangement) => {
            director.openPage('pageVelgInnslagType'); 
            director.addParam('pl_id', arrangement.id);
            director.addParam('k_id', arrangement.kommuner_id[0]);
            innslagType.initNew();

        },
        initFilter : () => {
            var callbackFilter = (numShown) => {
                if($('#searchInput').val().length < 3) {
                    $('.panel-body.fylke-body.search').removeClass('show');
                    $('.accordion-by .card-header-fylke .btn-link').removeClass('halv');
                }
                    for(let el of $('#alleFylkerOgKommuner .accordion .card .fylke-body.search')) {
                        if($(el).parent().parent().css('display') != 'none') {
                            var count = 0;
                            for(var kommune of $(el).find('.card-body-kommune.search')) {
                                if($(kommune).css('display') != 'none') {
                                    count++;
                                }
                            }
                            if(count == 0) {
                                $(el).find('.card-body-kommune.search').css('display', 'flex');
                            }
                            else if(count > 0 && count < 15) {
                                $(el).collapse('show');
                                $(el).parent().find('.fylke-btn').addClass('halv');
                            }
                        }
                    }
                
    
            };
    
            // Filter
            $('#searchInput').fastLiveFilter('#alleFylkerOgKommuner, .search-kommune', {
                callback: callbackFilter}
            );
        },
        async getArrangement(kommune) {
            kommune.arrangementer = await spaInteraction.runAjaxCall('get_arrangementer_i_kommune/' + kommune.id, 'GET', {});
            kommune.arrangementer_loaded = true;            
        }
    },
    template : /*html*/`
    <div>
        <div id="alleFylkerOgKommuner" class="alle-fylker">
            <div v-for="fylke in fylker" class="accordion panel-group accordion-item accordion-by" id="accordionBy">
                <div class="card panel panel-default">
                    <div class="panel-heading card-header accordion-header-root card-header-fylke">
                        <button class="btn btn-link btn-block fylke-btn btn-accordion-root text-left collapsed hover-button-delta" data-toggle="collapse" data-parent="#accordionBy" v-bind:href="['#collapseKommuneForFylke' + fylke.id]">
                            <svg class="caret-flip" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: rgb(160, 174, 192);">
                                <path d="M9 19L17 12 9 5z"></path>
                            </svg>
                            <span class="fylke-navn accordion-title-root">#{ fylke.navn }</span>
                        </button>
                    </div>

                    <div :id="['collapseKommuneForFylke' + fylke.id]" :f_id="['#' + fylke.id]" class="panel-body accordion-body-root fylke-body arrangementer-visning search collapse">
                        <div class="panel panel-default">
                            <div v-for="kommune in fylke.kommuner">
                                <div @click="getArrangement(kommune)" class="accordion-sub kommune-accordion search-kommune collapsed" :k_id="kommune.id" data-toggle="collapse" data-parent="#accordionKommune" :href="['#collapseArrangementer' + kommune.id]">
                                    <div class="panel-heading accordion-header-sub card-body card-body-kommune search">
                                        <span class="accordion-title-sub kommune-navn">
                                            #{ kommune.navn }
                                            <br class="info-label">
                                            <p class="description info-label">Hvilket arrangement vil du melde deg på?</p>
                                        </span>
                                        <div>
                                            <svg class="icon" style="height: 24px;width: 24px; margin: auto 0 auto auto;" xmlns="http://www.w3.org/2000/svg" viewbox="0 0 20 20" fill="#718096">
                                                <path fill-rule="evenod d" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <!-- Arrangementer -->
                                <div :id="['collapseArrangementer' + kommune.id]" class="panel-body accordion-child panel-body-arrangement collapse">
                                    <div v-for="arrangement in kommune.arrangementer">
                                        <div v-if="arrangement" class="panel-inner">
                                            <div class="panel-group" id="accordionKommune">
                                                <div class="panel panel-default accordion-panel-child arrangement-default" data-toggle="collapse" data-parent="#accordionArrangement" href="#collapseForm">
                                                    <div class="panel-heading accordion-header-child card-body card-body-arrangement meldpaa" @click="chooseArrangement(arrangement)">
                                                        <span>
                                                            #{ arrangement.navn }
                                                            <p class="info-label">#{ getDato(arrangement.frist_1) }</p>
                                                        </span>
                                                        <svg v-show="arrangement.kommuner_fellesmonstring == null" style="height: 24px;width: 24px; margin: auto 0 auto auto;" xmlns="http://www.w3.org/2000/svg" viewbox="0 0 20 20" fill="#718096">
                                                            <path fill-rule="evenod d" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    <div v-if="arrangement.kommuner_fellesmonstring" class="fellesmonstring-kommuner" data-toggle="collapse" :href="['#kommuner' + arrangement.id]" role="button" aria-expanded="true" :aria-controls="['kommuner' + arrangement.id]">
                                                        <div class="info-inactive-button" >
                                                            <svg style="height: 18px;width: auto; fill:#A0AEC0; transform: rotate(90deg)" class="caret-flip" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                                <path d="M9 19L17 12 9 5z"></path>
                                                            </svg>
                                                            <span class="info-label">HVILKEN BY ER DU FRA?</span>
                                                        </div>
                                                        <div :id="['kommuner' + arrangement.id]" class="collapse show">
                                                            <div v-for="felles_kommune in arrangement.kommuner_fellesmonstring" class="panel-heading accordion-header-sub card-body card-body-kommune hover-button-delta fellesmonstring">
                                                            <p class="accordion-title-sub kommune-navn">
                                                                #{ felles_kommune.navn }
                                                            </p>
                                                            <svg class="icon" style="height: 24px;width: auto;margin: auto 0 auto auto;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#718096">
                                                                <path fill-rule="evenod d" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>




                                    </div>

                                    <div v-if="kommune.arrangementer_loaded && kommune.arrangementer.length < 1" class="no-arrangement">
                                        <p>Beklager, vi har ingen arrangement for #{ kommune.navn } enda.</p>
                                        <a class="small-button-style kontakt-oss-btn hover-button-delta" :href="kommune.link">Kontakt oss</a>
                                    </div>
                                    <div v-show="!kommune.arrangementer_loaded" class="panel-inner phantom-arrangement">
                                        <div class="panel-group" id="accordionKommune">
                                            <div class="panel panel-default arrangement-default" data-toggle="collapse" data-parent="#accordionArrangement" href="#collapseForm">
                                                
                                                <div class="panel-heading card-body accordion-header-child card-body-arrangement">
                                                    <span>
                                                        <span class="phantom-loading">Arrangement navn</span>
                                                        <p class="info-label phantom-loading">Onsdag 9. Desember, 2020</p>
                                                    </span>
                                                    <svg class="phantom-loading" style="height: 24px;width: auto; margin: auto 0 auto auto;" xmlns="http://www.w3.org/2000/svg" viewbox="0 0 20 20" fill="#718096">
                                                        <path fill-rule="evenod d" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </div>                                        
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `
})


// The app
var meldtPaa = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#pageMeldPaaArrangement',
    data: {
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
        fylkerKommunerComponent
    }
})