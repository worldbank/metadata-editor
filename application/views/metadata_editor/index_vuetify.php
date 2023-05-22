<!DOCTYPE html>
<html>
<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
  
  <!--
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  
  <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
-->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

<link href="<?php echo base_url();?>javascript/splitpanes.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<?php
  //break template into smaller templates by spliting template ['items']
  $template_parts=array();
  
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
    <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />   
    
    <script src="https://cdn.jsdelivr.net/npm/vue-scrollto"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vee-validate/3.4.0/vee-validate.full.min.js" integrity="sha512-owFJQZWs4l22X0UAN9WRfdJrN+VyAZozkxlNtVtd9f/dGd42nkS+4IBMbmVHdmTd+t6hFXEVm65ByOxezV/Qxg==" crossorigin="anonymous"></script>

    <!-- <script src="https://cdn.jsdelivr.net/npm/vue-textarea-autosize@1.1.1/dist/vue-textarea-autosize.umd.js"></script> -->

    <!--
    <script src="https://unpkg.com/splitpanes@2.1"></script>
    <link href="https://unpkg.com/splitpanes/dist/splitpanes.css" rel="stylesheet">
  -->

    <script src="<?php echo base_url();?>javascript/splitpanes.umd.min.js"></script>
    




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
        tree_active_items:[],
        login_dialog:false,
        show_fields_mandatory:false,
        show_fields_recommended:false,
        show_fields_empty:false,
        show_fields_nonempty:false,
        show_fields_validation_errors:false,
        project_types_icons: {
          "document": "fas fa-file-alt",
          "survey": "fa fa-database",
          "geospatial": "fa fa-globe-americas",
          "table": "fa fa-database",
          "timeseries": "fa fa-chart-line",
          "timeseries-db": "fas fa-project-diagram",
          "image": "fa fa-image",
          "video": "fa fa-video",
          "script": "fa fa-file-code"
        }
      },
      created: async function(){
        await this.$store.dispatch('initData',{dataset_id:this.dataset_id});
        await this.$store.dispatch('initTreeItems');
        this.init_tree_data();

        /*let node_path=this.getNodeKeyFromPath(this.$route.path);

        if (node_path){        
          store.commit('tree_active_node_path',node_path);
        }*/
      }
      ,
      mounted: function(){
        let vm=this;
        axios.interceptors.response.use(
          function(resp) {
            return resp;
          },
          function(error) {
            if (error.response.status==403){
              vm.login_dialog=true;
            }
            return Promise.reject(error);
          }
        );
      },
      computed:{
        Title(){          
          let titles={
            "survey":"study_desc.title_statement.title",
            "timeseries":"series_description.name",
            "timeseries-db":"database_description.title_statement.title",
            "script":"project_desc.title_statement.title",
            "video":"video_description.title",
            "table":"table_description.title_statement.title",
            "document":"document_description.title_statement.title",
            "image":"image_description.dcmi.title",
            "geospatial":"description.identificationInfo.citation.title"
          };

          //image IPTC?
          if (this.dataset_type=='image'){
            let iptc_title=_.get(this.ProjectMetadata, 'image_description.iptc.photoVideoMetadataIPTC.title')
            if (iptc_title){
              titles['image']='image_description.iptc.photoVideoMetadataIPTC.title';
            }
          }

          if (titles[this.dataset_type]){
            title_= _.get(this.ProjectMetadata, titles[this.dataset_type]);
            return _.truncate(title_, {
              'length': 60,
              'separator': ' '
            });
          }else{
            return 'TODO';
          }

        },
        StudyIDNO(){          
          let idnos={
            "survey":"study_desc.title_statement.idno",
            "script":"project_desc.title_statement.idno",
            "timeseries-db":"database_description.title_statement.idno",
            "timeseries":"series_description.idno",
            "video":"video_description.idno",
            "table":"table_description.title_statement.idno",
            "document":"document_description.title_statement.idno",
            "image":"image_description.idno",
            "geospatial":"description.idno",
          };

          if (idnos[this.dataset_type]){
            idno_= _.get(this.ProjectMetadata, idnos[this.dataset_type]);
            return idno_;
          }else{
            return 'TODO';
          }
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
                title: file.file_name,
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
        VariableGroupsTreeNodes(){
          return [];          
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
        },
        TreeItems:
        {
            get(){
                return this.$store.state.treeItems;
            },
            set(val){
              return this.$store.state.treeItems=val;
            }
        }
        
      },      
      watch: {
        '$store.state.data_files': function() {
            this.update_tree();
        },
        '$store.state.external_resources': function() {
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
        getNodeKeyFromPath: function(path)
        {
          path=path.substr(0,1)=="/" ? path.substr(1,path.length) : path;
          let path_arr=path.split("/");

          if (path_arr.length>1 && path_arr[0]=='study'){
            return path_arr[1];
          }else{
            return '';
          }          
        },
        getTreeNestedPath: function(arr,name)
        {
            let vm=this;
            for(let item of arr){
                if(item.key===name) return `/${name}`;
                if(item.items) {
                    const child = vm.getTreeNestedPath(item.items, name);
                    if(child) return `/${item.key}${child}`
                }
            }
        },
        setTreeActiveNode: function(path)
        {
          this.tree_active_items=[];
          this.tree_active_items.push(path);
          let path_arr=path.split("/");

          //expand datafile
          if(path_arr[0]=='variables'){
            this.initiallyOpen.push("datafile/"+path_arr[1]);
          }

          if (path==""){
            this.tree_active_items.push("home");
          }else{          
            this.initiallyOpen.push(path);
          }          
        },
        filter_tree_items: function(items)
        {
          if (items.items){
            items=this.filter_tree_items(items);
          }

          return items.filter(item => item.is_custom!==true);
        },
        filter_empty_items: function(items)
        {
          if (items.items){
            items=this.filter_empty_items(items);
          }

          return items.filter(item => item.type=='section' || item.key=='doc_description');
        },
        filterRecursiveSearch: function(items,filter_type='')
        {
          let vm=this;
          let filtered_items=[];
          for (let item of items){

            if (item.items){              
                let filtered_children=vm.filterRecursiveSearch(item.items,filter_type);

                if (item.is_custom){
                  continue;              
                }

                if (filtered_children.length>0){
                  item.items=filtered_children;
                  filtered_items.push(item);
                }
            }else{
                if(!item.is_custom){
                    //show mandatory fields only
                    if (filter_type=='mandatory' && this.show_fields_mandatory==true && item.is_required){
                      filtered_items.push(item);
                    }

                    //show recommended fields only [recommended + mandatory]
                    else if (filter_type=='recommended' && this.show_fields_recommended==true && (item.is_recommended || item.is_required)){
                      filtered_items.push(item);
                    }

                    //show empty fields only
                    else if (filter_type=='empty' && this.show_fields_empty==true){
                      let field_value=this.getFieldValueByPath(item.key);
                      if (_.isEmpty(field_value)){
                        filtered_items.push(item);
                      }
                    }
                    else if (filter_type=='nonempty' && this.show_fields_nonempty==true){
                      let field_value=this.getFieldValueByPath(item.key);
                      if (field_value){
                        filtered_items.push(item);
                      }
                    }

                    else if (filter_type==''){
                      filtered_items.push(item);
                    }
                }
            }
          }

          return filtered_items;
        },
        getFieldValueByPath: function(path)
        {
          return _.get(this.ProjectMetadata,path);
        },
        toggleFields: function(field_type)
        {
          if (field_type=='mandatory'){
            this.show_fields_mandatory=!this.show_fields_mandatory;
          }
          if (field_type=='recommended'){
            this.show_fields_recommended=!this.show_fields_recommended;
          }
          if (field_type=='empty'){
            this.show_fields_empty=!this.show_fields_empty;
            this.show_fields_nonempty=false;
          }
          if (field_type=='nonempty'){
            this.show_fields_nonempty=!this.show_fields_nonempty;
            this.show_fields_empty=false;
          }
          this.init_tree_data();
          router.push('/');
        },
        cloneObject: function(obj)
        {
          return JSON.parse(JSON.stringify(obj));
        },
        init_tree_data: function() {
          this.is_loading=true;
          this.items=[];
          let tree_data=this.filterRecursiveSearch(this.cloneObject(this.form_template.template.items),'');

          if (this.show_fields_recommended){
            tree_data=this.filterRecursiveSearch(tree_data,'recommended');
          }
          if (this.show_fields_mandatory && this.show_fields_recommended==false){
            tree_data=this.filterRecursiveSearch(tree_data,'mandatory');
          }
          
          if (this.show_fields_empty){
            tree_data=this.filterRecursiveSearch(tree_data,'empty');
          }
          if (this.show_fields_nonempty){
            tree_data=this.filterRecursiveSearch(tree_data,'nonempty');
          }

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

            tree_data.push({
              title: 'Variable Groups',
              type:'variable-groups',
              file: 'database',
              key: 'variable-groups',
              items:this.VariableGroupsTreeNodes
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

          if (this.$route.path.startsWith("/external-resources")){
            this.initiallyOpen=["external-resources"];
            this.setTreeActiveNode("external-resources");
          }
          else{
            let active_node_name=this.$route.path;
            active_node_name=active_node_name.slice(active_node_name.lastIndexOf("/")+1);

            if (active_node_name){
              let node_paths= this.getTreeNestedPath(this.items,active_node_name);
              
              if (node_paths){
                this.initiallyOpen=node_paths.split("/");
                this.setTreeActiveNode(active_node_name);
              }
            }
          }
          
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

            if (this.items[k]["key"]=="external-resources"){
              this.items[k]["items"]=this.ExternalResourcesTreeNodes;
            }
            
          }
        },        
        templateToTree: function (){
          window.template=this.form_template;
        },
        treeOnUpdate: function(node_key)
        {
          console.log("clicked on",node_key);
        },
        treeClick: function (node){
          store.commit('tree_active_node_data',node);

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

          if (node.type=='variable-groups'){
            router.push('/variable-groups');
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

          router.push('/study/'+node.key);
            /*router.push('/study/' +node.key,{
                name: 'study',
                params: {
                    element_id: 'hello there' // or anything you want
                }
            });*/ 
        },
        saveProjectDebounce: _.debounce(function(data) {
            this.saveProject(data);
        }, 500),
        saveProject: function(){
          vm=this;          
          let url=CI.base_url + '/api/editor/update/'+vm.dataset_type+'/' + vm.dataset_id;
          
          form_data=JSON.parse(JSON.stringify(vm.ProjectMetadata));
          this.$refs.form.validateWithInfo().then(({ isValid, errors, $refs })=> {
              console.log("validation errors",errors);
              vm.form_errors=Object.values(errors).flat();
              //if (vm.form_errors=='' || vm.form_errors.length==0){
              //}                    
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
          )
          .then(function (response) {
              vm.schema_errors=[];
          })
          .catch(function (error) {
              console.log("data-errors",error);
              vm.schema_errors=error.response.data.errors;
          });
      },
      removeEmpty: function (obj) {
          vm=this;
          try {
            //console.log("removeempty object type is ",typeof(obj), obj);
            if (typeof(obj) == "string") { return; }

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
      }
    }
    })
  </script>

  <script>
    function resize_variable_list(){
        $(".variable-list-component").height($(".pane-main-content").height()-45)
      }

    $(document).ready(function(){
      jQuery(window).resize(function() {
        resize_variable_list();
      });

      resize_variable_list();
    });
  </script>
</body>
</html>
