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
            hello : 'Helloooo!98'
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
        <super>I am injected to child!</super>
        <h1>Message from musikk</h1>
    </div>
    `
});



// Components
var titler = new Vue({
    delimiters: ['#{', '}'], // For å bruke det på Twig
    el: '#alleTitler',
    data : {
        titler : [],
    },
    async mounted() {
        this.innslag_id = $('#pageOversiktInnslag').attr('innslag_id');
        var innslag = await spaInteraction.runAjaxCall('get_innslag/'+this.innslag_id, 'GET', {});
        this.innslag = innslag;
    },
    methods : {
    
    },
    components : {
        skjema,
        musikkComponent
    }
})