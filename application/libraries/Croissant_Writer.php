<?php

class Croissant_Writer
{
    private $data;
    private $writer;
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_model');
        $this->ci->load->model('Editor_resource_model');
        $this->ci->load->model('Editor_datafile_model');
        $this->ci->load->model('Editor_variable_model');
    }


    function write_croissant($sid, $output='php://output')
    {
        $dataset=$this->ci->Editor_model->get_row($sid);

        if ($dataset['type']!='survey'){
            throw new Exception('Only `microdata` datasets are supported:: '. $sid . ' - ' . $dataset['type']);
        }

        //dataset information (study level metadata)
        $dataset_info=$this->map_dataset_info($dataset);

        //distribution information (external resources/files)
        $resources=$this->ci->Editor_resource_model->select_all_with_path($sid);
        $distribution_info=$this->map_distribution_info($resources);

        if (!empty($distribution_info)){
            $dataset_info['distribution']=$distribution_info;
        }

        //recordset information (data file level metadata)
        $data_files=$this->ci->Editor_datafile_model->select_all($sid);

        foreach($data_files as $data_file){
            $variables=$this->ci->Editor_variable_model->select_all($sid,$data_file['file_id'],$detailed=false);
            $recordset_info=$this->get_recordset_info($data_file,$variables);

            if (!empty($recordset_info)){
                $dataset_info['recordSet'][]=$recordset_info;
            }
        }
        
        return $dataset_info;
    }


    /**
     * 
     * Map dataset information (study level metadata)
     * 
     * @param array $metadata - Dataset metadata [microdata]
     * @return array - Mapped dataset information
     */
    function map_dataset_info($projectObj)
    {
        $metadata = new \Adbar\Dot($projectObj['metadata']);
        $dataset_info=array();
        $dataset_info['@context'] = [
            '@language' => 'en',
            '@vocab' => 'https://schema.org/',
            'citeAs' => 'cr:citeAs',
            'column' => 'cr:column',
            'conformsTo' => 'dct:conformsTo',
            'cr' => 'http://mlcommons.org/croissant/',
            'rai' => 'http://mlcommons.org/croissant/RAI/',
            'dct' => 'http://purl.org/dc/terms/',
            'data' => [
                '@id' => 'cr:data',
                '@type' => '@json'
            ],
            'dataType' => [
                '@id' => 'cr:dataType',
                '@type' => '@vocab'
            ],
            'examples' => [
                '@id' => 'cr:examples',
                '@type' => '@json'
            ],
            'extract' => 'cr:extract',
            'field' => 'cr:field',
            'fileProperty' => 'cr:fileProperty',
            'fileObject' => 'cr:fileObject',
            'fileSet' => 'cr:fileSet',
            'format' => 'cr:format',
            'includes' => 'cr:includes',
            'isLiveDataset' => 'cr:isLiveDataset',
            'jsonPath' => 'cr:jsonPath',
            'key' => 'cr:key',
            'md5' => 'cr:md5',
            'parentField' => 'cr:parentField',
            'path' => 'cr:path',
            'recordSet' => 'cr:recordSet',
            'references' => 'cr:references',
            'regex' => 'cr:regex',
            'repeated' => 'cr:repeated',
            'replace' => 'cr:replace',
            'separator' => 'cr:separator',
            'source' => 'cr:source',
            'subField' => 'cr:subField',
            'transform' => 'cr:transform'
        ];

        $dataset_info['@type'] = 'Dataset';        
        $dataset_info['name'] = $metadata->get('study_desc.title_statement.title');
        $dataset_info['alternateName'] = $metadata->get('study_desc.title_statement.alternate_title');
        $dataset_info['identifier'] = $metadata->get('study_desc.title_statement.idno');
        $dataset_info['description'] = $metadata->get('study_desc.study_info.abstract');

        $dataset_info['creator'] = [];
        foreach ($metadata->get('study_desc.authoring_entity') as $author) {
            $dataset_info['creator'][] = [
                '@type' => 'Organization',
                'name' => $author['name']
                //'affiliation' => $author['affiliation']
            ];
        }

        $dataset_info['publisher'] = [];
        $producers=(array)$metadata->get('study_desc.production_statement.producers');

        if (count($producers)>0){
            foreach ($producers as $producer) {
                $dataset_info['publisher'][] = [
                    '@type' => 'Organization',
                    'name' => $producer['name']
                ];
            }
        }

        $dataset_info['dateCreated'] = date('Y-m-d',$projectObj['created']);//$metadata->get('study_desc.production_statement.prod_date');
        $dataset_info['datePublished'] = $metadata->get('study_desc.version_statement.version_date');
        $dataset_info['version'] = $metadata->get('study_desc.version_statement.version');

        $dataset_info['spatialCoverage'] = [];
        foreach ($metadata->get('study_desc.study_info.nation') as $nation) {
            $dataset_info['spatialCoverage'][] = [
                '@type' => 'Place',
                'name' => $nation['name']
            ];
        }

        $dataset_info['temporalCoverage'] = $metadata->get('study_desc.study_info.coll_dates.0.start');

        $dataset_info['license'] = $metadata->get('data_access.use_conditions');
        //$dataset_info['url'] = $metadata->get('study_desc.study_info.study_uri'); // optional
        //$dataset_info['isAccessibleForFree'] = true;

        $dataset_info['keywords'] = [];
        foreach ($metadata->get('study_desc.study_info.keywords') as $keyword) {
            $dataset_info['keywords'][] = $keyword['keyword'];
        }

        return $dataset_info;
    }



    /**
     * 
     * Map distribution information (file level metadata)
     * 
     * @param array $resources - Dataset resources [microdata]
     * @return array - Mapped distribution information
     */
    function map_distribution_info($resources)
    {
        $distribution_info=array();
        $resourcesArr= new \Adbar\Dot($resources);

        foreach ($resourcesArr as $resource) {
            $file_name=isset($resource['path'])?$resource['path']:$resource['filename'];

            if (is_url($file_name)){
                $file_size=0;
                $file_encoding_format='application/octet-stream';
            }else{
                $file_size=$this->get_file_size($file_name);
                $file_encoding_format=$this->get_file_encoding_format($file_name);
            }

            $distribution_info[] = [
                '@type' => 'cr:FileObject',
                '@id' => basename($file_name),
                'name' => basename($file_name),
                'encodingFormat' => $file_encoding_format,
                //'md5' => $resource['md5'],
                'contentSize' => $file_size,
                'description' => $resource['description'],
                'contentUrl' => null //$resource['url'],
            ];
        }

        return $distribution_info;
    }


    /**
     * 
     * Get recordset information
     * 
     * @param array $dataFileObj - Data file object
     * @param array $variables - Variables
     * @return array - Recordset information
     */
    function get_recordset_info($dataFileObj, $variables)
    {
        $recordset_info=array();
        $recordset_info['@type'] = 'cr:RecordSet';
        $recordset_info['field'] = [];

        $data_types_map=[
            'character' => 'sc:Text',
            'numeric' => 'sc:Number',
            'date' => 'sc:Date',
            'datetime' => 'sc:DateTime',
            'time' => 'sc:Time',
            'boolean' => 'sc:Boolean',
            'integer' => 'sc:Integer',
            'float' => 'sc:Float',
            'double' => 'sc:Double',
            'long' => 'sc:Long',
        ];

        foreach ($variables as $variable) {

            if (isset($data_types_map[$variable['field_dtype']])){
                $data_type=$data_types_map[$variable['field_dtype']];
            }else{
                $data_type='sc:Text';
            }

            $recordset_info['field'][] = [
                '@type' => 'cr:Field',
                'name' => $variable['name'],
                'description' => $variable['labl'],
                'dataType' => $data_type,
                'source' => [
                    '@id' => $dataFileObj['id'],
                    'fileObject' => [
                        '@id' => $dataFileObj['file_name'].'.csv'
                    ]
                ]
            ];
        }

        return $recordset_info;
    }


    
    
    /**
     * 
     * Get file size from the file name
     * 
     * @param string $file_name - File name
     * @return int - File size in bytes
     */
    function get_file_size($file_name){

        if (file_exists($file_name)){
            return filesize($file_name);
        }
        return 0;
    }


    /**
     * 
     * Get file encoding format from the file name
     * 
     */
    function get_file_encoding_format($file_name){
        
        $mime_types=[
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',            
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'bz2' => 'application/x-bzip2',
            'dta' => 'application/x-stata-data',
            'do' => 'text/plain',
            'sav' => 'application/x-spss-sav',
            'por' => 'application/x-spss-por',
            'r' => 'text/x-r-source'
        ];

        //check if file_name is a url
        if (filter_var($file_name, FILTER_VALIDATE_URL)){
            return 'application/octet-stream';
        }
        
        //get file extension
        $extension=pathinfo($file_name, PATHINFO_EXTENSION);

        if (isset($mime_types[$extension])){
            return $mime_types[$extension];
        }        

        return 'application/octet-stream';

    }

}  