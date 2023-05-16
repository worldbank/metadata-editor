/// datafile add/edit form
Vue.component('datafile-edit', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{}
        }
    },
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){
            this.field_data=[];
            this.field_data.push({});
        }

        this.form_local = Object.assign({}, this.field_data);        
    },
    computed: {
    },  
    template: `
            <div class="datafile-edit-component container-fluid">

            <div class="row">
                <div class="col-md-8">
            
                <div class="form-group form-field">
                    <label for="filename">File name</label> 
                    <span><input type="text" id="filename" class="form-control" v-model="form_local.file_name"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Description</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.description"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Producer</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.producer"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Data checks</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.data_checks"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Missing data</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.missing_data"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Version</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.version"/></span> 
                </div>

                <div class="form-group form-field">
                    <label for="description">Notes</label> 
                    <span><textarea id="description" class="form-control" v-model="form_local.notes"/></span> 
                </div>

                <button type="button" class="btn btn-primary" @click="saveForm">Save</button>
                <button type="button" class="btn btn-danger" @click="cancelForm">Cancel</button>

                </div>
                <div class="col-md-4">
                    <div><strong>File information</strong></div>
                    <div class="mt-2">
                        <div>
                            <label>Physical name:</label>
                            <div>{{form_local.file_physical_name}}</div>
                        </div>                            
                        <div class="mt-2">
                            <label>Rows:</label>
                            <div>{{form_local.case_count}}</div>
                        </div>
                        <div class="mt-2">
                            <label>Variables:</label>
                            <div>{{form_local.var_count}}</div>
                        </div>
                        <div class="mt-2" v-if="form_local.file_info">
                            <label>File size:</label>
                            <div>{{form_local.file_info.original.file_size}}</div>                        
                        </div>
                    </div> 
                </div>
                </div>

            </div>          
            `,
    methods:{
        saveForm: function (){    
            this.field_data = Object.assign({}, this.field_data, this.form_local);
            this.$emit('input', this.field_data);
            this.$emit("exit-edit", true);
        },
        cancelForm: function (){
            this.form_local = Object.assign({}, this.field_data);
            this.$emit("exit-edit", false);
        }        
    }
})