<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

$config['acl_system_roles'] = ['user','admin'];

$config['acl_debug'] = false;

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
    ]    
];


$config['acl_permissions'] = [
    'dashboard' => [
        'title' => 'Site dashboard',
        "permissions"=>[
            [
            'permission'=>'view',                
            'description'=>'View site administration dashboard'
            ],
            [
            'permission'=>'edit',
            'sub_permissions'=>['view'],
            'description'=>'Run analytics aggregation and dashboard maintenance'
            ]
        ]
    ],
    "editor"=>[ 
        "title" => "Editor",
        "description"=> "Alow users to manage own projects",
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
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete","publish"]
            ]
        ]
    ],
    "project_manager"=>[
        "title" => "Project manager",
        "description"=> "Global access to all projects",
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
                "permission" => "publish",
                "sub_permissions"=>["view"]
            ],
            [
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete","publish"]
            ]
        ]
    ],

    "template_manager"=>[ 
        "title" => "Template manager",
        "description"=> "Global access to all templates",
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
    
    "schema"=>[ 
        "title" => "Schema",
        "description"=> "Allow user to manage schemas",
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

    "tag"=>[ 
        "title" => "Tag",
        "description"=> "Allow user to manage tags",
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

    "codelist"=>[
        "title" => "Codelists",
        "description"=> "Manage the site-wide codelist registry",
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
                "permission" => "import",
                "sub_permissions"=>["edit","view"]
            ],
            [
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete","import"]
            ]
        ]
    ],

    "data_structure"=>[
        "title" => "Data structures",
        "description"=> "Manage the site-wide DSD / data structure catalogue",
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
                "permission" => "import",
                "sub_permissions"=>["edit","view"]
            ],
            [
                "permission" => "admin",
                "sub_permissions"=>["view","edit","delete","import"]
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
    ]
];

