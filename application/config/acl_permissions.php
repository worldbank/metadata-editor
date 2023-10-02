<?php if (!defined('BASEPATH')) exit('No direct script access allowed');


$config['acl_system_roles'] = ['user','admin'];



//$acl->allow('lsms_collection_reviewer', 'lsms', array('unpublish','publish','view'));
//$acl->allow('admin');

//give full access to admin to everything
$config['acl_system_role_permissions'] = [
    'user'=>[
        'role'=>'user',
        'resource'=>'', //no access to any resource
        'permissions'=>'' //no permissions
    ],
    'admin'=>[
        'role'=>'admin',
        'resource'=>null, //full access to all resources
        'permissions'=>null //allowed all permissions
    ],
    
];



$config['acl_permissions'] = [
    'dashboard' => [
        'title' => 'Site dashboard',
        "permissions"=>[
            [
            'permission'=>'view',                
            'description'=>'View site administration dashboard'
            ]
        ]
    ],
    "editor"=>[ 
        "title" => "Editor",
        "description"=> "Allow user to manage projects",
        "permissions"=>[
            [
                "permission" => "view"
            ],
            [
                "permission" => "edit"
            ],
            [
                "permission" => "delete"
            ],
            [
                "permission" => "publish"
            ],
            [
                "permission" => "admin"
            ]
        ]
    ],

    "template_manager"=>[ 
        "title" => "Template manager",
        "description"=> "Allow user to manage templates",
        "permissions"=>[
            [
                "permission" => "view"
            ],
            [
                "permission" => "edit",
                "sub_permissions"=>["view"]
            ],
            [
                "permission" => "delete"
            ],
            [
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete", "duplicate"]
            ],            
            [
                "permission" => "duplicate"
            ]
        ]
    ],
   
    "user"=>[ 
        "title" => "Users",
        "permissions"=>[
            [
                "permission" => "view"
            ],            
            [
                "permission" => "create"
            ],
            [
                "permission" => "edit"
            ],
            [
                "permission" => "delete"
            ]
        ]
    ],

    "collection"=>[ 
        "title" => "Collections",
        "permissions"=>[
            [
                "permission" => "view"
            ],            
            [
                "permission" => "edit",
                "sub_permissions"=>["view"]
            ],
            [
                "permission" => "delete",
                "sub_permissions"=>["view"]
            ],
            [
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete"]
            ]
        ]
    ],
    
   
    
    "configurations"=>[ 
        "title" => "Site configurations",
        "description"=> "Manage site configurations",
        "permissions"=>[
            [
                "permission" => "edit"
            ]
        ]
    ],
    
    "translate"=>[ 
        "title" => "Site translations",
        "description"=> "Manage translations",
        "permissions"=>[
            [
                "permission" => "edit"
            ]
        ]
    ],

];

//permissions by collections
//$config['acl_permissions_collections'] = ['study','licensed_request'];

