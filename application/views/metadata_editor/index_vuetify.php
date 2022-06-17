<!DOCTYPE html>
<html>
<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  
  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>


<?php
  $template_parts=array();
  //$metadata_template_arr=json_decode($metadata_template,true);
  
  get_template_part($metadata_template_arr['items'],$template_parts);

  function get_template_part($items,&$output){
    foreach($items as $item){
      if (isset($item['items'])){
        get_template_part($item['items'],$output);
      }
      if (isset($item['key'])){
        $output[$item['key']]=$item;
      }
    }        
  }  
?>


<style>
      <?php //echo $this->load->view('metadata_editor/bootstrap-forms.css',null,true); ?>
      <?php echo $this->load->view('metadata_editor/styles.css',null,true); ?>
</style>
<body class="hold-transition sidebar-mini layout-fixed">

    <script>
        var CI = {'base_url': '<?php echo site_url();?>'}; 
        let sid='<?php echo $sid;?>';
        let form_template=<?php echo $metadata_template;?>;
        let form_template_parts= <?php echo json_encode($template_parts,JSON_PRETTY_PRINT); ?>;
        let metadata_schema=<?php echo $metadata_schema;?>;        
    </script>

  <div id="app">
    <?php echo $this->load->view("metadata_editor/layout.php",null,true); ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <script src="https://unpkg.com/vue-router@3"></script>
  <script src="https://unpkg.com/vuex@3.4.0/dist/vuex.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
  <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.2/ajv.bundle.js" integrity="sha256-u9xr+ZJ5hmZtcwoxwW8oqA5+MIkBpIp3M2a4AgRNH1o=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />   
    
    <script src="https://cdn.jsdelivr.net/npm/vue-scrollto"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vee-validate/3.4.0/vee-validate.full.min.js" integrity="sha512-owFJQZWs4l22X0UAN9WRfdJrN+VyAZozkxlNtVtd9f/dGd42nkS+4IBMbmVHdmTd+t6hFXEVm65ByOxezV/Qxg==" crossorigin="anonymous"></script>

  <?php echo $this->load->view("metadata_editor/index_vuetify_main_app",null,true);?>

  <script>
    vue_app=new Vue({
      el: '#app',
      vuetify: new Vuetify(),
      router:router,
      store,
      data:{
          active_section:null,
          active_form_field:null,
          schema_validator: null,
          dataset_id:project_sid,
          dataset_idno:project_idno,
          dataset_type:project_type,
          form_template: form_template,
          metadata_schema: metadata_schema,
          /*dialog_box_option:{
              'title': '',
              'content': '',
              'erorrs': {}                    
          },*/
          form_errors:[],
          schema_errors:[],
          initiallyOpen: ['public'],
          files: {
            html: 'mdi-language-html5',
            js: 'mdi-nodejs',
            json: 'mdi-code-json',
            md: 'mdi-language-markdown',
            pdf: 'mdi-file-pdf',
            png: 'mdi-file-image',
            txt: 'mdi-file-document-outline',
            xls: 'mdi-file-excel',
            folder:'mdi-folder-multiple',
            file:'mdi-file-document-outline',
            database:'mdi-database',
            table:'mdi-table',
          },
        tree: [],
        items: [],
      },
      mounted: function(){
        this.loadExternalResources();
        //this.initSchemaValidator();
      },
      computed:{   
        get_tree_data(){
          let tree_data=this.form_template.items;
          if (this.dataset_type=='survey'){
            tree_data.push({
              title: 'Datasets',
              type:'datasets',
              file: 'database',
              items:this.get_data_files
            });
          }

          tree_data.push({
              title: 'External resources',
              type: 'resources',
              file: 'datasets',
              items:this.ExternalResourcesTreeNodes
            });

            this.items=tree_data;
        },             
        get_data_files(){
          //return this.test_data_files;          
          let dataFiles=[
            {
              'title':'test file 1',
            }
          ];
          return dataFiles;
        }, 
        ProjectMetadata(){
          return this.$store.state.formData;
        },
        ExternalResources()
        {
          return this.$store.state.external_resources;          
        },
        ExternalResourcesTreeNodes(){
          let resources=this.$store.state.external_resources;
          if (resources.length==0){
            return [];
          }

          let resources_nodes=[];

          i=0;
          for (let resource of resources) {
            resources_nodes.push(
              {
                title: resource.title,
                type:'resource',
                index:i,
                file: 'database',
                resource:  resource
              }
            );
            i++;
          }
          console.log("resources nodes:",resources_nodes);
          return resources_nodes;
        }
      },
      watch: {
      '$store.state.data_files': function() {
        //console.log(this.$store.state.drawer)
        this.test_data_files=this.$store.state.data_files;
        this.get_tree_data;
        console.log("update updated");
      }
      },
      methods:{
        loadExternalResources: function() {
            vm=this;

            let url=CI.base_url + '/api/datasets/'+vm.dataset_idno + '/resources';
            axios.get(url)
            .then(function (response) {
                console.log(response);
                if(response.data.resources){
                    //vm.$store.state.external_resources=response.data.resources;
                    vm.$store.commit('external_resources',response.data.resources);
                    vm.get_tree_data;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        templateToTree: function (){
          window.template=this.form_template;
        },
        treeOnUpdate: function(node_key)
        {
          console.log("clicked on",node_key);
          /*this.treeClick({
            'key':node_key
          });*/
        },
        treeClick: function (node){
          //this.active_form_field=node;
          //this.$store.state.active_form_field=node;
          store.commit('tree_active_node',node.key);
          //console.log(node.key);
          //return;

          //store.commit('tree_active_node',node);

          if (node.type=='datasets'){
            router.push('/datafiles');
            return;
          }

          if (node.type=='resources'){
            router.push('/external-resources');
            return;
          }

          if (node.type=='resource'){
            router.push('/external-resources/'+node.index);
            return;
          }

         /*if (node.name=='study_description'){
          router.push('/')
         }*/

         /*if (node.type=='section'){
           EventBus.$emit('activeSection', node.key);
         }*/

          router.push('/study/'+node.key);
            /*router.push('/study/' +node.key,{
                name: 'study',
                params: {
                    element_id: 'hello there' // or anything you want
                }
            });*/ 
            
            console.log(node); 
        },
        saveProject: function(){
          vm=this;          
          let url=CI.base_url + '/api/datasets/update/'+vm.dataset_type+'/' + vm.dataset_idno;
          
          form_data=JSON.parse(JSON.stringify(vm.ProjectMetadata));
          this.$refs.form.validateWithInfo().then(({ isValid, errors, $refs })=> {
              vm.form_errors=Object.values(errors).flat();                                                
              if (vm.form_errors=='' || vm.form_errors.length==0){
                  //vm.saveForm();
                  console.log("save form");
              }                    
          });
                  
          //vm.removeEmpty(form_data);
          //console.log(form_data);
          /*
          validation_result=this.validateData(form_data);
          console.log("validation result",validation_result);

          //remove additionalProperties not supported by schema
          validation_errors=[];
          if(validation_result!==true){
              for (let i = 0; i < validation_result.length; i++) {
                  if (validation_result[i]['keyword'] == "additionalProperties"){
                      elem_path=validation_result[i].dataPath + '.' + validation_result[i].params.additionalProperty
                      if(elem_path[0]=='.'){
                          elem_path=elem_path.substring(1);
                      }
                      _.unset(form_data, elem_path);
                  }else{
                      validation_errors.push( validation_result[i]);
                  }                            
              }                        
          }

          if (validation_errors.length>0){   
              vm.schema_errors=validation_errors;    
              return false;
          }*/

          /*if (vm.form_errors=='' || vm.form_errors.length==0){
            alert("validation successful");
          }
          else{
            alert("validation failed");
            return;
          }*/
          /*if (!vm.project_sid){
              vm.removeEmpty(form_data);
              console.log(form_data);
          }*/

          axios.post(url, 
              form_data
              /*headers: {
                  "xname" : "value"
              }*/
          )
          .then(function (response) {
              console.log(response);
              vm.dataset_id=response.data.dataset.id;
              vm.dataset_idno=response.data.dataset.idno;
              alert("Your changes were saved");
          })
          .catch(function (error) {
              console.log(error);
              vm.schema_errors=error.response.data.errors;
              
              /*console.log(error.response.data);
              vm.dialog_box_option.title=error;
              vm.dialog_box_option.errors=error.response.data;
              $('#app_dialog').modal('show');
              */
          })
          .then(function () {
              // always executed
              console.log("request completed");
          });
      },
      /*validateData: function(data){                             
          if (this.schema_validator == undefined){
              alert("Schema validator not set");
              return true;
          }
          console.log("valdiation data ",data);
          
          if (!this.schema_validator(data)){
              return this.schema_validator.errors
          }

          return true;                    
      },*/
      /*initSchemaValidator: function(){
        //initialize schema validator
        var ajv = Ajv({
            allErrors : true
        });

        provenance_schema=<?php echo file_get_contents('application/schemas/provenance-schema.json');?>;        
        ajv.addSchema(provenance_schema,'http://ihsn.org/schemas/provenance-schema.json');

        if (this.dataset_type=='survey'){
            survey_schema=<?php echo file_get_contents('application/schemas/survey-schema.json');?>;
            ddi_schema=<?php echo file_get_contents('application/schemas/ddi-schema.json');?>;
            datafile_schema=<?php echo file_get_contents('application/schemas/datafile-schema.json');?>;  
            variable_schema=<?php echo file_get_contents('application/schemas/variable-schema.json');?>;  
            variable_group_schema=<?php echo file_get_contents('application/schemas/variable-group-schema.json');?>;    
            //ajv.addSchema(survey_schema,'survey-schema');
            ajv.addSchema(ddi_schema,'http://ihsn.org/schemas/ddi-schema.json');
            ajv.addSchema(datafile_schema,'http://ihsn.org/schemas/datafile-schema.json');
            ajv.addSchema(variable_schema,'http://ihsn.org/schemas/variable-schema.json');  
            ajv.addSchema(variable_group_schema,'http://ihsn.org/schemas/variable-group-schema.json');                    
            
            this.schema_validator= ajv.compile(survey_schema);            
        }
        else if (this.dataset_type=='image'){
            image_schema=<?php echo file_get_contents('application/schemas/image-schema.json');?>;
            iptc_schema=<?php echo file_get_contents('application/schemas/iptc-pmd-schema.json');?>;
            iptc_shared_schema=<?php echo file_get_contents('application/schemas/iptc-phovidmdshared-schema.json');?>;
            ajv.addSchema(iptc_schema,'iptc-pmd-schema.json');
            ajv.addSchema(iptc_shared_schema,'https://www.iptc.org/std/photometadata/specification/iptc-phovidmdshared-schema.json');
            this.schema_validator= ajv.compile(image_schema);
        }
        else{
            this.schema_validator= ajv.compile(this.metadata_schema);
        }
        window.validator_=this.schema_validator;
        window._formdata=this.formData; 
      }*/

    }
    })
  </script>
</body>
</html>
