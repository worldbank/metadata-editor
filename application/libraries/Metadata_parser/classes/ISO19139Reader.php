<?php

class ISO19139Reader
{
    private $xml;
    private $root_tag='gmi:MI_Metadata';//'gmd:MD_Metadata';
    private $xpath_map;


    //todo:
    //review: transferOptions under distributionInfo
    //DQ_DomainConsistency 


    public function __construct(string $xmlFilePath)
    {
        if (!file_exists($xmlFilePath)) {
            throw new InvalidArgumentException("File not found: $xmlFilePath");
        }

        $this->xml = simplexml_load_file($xmlFilePath);
        if ($this->xml === false) {
            throw new RuntimeException("Failed to load XML file: $xmlFilePath");
        }

        $this->registerNamespaces();
    }

    function parse()
    {
        $result = array();
        $description=[
            "idno"=>(string) $this->xpath_query('//gmd:fileIdentifier/gco:CharacterString'),
            "language"=> (string) $this->xpath_query('//gmd:language/gco:CharacterString'),
            "characterSet" =>[
                "codeListValue"=>(string) $this->xpath_query('//gmd:characterSet/gmd:MD_CharacterSetCode/@codeListValue'),
                "codeList"=>(string) $this->xpath_query('//gmd:characterSet/gmd:MD_CharacterSetCode/@codeList'),
            ],
            "parentIdentifier"=> (string) $this->xpath_query('//gmd:parentIdentifier/gco:CharacterString'),
            "hierarchyLevel"=>(string) $this->xpath_query('//gmd:hierarchyLevel/gmd:MD_ScopeCode/@codeListValue'),
            "hierarchyLevelName"=>(string) $this->xpath_query('//gmd:hierarchyLevelName/gco:CharacterString'),
            "contact"=>$this->parseContacts($this->xml->xpath('//gmd:contact/gmd:CI_ResponsibleParty')),
            "dateStamp"=>(string) $this->xpath_query('//gmd:dateStamp/gco:Date'),
            "metadataStandardName"=>(string) $this->xpath_query('//gmd:metadataStandardName/gco:CharacterString'),
            "metadataStandardVersion"=>(string) $this->xpath_query('//gmd:metadataStandardVersion/gco:CharacterString'),
            "dataSetURI"=>(string) $this->xpath_query('//gmd:dataSetURI/gco:CharacterString'),
        ];

        $result['description']=$description;        
        
        //SpatialRepresentationInfo
        $result['description']['spatialRepresentationInfo']=$this->getSpatialRepresentationInfo();

        //referenceSystemInfo
        //todo: review additional elements under referenceSystemInfo
        $result['description']['referenceSystemInfo']=$this->getReferenceSystemInfo();

        //identificationInfo
        $result['description']['identificationInfo']=$this->getidentificationInfo();

        //distributionInfo
        $result['description']['distributionInfo']=$this->getDistributionInfo();

        //dataQualityInfo
        $result['description']['dataQualityInfo']=$this->getDataQualityInfo();

        //metadataMaintenance
        $result['description']['metadataMaintenance']=$this->getMetadataMaintenance();

        return $result;
    }


    function getSpatialRepresentationInfo()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:spatialRepresentationInfo');

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            // Get vectorSpatialRepresentation nodes
            $vectorSpatialRepresentation = (array) $node->xpath('gmd:MD_VectorSpatialRepresentation');
        
            // Iterate over vectorSpatialRepresentation and extract sub-elements
            foreach ($vectorSpatialRepresentation as $row) {
                $geometricObjects = $row->xpath('gmd:geometricObjects/gmd:MD_GeometricObjects');
                $geometricObjectsArray = [];
        
                // Iterate over all geometricObjects
                foreach ($geometricObjects as $geometricObject) {
                    $geometricObjectsArray[] = [
                        "geometricObjectType" => (string) $this->xpath_query('gmd:geometricObjectType/gmd:MD_GeometricObjectTypeCode/@codeListValue', $geometricObject),
                        "geometricObjectCount" => (int) $this->xpath_query('gmd:geometricObjectCount/gco:Integer', $geometricObject),
                    ];
                }
        
                $result[] = [
                    "vectorSpatialRepresentation" => [
                        "topologyLevel" => (string) $this->xpath_query('gmd:topologyLevel/gmd:MD_TopologyLevelCode/@codeListValue', $row),
                        "geometricObjects" => $geometricObjectsArray,
                    ],
                ];
            }

