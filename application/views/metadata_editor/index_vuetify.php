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
  //break template into smaller templates by spliting template ['items']
  $template_parts=array();
  //$metadata_template_arr=json_decode($metadata_template,true);
  
  //update template_parts
  get_template_part($metadata_template_arr['items'],$template_parts);

  function get_template_part($items,&$output)
  {
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

  <div id="app" data-app>
    <?php echo $this->load->view("metadata_editor/layout.php",null,true); ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
  <!--
  <script src="https://unpkg.com/vue-router@3"></script>
  <script src="https://unpkg.com/vuex@3.4.0/dist/vuex.js"></script>
-->

  <script src="<?php echo base_url();?>javascript/vue-router.min.js"></script>
  <script src="<?php echo base_url();?>javascript/vuex.min.js"></script>
  <script src="<?php echo base_url();?>javascript/axios.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
    <!--<script src="https://unpkg.com/axios/dist/axios.min.js"></script>-->
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/vue-deepset@0.6.3/vue-deepset.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ajv/6.12.2/ajv.bundle.js" integrity="sha256-u9xr+ZJ5hmZtcwoxwW8oqA5+MIkBpIp3M2a4AgRNH1o=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/deepdash/browser/deepdash.standalone.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />   
    
    <script src="https://cdn.jsdelivr.net/npm/vue-scrollto"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vee-validate/3.4.0/vee-validate.full.min.js" integrity="sha512-owFJQZWs4l22X0UAN9WRfdJrN+VyAZozkxlNtVtd9f/dGd42nkS+4IBMbmVHdmTd+t6hFXEVm65ByOxezV/Qxg==" crossorigin="anonymous"></script>

    <!--
    <script src="https://unpkg.com/splitpanes@2.1"></script>
    <link href="https://unpkg.com/splitpanes/dist/splitpanes.css" rel="stylesheet">
  -->

    <script src="<?php echo base_url();?>javascript/splitpanes.umd.min.js"></script>
    <link href="<?php echo base_url();?>javascript/splitpanes.css" rel="stylesheet">




<!-- CDNJS :: Sortable (https://cdnjs.com/) -->
<script src="//cdn.jsdelivr.net/npm/sortablejs@1.8.4/Sortable.min.js"></script>
<!-- CDNJS :: Vue.Draggable (https://cdnjs.com/) -->
<script src="//cdnjs.cloudflare.com/ajax/libs/Vue.Draggable/2.20.0/vuedraggable.umd.min.js"></script>



  <?php echo $this->load->view("metadata_editor/index_vuetify_main_app",null,true);?>

  
  <script>
    
//    const { Splitpanes, Pane } = splitpanes;


/*
Vue.config.errorHandler = (err, vm, info) => {
  console.log("error handler",err,vm,info);
};
*/

    Vue.filter('truncate', function (text, stop, clamp) {
        return text.slice(0, stop) + (stop < text.length ? clamp || '...' : '')
    });

    Vue.filter('kb', val => {
      return Math.floor(val/1024);  
    });

    Vue.filter('mb', val => {
      //return Math.floor(val/1024);  
      return (val / (1024*1024)).toFixed(2);
    });

    Vue.filter('kbmb', val => {
      if (val<1024*1024){
        return Math.floor(val/1024) + ' KB';  
      }

      return (val / (1024*1024)).toFixed(2) + ' MB';
    });

    vue_app=new Vue({
      el: '#app',
  //    components:{Splitpanes, Pane},
      vuetify: new Vuetify(),
      router:router,
      store,
      data:{          
          active_section:null,
          active_form_field:null,
          schema_validator: null,
          dataset_id:sid,
          dataset_idno:project_idno,
          dataset_type:project_type,
          form_template: form_template,
          metadata_schema: metadata_schema,
          is_loading:false,
          vuex_is_loaded:false,
          loading_status:null,
          /*dialog_box_option:{
              'title': '',
              'content': '',
              'erorrs': {}                    
          },*/
          form_errors:[],
          schema_errors:[],
          initiallyOpen: [],
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
            database:'mdi-folder-table',
            table:'mdi-table',
            datafile:'mdi-database',
            variable:'mdi-file-table-outline',
            resource: 'mdi-folder-multiple'
          },
        tree: [],
        items: [],
        tree_active_items:[]
      },
      created: async function(){
       await this.$store.dispatch('initData',{dataset_id:this.dataset_id});
       this.init_tree_data();
      }
      ,
      mounted: function(){
      },
      computed:{
        Title(){
          //return 'title';//this.ProjectMetadata._study_info.title;
          if (this.dataset_type=='survey'){
            //return 'TODO';
            title_= _.get(this.ProjectMetadata, 'study_desc.title_statement.title');
            return _.truncate(title_, {
              'length': 60,
              'separator': ' '
            });
            //return this.ProjectMetadata.study_desc.title_statement.title;
          }else{
            return 'TODO';
          }
        },
        StudyIDNO(){
          //return this.dataset_idno;
          if (this.dataset_type=='survey'){
            return _.get(this.ProjectMetadata, 'study_desc.title_statement.idno'); 
          }

          return this.dataset_idno;
          
        },
        ProjectMetadata(){
          return this.$store.state.formData;
        },
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        DataFiles(){
          return this.$store.state.data_files;
        },
        DataFilesTreeNodes(){
          if (this.DataFiles.length==0){
            return [];
          }

          let datafiles_nodes=[];

          i=0;
          for (let file of this.DataFiles) {
            datafiles_nodes.push(
              {
                title: file.file_name, //file.file_id + ' - ' + file.file_name, //+ ' [' + file.file_id + ']',
                type:'datafile',
                index:i,
                key:'datafile/'+file.file_id,
                file: 'datafile',
                datafile:  file,
                items:[{
                    title:'Variables',
                    type: 'variables',
                    file: 'variable',
                    datafile: file,
                    key:'variables/'+file.file_id
                },
                {
                    title:'Data',
                    type: 'variable_data',
                    file: 'table',
                    datafile: file,
                    key:'d'+i
                }]
              }
            );
            i++;
          }
          console.log("data file nodes:",datafiles_nodes);
          return datafiles_nodes;
          

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
                index:resource.id,
                file: 'file',
                key:'resource-'+resource.id,
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
            this.update_tree();
        },
        $route(to, from) {
          console.log("route changed to", to, from);
          this.setTreeActiveNode(to.path);
        },
        ProjectMetadata: 
        {
            deep:true,
            handler(val){
              this.saveProjectDebounce(val);
            }
        }
      },
      methods:{
        setTreeActiveNode: function(path)
        {
          this.tree_active_items=[];
          this.tree_active_items.push(path.substring(1));

          let path_arr=path.substring(1).split("/");

          //expand datafile
          if(path_arr[0]=='variables'){
            this.initiallyOpen.push("datafile/"+path_arr[1]);
          }

          if (path.substring(1)==""){
            this.tree_active_items.push("home");
          }else{          
            this.initiallyOpen.push(path.substring(1));
          }
        },
        filter_tree_items: function(items)
        {
          if (items.items){
            items=this.filter_tree_items(items);
          }

          return items.filter(item => item.is_custom!==true);
        },
        init_tree_data: function() {
          this.is_loading=true;
          this.items=[];
          let tree_data=this.filter_tree_items(this.form_template.template.items);

          tree_data.unshift({
              title: 'Home',
              type:'home',
              file: 'database',
              key: 'home'              
            });

          if (this.dataset_type=='survey'){
            tree_data.push({
              title: 'Data files',
              type:'datasets',
              file: 'database',
              key: 'datasets',
              items:this.DataFilesTreeNodes
            });
          }

          tree_data.push({
              title: 'External resources',
              type: 'resources',
              file: 'resource',
              key:'external-resources',
              items:this.ExternalResourcesTreeNodes
          });

          this.items=tree_data;
          this.initiallyOpen= ["study_description","datasets","document"];
          this.setTreeActiveNode(this.$route.path);
          
          //set active tree node
          //this.tree_active_items=["datafile/F22"];
          console.log('tree_data',tree_data);
        },
        update_tree: function()
        {
          if (this.items.length<1){return;}

          k=0;
          for(k=0;k<=this.items.length;k++){            
            
            if (!this.items[k]){
              continue;
            }

            if (this.items[k]["title"]=="Data files"){
              this.items[k]["items"]=this.DataFilesTreeNodes
            }
          }
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
          console.log("treeClick",node);

          //expand tree node          
          this.initiallyOpen.push(node.key);

          if (node.type=='home'){
            router.push('/');
            return;
          }

          if (node.type=='datafile'){
            router.push('/datafile/'+node.datafile.file_id);
            return;
          }

          if (node.type=='datasets'){
            router.push('/datafiles');
            return;
          }

          if (node.type=='variables'){
            router.push('/variables/'+ node.datafile.file_id);
            return;
          }

          if (node.type=='variable_data'){
            router.push('/data-explorer/'+ node.datafile.file_id);
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
        saveProjectDebounce: _.debounce(function(data) {
            this.saveProject(data);
        }, 500),
        saveProject: function(){
          vm=this;          
          let url=CI.base_url + '/api/editor/update/'+vm.dataset_type+'/' + vm.dataset_id;
          
          form_data=JSON.parse(JSON.stringify(vm.ProjectMetadata));
          this.$refs.form.validateWithInfo().then(({ isValid, errors, $refs })=> {
              vm.form_errors=Object.values(errors).flat();                                                
              if (vm.form_errors=='' || vm.form_errors.length==0){
                  //vm.saveForm();
                  console.log("save form",form_data);
              }                    
          });
                  
          vm.removeEmpty(form_data);

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
              vm.schema_errors=[];
              //alert("Your changes were saved");
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
      removeEmpty: function (obj) {
          vm=this;
          try {
          $.each(obj, function(key, value){
              if (value === "" || value === null || ($.isArray(value) && value.length === 0) ){
                  delete obj[key];
              } else if (JSON.stringify(value) == '[{}]' || JSON.stringify(value) == '[[]]'){
                  delete obj[key];
              } else if (Object.prototype.toString.call(value) === '[object Object]') {
                  vm.removeEmpty(value);
              } else if ($.isArray(value)) {
                  $.each(value, function (k,v) { vm.removeEmpty(v); });
              }
          });
          }catch (error) {
            console.error(error);
            // expected output: ReferenceError: nonExistentFunction is not defined
            // Note - error messages will vary depending on browser
          }
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
