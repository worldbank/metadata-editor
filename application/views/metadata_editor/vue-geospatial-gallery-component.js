/// Geospatial gallery component
Vue.component('geospatial-gallery', {
    props:['value'],
    data: function () {    
        return {            
            
        }
    },
    created: async function(){
        //this.name=this.$route.params.feature_name;
    },
    
    computed: {        
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        ExternalResourcesImages()
        {
            let images=[];
            let base_url=CI.base_url;
            for (let i=0;i<this.ExternalResources.length;i++){
                let resource=this.ExternalResources[i];

                //if dctype contains [pic]
                if (resource.dctype.indexOf("pic")>=0){
                    images.push({src:base_url + '/api/resources/download/'+resource.sid + '/'+resource.id});
                }
            }
            return images;
        },
        ProjectType(){
            return this.$store.state.project_type;
        }
    },
    methods:{
        
    },
    template: `
            <div class="geospatial-gallery-component mt-5 p-5" style="height:100%;">

            <h2>Image gallery</h2>
            
            <div v-if="ProjectType=='geospatial'" class="">

                <v-card max-width="800" >
                    <v-carousel >
                        <v-carousel-item
                        v-for="(item,i) in ExternalResourcesImages"
                        :key="i"
                        :src="item.src"
                        reverse-transition="fade-transition"
                        transition="fade-transition"
                        ></v-carousel-item>
                    </v-carousel>
                </v-card>

            </div>

            </div>
            `    
});

