// Components
var dialogComponent = Vue.component('modaldialog-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        alleInnslag : []
    },
    data : function() {
        return {
            melding : '',
            title : '',
            buttons : [] // [{name : 'Accept', class : "confirm-btn", callback : ()=> { ... }]
        }
    },
    async mounted() {

    },
    methods : {
        openDialog : function(title, melding, buttons = null) {
            this.melding = melding;
            this.buttons = buttons;
            this.title = title ? title : '';
            $('#mainModalDialog').modal('show');
        },
        buttonClick : function(button) {
            if(button.callback) {
                button.callback();
            }
        }
    },
    template : /*html*/`
        <div class="modal fade" id="mainModalDialog" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content accordion-item with-shadow with-radius">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLongTitle">#{title}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        #{melding}
                    </div>
                    <div class="modal-footer">
                        <button v-for="btn in buttons" @click="buttonClick(btn)" type="button" class="round-style-button hover-button-delta" :class="btn.class" data-dismiss="modal">#{btn.name}</button>
                    </div>
                </div>
            </div>
        </div>
    `
})


// The app
var interactionVue = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#deltaHeader',
    data: {
        alle_innslag : []
    },
    async mounted() {
        // this.updateData();
    },
    methods : {
        openDialog : function(title, msg, buttons = null) {
            this.$refs.modalDialog.openDialog(title, msg, buttons);
        }
    },
    components : { 'dialog' : dialogComponent }
})