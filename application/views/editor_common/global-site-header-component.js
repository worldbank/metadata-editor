Vue.component('vue-global-site-header', {
    data() {
        return {
            languages: [],
            current_language: {
                title:'Language',
            },
            unreadCount: 0,
            recentNotifications: [],
            notificationsLoading: false,
            notificationPollTimer: null
        }  
    },
    mounted() {
        this.loadLanguages();
        this.startNotificationPolling();
    },
    beforeDestroy() {
        this.stopNotificationPolling();
    },
    methods: {
        pageLink: function(page) {
            window.location.href = CI.site_url + '/' + page;
        },
        notificationsApi: function() {
            return (CI.site_url || '').replace(/\/?$/, '/') + 'api/notifications';
        },
        startNotificationPolling: function() {
            if (!this.isLoggedIn) {
                return;
            }
            this.refreshNotifications();
            var vm = this;
            this.stopNotificationPolling();
            this.notificationPollTimer = setInterval(function () {
                vm.refreshUnreadCount();
            }, 90000);
        },
        stopNotificationPolling: function() {
            if (this.notificationPollTimer) {
                clearInterval(this.notificationPollTimer);
                this.notificationPollTimer = null;
            }
        },
        refreshUnreadCount: function() {
            var vm = this;
            if (!vm.isLoggedIn) {
                return;
            }
            axios.get(vm.notificationsApi() + '/unread_count')
                .then(function (response) {
                    if (response.data && typeof response.data.unread_count === 'number') {
                        vm.unreadCount = response.data.unread_count;
                    }
                })
                .catch(function () {
                    // keep last known count
                });
        },
        refreshNotifications: function() {
            var vm = this;
            if (!vm.isLoggedIn) {
                return;
            }
            vm.notificationsLoading = true;
            axios.get(vm.notificationsApi(), { params: { unread: 1, limit: 5, offset: 0 } })
                .then(function (response) {
                    var data = response.data || {};
                    vm.recentNotifications = data.notifications || [];
                    if (typeof data.unread_count === 'number') {
                        vm.unreadCount = data.unread_count;
                    }
                })
                .catch(function () {
                    // keep last known list
                })
                .finally(function () {
                    vm.notificationsLoading = false;
                });
        },
        onNotificationsMenu: function(isOpen) {
            if (isOpen) {
                this.refreshNotifications();
            }
        },
        openHeaderNotification: function(item) {
            var page = 'notifications';
            if (item && item.id) {
                page += '?id=' + item.id;
            }
            this.pageLink(page);
        },
        formatNotificationTime: function(value) {
            if (!value) {
                return '';
            }
            if (typeof moment !== 'undefined') {
                return moment.unix(value).fromNow();
            }
            return '';
        },
        switchLanguage: function(lang) {
            const params = new URLSearchParams();
            params.append('language', lang);
            
            axios.post(CI.site_url + '/api/languages/switch', params, {
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => {
                if (response.data && response.data.status === 'success') {
                    // Update current language display
                    if (response.data.language_display) {
                        this.current_language.title = response.data.language_display;
                    }
                    // Reload the page to apply the new language
                    window.location.reload();
                } else {
                    console.error('Language switch failed:', response.data);
                    alert('Failed to switch language. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error switching language:', error);
                alert('Error switching language. Please try again.');
            });
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
        },
        canAccessSiteAdmin(){
            if (!CI || !CI.user_info) {
                return false;
            }
            return CI.user_info.can_access_admin_dashboard === true;
        },
        canViewPublishQueue(){
            if (!CI || !CI.user_info) {
                return false;
            }
            if (CI.user_info.is_admin === true) {
                return true;
            }
            return CI.user_info.can_view_publish_queue === true;
        },
        isLoggedIn(){
            if (!CI || !CI.user_info) {
                return false;
            }
            return CI.user_info.is_logged_in === true || !!CI.user_info.username;
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

                <v-menu
                    v-if="isLoggedIn"
                    offset-y
                    :close-on-content-click="true"
                    style="z-index: 2000;"
                    min-width="360"
                    max-width="420"
                    @input="onNotificationsMenu"
                >
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn
                            text
                            dark
                            v-bind="attrs"
                            v-on="on"
                            :title="$t('notifications') || 'Notifications'"
                        >
                            <v-badge
                                :value="unreadCount > 0"
                                color="error"
                                dot
                                overlap
                                class="header-notification-badge"
                            >
                                <v-icon>mdi-bell</v-icon>
                            </v-badge>
                        </v-btn>
                    </template>
                    <v-list two-line dense>
                        <v-subheader>{{ $t('notifications') || 'Notifications' }}</v-subheader>
                        <v-list-item v-if="notificationsLoading && recentNotifications.length === 0">
                            <v-list-item-title class="grey--text">{{ $t('loading') || 'Loading...' }}</v-list-item-title>
                        </v-list-item>
                        <div v-else-if="recentNotifications.length === 0" class="header-notification-empty">
                            {{ $t('notifications_empty_unread') || 'No unread notifications.' }}
                        </div>
                        <v-list-item
                            v-for="item in recentNotifications"
                            :key="item.id"
                            @click="openHeaderNotification(item)"
                        >
                            <v-list-item-icon class="header-notification-item-icon">
                                <v-icon>mdi-bell-outline</v-icon>
                            </v-list-item-icon>
                            <v-list-item-content>
                                <v-list-item-title>{{ item.subject || item.title }}</v-list-item-title>
                                <v-list-item-subtitle>{{ item.title }}</v-list-item-subtitle>
                            </v-list-item-content>
                            <v-list-item-action v-if="item.created">
                                <v-list-item-action-text>{{ formatNotificationTime(item.created) }}</v-list-item-action-text>
                            </v-list-item-action>
                        </v-list-item>
                        <v-list-item class="header-notification-view-all">
                            <v-btn small text color="primary" @click.stop="pageLink('notifications')">
                                {{ $t('view_all_notifications') || 'View all' }}
                            </v-btn>
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
                        <v-icon>mdi-cog</v-icon>
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item>
                            <v-list-item-title><v-btn text @click="pageLink('settings/catalogs')">{{$t('catalog_connections')}}</v-btn></v-list-item-title>
                        </v-list-item>
                        <v-list-item>
                            <v-list-item-title><v-btn text @click="pageLink('jobs')">{{$t('my_jobs')}}</v-btn></v-list-item-title>
                        </v-list-item>
                        <v-list-item v-if="canViewPublishQueue">
                            <v-list-item-title><v-btn text @click="pageLink('publish-queue')">{{$t('publishing_queue')}}</v-btn></v-list-item-title>
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
                        <v-list-item-title><v-btn text @click="pageLink('notifications')">{{$t('notifications') || 'Notifications'}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item>
                        <v-list-item-title><v-btn @click="pageLink('auth/profile')" text>{{$t('profile')}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item>
                        <v-list-item-title><v-btn text @click="pageLink('auth/change_password')" >{{$t('password')}}</v-btn></v-list-item-title>
                    </v-list-item>
                    <v-list-item v-if="canAccessSiteAdmin">
                        <v-list-item-title><v-btn text @click="pageLink('admin')">{{$t('site_administration')}}</v-btn></v-list-item-title>
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

