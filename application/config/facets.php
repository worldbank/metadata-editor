<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Configurations for Facets
|--------------------------------------------------------------------------
|
| 
|
*/


$config['facets'] = [
    'keyword'=>[
        'title'=> 'Keywords',
        'icon'=>'use fontawesome-name',
        'enabled'=>false,
        'mappings'=>[
            'survey'=>[
                'path'=>'study_desc.study_info.keywords',
                'column'=>'keyword' //column for composite fields
            ],
            'image'=>[
                'path'=>'image_description.iptc.photoVideoMetadataIPTC.keywords',
            ]
        ]
    ],
    'language'=>[
        'title'=> 'Languages',
        'icon'=>'use fontawesome-name',
        'enabled'=>true,
        'include_empty_value'=>true,
        'mappings'=>[
            'document'=>[
                'path'=>'document_description.languages',
                'column'=>'name' //column for composite fields
            ]
        ]
    ],

    
    'topic'=>[
        'title'=> 'topics',
        'enabled'=>false,
        'mappings'=>[
            'survey'=>[
                'path'=>'study_desc.study_info.topics',
                'column'=>'topic'
            ]
        ]
    ],
    'datakind'=>[
        'title'=> 'Data kind',
        'enabled'=>true,
        'mappings'=>[
            'survey'=>[
                'path'=>'study_desc.study_info.data_kind',                
            ]
        ]        
    ],
    'producer'=>[
        'title'=> 'Producers',
        'enabled'=>true,
        'mappings'=>[
            'survey'=>[
                'path'=>'study_desc.production_statement.producers',
                'column'=>'name'
            ]
        ]        
    ],
    'contact'=>[
        'title'=> 'Contacts',
        'enabled'=>true,
        'mappings'=>[
            'survey'=>[
                'path'=>'study_desc.distribution_statement.contact',
                'column'=>'name'
            ],
            'geospatial'=>[
                'path'=>'dataset_description.contact',
                'column'=>'person_name'
            ]
        ]        
    ],
    'doctype'=>[
        'title'=> 'Document type',
        'enabled'=>true,
        'mappings'=>[
            'document'=>[
                'path'=>'document_description.type'
            ]
        ]        
    ],
    'dgsrctype'=>[
        'title'=> 'Digital source type',
        'enabled'=>true
    ],
];
