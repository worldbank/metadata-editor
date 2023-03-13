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
    "menu"=>[ 
        "title" => "Site menu pages",
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
    "citation"=>[ 
        "title" => "Citations",
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
    "licensed_request"=>[ 
        "title" => "Licensed requests",
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
        "title" => "Manage collections",
        "description" => "Allows access to create, view, edit and delete collections",
        "permissions"=>[
            [
                "permission" => "view",
                "description" => "Browse and search collections"
            ],
            [
                "permission" => "create",
                "description" => "Create a new collection"
            ],
            [
                "permission" => "edit",
                "description" => "Edit collection information"
            ],
            [
                "permission" => "delete",
                "description" => "Delete a collection"
            ],
            [
                "permission" => "publish",
                "description" => "Publish or unpublish a collection"
            ]
        ]
    ], 
    "study"=>[ 
        "title" => "Manage studies",
        "description"=> "Global access to studies in all collections. For restricting access by collection, see <i>'Permissions by collection'</i> section below",
        "permissions"=>[
            [
                "permission" => "view"
            ],
            [
                "permission" => "create",
                "description" => "Allows creating new studies including importing from DDI"
            ],
            [
                "permission" => "edit",
                "description" => "Edit study options, file uploads and external resources"
            ],
            [
                "permission" => "delete",
                "description" => "Delete a study"
            ],
            [
                "permission" => "publish",
                "description" => "Publish or unpublish a study"
            ]
        ]
    ],    
    "reports"=>[ 
        "title" => "Reports",
        "description"=> "Reports",
        "permissions"=>[
            [
                "permission" => "view"
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
    "vocabularies"=>[ 
        "title" => "Vocabularies",
        "description"=> "Manage vocabularies and terms",
        "permissions"=>[
            [
                "permission" => "edit"
            ]
        ]
    ],
    "countries"=>[ 
        "title" => "Countries configurations",
        "description"=> "Manage countries list",
        "permissions"=>[
            [
                "permission" => "edit"
            ]
        ]
    ],
    "regions"=>[ 
        "title" => "Regions",
        "description"=> "Manage regions",
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
$config['acl_permissions_collections'] = ['study','licensed_request'];

