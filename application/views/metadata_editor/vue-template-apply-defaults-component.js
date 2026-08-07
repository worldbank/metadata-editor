/// Template apply defaults component
Vue.component('template-apply-defaults-component', {
    props:['value'],
    data () {
        return {
            options: 'empty',
            validation_report: [],
            is_processed: false
        }
    },
    mounted: function(){
        this.resetDialogState();
    },
    watch:{
        dialog: function (isOpen) {
            if (isOpen) {
                this.resetDialogState();
            }
        },
        options: function () {
            if (this.is_processed) {
                this.is_processed = false;
                this.validation_report = [];
            }
        }
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        ProjectTemplates()
        {
            return this.$store.state.templates;
        },
        ProjectTemplate()
        {
            return this.$store.state.formTemplate;
        },
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        },
        previewFields: function () {
            if (!this.dialog || this.is_processed) {
                return [];
            }
            if (typeof TemplateDefaultsUtil === 'undefined') {
                return [];
            }
            return TemplateDefaultsUtil.listTemplateDefaultsToApply(
                this.ProjectTemplate,
                this.ProjectMetadata,
                this.options
            );
        },
        canApply: function () {
            return !this.is_processed && this.previewFields.length > 0;
        }
    },
    methods:{
        resetDialogState: function () {
            this.validation_report = [];
            this.is_processed = false;
            this.options = 'empty';
        },
        fieldDisplayTitle: function (entry) {
            if (entry.item && entry.item.title) {
                return entry.item.title;
            }
            return entry.key;
        },
        templateApplyDefaults: async function()
        {
            if (!this.canApply && !this.is_processed) {
                return;
            }

            if (typeof TemplateDefaultsUtil === 'undefined') {
                return;
            }

            this.validation_report = TemplateDefaultsUtil.applyTemplateDefaults(
                this.ProjectTemplate,
                this.ProjectMetadata,
                this.options
            );
            this.is_processed = true;
        },
        closeDialog: function () {
            this.dialog = false;
            this.resetDialogState();
        }
    },
    template: `
            <div class="template-apply-defaults-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" max-width="620" scrollable persistent xstyle="z-index:5000">
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t('apply_template_defaults')}}
                    </v-card-title>
                    <v-card-subtitle>
                        <div class="pt-2">{{$t('apply_template_defaults_description')}}</div>
                    </v-card-subtitle>
                    <v-card-text style="min-height: 100px;">
                    <div>

                    <v-radio-group
                        v-model="options"
                        mandatory
                        >
                        <v-radio
                            :label="$t('update_empty_fields')"
                            value="empty"
                            class="font-weigh-normal"
                        ></v-radio>
                        <v-radio
                            :label="$t('update_all_fields')"
                            value="all"
                            class="font-weigh-normal"
                        ></v-radio>
                    </v-radio-group>

                    </div>

                    <div v-if="!is_processed">
                        <v-divider class="mb-2"></v-divider>
                        <div class="font-weight-medium mb-2">
                            {{ $t('template_defaults_preview_heading') }}
                            <span v-if="previewFields.length">({{ previewFields.length }})</span>
                        </div>
                        <div v-if="previewFields.length === 0" class="text-body-2 text--secondary">
                            {{ $t('template_defaults_preview_empty') }}
                        </div>
                        <ul v-else class="pl-4 mb-0" style="max-height:240px;overflow:auto;">
                            <li v-for="(entry, idx) in previewFields" :key="entry.key + '-' + idx" class="mb-1">
                                <strong>{{ fieldDisplayTitle(entry) }}</strong>
                                <span v-if="entry.willOverwrite" class="text-caption text--secondary ml-1">({{ $t('template_defaults_will_overwrite') }})</span>
                                <div class="text-caption text--secondary">{{ entry.key }}</div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="is_processed && validation_report.length>0">
                        <v-divider></v-divider>
                        <div>
                            {{$t('items_updated')}}:
                        </div>
                        <ul style="margin-left:20px;">
                            <template v-for="(item, idx) in validation_report">
                            <li :key="item.key + '-' + idx"><strong>{{ fieldDisplayTitle(item) }}</strong>: {{item.key}}</li>
                            </template>
                        </ul>
                        <div class="mt-2 text-caption">{{$t('template_defaults_save_after_apply')}}</div>
                    </div>
                    <div v-if="is_processed && validation_report.length==0">
                        <v-divider></v-divider>
                        <div>
                            {{$t('no_items_updated')}}
                        </div>
                    </div>


                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="templateApplyDefaults" :disabled="!canApply">
                        {{$t('apply')}}
                    </v-btn>
                    <v-btn color="primary" text @click="closeDialog" >
                        {{$t('close')}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->

            </div>
            `
});
