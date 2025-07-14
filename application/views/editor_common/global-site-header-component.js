Vue.component('vue-global-site-header', {
    data() {
        return {
            languages: [],
            current_language: {
                title:'Language',
            }
        }  
    },
    mounted() {
        this.loadLanguages();
    },
    methods: {
        pageLink: function(page) {
            window.location.href = CI.site_url + '/' + page;
        },
        switchLanguage: function(lang) {
            let page = window.location.pathname;            

            if (page.endsWith('/')) {
                page = page.slice(0, -1);
            }
            page = page.split('/').pop();

            if (page === 'index.php' || page === '/') {
                page = 'projects';
            }

            //remove starting slash if exists
            if (page.startsWith('/')) {
                page = page.slice(1);
            }

            window.location.href = CI.site_url + '/switch_language/' + lang + '/?destination=' + page;
        },
        loadLanguages: function() {
            axios.get(CI.site_url + '/api/languages')
                .then(response => {
                    console.log('Languages loaded:', response.data);
                    if (response.data && response.data.languages) {
                    this.languages = response.data.languages;

                        if (response.data.current_language_title) {
                            this.current_language.title = response.data.current_language_title;
                        }

                    } else {
                        console.error('Unexpected response format:', response.data);
                    }
                })
                .catch(error => {
                    console.error('Error loading languages:', error);
                }
            );
        }
    },
    computed:{

        BaseUrl(){
            //remove index.php
            let base_url = CI.base_url;
            if (base_url.endsWith('/index.php')) {
                base_url = base_url.slice(0, -10);
            }
            return base_url;
        }

    },
    template: `
        <div class="vue-global-site-header">
            <v-app-bar color="primary-dark" dark>
                <v-toolbar-title>
                <a :href="BaseUrl" style="color: white;text-decoration: none;">
                <img :src="BaseUrl + '/vue-app/assets/images/logo-white.svg'" style="height: 20px;margin-right: 1px;">
                Metadata Editor
                </a>
                </v-toolbar-title>
                <v-spacer></v-spacer>
                
                <v-btn text @click="pageLink('about')">{{$t('About')}}</v-btn>

                <v-menu offset-y style="z-index: 2000;" >
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn
                        text
                        dark
                        v-bind="attrs"
                        v-on="on"
                        >
                        <v-icon>mdi mdi-translate</v-icon> {{current_language.title}}
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item v-for="lang in languages" :key="lang.code">
                            <v-list-item-title>
                                <v-btn text @click="switchLanguage(lang.name)" >                                    
                                    {{lang.display}}
                                </v-btn>
                            </v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
                
                <v-menu offset-y style="z-index: 2000;" >
                <template v-slot:activator="{ on, attrs }">
                    <v-btn
                     text
                    dark
                    v-bind="attrs"
                    v-on="on"
                    >
                    <v-icon>mdi-account-circle</v-icon> {{CI.user_info.username}}
                    </v-btn>
                </template>
                <v-list>
                    <v-list-item>
                        <v-list-item-title><v-btn @click="pageLink('auth/profile')" text>{{$t('profile')}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item>
                        <v-list-item-title><v-btn text @click="pageLink('auth/change_password')" >{{$t('password')}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item v-if="CI.user_info.is_admin">
                        <v-list-item-title><v-btn text @click="pageLink('admin')" >{{$t('site_administration')}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item>
                        <v-list-item-title><v-btn text @click="pageLink('auth/logout')" >{{$t('logout')}}</v-btn></v-list-item-title>
                    </v-list-item>
                </v-list>
                </v-menu>
                

            </v-app-bar>
        </div>
    `
});

