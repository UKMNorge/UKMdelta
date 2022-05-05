// Dialog Components
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
});


// Message Components
var messageComponent = Vue.component('messagemodal-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    props: {
        alleInnslag : []
    },
    data : function() {
        return {
            melding : '',
            title : '',
            type : 0, // -1 error, 0 - normal, 1 - warning
            open : false,
            timeout : 0,
            timeoutFunction : null
        }
    },
    async mounted() {

    },
    methods : {
        openMessage : function(title, melding, type = 0) {
            if(this.timeoutFunction) {
                clearTimeout(this.timeoutFunction);
            }
            
            this.title = title
            this.melding = melding;
            this.type = type;

            this.openModal(type);
            
            this.timeoutFunction = setTimeout(() => {
                this.closeModal();
            }, 5000);
        },
        openModal : function(type) {
            if(this.open) {
                this.closeModal();
                setTimeout(() => {
                    this.openMessage(this.title, this.melding, this.type);
                }, 300);
                return;
            }

            $('#mainMessageDiv').modal('show');
            this.open = true;
        },
        closeModal : function() {
            $('#mainMessageDiv').modal('hide');
            this.open = false;
        }
    },
    template : /*html*/`
        <div class="modal fade" id="mainMessageDiv" tabindex="-1" role="dialog" data-backdrop="false" aria-hidden="false">
            <div :class="{ 'error' : type == -1, 'warning' : type == 1}" class="modal-dialog modal-dialog-centered" role="document">
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
                </div>
            </div>
        </div>
    `
})


// Loading Components
var loadingComponent = Vue.component('loading-component', { 
    delimiters: ['#{', '}'], // For å bruke det på Twig
    data : function() {
        return {
            show : false
        }
    },
    async mounted() {
        this.hideLoading();
    },
    methods : {
        showLoading : function() {
            this.show = true;
        },
        hideLoading : function() {
            this.show = false;
        }
    },
    template : /*html*/`
    <div id="headerMainLoader" :class="{ 'hide' :  !show }">
        <div class="spinner-border" role="status">
            <span class="sr-only">Loading...</span>
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
        },
        showMessage : function(title, msg, type = 0) {
            this.$refs.messageModal.openMessage(title, msg, type);
        },
        showLoading : function() {
            if(this.$refs.mainLoading) {
                this.$refs.mainLoading.showLoading();
            }
        },
        hideLoading : function() {
            if(this.$refs.mainLoading) {
                this.$refs.mainLoading.hideLoading();
            }
        }
    },
    components : { 
        'dialog' : dialogComponent,
        'message' : messageComponent,
        'loading' : loadingComponent
    }
})