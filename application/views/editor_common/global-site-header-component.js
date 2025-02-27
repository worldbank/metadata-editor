Vue.component('vue-global-site-header', {
    data() {
        return {}  
    },
    methods: {
        pageLink: function(page) {
            window.location.href = CI.site_url + '/' + page;
        },
    },
    computed:{
    },
    template: `
        <div class="vue-global-site-header">
            <v-app-bar color="primary-dark" dark>
                <v-toolbar-title>
                <a :href="CI.base_url" style="color: white;text-decoration: none;">
                <img :src="CI.base_url + '/vue-app/assets/images/logo-white.svg'" style="height: 20px;margin-right: 1px;">
                Metadata Editor
                </a>
                </v-toolbar-title>
                <v-spacer></v-spacer>
                
                <v-btn text @click="pageLink('about')">About</v-btn>
                
                <v-menu offset-y>
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

