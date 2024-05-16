/// Geospatial feature component
Vue.component('geospatial-feature', {
    props:['feature_name'],
    data: function () {    
        return {            
            name: this.feature_name,
            active_characteristic_index: 0
        }
    },
    created: async function(){
        this.name=this.$route.params.feature_name;
    },
    
    computed: {        
        ProjectMetadata(){
            return this.$store.state.formData;
          },
        Feature(){
            if  (this.ProjectMetadata.description  && 
                    this.ProjectMetadata.description.feature_catalogue && 
                    this.ProjectMetadata.description.feature_catalogue.featureType &&
                    this.name
                    ){
                
                //find feature by feature_name
                for(i=0;i<this.ProjectMetadata.description.feature_catalogue.featureType.length;i++){
                    let feature=this.ProjectMetadata.description.feature_catalogue.featureType[i];
                    if (feature.typeName && feature.typeName==this.name){
                        return feature;
                    }
                }
            }

            return {};
        },
        Features(){
            if  (this.ProjectMetadata.description  && this.ProjectMetadata.description.feature_catalogue){
                return this.ProjectMetadata.description.feature_catalogue;
            }

            return {};
        },
        ActiveCharacteristic(){
            if (this.Feature.carrierOfCharacteristics && this.Feature.carrierOfCharacteristics.length>0){
                return this.Feature.carrierOfCharacteristics[this.active_characteristic_index];
            }
            return {};
        },
        ProjectID(){
            return this.$store.state.project_id;
        },
        CharacteristicsTemplate: function(){
            let key='description.feature_catalogue.featureType';
            let feature_template= this.getTemplateByKey(key);
            if (feature_template && feature_template.props){
                for (let i=0;i<feature_template.props.length;i++){
                    if (feature_template.props[i].key=='carrierOfCharacteristics'){
                        return feature_template.props[i];
                    }
                }
            }

            return {};
        },
        FeatureTemplate: function(){
            let key='description.feature_catalogue.featureType';
            let feature_template= this.getTemplateByKey(key);

            if (feature_template && feature_template.props){                
                //remove 'carrierOfCharacteristics'

                feature_template=JSON.parse(JSON.stringify(feature_template));

                if (feature_template.props && feature_template.props.length>0){
                    for (let i=0;i<feature_template.props.length;i++){
                        if (feature_template.props[i].key=='carrierOfCharacteristics'){
                            feature_template.props.splice(i,1);
                        }
                    }
                }
                return feature_template;
            }

            return {};
        },
    },
    methods:{
        isActiveClass: function(index){
            return {
                'table-active active': index==this.active_characteristic_index
            }
        },
        updateCharacteristics: function (key,value)
        {            
            Vue.set(this.ActiveCharacteristic,key,value);
            console.log("updating value for key",key,value);
        },
        updateFeature: function (key,value)
        {            
            Vue.set(this.Feature,key,value);
            console.log("updating value for key",key,value);
        },
        getTemplateByKey: function(key){

            let findTemplateByItemKey= function (items,key){
                let item=null;
                let found=false;
                let i=0;

                while(!found && i<items.length){
                    if (items[i].key==key){
                        item=items[i];
                        found=true;
                    }else{
                        if (items[i].items){
                            item=findTemplateByItemKey(items[i].items,key);
                            if (item){
                                found=true;
                            }
                        }
                    }
                    i++;                        
                }
                return item;
            }

            //search nested formTemplate
            let items=this.$store.state.formTemplate.template.items;
            console.log('items',items);
            let item=findTemplateByItemKey(items,key);

            return item;        
        },
        localValue: function(key)
        {
            return _.get(this.ActiveCharacteristic,key);
        },
        featureValue: function(key)
        {
            return _.get(this.Feature,key);
        }
    },
    template: `
            <div class="geospatial-feature-component mt-5 p-5" style="height:100%;">
            <h2>Feature - {{name}}</h2>

            <div v-for="(column,idx_col) in FeatureTemplate.props" scope="row" :key="column.key" >
                <template v-if="column.type=='section'">
                
                    <form-input
                        :value="featureValue(column.key)"
                        :field="column"
                        @input="updateFeature(column.key, $event)"
                    ></form-input>
                    
                </template>
                <template v-else>
                    <form-input
                        :value="featureValue(column.key)"
                        :field="column"
                        @input="updateFeature(column.key, $event)"
                    ></form-input>                     
                    
                </template>
            </div>

            <h3 class="mt-5 pt-5">Characteristics</h3>

            <div class="d-flex border" style="max-height:700px;">
              <div style="min-width: 300px;overflow:auto;" class="flex-grow-0 flex-shrink-1 bg-light">
                <table class="table table-sm table-striped border">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Feature</th>
                            <th>Feature Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr :class="isActiveClass(index)" v-for="(attribute,index) in Feature.carrierOfCharacteristics" @click="active_characteristic_index=index">
                            <td>{{index+1}}</td>
                            <td>{{attribute.memberName}}</td>
                            <td>{{attribute.valueType}}</td>
                        </tr>
                    </tbody>
                </table>
              </div>
              <div class="flex-grow-1 flex-shrink-0 bg-light p-3" style="overflow:auto;">

                    <div v-for="(column,idx_col) in CharacteristicsTemplate.props" scope="row" :key="column.key" >
                        <template v-if="column.type=='section'">
                        
                            <form-input
                                :value="localValue(column.key)"
                                :field="column"
                                @input="updateCharacteristics(column.key, $event)"
                            ></form-input>
                            
                        </template>
                        <template v-else>
                            <form-input
                                :value="localValue(column.key)"
                                :field="column"
                                @input="updateCharacteristics(column.key, $event)"
                            ></form-input>                              
                            
                        </template>
                    </div>

              </div>
            </div>

            

                
            </div>
            `    
});

