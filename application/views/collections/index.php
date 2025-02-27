<!DOCTYPE html>
<html>

<head>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/bootstrap.min.css" rel="stylesheet" >
    <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">

    <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>

    <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/moment-with-locales.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/vue-i18n.js"></script>

    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

    <?php
        $user=$this->session->userdata('username');

        $user_info=[
            'username'=> $user,
            'is_logged_in'=> !empty($user),
            'is_admin'=> $this->ion_auth->is_admin(),
        ];
        
        ?>
    
    <style>
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
        'site_url': '<?php echo site_url(); ?>',
        'base_url': '<?php echo base_url(); ?>',
        'user_info': <?php echo json_encode($user_info); ?>
        };
    </script>

    <div id="app" data-app>

    <!-- 
        <vue-edit-collection v-model="dialog_edit" :collection="edit_collection" v-on:update-collection="updateCollection" vonremove-access="UnshareProjectWithUser"></vue-edit-collection>
    -->
        

        <div class="wrapper">
            <v-app>
            <?php //echo $this->load->view('editor_common/global-header', null, true); ?>
            <vue-global-site-header></vue-global-site-header>
            <router-view :key="$route.fullPath"></router-view>
            </v-app>
        </div>


    </div>

    <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/vue-router.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
    <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
  

    <script>
        <?php
            echo $this->load->view("collections/vue-tree-list-component.js", null, true);
            echo $this->load->view("collections/vue-collections-component.js", null, true);
            echo $this->load->view("collections/vue-edit-collection-component.js", null, true);
            echo $this->load->view("collections/vue-manage-users-component.js", null, true);
            echo $this->load->view("collections/vue-copy-collection-component.js", null, true);
            echo $this->load->view("collections/vue-move-collection-component.js", null, true);
            echo $this->load->view("editor_common/global-site-header-component.js", null, true);
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

        // 2. Define some routes
        // Each route should map to a component. The "component" can
        // either be an actual component constructor created via
        // `Vue.extend()`, or just a component options object.
        // We'll talk about nested routes later.
        const routes = [
            { path: '/edit/:id', component: EditCollection, name:"edit" },
            { path: '/manage-users/:id', component: ManageAccess, name:"manage-access" },
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
                    "primary-dark": '#0c1a4d',
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