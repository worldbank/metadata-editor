<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


use Spatie\ArrayToXml\ArrayToXml;

class MsdWriter21
{

    private $ci;
    private $schema_items=[];


    function __construct()
	{
		log_message('debug', "MsdWriter21 Class Initialized.");
		$this->ci =& get_instance();		
        $this->ci->load->model("Editor_template_model");
	}


    function build_msd($data)
    {
        //root element
        $rootElement = $this->get_root_element();

        $array=[];

        //header 
        $array['message:Header']= $this->get_header_element($data);

        //structures
        $array['message:Structures']=[
            //'message:AgencySchemes' => [],
            'structure:Concepts' => [],
        ];

        //agency schemes
        //$array['message:Structures']['message:AgencySchemes']['message:AgencyScheme'] = $this->get_agency_schemes_element($data);
       

        //concept schemes
        $array['message:Structures']['structure:Concepts']['structure:ConceptScheme'] = [
            '_attributes' => [
                //'urn' => 'urn:sdmx:org.sdmx.infomodel.conceptscheme.ConceptScheme=WB:IND_META_CONCEPTS(1.0)',
                //'structureURL' => 'https://registry.sdmx.org/ws/public/sdmxapi/rest/conceptscheme/WB/IND_META_CONCEPTS/1.0',
                //'isExternalReference' => 'false',
                'agencyID' => 'WB',
                'id' => 'IND_META_CONCEPTS',
                'version' => '1.0'                            
            ],
            'common:Name' =>  [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => 'Indicators Metadata Concepts'
            ],
            'common:Description' =>  [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => 'Metadata concepts for Indicators'
            ]            
        ];

        //concepts
        $concepts=[];
       foreach ($data as $key=>$concept){
            $concept['key'] = str_replace("/", "__", $key);
            $concepts[] = $this->get_concept_element($concept);
        }

        $array['message:Structures']['structure:Concepts']['structure:ConceptScheme']['structure:Concept'] = $concepts;

        //metadatastructures
        $metadataAttributes=[];
        foreach ($data as $key=>$concept){
            $concept['key'] = str_replace("/", "__", $key);
            $metadataAttributes[] = $this->get_metadata_attribute($concept);  
        }

        $array['message:Structures']['structure:MetadataStructures'] = [
            'structure:MetadataStructure' => [
                '_attributes' => [
                    //'urn' => 'urn:sdmx:org.sdmx.infomodel.metadatastructure.MetadataStructure=WB:IND_MSD(1.0)',
                    //'structureURL' => 'https://registry.sdmx.org/ws/public/sdmxapi/rest/metadatastructure/WB/MSD/1.0',
                    //'isExternalReference' => 'false',
                    'agencyID' => 'WB',
                    'id' => 'IND_MSD',
                    'version' => '1.0'                            
                ],
                'common:Name' =>  [
                    '_attributes' => ['xml:lang' => 'en'],
                    '_value' => 'Indicators Metadata Structure Definition'
                ],
                'common:Description' =>  [
                    '_attributes' => ['xml:lang' => 'en'],
                    '_value' => 'Metadata structure Definition for Indicators'
                ],
                'structure:MetadataStructureComponents' => [
                ]                
            ]
        ];

        /*
        <structure:MetadataTarget id="FULL_ISMS_TOPMETA">
            <structure:IdentifiableObjectTarget id="DATAFLOW" objectType="Dataflow">
            <structure:LocalRepresentation>
            <structure:Enumeration>
            <Ref id="CS_MSD_TOPMETA" version="1.0" agencyID="IT1" package="conceptscheme" class="ConceptScheme"/>
            </structure:Enumeration>
            </structure:LocalRepresentation>
            </structure:IdentifiableObjectTarget>
        </structure:MetadataTarget>
        */

        //MetadataTarget
        $array['message:Structures']['structure:MetadataStructures']['structure:MetadataStructure']['structure:MetadataStructureComponents']['structure:MetadataTarget'] = [
            '_attributes' => [
                'id' => 'IND_METADATA_TARGET_ID'
            ],
            'structure:IdentifiableObjectTarget' => [
                '_attributes' => [
                    'id' => 'DATAFLOW',
                    'objectType' => 'Dataflow'
                ],
                'structure:LocalRepresentation' => [
                    'structure:Enumeration' => [
                        'Ref' => [
                            '_attributes' => [
                                'id' => 'IND_META_CONCEPTS',
                                'version' => '1.0',
                                'agencyID' => 'WB',
                                'package' => 'conceptscheme',
                                'class' => 'ConceptScheme'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        //ReportStructure
        $array['message:Structures']['structure:MetadataStructures']['structure:MetadataStructure']['structure:MetadataStructureComponents']['structure:ReportStructure'] = [
            '_attributes' => [
                //'urn' => 'urn:sdmx:org.sdmx.infomodel.metadatastructure.ReportStructure=WB:IND_MSD(1.0).IND_REPORT_FULL',
                'id' => 'IND_REPORT_FULL'
            ],
            /*'common:Name' => [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => 'Indicators Report Structure'
            ],
            'common:Description' => [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => 'Indicators Report Structure'
            ]*/            
        ];

        //MetadataAttributes
        $array['message:Structures']['structure:MetadataStructures']['structure:MetadataStructure']['structure:MetadataStructureComponents']['structure:ReportStructure']['structure:MetadataAttribute'] = $metadataAttributes;

        /*
        <structure:MetadataTarget>
              <Ref id="FULL_ISMS_TOPMETA" />
            </structure:MetadataTarget>
        */
        //MetadataTarget
        $array['message:Structures']['structure:MetadataStructures']['structure:MetadataStructure']['structure:MetadataStructureComponents']['structure:ReportStructure']['structure:MetadataTarget'] = [
            'Ref' => [
                '_attributes' => [
                    'id' => 'IND_METADATA_TARGET_ID'
                ]
            ]
        ];


        $arrayToXml = new ArrayToXml($array, $rootElement, true, 'UTF-8');        
        $result = $arrayToXml->prettify()->toXml();
        return $result;
    }


    private function get_root_element(){

        return [
            'rootElementName' => 'message:Structure',
            '_attributes' => [
                //'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:message' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message',
                'xmlns:structure' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure',
                'xmlns:common' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common',
            ]
        ];
    }

    private function get_header_element($data){
        
        return [            
                'message:ID' => 'ID',
                'message:Test' => 'false',
                'message:Prepared' => date('Y-m-d\TH:i:s\Z'),
                'message:Sender' => [
                    '_attributes' => ['id' => 'ZZZ']
                ],
                'message:Receiver' => [
                    '_attributes' => ['id' => 'not_supplied']
                ]
        ];
    }

    private function get_agency_schemes_element($data){
        return [
            //'structure:AgencySchema' => [
                '_attributes' => [
                    'urn' => 'urn:sdmx:org.sdmx.infomodel.base.AgencyScheme=SDMX:AGENCIES(1.0)',
                    'isExternalReference' => 'false',
                    'agencyID' => 'SDMX',
                    'id' => 'AGENCIES'
                ],
                'common:Name' => [
                    '_attributes' => ['xml:lang' => 'en'],
                    '_value' => 'SDMX Agency Scheme'
                ],
                'structure:Agency' => [
                    '_attributes' => [
                        'urn' => 'urn:sdmx:org.sdmx.infomodel.base.Agency=SDMX:AGENCIES(1.0).WB',
                        'id' => 'WB'
                    ],
                    'common:Name' => [
                        '_attributes' => ['xml:lang' => 'en'],
                        '_value' => 'World Bank (WB)'
                    ],
                    'common:Description' => [
                        '_attributes' => ['xml:lang' => 'en'],
                        '_value' => 'World Bank Group'
                    ],
                    'structure:Contact' => [
                        'common:Name' => [
                            '_attributes' => ['xml:lang' => 'en'],
                            '_value' => 'WBG Data Help Desk'
                        ],
                        'structure:Department' => [
                            '_attributes' => ['xml:lang' => 'en'],
                            '_value' => 'WBG Data Help Desk'
                        ],
                        'structure:Role' => [
                            '_attributes' => ['xml:lang' => 'en'],
                            '_value' => 'Single entry point for external inquiries'
                        ],
                        'structure:URI' => 'https://worldbank.org',
                        'structure:Email' => 'data@worldbank.org'
                ]
            //]
         ]
        ];
    }


    /**
     * 
     * 
     * 
     */
    private function get_concept_element($item){
        $output=[
            '_attributes' => [
                'urn' => 'urn:sdmx:org.sdmx.infomodel.conceptscheme.ConceptScheme=WB:IND_META_CONCEPTS(1.0).' . strtoupper($item['key']),
                'id' => strtoupper($item['key'])
            ],
            'common:Name' =>  [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => (isset($item['title']) && !empty($item['title'])) ? $item['title'] : $item['key']
            ],
            'common:Description' =>  [
                '_attributes' => ['xml:lang' => 'en'],
                '_value' => (isset($item['description']) && !empty($item['description'])) ? $item['description'] : $item['key']
            ]
        ];

        /*if (isset($item['parent']) && !empty($item['parent'])){
            $output['structure:Parent'] = strtoupper($item['parent']);
        }*/

        return $output;
    }

    private function get_metadata_attribute($item)
    {
        return [
            '_attributes' => [
                //'urn' => 'urn:sdmx:org.sdmx.infomodel.metadatastructure.MetadataAttribute=WB:IND_META_CONCEPTS(1.0).' . strtoupper($item['key']),
                'minOccurs' => '0',
                'maxOccurs' => '1',
                'id' => strtoupper($item['key'])
            ],
            //'structure:ConceptIdentity' => 'urn:sdmx:org.sdmx.infomodel.conceptscheme.Concept=WB:IND_META_CONCEPTS(1.0).' . strtoupper($item['key'])

            /*
            <structure:ConceptIdentity>
            <Ref id="DATA_SOURCE_LINK" maintainableParentID="CS_MSD_TOPMETA" maintainableParentVersion="1.0" agencyID="IT1" package="conceptscheme" class="Concept"/>
            </structure:ConceptIdentity>
            */

            'structure:ConceptIdentity' => [
                'Ref' => [
                    '_attributes' => [
                        'id' => strtoupper($item['key']),
                        'maintainableParentID' => 'IND_META_CONCEPTS',
                        'maintainableParentVersion' => '1.0',
                        'agencyID' => 'WB',
                        'package' => 'conceptscheme',
                        'class' => 'Concept'
                    ]
                ]
            ],
            /*
            <structure:LocalRepresentation>
<structure:TextFormat textType="String"/>
</structure:LocalRepresentation>
            */
            'structure:LocalRepresentation' => [
                'structure:TextFormat' => [
                    '_attributes' => [
                        'textType' => 'String'
                    ]
                ]
            ]
        ];
    }






    function write($data, $filename)
    {
        $xml = ArrayToXml::convert($data, [
            'rootElementName' => 'message:Structure',
            '_attributes' => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:mes' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message',
                'xmlns:str' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/structure',
                'xmlns:com' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/common',
            ],
        ], true, 'UTF-8');

        return $xml;
    }


    function template_to_array($template_uid)
    {
        $template=$this->ci->Editor_template_model->get_template_by_uid($template_uid);

        if (!$template){
            throw new Exception("TEMPLATE_NOT_FOUND");
        }
        
        if (isset($template['template'])){
            $template=$template['template'];
        }

        if (!isset($template['items'])){
            throw new Exception("TEMPLATE_ITEMS_NOT_FOUND");
        }

        $json_schema_path = 'application/schemas/timeseries-schema.json';
        $this->schema_items=$this->json_schema_to_array($json_schema_path);

        $array=$this->template_to_array_recursive($template['items']);
        return $array;
    }

    function template_to_array_recursive($template)
    {
        $array=[];
        foreach ($template as $key=>$item){
            
            //section container
            if ($item['type']=='section_container'){

                $array=array_merge($array, $this->template_to_array_recursive($item['items']));
                continue;
            }

            //section
            if ($item['type']=='section'){
                if (isset($item['items'])){
                    $array=array_merge($array, $this->template_to_array_recursive($item['items']));
                }
                
                if (isset($item['props'])){
                    $array=array_merge($array, $this->template_to_array_recursive($item['props']));
                }

                //$array=array_merge($array, $this->template_to_array_recursive($item['items']));
                continue;
            }

            //nested_array
            if ($item['type']=='nested_array'){
                $array=array_merge($array, $this->template_to_array_recursive($item['props']));
                continue;
            }

            //array
            if ($item['type']=='array'){
                $array=array_merge($array, $this->template_to_array_recursive($item['props']));
                continue;
            }
            
            $new_key=(isset($item['prop_key']) ? $item['prop_key'] : $item['key']);
            $parent_key=str_replace(".", "/", $this->extract_parent_key($new_key));

            //check if parent item exists
            if (!isset($array[$parent_key])){

                //get parent info from schema
                if (isset($this->schema_items[$parent_key])){                    
                    $array[$this->convert_key($parent_key)]=$this->schema_items[$parent_key];
                }
                else{

                    $array[$this->convert_key($parent_key)]=[
                        'type'=>'object',
                        'title'=>$parent_key,
                        'description'=>'NOT-AVAILABLE',
                        'parent'=>''
                    ];
                }
            }

            $array[$this->convert_key($new_key)]=[
                'type'=>isset($item['type']) ? $item['type'] : 'string',
                'title'=>isset($item['title']) ? $item['title'] : '',
                'description'=>isset($item['help_text']) ? $item['help_text'] : '',
                'parent'=>str_replace("/", "__", $parent_key)
            ];

            if (isset($item['enum'])){
                $array[$this->convert_key($new_key)]['enum']=$item['enum'];
            }

            if (isset($value['properties'])){
                $array=array_merge($array, $this->template_to_array_recursive($value['properties'], $new_key, $key));
            }

            if (isset($value['items'])){
                $array=array_merge($array, $this->template_to_array_recursive($value['items'], $new_key, $key));
            }
        }

        return $array;
    }



    /**
     * 
     * Extract parent key from full path
     * 
     */
    function extract_parent_key($key)
    {
        $parts=explode(".", $key);
        $parent_key='';
        if (count($parts)>1){
            $parent_key=implode(".", array_slice($parts, 0, -1));
        }
        return $parent_key;
    }

    /**
     * 
     * Convert key to schema format
     * 
     */
    function convert_key($key, $search='.', $replace='/'){
        return str_replace($search, $replace, $key);
    }


    function json_schema_to_array($json_schema_path)
    {
        $json_schema = json_decode(file_get_contents($json_schema_path));                
        $array = $this->json_schema_to_array_recursive($json_schema);
        return $array;
    }
    
    function json_schema_to_array_recursive($json_schema, $parent_key = '', $parent_element_id='')
    {
        $array = array();
        if (isset($json_schema->properties))
        {
            foreach ($json_schema->properties as $key => $value)
            {
                //create a flat array using parent key and child key
                $new_key = $parent_key . ($parent_key ? '/' : '') . $key;
                $array[$new_key] = [
                    'type' => isset($value->type) ? $value->type : 'string',
                    'title' => isset($value->title) ? $value->title : '',
                    'description' => isset($value->description) ? $value->description : '',
                    'parent' => str_replace("/", "__", $parent_key)
                ];

                //enum?
                if (isset($value->enum))
                {
                    $array[$new_key]['enum'] = $value->enum;
                }

                //if the child has properties, call the function recursively
                if (isset($value->properties))
                {
                    $array = array_merge($array, $this->json_schema_to_array_recursive($value, $new_key, $key));
                }

                if (isset($value->items))
                {
                    $array = array_merge($array, $this->json_schema_to_array_recursive($value->items, $new_key, $key));
                }
            }
        }
        return $array;

        
    }
        
}