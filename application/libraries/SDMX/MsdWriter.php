<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


use Spatie\ArrayToXml\ArrayToXml;

class MsdWriter
{

    private $ci;
    private $schema_items=[];


    function __construct()
	{
		log_message('debug', "MsdWriter Class Initialized.");
		$this->ci =& get_instance();		
        $this->ci->load->model("Editor_template_model");
	}


    function build_msd($data)
    {
        //root element
        $rootElement = $this->get_root_element();

        $array=[];

        //header 
        $array['mes:Header']= $this->get_header_element($data);

        //structures
        $array['mes:Structures']=[
            'mes:AgencySchemes' => [],
            'mes:ConceptSchemes' => [],
        ];

        //agency schemes
        $array['mes:Structures']['mes:AgencySchemes']['mes:AgencyScheme'] = $this->get_agency_schemes_element($data);
       

        //concept schemes
        $array['mes:Structures']['mes:ConceptSchemes']['mes:ConceptScheme'] = [
            '_attributes' => [
                'urn' => 'urn:sdmx:org.sdmx.infomodel.conceptscheme.ConceptScheme=WB:IND_META_CONCEPTS(1.0)',
                'structureURL' => 'https://registry.sdmx.org/ws/public/sdmxapi/rest/conceptscheme/WB/IND_META_CONCEPTS/1.0',
                'isExternalReference' => 'false',
                'agencyID' => 'WB',
                'id' => 'IND_META_CONCEPTS',
                'version' => '1.0'                            
            ],
            'com:Name' =>  [
                '_attributes' => ['lang' => 'en'],
                '_value' => 'Indicators Metadata Concepts'
            ],
            'com:Description' =>  [
                '_attributes' => ['lang' => 'en'],
                '_value' => 'Metadata concepts for Indicators'
            ]            
        ];

        //concepts
        $concepts=[];
       foreach ($data as $key=>$concept){
            $concept['key'] = str_replace("/", "__", $key);
            $concepts[] = $this->get_concept_element($concept);
        }

        $array['mes:Structures']['mes:ConceptSchemes']['mes:ConceptScheme']['str:Concept'] = $concepts;

        //metadatastructures
        $metadataAttributes=[];
        foreach ($data as $key=>$concept){
            $concept['key'] = str_replace("/", "__", $key);
            $metadataAttributes[] = $this->get_metadata_attribute($concept);    
        }

        $array['mes:Structures']['mes:MetadataStructures'] = [
            'str:MetadataStructure' => [
                '_attributes' => [
                    'urn' => 'urn:sdmx:org.sdmx.infomodel.metadatastructure.MetadataStructure=WB:IND_MSD(1.0)',
                    'structureURL' => 'https://registry.sdmx.org/ws/public/sdmxapi/rest/metadatastructure/WB/MSD/1.0',
                    'isExternalReference' => 'false',
                    'agencyID' => 'WB',
                    'id' => 'IND_MSD',
                    'version' => '1.0'                            
                ],
                'com:Name' =>  [
                    '_attributes' => ['lang' => 'en'],
                    '_value' => 'Indicators Metadata Structure Definition'
                ],
                'com:Description' =>  [
                    '_attributes' => ['lang' => 'en'],
                    '_value' => 'Metadata structure Definition for Indicators'
                ],
                'str:MetadataStructureComponents' => [
                ]                
            ]
        ];

        $array['mes:Structures']['mes:MetadataStructures']['str:MetadataStructure']['str:MetadataStructureComponents']['str:MetadataAttributeList']['str:MetadataAttribute'] = $metadataAttributes;

        $arrayToXml = new ArrayToXml($array, $rootElement, true, 'UTF-8');        
        $result = $arrayToXml->prettify()->toXml();
        return $result;
    }


    private function get_root_element(){

        return [
            'rootElementName' => 'mes:Structure',
            '_attributes' => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:mes' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message',
                'xmlns:str' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/structure',
                'xmlns:com' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/common',
            ]
        ];
    }

    private function get_header_element($data){
        
        return [            
                'mes:ID' => 'ID',
                'mes:Test' => 'false',
                //'mes:Prepared' => date('Y-m-d\TH:i:s\Z'),
                'mes:Sender' => [
                    '_attributes' => ['id' => 'ZZZ']
                ],
                'mes:Receiver' => [
                    '_attributes' => ['id' => 'not_supplied']
                ]
        ];
    }

    private function get_agency_schemes_element($data){
        return [
            //'str:AgencySchema' => [
                '_attributes' => [
                    'urn' => 'urn:sdmx:org.sdmx.infomodel.base.AgencyScheme=SDMX:AGENCIES(1.0)',
                    'isExternalReference' => 'false',
                    'agencyID' => 'SDMX',
                    'id' => 'AGENCIES'
                ],
                'com:Name' => [
                    '_attributes' => ['lang' => 'en'],
                    '_value' => 'SDMX Agency Scheme'
                ],
                'str:Agency' => [
                    '_attributes' => [
                        'urn' => 'urn:sdmx:org.sdmx.infomodel.base.Agency=SDMX:AGENCIES(1.0).WB',
                        'id' => 'WB'
                    ],
                    'com:Name' => [
                        '_attributes' => ['lang' => 'en'],
                        '_value' => 'World Bank (WB)'
                    ],
                    'com:Description' => [
                        '_attributes' => ['lang' => 'en'],
                        '_value' => 'World Bank Group'
                    ],
                    'str:Contact' => [
                        'com:Name' => [
                            '_attributes' => ['lang' => 'en'],
                            '_value' => 'WBG Data Help Desk'
                        ],
                        'str:Department' => [
                            '_attributes' => ['lang' => 'en'],
                            '_value' => 'WBG Data Help Desk'
                        ],
                        'str:Role' => [
                            '_attributes' => ['lang' => 'en'],
                            '_value' => 'Single entry point for external inquiries'
                        ],
                        'str:URI' => 'https://worldbank.org',
                        'str:Email' => 'data@worldbank.org'
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
            'com:Name' =>  [
                '_attributes' => ['lang' => 'en'],
                '_value' => (isset($item['title']) && !empty($item['title'])) ? $item['title'] : $item['key']
            ],
            'com:Description' =>  [
                '_attributes' => ['lang' => 'en'],
                '_value' => (isset($item['description']) && !empty($item['description'])) ? $item['description'] : $item['key']
            ]
        ];

        if (isset($item['parent']) && !empty($item['parent'])){
            $output['str:Parent'] = strtoupper($item['parent']);
        }

        return $output;
    }

    private function get_metadata_attribute($item)
    {
        return [
            '_attributes' => [
                'urn' => 'urn:sdmx:org.sdmx.infomodel.metadatastructure.MetadataAttribute=WB:IND_META_CONCEPTS(1.0).' . strtoupper($item['key']),
                'minOccurs' => '0',
                'maxOccurs' => '1',
                'id' => strtoupper($item['key'])
            ],
            'str:ConceptIdentity' => 'urn:sdmx:org.sdmx.infomodel.conceptscheme.Concept=WB:IND_META_CONCEPTS(1.0).' . strtoupper($item['key'])
        ];
    }






    function write($data, $filename)
    {
        $xml = ArrayToXml::convert($data, [
            'rootElementName' => 'mes:Structure',
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