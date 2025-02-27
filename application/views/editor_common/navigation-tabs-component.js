Vue.component('vue-navigation-tabs', {
    props: ['value','active'],
    data() {
        return {
            
        }
    },
    mounted: async function(){        
    },
    methods: {        
        pageLink: function(page) {
            window.location.href = CI.site_url + '/' + page;
        },
    },
    computed:{
    },
    template: `
        <div class="vue-navigation-tabs-component">
            <v-tabs background-color="transparent">
                <v-tab @click="pageLink('projects')"><v-icon class="mr-1">mdi-text-box</v-icon> {{$t('projects')}}</v-tab>
                <v-tab @click="pageLink('collections')"><v-icon class="mr-1">mdi-folder-text</v-icon> <a href="<?php echo site_url('collections');?>">{{$t("collections")}}</a> </v-tab>                          
                <v-tab @click="pageLink('templates')"><v-icon class="mr-1">mdi-alpha-t-box</v-icon> <a href="<?php echo site_url('templates');?>">{{$t("templates")}}</a></v-tab>                          
            </v-tabs>        
        </div>
    `
});