            //gridSpatialRepresentation
            $gridSpatialRepresentation = (array) $node->xpath('gmd:MD_GridSpatialRepresentation');

            // Iterate over gridSpatialRepresentation and extract sub-elements
            foreach ($gridSpatialRepresentation as $row) {
                $axisDimensionProperties = $row->xpath('gmd:axisDimensionProperties');
                $axisDimensionPropertiesArray = [];

                // Iterate over all axisDimensionProperties
                foreach ($axisDimensionProperties as $axisDimensionProperty) {
                    $axisDimensionPropertiesArray[] = [
                        "dimensionName" => (string) $this->xpath_query('gmd:MD_Dimension/gmd:dimensionName/gmd:MD_DimensionNameTypeCode/@codeListValue', $axisDimensionProperty),
                        "dimensionSize" => (int) $this->xpath_query('gmd:MD_Dimension/gmd:dimensionSize/gco:Integer', $axisDimensionProperty),
                        "resolution" => (float) $this->xpath_query('gmd:MD_Dimension/gmd:resolution/gco:Measure', $axisDimensionProperty),
                        "resolution_uom" => (string) $this->xpath_query('gmd:MD_Dimension/gmd:resolution/gco:Measure/@uom', $axisDimensionProperty),
                    ];
                }

                $result[] = [
                    "gridSpatialRepresentation" => [
                        "numberOfDimensions" => (int) $this->xpath_query('gmd:numberOfDimensions/gco:Integer', $row),
                        "axisDimensionProperties" => $axisDimensionPropertiesArray,
                        "cellGeometry" => (string) $this->xpath_query('gmd:cellGeometry/gmd:MD_CellGeometryCode/@codeListValue', $row),
                        "transformationParameterAvailability" => (bool) $this->xpath_query('gmd:transformationParameterAvailability/gco:Boolean', $row),
                    ],
                ];
            }

        }
        return $result;
    }

    function getReferenceSystemInfo()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:referenceSystemInfo');

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node){
            $referenceSystemIdentifier = (array) $node->xpath('gmd:MD_ReferenceSystem/gmd:referenceSystemIdentifier');
        
            // Iterate over referenceSystemIdentifier and extract sub-elements
            foreach ($referenceSystemIdentifier as $row) {
                $result[]=[
                        "code" => (string) $this->xpath_query('gmd:RS_Identifier/gmd:code/gco:CharacterString', $row),
                        "codeSpace" => (string) $this->xpath_query('gmd:RS_Identifier/gmd:codeSpace/gco:CharacterString', $row),
                ];
            }
        }
        
        return $result;
    }


    function getidentificationInfo()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:identificationInfo/gmd:MD_DataIdentification');

        if (empty($nodes)){
            return null;
        }

        $node=$nodes[0]; // Get the first node, as only one identificationInfo is expected

        $result= [
            'citation' =>[
                "title" => (string) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:title/gco:CharacterString', $node),
                "alternateTitle" => (array) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:alternateTitle/gco:CharacterString', $node, true),
                "collectiveTitle" => (string) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:collectiveTitle/gco:CharacterString', $node),
                "date" => (array) $this->getDateArray($node->xpath('gmd:citation/gmd:CI_Citation/gmd:date/gmd:CI_Date')),
                "edition" => (string) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:edition/gco:CharacterString', $node),
                "editionDate" => (string) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:editionDate/gco:Date', $node),
                "identifier" => (array) $this->getIdentifiers($node->xpath('gmd:citation/gmd:CI_Citation/gmd:identifier/gmd:RS_Identifier')),
                "citedResponsibleParty" => (array) $this->parseContacts($node->xpath('gmd:citation/gmd:CI_Citation/gmd:citedResponsibleParty/gmd:CI_ResponsibleParty')),
                "presentationForm" => (array) $this->getPresentationForm($node->xpath('gmd:citation/gmd:CI_Citation/gmd:presentationForm')),
                //series
                //otherCitationDetails
                "collectiveTitle" => (string) $this->xpath_query('gmd:citation/gmd:CI_Citation/gmd:collectiveTitle/gco:CharacterString', $node),
                //ISBN
                //ISSN
                ],
            'abstract' => (string) $this->xpath_query('gmd:abstract/gco:CharacterString', $node),
            'purpose' => (string) $this->xpath_query('gmd:purpose/gco:CharacterString', $node),
            'credit' => (string) $this->xpath_query('gmd:credit/gco:CharacterString', $node),
            'status' => (array) $this->xpath_query('gmd:status/gmd:MD_ProgressCode/@codeListValue', $node, true),
            'pointOfContact' => $this->parseContacts($node->xpath('gmd:pointOfContact/gmd:CI_ResponsibleParty')),            
            'resourceMaintenance' => (array) $this->getResourceMaintenance($node->xpath('gmd:resourceMaintenance/gmd:MD_MaintenanceInformation')),
            'graphicOverview' => (array) $this->getGraphicOverview($node->xpath('gmd:graphicOverview/gmd:MD_BrowseGraphic')),
            'resourceFormat' => (array) $this->getResourceFormat($node->xpath('gmd:resourceFormat/gmd:MD_Format')),
            'descriptiveKeywords' => (array) $this->getDescriptiveKeywords($node->xpath('gmd:descriptiveKeywords/gmd:MD_Keywords')),
            //'resourceConstraints' =>$this->getResourceConstraintsArray($node->xpath('gmd:resourceConstraints')),
            'resourceConstraints'=>[
                [
                'legalConstraints' => $this->getResourceConstraints($node->xpath('gmd:resourceConstraints/gmd:MD_LegalConstraints')),
                ]
                //securityConstraints -- TODO
            ],
    
            'resourceSpecificUsage' => (array) $this->getResourceSpecificUsage($node->xpath('gmd:resourceSpecificUsage/gmd:MD_Usage')),
            //'aggregationInfo' 
            'extent' => $this->getExtent($node->xpath('gmd:extent/gmd:EX_Extent')),
            'spatialRepresentationType' => (array) $this->xpath_query('gmd:spatialRepresentationType/gmd:MD_SpatialRepresentationTypeCode/@codeListValue', $node, true),
            'spatialResolution' => (array) $this->getSpatialResolution($node->xpath('gmd:spatialResolution')),
            'language' => (array) $this->xpath_query('gmd:language/gco:CharacterString', $node),
            'characterSet' => (array) $this->getCharacterSetArray($node->xpath('gmd:characterSet')),
            'topicCategory' => (array) $this->getTopicCategory($node->xpath('gmd:topicCategory')),
            'supplementalInformation' => (string) $this->xpath_query('gmd:supplementalInformation/gco:CharacterString', $node),
            //'serviceIdentification'
        ];
        
        return $result;
    }


    function getPresentationForm($presentationForm)
    {
        $result = array();

        if (empty($presentationForm)){
            return null;
        }

        foreach ($presentationForm as $form) {
            $result[] = (string) $this->xpath_query('gmd:MD_PresentationFormCode/@codeListValue', $form);
        }
        
        return $result;
    }



    function getIdentifiers($identifiers)
    {
        $result = array();

        if (empty($identifiers)){
            return null;
        }

        foreach ($identifiers as $identifier) {
            $result[] = [
                "code" => (string) $this->xpath_query('gmd:code/gco:CharacterString', $identifier),
                "authority" => (string) $this->xpath_query('gmd:codeSpace/gco:CharacterString', $identifier),                
            ];
        }
        
        return $result;
    }

    function getDateArray($dates)
    {
        $result = array();

        if (empty($dates)){
            return null;
        }

        foreach ($dates as $date) {
            $result[] = [
                "date" => (string) $this->xpath_query('gmd:date/gco:Date', $date),
                "type" => (string) $this->xpath_query('gmd:dateType/gmd:CI_DateTypeCode/@codeListValue', $date),
            ];
        }
        
        return $result;
    }


    function getResourceMaintenance($resourceMaintenance)
    {
        $result = array();

        $nodes=$resourceMaintenance;

        /*
        "resourceMaintenance": [
            {
                "maintenanceAndUpdateFrequency": "string",
                "dateOfNextUpdate": "string",
                "userDefinedMaintenanceFrequency": "string",
                "updateScope": [
                    {
                    "scope": "string",
                    "description": "string"
                    }
                ],
                "maintenanceNote": [
                    "string"
                ],
                "contact": {
                    "individualName": "string",
                    "organisationName": "string",
                    "positionName": "string",
                    "contactInfo": {},
                    "role": "string"
                }
            }
            ],
        */

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $tmp= [
                "maintenanceAndUpdateFrequency" => (string) $this->xpath_query('gmd:maintenanceAndUpdateFrequency/gmd:MD_MaintenanceFrequencyCode/@codeListValue', $node),
                "maintenanceNote" => (array) $this->xpath_query('gmd:maintenanceNote/gco:CharacterString', $node),                                
            ];

            $tmp["updateScope"][] =[
                    "scope" => (string) $this->xpath_query('gmd:updateScope/gmd:MD_Scope/gmd:level/gmd:MD_ScopeCode/@codeListValue', $node),
            ];

            $result[] =$tmp;
        }
        
        return $result;
    }

    function getGraphicOverview($graphicOverview)
    {
        $result = array();

        $nodes=$graphicOverview;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "fileName" => (string) $this->xpath_query('gmd:fileName/gco:CharacterString', $node),
                "fileDescription" => (string) $this->xpath_query('gmd:fileDescription/gco:CharacterString', $node),
                "fileType" => (string) $this->xpath_query('gmd:fileType/gco:CharacterString', $node),
            ];
        }
        
        return $result;
    }


    function getResourceFormat($resourceFormat)
    {
        $result = array();
        $nodes=$resourceFormat;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "name" => (string) $this->xpath_query('gmd:name/gco:CharacterString', $node),
                "version" => (string) $this->xpath_query('gmd:version/gco:CharacterString', $node),
                "specification" => (string) $this->xpath_query('gmd:specification/gco:CharacterString', $node),
                "fileDecompressionTechnique" => (string) $this->xpath_query('gmd:fileDecompressionTechnique/gco:CharacterString', $node),
            ];
        }
        
        return $result;
    }


    function getDescriptiveKeywords($descriptiveKeywords)
    {
        $result = array();

        $nodes=$descriptiveKeywords;

        if (empty($nodes)){
            return null;
        }

         foreach ($nodes as $keywordsNode) {
            
            $keywordType = (string) $this->xpath_query('gmd:type/gmd:MD_KeywordTypeCode/@codeListValue', $keywordsNode);
            $thesaurusName = (string) $this->xpath_query('gmd:thesaurusName/gmd:CI_Citation/gmd:title/gco:CharacterString', $keywordsNode);
            $keywordNodes = $keywordsNode->xpath('gmd:keyword/gco:CharacterString');

            foreach ($keywordNodes as $keywordNode) {
                $result[] = [
                    'keyword' => (string) $keywordNode,
                    'type' => $keywordType,
                    'thesaurusName' => $thesaurusName,
                ];
            }
        }
        
        return $result;
    }


    function getResourceConstraints($resourceConstraints)
    {
        $result = array();

        $nodes=$resourceConstraints;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result = [
                "useLimitation" => (array) $this->xpath_query('gmd:useLimitation/gco:CharacterString', $node,true),
                "otherConstraints" => (array) $this->xpath_query('gmd:otherConstraints/gco:CharacterString', $node, true),
                'accessConstraints' => (array) $this->xpath_query('gmd:accessConstraints/gmd:MD_RestrictionCode/@codeListValue', $node, true),
                'useConstraints' => (array) $this->xpath_query('gmd:useConstraints/gmd:MD_RestrictionCode/@codeListValue', $node, true),           
            ];
        }

        return $result;
    }

    function getResourceSpecificUsage($resourceSpecificUsage)
    {
        $result = array();

        $nodes=$resourceSpecificUsage;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "specificUsage" => (string) $this->xpath_query('gmd:specificUsage/gco:CharacterString', $node),
                "usageDateTime" => (string) $this->xpath_query('gmd:usageDateTime/gco:DateTime', $node),
                "userDeterminedLimitations" => (string) $this->xpath_query('gmd:userDeterminedLimitations/gco:CharacterString', $node),
                "userContactInfo" => (array) $this->parseContacts($node->xpath('gmd:userContactInfo/gmd:CI_ResponsibleParty')),
            ];
        }

        return $result;
    }


    /**
     * 
     * 
     * TODO: not all elements are parsed
     */
    function getExtent($extent)
    {
        $result = array();

        $nodes=$extent;

        if (empty($nodes)){
            return null;
        }

        /*
        "extent": {
            "geographicElement": [
                {
                    "geographicBoundingBox": {
                        "westBoundLongitude": -180,
                        "eastBoundLongitude": -180,
                        "southBoundLatitude": -180,
                        "northBoundLatitude": -180
                    },
                    "geohash": {
                    "geohash": "string",
                    "note": "string"
                    },
                    "geographicDescription": "string",
                    "geographicBoundingPolygon": {
                        "id": "string",
                        "polygon": []
                    }
                }
            ],
            "temporalElementExtent": [
                {
                "beginPosition": "string",
                "endPosition": "string"
                }
            ],
            "verticalElement": [
                {
                "minimumValue": 0,
                "maximumValue": 0,
                "verticalCRS": null
                }
            ]
        },
         */

       //extent geographicElement
        foreach ($nodes as $node) {
            $geographicElement = (array) $node->xpath('gmd:geographicElement');
            $geographicElementArray = [];
            foreach ($geographicElement as $geoElement) {
                $geographicBoundingBox = (array) $geoElement->xpath('gmd:EX_GeographicBoundingBox');
                $geographicBoundingBoxArray = [];
        
                foreach ($geographicBoundingBox as $geoBoundingBox) {
                    $geographicBoundingBoxArray = [
                        "westBoundLongitude" => (float) $this->xpath_query('gmd:westBoundLongitude/gco:Decimal', $geoBoundingBox),
                        "eastBoundLongitude" => (float) $this->xpath_query('gmd:eastBoundLongitude/gco:Decimal', $geoBoundingBox),
                        "southBoundLatitude" => (float) $this->xpath_query('gmd:southBoundLatitude/gco:Decimal', $geoBoundingBox),
                        "northBoundLatitude" => (float) $this->xpath_query('gmd:northBoundLatitude/gco:Decimal', $geoBoundingBox),
                    ];
                }
        
                $geographicElementArray[] = [
                    "geographicBoundingBox" => $geographicBoundingBoxArray,
                    "geohash" =>[
                        'geohash'=> (string) $this->xpath_query('gmd:geohash/gco:CharacterString', $geoElement)
                    ], 
                    "geographicDescription" => (string) $this->xpath_query('gmd:geographicDescription/gco:CharacterString', $geoElement),
                ];
            }
            $result[] = [
                "geographicElement" => $geographicElementArray,
            ];

            //temporalElementExtent
            $temporalElementExtent = (array) $node->xpath('gmd:temporalElement');

            $temporalElementExtentArray = [];
            foreach ($temporalElementExtent as $temporalElement) {

                $temporalElementExtentArray[] = [
                    "description" => (string) $this->xpath_query('gmd:EX_TemporalExtent/gmd:extent/gml:TimePeriod/gml:description', $temporalElement),
                    "beginPosition" => (string) $this->xpath_query('gmd:EX_TemporalExtent/gmd:extent/gml:TimePeriod/gml:beginPosition', $temporalElement),
                    "endPosition" => (string) $this->xpath_query('gmd:EX_TemporalExtent/gmd:extent/gml:TimePeriod/gml:endPosition', $temporalElement),
                ];
            }
            $result[] = [
                "temporalElementExtent" => $temporalElementExtentArray,
            ];
            
        }

        return [
            "geographicElement" => $result[0]['geographicElement'],
            "temporalElementExtent" => $result[1]['temporalElementExtent'],
        ];
    }

    function getSpatialResolution($spatialResolution)
    {
        $result = array();

        $nodes=$spatialResolution;

        /* 
        {
            "spatialResolution": [
                "equivalentScale": {
                "denominator": 25000,
                },
                "distanceResolution": {
                "value": 30,
                "uom": "m",
                }
            ]
            }
        
        */


        if (empty($nodes)){
            return null;
        }


        foreach ($nodes as $node) {

            /*
            //check if equivalentScale/denominator exists
            $denominator = (int) $node->xpath('gmd:MD_Resolution/gmd:equivalentScale/gmd:MD_RepresentativeFraction/gmd:denominator/gco:Integer');

            if ($denominator) {
                $result[]["equivalentScale"]["denominator"] =$denominator;
            }
            */

            //check if distanceResolution exists
            $distanceResolution = (float) $node->xpath('gmd:MD_Resolution/gmd:distance/gco:Distance');
            if ($distanceResolution) {
                $result[] =[
                        "value" => $distanceResolution,
                        "uom" => (string) $this->xpath_query('gmd:MD_Resolution/gmd:distance/gco:Distance/@uom', $node),
                ];
            }

        }

        return $result;
    }



    function getCharacterSetArray($characterSet)
    {
        $result = array();

        $nodes=$characterSet;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "codeListValue" => (string) $this->xpath_query('gmd:MD_CharacterSetCode/@codeListValue', $node),
                "codeList" => (string) $this->xpath_query('gmd:MD_CharacterSetCode/@codeList', $node),
            ];
        }
        
        return $result;
    }

    function getCharacterSet($characterSet)
    {
        $result = array();

        $nodes=$characterSet;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result = [
                "codeListValue" => (string) $this->xpath_query('gmd:MD_CharacterSetCode/@codeListValue', $node),
                "codeList" => (string) $this->xpath_query('gmd:MD_CharacterSetCode/@codeList', $node),
            ];
        }
        
        return $result;
    }


    function getTopicCategory($topicCategory)
    {
        $result = array();

        $nodes=$topicCategory;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = (string) $this->xpath_query('gmd:MD_TopicCategoryCode', $node);
        }
        
        return $result;
    }


    function getDistributionInfo()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:distributionInfo/gmd:MD_Distribution');

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result = [
                "distributionFormat" =>(array) $this->getResourceFormat($node->xpath('gmd:distributionFormat/gmd:MD_Format')),
                "distributor" => (array) $this->parseContacts($node->xpath('gmd:distributor/gmd:MD_Distributor/gmd:distributorContact/gmd:CI_ResponsibleParty')),
                "transferOptions" => (array) $this->getTransferOptions($node->xpath('gmd:transferOptions/gmd:MD_DigitalTransferOptions')),
            ];
        }
        
        return $result;
    }

    function getTransferOptions($transferOptions)
    {
        $result = array();

        $nodes=$transferOptions;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "onLine" => (array) $this->getOnLine($node->xpath('gmd:onLine/gmd:CI_OnlineResource')),
                "offLine" => (string) $this->xpath_query('gmd:offLine/gmd:MD_OfflineResource', $node),
            ];
        }
        
        return $result;
    }

    function getOnLine($onLine)
    {
        $result = array();

        $nodes=$onLine;

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "linkage" => (string) $this->xpath_query('gmd:linkage/gco:CharacterString', $node),
                "protocol" => (string) $this->xpath_query('gmd:protocol/gco:CharacterString', $node),
                "name" => (string) $this->xpath_query('gmd:name/gco:CharacterString', $node),
                "description" => (string) $this->xpath_query('gmd:description/gco:CharacterString', $node),
            ];
        }
        
        return $result;
    }



    function getDataQualityInfo()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:dataQualityInfo/gmd:DQ_DataQuality');

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result[] = [
                "scope" => (string) $this->xpath_query('gmd:scope/gmd:DQ_Scope/gmd:level/gmd:MD_ScopeCode', $node),
                'lineage'=>[
                    "statement" => (string) $this->xpath_query('gmd:lineage/gmd:LI_Lineage/gmd:statement/gco:CharacterString', $node),
                ]
            ];
        }
        
        return $result;
    }



    private function registerNamespaces(): void
    {
        $namespaces = $this->xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            $this->xml->registerXPathNamespace($prefix, $uri);
        }
    }


    function getMetadataMaintenance()
    {
        $result = array();

        $nodes=$this->xml->xpath('//gmd:metadataMaintenance/gmd:MD_MaintenanceInformation');

        if (empty($nodes)){
            return null;
        }

        foreach ($nodes as $node) {
            $result = [
                "maintenanceAndUpdateFrequency" => (string) $this->xpath_query('gmd:maintenanceAndUpdateFrequency/gmd:MD_MaintenanceFrequencyCode/@codeListValue', $node),
                "maintenanceNote" => (array) $this->xpath_query('gmd:maintenanceNote/gco:CharacterString', $node),
            ];
        }
        
        return $result;
    }


    public function parseContacts($contactNodes): array | null
    {
        $contacts = [];
        foreach ($contactNodes as $contactNode) {
            $contacts[] =[
                "individualName"=> (string) $this->xpath_query('gmd:individualName/gco:CharacterString', $contactNode),
                "organisationName"=> (string) $this->xpath_query('gmd:organisationName/gco:CharacterString', $contactNode),
                "positionName"=> ( string) $this->xpath_query('gmd:positionName/gco:CharacterString', $contactNode),
                "role"=> (string) $this->xpath_query('gmd:role/gmd:CI_RoleCode/@codeListValue', $contactNode),
                "contactInfo"=>[
                    "phone"=>[
                        "voice" => (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:voice/gco:CharacterString',$contactNode),
                        "facsimile"=> (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:phone/gmd:CI_Telephone/gmd:facsimile/gco:CharacterString' ,$contactNode),
                    ],
                    "address"=>[
                        "deliveryPoint"=> (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:deliveryPoint/gco:CharacterString' ,$contactNode),
                        "city" => (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:city/gco:CharacterString' ,$contactNode),
                        "postalCode" => (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:postalCode/gco:CharacterString' ,$contactNode),
                        "country" => (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:country/gco:CharacterString' ,$contactNode),
                        "electronicMailAddress" => (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact//gmd:CI_Address/gmd:electronicMailAddress/gco:CharacterString' ,$contactNode),
                    ],
                    "onlineResource"=>[
                        "linkage"=> (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:onlineResource/gmd:CI_OnlineResource/gmd:linkage/gco:CharacterString' ,$contactNode),
                        "name"=> (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:onlineResource/gmd:CI_OnlineResource/gmd:name/gco:CharacterString' ,$contactNode),
                        "description"=> (string) $this->xpath_query('gmd:contactInfo/gmd:CI_Contact/gmd:onlineResource/gmd:CI_OnlineResource/gmd:description/gco:CharacterString' ,$contactNode),
                    ]
                ]            
            ];
        }

        if (empty($contacts)) {
            return null;
        }

        return $contacts;
    }


    private function xpath_query($xpath,$node=null, $is_repeated=false)
    {
        $context = $node ?? $this->xml;

        $result = $context->xpath($xpath);

        if (!$result) {
            return NULL;
        }

        if (!$is_repeated) {
            return $result[0];
        }

        $output=array();
        foreach ($result as $row) {
            $value = trim(dom_import_simplexml($row)->textContent);
            if ($value) {
                $output[]=$value;
            }
        }

        return $output;
    }
    
}