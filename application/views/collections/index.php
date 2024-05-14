<!DOCTYPE html>
<html>

<head>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.x/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">

    <script src="https://adminlte.io/themes/v3/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.20/lodash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/moment@2.26.0/moment.js"></script>
    <script src="https://unpkg.com/vue-i18n@8"></script>

    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

    
    <style>
        <?php echo $this->load->view('metadata_editor/styles.css', null, true); ?>
        .capitalize {
            text-transform: capitalize;
        }

        .text-small {
            font-size: 0.8rem;
        }

        .btn-xs {
            padding: 0.25rem;
            font-size: 0.7rem;
            line-height: 1.4;
            border-radius: 0.2rem;
        }

        .v-tabs-bar{
            background-color: transparent!important;
            margin-bottom:50px;
        }
       
        .vue-tree-list .vue-tree-icon{
            padding-left: 20px;        
        }

        .item-level-1{
            
        }
        .item-level-2{
            margin-left: 22px;            
        }
        .item-level-3{
            margin-left: 40px;
            
        }
        .item-level-4{
            margin-left: 60px;
            
        }
        .item-level-5{
            margin-left: 80px;
            
        }
        .collection-item:hover{
            cursor:pointer;
            color: #526bc7;
        }
        .collection-row{
            padding-top: 15px;
            padding-bottom:5px;
        }

        .collection-leaf{
            padding-left:22px;
        }

        .v-card .primary {
            background-color: #526bc7 !important;
            border-color: #526bc7 !important;
        }
    </style>
</head>

<body class="layout-top-nav">

    <script>
        var CI = {
            'base_url': '<?php echo site_url(); ?>'
        };
    </script>

    <div id="app" data-app>

    <!-- 
        <vue-edit-collection v-model="dialog_edit" :collection="edit_collection" v-on:update-collection="updateCollection" vonremove-access="UnshareProjectWithUser"></vue-edit-collection>
    -->
        

        <div class="wrapper">
            <v-app>
            <?php echo $this->load->view('editor_common/global-header', null, true); ?>
            <router-view :key="$route.fullPath"></router-view>
            </v-app>
        </div>


    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
    <script src="<?php echo base_url(); ?>javascript/vue-router.min.js"></script>
    <script src="<?php echo base_url(); ?>javascript/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>


    <script>
        <?php
            echo $this->load->view("collections/vue-tree-list-component.js", null, true);
            echo $this->load->view("collections/vue-collections-component.js", null, true);
            echo $this->load->view("collections/vue-edit-collection-component.js", null, true);
            echo $this->load->view("collections/vue-manage-users-component.js", null, true);
        ?>


        // 1. Define route components.
        // These can be imported from other files
        const Home = {
            template: '<div><vue-collection/></div>'
        }

        const EditCollection = {
            template: '<div><vue-edit-collection v-on:update-access="updateAccess"/></div>'
        }

        const ManageAccess = {
            template: '<div><vue-manage-users/></div>'
        }

        const Foo = {
            template: '<div>foo</div>'
        }

        const Bar = {
            template: '<div>bar</div>'
        }

        // 2. Define some routes
        // Each route should map to a component. The "component" can
        // either be an actual component constructor created via
        // `Vue.extend()`, or just a component options object.
        // We'll talk about nested routes later.
        const routes = [
            { path: '/edit/:id', component: EditCollection, name:"edit" },
            { path: '/manage-users/:id', component: ManageAccess, name:"manage-access" },
            {
                path: '/foo',
                component: Foo
            },
            {
                path: '/bar',
                component: Bar
            },
            
            {
                path: '*',
                component: Home,
                name:"home"
            }
        ]

        // 3. Create the router instance and pass the `routes` option
        // You can pass in additional options here, but let's
        // keep it simple for now.
        const router = new VueRouter({
            routes // short for `routes: routes`
        })


        <?php 
        $translations="";
        ?>

        const translation_messages = {
        default: <?php echo json_encode($translations,JSON_HEX_APOS);?>
        }

        const i18n = new VueI18n({
        locale: 'default', // set locale
        messages: translation_messages, // set locale messages
        })

        const vuetify = new Vuetify({
            theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
            },
        })

        vue_app = new Vue({
            el: '#app',
            i18n: i18n,
            router: router,
            vuetify: vuetify,
            data: {                
            }
        })
    </script>
</body>

</html>