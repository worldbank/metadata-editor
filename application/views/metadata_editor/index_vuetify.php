<!DOCTYPE html >
<html>
<head>
  <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
  <link href="<?php echo base_url();?>vue-app/assets/bootstrap.min.css" rel="stylesheet" >
  <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/popper.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
  
  <link href="<?php echo base_url();?>vue-app/assets/splitpanes.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
</head>

<?php
  //break template into smaller templates by spliting template ['items']
  $template_parts=array();
  
  //update template_parts
  //get_template_part($metadata_template_arr['items'],$template_parts);

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
  
  
  get_template_keys($metadata_template_arr['items'],$template_keys);
  function get_template_keys($items,&$output)
  {
    foreach($items as $item){
      if (isset($item['items'])){
        get_template_keys($item['items'],$output);
      }
      if (!isset($item['type'])){
        $item['type']='string';
      }
      if (isset($item['key']) && $item['type']!='section' ){
        $output[]=$item['key'];
      }
    }        
  }
  
?>

<body class="hold-transition sidebar-mini layout-fixed">


  <?php
      $user=$this->session->userdata('username');

      $user_info=[
        'username'=> $user,
        'is_logged_in'=> !empty($user),
        'is_admin'=> $this->ion_auth->is_admin(),
      ];
      
    ?>

    <script>
        var CI = {
          'site_url': '<?php echo site_url();?>',
          'base_url': '<?php echo site_url();?>',
          'base_asset_url': '<?php echo base_url();?>',
          'user_info': <?php echo json_encode($user_info); ?>
        }; 
        let sid='<?php echo $sid;?>';
        let form_template=<?php echo $metadata_template;?>;
        let form_template_parts= <?php echo json_encode($template_parts,JSON_PRETTY_PRINT); ?>;
    </script>

  <div id="app" data-app>
    <?php echo $this->load->view("metadata_editor/layout.php",null,true); ?>
  </div>

  <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-router.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuex.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
  <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-deepset.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/ajv.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/deepdash.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/moment-with-locales.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-i18n.js"></script>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />   
    
  <script src="<?php echo base_url(); ?>vue-app/assets/vue-scrollto.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vee-validate.full.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/splitpanes.umd.min.js"></script>
    
  <script src="<?php echo base_url(); ?>vue-app/assets/sortable.min.js"></script>
  <script src="<?php echo base_url(); ?>vue-app/assets/vuedraggable.umd.min.js"></script>

  <script src="<?php echo base_url(); ?>vue-app/assets/vue-json-pretty.min.js"></script>
  <link rel="stylesheet" href="<?php echo base_url(); ?>vue-app/assets/vue-json-pretty.min.css">
  <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">



  <?php echo $this->load->view("metadata_editor/index_vuetify_main_app",null,true);?>

  <script>
    
    const translation_messages = {
      default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
    }

    const i18n = new VueI18n({
      locale: 'default',
      messages: translation_messages,
      //show warnings in console
      silentTranslationWarn: false
    })

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

    const vuetify = new Vuetify({
            theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    "primary-dark": '#0c1a4d',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
            },
        })

    vue_app=new Vue({
      el: '#app',
      i18n,
      vuetify: vuetify,
      router:router,
      store,
      data:{          
          active_section:null,
          active_form_field:null,
          schema_validator: null,
          dataset_id:sid,
          dataset_idno:project_idno,
          dataset_type:project_type,
          //form_template: form_template,
          is_loading:false,
          is_dirty:false,//form data has been modified
          vuex_is_loaded:false,
          loading_status:null,
          form_errors:[],
          schema_errors:[],
          initiallyOpen: [],
          toggleTreeExpand: false,
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
            resource: 'mdi-folder-text',
            'file-manager':'mdi-folder-network',
          },
        tree: [],
        items: [],
        tree_active_items:[],
        tree_search:'',
        login_dialog:false,
        export_json_dialog:false,
        base_url:CI.base_url,
        show_fields_mandatory:false,
        show_fields_recommended:false,
        show_fields_empty:false,
        show_fields_nonempty:false,
        show_fields_validation_errors:false,
        project_types_icons: {
          "document": "mdi-file-document",
          "survey": "mdi-database", 
          "geospatial": "mdi-earth",
          "table": "mdi-table",
          "timeseries": "mdi-chart-line",
          "timeseries-db": "mdi-resistor-nodes",
          "image": "mdi-file-image",
          "video": "mdi-video",
          "script": "mdi-file-code",
          "resource": "mdi-file-link-outline",
          "admin_meta": "mdi-file-outline"
        },

        apply_defaults_dialog:false,
        apply_defaults_dialog_key:0
      },
      created: async function(){
        await this.$store.dispatch('initData',{dataset_id:this.dataset_id});
        await this.$store.dispatch('initTreeItems');
        this.init_tree_data();

        let vm=this;

        window.addEventListener('beforeunload', function(event) {
          return vm.onWindowUnload(event);
        });
      }
      ,
      mounted: function(){
        let vm=this;
        axios.interceptors.response.use(
          function(resp) {            
            return resp;
          },
          function(error) {
            if (error.response.status==401){
              vm.login_dialog=true;
            }
            return Promise.reject(error);
          }
        );
      },
      computed:{
        ProjectIsLoading(){
          return this.$store.state.project_isloading;
        },
        hideProjectSaveOnRoute(){
          return this.$route.path.startsWith("/datafile/") 
            || this.$route.path.startsWith("/external-resources/") ;
        },
        form_template(){
          return this.$store.state.formTemplate;
        },
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        UserHasEditAccess(){
          return this.$store.state.user_has_edit_access;
        },
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

            if (!title_){
              return "Untitled";
            }

            return title_;

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
        MetadataTypes(){
          return this.$store.state.metadata_types;
        },
        MetadataTypesTreeNodes(){
          
          let metadata_types=this.MetadataTypes;
          if (metadata_types.length==0){
            return [];
          }

          let metadata_types_nodes=[];

          i=0;
          for (let metadata_type of metadata_types) {
            if (!metadata_type.is_active){
              continue;
            }
            metadata_types_nodes.push(
              {
                title: metadata_type.name,
                type:'metadata-type',
                index:metadata_type.id,
                file: 'file',
                key:'metadata-types/'+metadata_type.id,
                metadata_type:  metadata_type
              }
            );
            i++;
          }
          console.log("metadata types nodes:",metadata_types_nodes);
          return metadata_types_nodes;
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
                    title:this.$t('variables'),
                    type: 'variables',
                    file: 'variable',
                    datafile: file,
                    key:'variables/'+file.file_id
                },
                {
                    title:this.$t('data'),
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
        },
        Items:function(){
          
          if (!this.tree_search || this.tree_search.length<1){
            return this.items; 
          }
            
          //keyword search
          let search_keywords=this.tree_search.toLowerCase().split(" ");
          let recursive_keyword_search=function(items){
            let filtered_items=[];
            for (let item of items){
                if (item.items){
                  let children=recursive_keyword_search(item.items);
                  if (children.length>0){
                    item.items=children;
                    filtered_items.push(item);
                  }
                }else{
                  
                  let item_title=item.title.toLowerCase();
                  let item_key=item.key.toLowerCase();
                  let item_help_text='';

                  if (item.help_text){
                    item_help_text=item.help_text.toLowerCase();
                  }
                  
                  let found=false;
                  for (let keyword of search_keywords){
                    if (item_title.includes(keyword) 
                        || item_key.includes(keyword) 
                        || item_help_text.includes(keyword) 
                      ){
                      found=true;                        
                    }else{
                      found=false;
                      break;
                    }
                  }

                  if (found){
                    filtered_items.push(item);
                  }

                }
            }
            return filtered_items;
          }

          return recursive_keyword_search(JSON.parse(JSON.stringify(this.items)));
        },
        GeospatialFeatures(){
          if (this.dataset_type!='geospatial'){
            return false;
          }

          let features=_.get(this.ProjectMetadata,'description.feature_catalogue.featureType');
          if (!features){
            return [];
          }

          let feature_list=[];
          for (let feature of features){
            feature_list.push({
              title:feature.typeName,
              type:'geospatial-feature',
              key:'feature/'+feature.typeName,
              file:'datafile',
              feature:feature,
              items:[{
                    title:this.$t('feature-attributes'),
                    type: 'feature-attribute',
                    file: 'variable',                    
                    key:'feature-attributes/'+feature.typeName,
                    feature:feature,
                },
                {
                    title:this.$t('data'),
                    type: 'feature-data',
                    file: 'table',
                    key:'feature-data'+feature.typeName
                }]
            });
          }

          return feature_list;
        }
      },      
      watch: {
        '$store.state.formTemplate': function() {
            this.init_tree_data();
        },
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
            handler(val, oldVal){
              

              if (JSON.stringify(oldVal) == '{}') {
                this.is_dirty=false;
                return;
              }
              /*else if (JSON.stringify(val) == JSON.stringify(oldVal)){
                this.is_dirty=false;
                return;
              }*/
              
              //this.saveProjectDebounce(val);              
                this.is_dirty=true;
            }
        }
      },
      methods:{
        templateApplyDefaults: function(){
            this.apply_defaults_dialog_key+=1;
            this.apply_defaults_dialog=true;
        },
        onLinkClick: function(link){
            window.open(link, '_blank');
        },
        onRouterLinkClick: function(link){
            router.push(link);
        },
        onWindowUnload: function(event){

          if (this.UserHasEditAccess==false){
            return null;
          }

          if (!this.is_dirty){
            return null;
          }

          let message=this.$t('unsaved_changes');

          event.returnValue = message;
          return message;
        },
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
            this.initiallyOpen.push("datasets");
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
        toggleTree: function()
        {
          this.toggleTreeExpand=!this.toggleTreeExpand;
          if (!this.toggleTreeExpand){
            this.initiallyOpen=[];
          }else{
            for(let item of this.items){
              this.initiallyOpen.push(item.key);
            }
          }
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
        //function to move item to the end of the array
        move_item_to_end: function(arr, item_key) {          
          let _index=_.findIndex(arr, {key: item_key});

          if (_index>=0){
            let _item=arr[_index];
            arr.splice(_index,1);
            arr.push(_item);
          }
        },
        init_tree_data: function() {
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
              title: this.$t('home'),
              type:'home',
              file: 'database',
              key: 'home',
              "items":[
                {
                title: 'Preview',
                type:'preview',
                file: 'txt',
                key: 'page-preview'
                }
              ]
            });

          if (this.dataset_type=='survey'){
            tree_data.push({
              title: this.$t('data-files'),
              type:'datafiles',
              file: 'database',
              key: 'datafiles',
              items:this.DataFilesTreeNodes
            });

            tree_data.push({
              title: this.$t('variable-groups'),
              type:'variable-groups',
              file: 'database',
              key: 'variable-groups',
              items:this.VariableGroupsTreeNodes
            });
          }
          
          tree_data.push({
              title: this.$t('external-resources'),
              type: 'resources',
              file: 'resource',
              key:'external-resources',
              items:this.ExternalResourcesTreeNodes
          });

          //move tags after datafiles
          this.move_item_to_end(tree_data, 'tags_container');

          //move dataCite at the end
          this.move_item_to_end(tree_data, 'datacite_container');

          //move provenance at the end
          this.move_item_to_end(tree_data, 'provenance_container');

          /*tree_data.push({
              title: this.$t('File manager'),
              type: 'files',
              file: 'file-manager',
              key:'files'
          });
          */

          if (this.dataset_type=='geospatial'){
            tree_data.push({
              title: this.$t('Geospatial features'),
              type: 'geospatial-features',
              file: 'database',
              key:'geospatial-features',
              items:this.GeospatialFeatures
            });

            tree_data.push({
              title: this.$t('Image Gallery'),
              type: 'geospatial-gallery',
              file: 'database',
              key:'geospatial-gallery'
            });

          }

          if (this.MetadataTypesTreeNodes.length>0){
            //metadata types
            tree_data.push({
                title: this.$t('Administrative metadata'),
                type: 'metadata-types',
                file: 'database',
                key:'metadata-types',
                items:this.MetadataTypesTreeNodes
            });
        }          

          this.items=tree_data;
          if (this.$route.path.startsWith("/datafile/")){
            this.initiallyOpen=["datafiles"];
            this.initiallyOpen.push(this.$route.path.substr(1,this.$route.path.length));
            this.setTreeActiveNode(this.$route.path);
          }
          else if (this.$route.path.startsWith("/variables/")){
            this.initiallyOpen=["datafiles"];
            this.initiallyOpen.push(this.$route.path.substr(1,this.$route.path.length));
            this.setTreeActiveNode(this.$route.path);
          }
          else if (this.$route.path.startsWith("/external-resources")){
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

            if (this.items[k]["key"]=="datafiles"){
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
          //console.log("clicked on",node_key);
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

          if (node.type=='datafiles'){
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

          if (node.type=='metadata-types'){
            router.push('/metadata-types');
            return;
          }

          if (node.type=='metadata-type'){
            router.push('/metadata-types/'+node.metadata_type.uid);
            return;
          }

          if (node.type=='files'){
            router.push('/files');
            return;
          }

          if (node.type=='resource'){
            router.push('/external-resources/'+node.index);
            return;
          }

          if (node.type=='feature-attribute'){
            router.push('/geospatial-feature/'+node.feature.typeName);
            return;
          }

          if (node.type=='preview'){
            router.push('/page-preview/');
            return;
          }

          if (node.type=='geospatial-gallery'){
            router.push('/geospatial-gallery');
            return;
          }

          router.push('/study/'+node.key);
        },
        cancelProject: function(){
          if (this.is_dirty){
            if (!confirm(this.$t('Do you want to discard changes?'))){
              return;
            }
          }          
          this.$store.dispatch('initData',{dataset_id:this.dataset_id}).then(()=>{
            this.is_dirty=false;
          });          
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
              vm.is_dirty=false;
          })
          .catch(function (error) {
              console.log("data-errors",error);
              vm.schema_errors=error.response.data.errors;

              let error_message='';
              if (error.response.data.message){
                error_message=error.response.data.message;
              }

              alert("Error saving project: " + error_message);
          });
      },
      removeEmpty: function (obj) {
          vm=this;
          try {
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
          }
      }
    }
    })

    Vue.component('VueJsonPretty', VueJsonPretty.default);
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
