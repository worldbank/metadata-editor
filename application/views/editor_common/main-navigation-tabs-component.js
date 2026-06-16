/**
 * Main Navigation Tabs Component
 * 
 * 
 * Props:
 *   - activeTab: String - The currently active tab ('projects', 'collections', 'templates', 'schemas', 'tags', 'codelists')
 *   - value: Number - v-model value for tab selection (optional)
 * 
 */
Vue.component('main-navigation-tabs', {
    props: {
        activeTab: {
            type: String,
            default: null,
            validator: function(value) {
                return value === null || ['projects', 'collections', 'templates', 'schemas', 'tags', 'codelists', 'data_structures', 'issues'].includes(value);
            }
        },
        value: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            internalValue: null
        };
    },
    computed: {
        tabValue() {
            return this.value !== null && this.value !== undefined ? this.value : this.internalValue;
        },
        isAdmin() {
            return CI && CI.user_info && CI.user_info.is_admin === true;
        },
        hasSchemaPermission() {
            // Check for schema permission, fallback to admin check for backward compatibility
            if (CI && CI.user_info) {
                if (CI.user_info.has_schema_permission !== undefined) {
                    return CI.user_info.has_schema_permission === true;
                }
                // Fallback to admin check if permission not set
                return CI.user_info.is_admin === true;
            }
            return false;
        },
        hasCodelistPermission() {
            if (CI && CI.user_info) {
                if (CI.user_info.has_codelist_permission !== undefined) {
                    return CI.user_info.has_codelist_permission === true;
                }
                return CI.user_info.is_admin === true;
            }
            return false;
        },
        hasDataStructurePermission() {
            if (CI && CI.user_info) {
                if (CI.user_info.has_data_structure_permission !== undefined) {
                    return CI.user_info.has_data_structure_permission === true;
                }
                return CI.user_info.is_admin === true;
            }
            return false;
        },
        siteBaseUrl() {
            return (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url : '';
        }
    },
    watch: {
        value(newVal) {
            this.internalValue = newVal;
        },
        activeTab(newVal) {
            // Set tab value based on activeTab prop
            if (newVal) {
                const tabMap = {
                    'projects': 0,
                    'collections': 1,
                    'templates': 2,
                    'schemas': 3,
                    'tags': 4,
                    'codelists': 5,
                    'data_structures': 6,
                    'issues': 7
                };
                this.internalValue = tabMap[newVal] !== undefined ? tabMap[newVal] : null;
            }
        }
    },
    mounted() {
        // Initialize tab value based on activeTab prop
        if (this.activeTab) {
            const tabMap = {
                'projects': 0,
                'collections': 1,
                'templates': 2,
                'schemas': 3,
                'tags': 4,
                'codelists': 5,
                'data_structures': 6,
                'issues': 7
            };
            this.internalValue = tabMap[this.activeTab] !== undefined ? tabMap[this.activeTab] : null;
        }
    },
    methods: {
        pageLink(page) {
            const url = this.siteBaseUrl + '/' + page;
            window.location.href = url;
        },
        isActiveTab(page) {
            return this.activeTab === page;
        },
        updateValue(value) {
            this.internalValue = value;
            if (this.value !== null && this.value !== undefined) {
                this.$emit('input', value);
            }
        }
    },
    template: `
        <div class="main-navigation-tabs">
            <v-tabs background-color="transparent" v-model="tabValue" @change="updateValue">
                <v-tab :value="0" @click="pageLink('editor')">
                    <v-icon>mdi-text-box</v-icon>
                    <span class="ml-2">{{$t('projects')}}</span>
                </v-tab>
                <v-tab :value="1" @click="pageLink('collections')">
                    <v-icon>mdi-folder-text</v-icon>
                    <span class="ml-2">{{$t('collections')}}</span>
                </v-tab>
                <v-tab :value="2" @click="pageLink('templates')">
                    <v-icon>mdi-alpha-t-box</v-icon>
                    <span class="ml-2">{{$t('templates')}}</span>
                </v-tab>
                <v-tab v-if="hasSchemaPermission" :value="3" @click="pageLink('schemas')">
                    <v-icon>mdi-file-tree</v-icon>
                    <span class="ml-2">{{$t('schemas')}}</span>
                </v-tab>
                <v-tab v-if="hasSchemaPermission" :value="4" @click="pageLink('tags')">
                    <v-icon>mdi-tag-multiple</v-icon>
                    <span class="ml-2">{{$t('Tags')}}</span>
                </v-tab>
                <v-tab v-if="hasCodelistPermission" :value="5" @click="pageLink('codelists')">
                    <v-icon>mdi-format-list-bulleted-type</v-icon>
                    <span class="ml-2">{{$t('codelists')}}</span>
                </v-tab>
                <v-tab v-if="hasDataStructurePermission" :value="6" @click="pageLink('data_structures')">
                    <v-icon>mdi-sitemap</v-icon>
                    <span class="ml-2">Data structures</span>
                </v-tab>
                <v-tab :value="7" @click="pageLink('issues')">
                    <v-icon>mdi-alert-circle-outline</v-icon>
                    <span class="ml-2">{{$t('Issues')}}</span>
                </v-tab>
            </v-tabs>
        </div>
    `
});


