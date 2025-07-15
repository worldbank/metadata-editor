Vue.component('vue-template-acl', {
    props: ['value','users','template_id'],
    data() {
        return {
            tab: null,
        }
    },
    created:function(){
        
    },
    watch:{
        search (val) {
            if (!val) return
            if (this.is_loading) return
            this.is_loading = true
  
          let vm=this;
          this.searchUsers(val);
        }          
    },
    methods: {   
    },
    computed:{
        dialog: {
            get: function () {
                return this.value;
            },
            set: function (newValue) {
                this.$emit('input', newValue);               
            }
       },
    },
    template: `
        <div class="vue-project-share">

        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="800px"
                scrollable                
                >

                   <v-card>

                    <v-card-text>

                     <v-tabs v-model="tab" align-with-title>
                        <v-tabs-slider color="yellow"></v-tabs-slider>

                        <v-tab key="share">{{$t('share')}}</v-tab>
                        <v-tab key="acl">{{$t('acl')}}</v-tab>
                    </v-tabs>

                    <v-tabs-items v-model="tab">
                        <v-tab-item key="share">
                            <vue-template-share-common :key="template_id" :template_id="template_id"></vue-template-share-common>
                        </v-tab-item>

                        <v-tab-item key="acl">
                            <vue-template-acl-common :key="template_id" :template_id="template_id"></vue-template-acl-common>
                        </v-tab-item>
                    </v-tabs-items>

                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn
                        color="primary"
                        text
                        @click="dialog = false"
                    >
                        {{$t('close')}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
               

                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

