<?php

class ISO19139Writer
{
    private $xml;
    private $metadata;

    public function __construct()
    {

    }

    public function generate($metadata)
    {
        $metadata = new \Adbar\Dot($metadata);
        $this->xml = new SimpleXMLElement('<gmd:MD_Metadata xmlns:gmd="http://www.isotc211.org/2005/gmd" xmlns:gco="http://www.isotc211.org/2005/gco" xmlns:gml="http://www.opengis.net/gml/3.2" xmlns:gmi="http://www.isotc211.org/2005/gmi" xmlns:xlink="http://www.w3.org/1999/xlink" />');

        //fileIdentifier
        $fileIdentifier = $this->xml->addChild('gmd:fileIdentifier');
        $fileIdentifier->addChild('gco:CharacterString', $metadata['idno'], 'http://www.isotc211.org/2005/gco');

        //language
        $language = $this->xml->addChild('gmd:language');
        $language->addChild('gco:CharacterString', $metadata['language'], 'http://www.isotc211.org/2005/gco');

        //characterSet
        $characterSet = $this->xml->addChild('gmd:characterSet');
        $characterSetCode = $characterSet->addChild('gmd:MD_CharacterSetCode', $metadata['characterSet.codeListValue']);
        $characterSetCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_CharacterSetCode');
        $characterSetCode->addAttribute('codeListValue', $metadata['characterSet.codeList']);

        //parentIdentifier
        $parentIdentifier = $this->xml->addChild('gmd:parentIdentifier');
        $parentIdentifier->addChild('gco:CharacterString', $metadata['parentIdentifier'], 'http://www.isotc211.org/2005/gco');


        //hierarchyLevel
        $hierarchyLevel = $this->xml->addChild('gmd:hierarchyLevel');
        $hierarchyLevelCode = $hierarchyLevel->addChild('gmd:MD_ScopeCode', $metadata['hierarchyLevel']);
        $hierarchyLevelCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_ScopeCode');
        $hierarchyLevelCode->addAttribute('codeListValue', $metadata['hierarchyLevel']);

        //hierarchyLevelName

        //contact
        if (isset($metadata['contact'])) {
            $contactElement = $this->xml->addChild('gmd:contact');
            $contacts = $this->createContacts($contactElement, $metadata['contact']);
        }

        //dateStamp
        $dateStamp = $this->xml->addChild('gmd:dateStamp');
        $dateStamp->addChild('gco:Date', $metadata['dateStamp'], 'http://www.isotc211.org/2005/gco');


        //metadataStandardName
        $metadataStandardName = $this->xml->addChild('gmd:metadataStandardName');
        $metadataStandardName->addChild('gco:CharacterString', $metadata['metadataStandardName'], 'http://www.isotc211.org/2005/gco');

        //metadataStandardVersion
        $metadataStandardVersion = $this->xml->addChild('gmd:metadataStandardVersion');
        $metadataStandardVersion->addChild('gco:CharacterString', $metadata['metadataStandardVersion'], 'http://www.isotc211.org/2005/gco');

        //datasetURI
        $datasetURI = $this->xml->addChild('gmd:dataSetURI');
        $datasetURI->addChild('gco:CharacterString', $metadata['dataSetURI'], 'http://www.isotc211.org/2005/gco');

        //spatialRepresentationInfo
        $this->createSpatialRepresentationInfo($this->xml,$metadata['spatialRepresentationInfo']);

        //referenceSystemInfo
        $this->createReferenceSystemInfo($this->xml,$metadata['referenceSystemInfo']);

        //identificationInfo
        $this->createIdentificationInfo($this->xml,$metadata['identificationInfo']);

        //contentInfo

        //distributionInfo
        $distributionInfoNode = $this->xml->addChild('gmd:distributionInfo');
        $ciDistribution = $distributionInfoNode->addChild('gmd:MD_Distribution');

        //distributionInfo.distributionFormat [array]
        foreach ((array)$metadata['distributionInfo.distributionFormat'] as $distributionFormat) {
            $distributionFormat = new \Adbar\Dot($distributionFormat);

            //format name
            $format = $ciDistribution->addChild('gmd:distributionFormat');
            $formatCode = $format->addChild('gmd:MD_Format');
            $name = $formatCode->addChild('gmd:name');
            $name->addChild('gco:CharacterString', $distributionFormat['name'], 'http://www.isotc211.org/2005/gco');

            //version
            if (isset($distributionInfo['format']['version'])) {
                $version = $formatCode->addChild('gmd:version');
                $version->addChild('gco:CharacterString', $distributionFormat['version'], 'http://www.isotc211.org/2005/gco');
            }
        }

        //distributionInfo.distributor [array]
        foreach ((array)$metadata['distributionInfo.distributor'] as $distributor) {
            $distributorNode = $ciDistribution->addChild('gmd:distributor');
            $md_distributor=$distributorNode->addChild('gmd:MD_Distributor');
            $distributorContact = $md_distributor->addChild('gmd:distributorContact');
            $this->createContact($distributorContact, $distributor);
        }
        

        //dataQualityInfo [array] 
        foreach((array)$metadata['dataQualityInfo'] as $dataQualityInfo) {

            $dataQualityInfo=new \Adbar\Dot($dataQualityInfo);

            $dataQualityInfoNode = $this->xml->addChild('gmd:dataQualityInfo');
            $DQ_DataQuality=$dataQualityInfoNode->addChild('gmd:DQ_DataQuality');
            //scope
            $scope = $DQ_DataQuality->addChild('gmd:scope');
            $dqScope = $scope->addChild('gmd:DQ_Scope');
            $level = $dqScope->addChild('gmd:level');
            $levelCode = $level->addChild('gmd:MD_ScopeCode', $dataQualityInfo['scope']);
            $levelCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_ScopeCode');
            $levelCode->addAttribute('codeListValue', $dataQualityInfo['scope']);

            //lineage
            $lineage = $DQ_DataQuality->addChild('gmd:lineage');
            $lineage_lineage = $lineage->addChild('gmd:LI_Lineage');
            $statement = $lineage_lineage->addChild('gmd:statement');
            $statement->addChild('gco:CharacterString', $dataQualityInfo['lineage.statement'], 'http://www.isotc211.org/2005/gco');
        }

        //metadataMaintenanceInfo
        $metadataMaintenanceInfo = $this->xml->addChild('gmd:metadataMaintenance');
        $metadataMaintenance = $metadataMaintenanceInfo->addChild('gmd:MD_MaintenanceInformation');
        $maintenanceAndUpdateFrequency=$metadataMaintenance->addChild('gmd:maintenanceAndUpdateFrequency');
        $maintenanceAndUpdateFrequencyCode = $maintenanceAndUpdateFrequency->addChild('gmd:MD_MaintenanceFrequencyCode', $metadata['metadataMaintenance.maintenanceAndUpdateFrequency']);
        $maintenanceAndUpdateFrequencyCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_MaintenanceFrequencyCode');
        $maintenanceAndUpdateFrequencyCode->addAttribute('codeListValue', $metadata['metadataMaintenance.maintenanceAndUpdateFrequency']);


        //portrayalCatalogueInfo

        //featureCatalogueInfo

        //pretty format xml
        $dom = dom_import_simplexml($this->xml)->ownerDocument;
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $xpath = new DOMXPath($dom);


        // Find and remove empty nodes
        // Limit the loop to a maximum of 10 iterations
        // iterations are needed to remove parents of empty nodes
        $maxIterations = 10;
        $iterationCount = 0;

        do {
            $emptyNodes = $xpath->query('//*[not(node())]');
            foreach ($emptyNodes as $emptyNode) {
                $emptyNode->parentNode->removeChild($emptyNode);
            }
            $iterationCount++;
        } while ($emptyNodes->length > 0 && $iterationCount < $maxIterations);

        //validate
        //$result=$this->validate($dom->saveXML());

        return $dom->saveXML();
        //return $this->xml->asXML();        
    }

    /**
     * 
     * Validate XML against XSD
     * 
     */
    function validate($xml, $xsd_path)
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        // Validate against the schema
        if ($dom->schemaValidate($xsd_path)) {
            return true;
        } else {
            return false;
        }
    }


    function createSpatialRepresentationInfo($xmlNode, $spatialRepresentationInfoArray)
    {
        $metadata_arr=new \Adbar\Dot($spatialRepresentationInfoArray);

        foreach($metadata_arr as $metadata){
            $spatialRepresentationInfo = $xmlNode->addChild('gmd:spatialRepresentationInfo');
            
            //vectorSpatialRepresentation
            $vectorSpatialRepresentation = $spatialRepresentationInfo->addChild('gmd:MD_VectorSpatialRepresentation');
            
            //topologyLevel
            $topologyLevel = $vectorSpatialRepresentation->addChild('gmd:topologyLevel');
            $topologyLevelCode = $topologyLevel->addChild('gmd:MD_TopologyLevelCode', $metadata['vectorSpatialRepresentation']['topologyLevel']);
            $topologyLevelCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_TopologyLevelCode');
            $topologyLevelCode->addAttribute('codeListValue', $metadata['vectorSpatialRepresentation']['topologyLevel']);
            
            //geometricObjects
            $geometricObjects = $vectorSpatialRepresentation->addChild('gmd:geometricObjects');
            foreach ($metadata['vectorSpatialRepresentation']['geometricObjects'] as $object) {
                $mdGeometricObjects = $geometricObjects->addChild('gmd:MD_GeometricObjects');
                $geometricObjectType = $mdGeometricObjects->addChild('gmd:geometricObjectType');
                $mdGeometricObjectTypeCode = $geometricObjectType->addChild('gmd:MD_GeometricObjectTypeCode', $object['geometricObjectType']);
                $mdGeometricObjectTypeCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_GeometricObjectTypeCode');
                $mdGeometricObjectTypeCode->addAttribute('codeListValue', $object['geometricObjectType']);
                
                $geometricObjectCount = $mdGeometricObjects->addChild('gmd:geometricObjectCount');
                $geometricObjectCount->addChild('gco:Integer', $object['geometricObjectCount'], 'http://www.isotc211.org/2005/gco');
            }

            //gridSpatialRepresentation
            $gridSpatialRepresentation = $spatialRepresentationInfo->addChild('gmd:MD_GridSpatialRepresentation');
            $numberOfDimensions = $gridSpatialRepresentation->addChild('gmd:numberOfDimensions');
            $numberOfDimensions->addChild('gco:Integer', $metadata['gridSpatialRepresentation']['numberOfDimensions'], 'http://www.isotc211.org/2005/gco');

            //axisDimensionProperties
            $axisDimensionProperties = $gridSpatialRepresentation->addChild('gmd:axisDimensionProperties');
            foreach ($metadata['gridSpatialRepresentation']['axisDimensionProperties'] as $dimension) {
                $mdDimension = $axisDimensionProperties->addChild('gmd:MD_Dimension');
                $dimensionName = $mdDimension->addChild('gmd:dimensionName');
                $dimensionNameCode = $dimensionName->addChild('gmd:MD_DimensionNameTypeCode', $dimension['dimensionName'], 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_DimensionNameTypeCode');
                $dimensionNameCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_DimensionNameTypeCode');
                $dimensionNameCode->addAttribute('codeListValue', $dimension['dimensionName']);

                $dimensionSize = $mdDimension->addChild('gmd:dimensionSize');
                $dimensionSize->addChild('gco:Integer', $dimension['dimensionSize'], 'http://www.isotc211.org/2005/gco');

                $resolution = $mdDimension->addChild('gmd:resolution');
                $resolution->addChild('gco:Measure', $dimension['resolution'], 'http://www.isotc211.org/2005/gco')->addAttribute('uom', 'm');
            }

            //cellGeometry
            $cellGeometry = $gridSpatialRepresentation->addChild('gmd:cellGeometry');
            $cellGeometryCode = $cellGeometry->addChild('gmd:MD_CellGeometryCode', $metadata['gridSpatialRepresentation']['cellGeometry']);
            $cellGeometryCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_CellGeometryCode');
            $cellGeometryCode->addAttribute('codeListValue', $metadata['gridSpatialRepresentation']['cellGeometry']);

            //transformationParameterAvailability
            $transformationParameterAvailability = $gridSpatialRepresentation->addChild('gmd:transformationParameterAvailability');
            $transformationParameterAvailability->addChild('gco:Boolean', $metadata['gridSpatialRepresentation']['transformationParameterAvailability'], 'http://www.isotc211.org/2005/gco');
        }
    }


    function createReferenceSystemInfo($xmlNode, $referenceSystemInfoArray)
    {
        $metadata_arr=new \Adbar\Dot($referenceSystemInfoArray);

        foreach($metadata_arr as $metadata)
        {
            $referenceSystemInfo = $xmlNode->addChild('gmd:referenceSystemInfo');
            $referenceSystem = $referenceSystemInfo->addChild('gmd:MD_ReferenceSystem');
            $referenceSystemIdentifier = $referenceSystem->addChild('gmd:referenceSystemIdentifier');
            $rsIdentifier = $referenceSystemIdentifier->addChild('gmd:RS_Identifier');
            $code = $rsIdentifier->addChild('gmd:code');
            $code->addChild('gco:CharacterString', $metadata['code'], 'http://www.isotc211.org/2005/gco');
            $codeSpace = $rsIdentifier->addChild('gmd:codeSpace');
            $codeSpace->addChild('gco:CharacterString', $metadata['codeSpace'], 'http://www.isotc211.org/2005/gco');
        }

    }

    function createIdentificationInfo($xmlNode, $identificationInfo)
    {
        $metadata=new \Adbar\Dot($identificationInfo);

        //citation
        $identificationInfo = $xmlNode->addChild('gmd:identificationInfo');
        $dataIdentification = $identificationInfo->addChild('gmd:MD_DataIdentification');
        $citation = $dataIdentification->addChild('gmd:citation');
        $ciCitation = $citation->addChild('gmd:CI_Citation');


        //title
        $title = $ciCitation->addChild('gmd:title');
        $title->addChild('gco:CharacterString', $metadata['citation.title'], 'http://www.isotc211.org/2005/gco');


        //alternateTitle [array]        
        foreach ((array)$metadata['citation.alternateTitle'] as $altTitle) {
            $alternateTitle = $ciCitation->addChild('gmd:alternateTitle');
            $altTitleNode = $alternateTitle->addChild('gco:CharacterString', $altTitle, 'http://www.isotc211.org/2005/gco');
        }

        //collectiveTitle
        $collectiveTitle = $ciCitation->addChild('gmd:collectiveTitle');
        $collectiveTitle->addChild('gco:CharacterString', $metadata['citation.collectiveTitle'], 'http://www.isotc211.org/2005/gco');

        

        //date[array]
        foreach ((array)$metadata['citation.date'] as $date) {
            $dateNode = $ciCitation->addChild('gmd:date');
            $ciDate= $dateNode->addChild('gmd:CI_Date');
            $gmd_date=$ciDate->addChild('gmd:date');
            $gmd_date->addChild('gco:Date', $date['date'], 'http://www.isotc211.org/2005/gco');

            $dateType = $ciDate->addChild('gmd:dateType');
            $dateTypeCode = $dateType->addChild('gmd:CI_DateTypeCode', $date['type']);
            $dateTypeCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#CI_DateTypeCode');
            $dateTypeCode->addAttribute('codeListValue', $date['type']);
        }

        //edition 

        //editionDate

        //identifier[array]
        foreach ((array)$metadata['citation.identifier'] as $identifier) {
            $identifierNode = $ciCitation->addChild('gmd:identifier');
            $ciIdentifier= $identifierNode->addChild('gmd:MD_Identifier');
            $code = $ciIdentifier->addChild('gmd:code');
            $code->addChild('gco:CharacterString', $identifier['code'], 'http://www.isotc211.org/2005/gco');

            $codeSpace = $ciIdentifier->addChild('gmd:codeSpace');
            $codeSpace->addChild('gco:CharacterString', $identifier['authority'], 'http://www.isotc211.org/2005/gco');
        }


        //citeResponsibleParty
        $ciCiteResponsibleParty=$ciCitation->addChild('gmd:citeResponsibleParty');        
        $this->createContacts($ciCiteResponsibleParty,$metadata['citation.citedResponsibleParty']);

        //presentationForm [array]
        foreach ((array)$metadata['citation.presentationForm'] as $presentationForm) {
            $presentationFormNode = $ciCitation->addChild('gmd:presentationForm');
            $presentationFormCode = $presentationFormNode->addChild('gmd:MD_PresentationFormCode', $presentationForm);
            $presentationFormCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_PresentationFormCode');
            $presentationFormCode->addAttribute('codeListValue', $presentationForm);
        }

        //abstract
        $abstract = $dataIdentification->addChild('gmd:abstract');
        $abstract->addChild('gco:CharacterString', $metadata['abstract'], 'http://www.isotc211.org/2005/gco');        

        //purpose
        $purpose = $dataIdentification->addChild('gmd:purpose');
        $purpose->addChild('gco:CharacterString', $metadata['purpose'], 'http://www.isotc211.org/2005/gco');

        //credit
        $credit = $dataIdentification->addChild('gmd:credit');
        $credit->addChild('gco:CharacterString', $metadata['credit'], 'http://www.isotc211.org/2005/gco');

        //status [array]
        foreach ((array)$metadata['status'] as $status) {
            $statusNode = $dataIdentification->addChild('gmd:status');
            $statusCode = $statusNode->addChild('gmd:MD_ProgressCode', $status);
            $statusCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_ProgressCode');
            $statusCode->addAttribute('codeListValue', $status);
        }        

        //pointOfContact [array]
        $pointOfContact = $dataIdentification->addChild('gmd:pointOfContact');
        $this->createContacts($pointOfContact,$metadata['pointOfContact']);
        

        //resourceMaintenance [array]
        foreach ((array)$metadata['resourceMaintenance'] as $resourceMaintenance) {
            $resourceMaintenanceNode = $dataIdentification->addChild('gmd:resourceMaintenance');
            $maintenanceInfo = $resourceMaintenanceNode->addChild('gmd:MD_MaintenanceInformation');
            $maintenanceAndUpdateFrequency = $maintenanceInfo->addChild('gmd:maintenanceAndUpdateFrequency');
            $maintenanceAndUpdateFrequencyCode = $maintenanceAndUpdateFrequency->addChild('gmd:MD_MaintenanceFrequencyCode', $resourceMaintenance['maintenanceAndUpdateFrequency']);
            $maintenanceAndUpdateFrequencyCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_MaintenanceFrequencyCode');
            $maintenanceAndUpdateFrequencyCode->addAttribute('codeListValue', $resourceMaintenance['maintenanceAndUpdateFrequency']);            
        }

        //graphicOverview [array]
        foreach ((array)$metadata['graphicOverview'] as $graphicOverview) {
            $graphicOverviewNode = $dataIdentification->addChild('gmd:graphicOverview');
            $ciImage = $graphicOverviewNode->addChild('gmd:MD_BrowseGraphic');
            $fileName = $ciImage->addChild('gmd:fileName');
            $fileName->addChild('gco:CharacterString', $graphicOverview['fileName'], 'http://www.isotc211.org/2005/gco');

            $fileDescription = $ciImage->addChild('gmd:fileDescription');
            $fileDescription->addChild('gco:CharacterString', $graphicOverview['fileDescription'], 'http://www.isotc211.org/2005/gco');

            if(isset($graphicOverview['fileType'])){
            $fileType = $ciImage->addChild('gmd:fileType');
            $fileType->addChild('gco:CharacterString', $graphicOverview['fileType'], 'http://www.isotc211.org/2005/gco');
            }
        }


        //resourceFormat [array]
        foreach ((array)$metadata['resourceFormat'] as $resourceFormat) {
            $resourceFormatNode = $dataIdentification->addChild('gmd:resourceFormat');
            $formatCode = $resourceFormatNode->addChild('gmd:MD_Format');
            $name = $formatCode->addChild('gmd:name');
            $name->addChild('gco:CharacterString', $resourceFormat['name'], 'http://www.isotc211.org/2005/gco');

            //version
            if (isset($resourceFormat['version'])) {
                $version = $formatCode->addChild('gmd:version');
                $version->addChild('gco:CharacterString', $resourceFormat['version'], 'http://www.isotc211.org/2005/gco');
            }
        }


        foreach ((array)$metadata['descriptiveKeywords'] as $descriptiveKeywords) {
            if (!isset($descriptiveKeywords['keyword'])) {
                continue;
            }

            $descriptiveKeywordsNode = $dataIdentification->addChild('gmd:descriptiveKeywords');
            $keyword = $descriptiveKeywordsNode->addChild('gmd:MD_Keywords');
            $keyword->addChild('gmd:keyword')->addChild('gco:CharacterString', $descriptiveKeywords['keyword'], 'http://www.isotc211.org/2005/gco');

            //thesaurusName
            if (isset($descriptiveKeywords['thesaurusName'])) {                            
                $thesaurusName = $keyword->addChild('gmd:thesaurusName');
                $ciCitation = $thesaurusName->addChild('gmd:CI_Citation');
                $title = $ciCitation->addChild('gmd:title');
                $title->addChild('gco:CharacterString', $descriptiveKeywords['thesaurusName'], 'http://www.isotc211.org/2005/gco');                
            }

            //type
            if (isset($descriptiveKeywords['type'])) {
                $type = $keyword->addChild('gmd:type');
                $typeCode = $type->addChild('gmd:MD_KeywordTypeCode', $descriptiveKeywords['type']);
                $typeCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_KeywordTypeCode');
                $typeCode->addAttribute('codeListValue', $descriptiveKeywords['type']);
            }

        }


        //resourceConstraints
        foreach ((array)$metadata['resourceConstraints'] as $resourceConstraints) {
            $resourceConstraintsNode = $dataIdentification->addChild('gmd:resourceConstraints');
            $ciResourceConstraints = $resourceConstraintsNode->addChild('gmd:MD_LegalConstraints');

            //legalConstraints [array]
            $legalConstraints = $resourceConstraints['legalConstraints'];

            //accessConstraints [array]
            if (isset($legalConstraints['accessConstraints'])){
                foreach ($legalConstraints['accessConstraints'] as $accessConstraint) {
                    $accessConstraintNode = $ciResourceConstraints->addChild('gmd:accessConstraints');
                    $accessConstraintCode = $accessConstraintNode->addChild('gmd:MD_RestrictionCode', $accessConstraint);
                    $accessConstraintCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_RestrictionCode');
                    $accessConstraintCode->addAttribute('codeListValue', $accessConstraint);
                }
            }

            //useConstraints [array]
            if (isset($legalConstraints['useConstraints'])){
                foreach ((array)$legalConstraints['useConstraints'] as $useConstraint) {
                    $useConstraintNode = $ciResourceConstraints->addChild('gmd:useConstraints');
                    $useConstraintCode = $useConstraintNode->addChild('gmd:MD_RestrictionCode', $useConstraint);
                    $useConstraintCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_RestrictionCode');
                    $useConstraintCode->addAttribute('codeListValue', $useConstraint);
                }
            }

            //otherConstraints [array]
            if (isset($legalConstraints['otherConstraints'])){
                foreach ((array)$legalConstraints['otherConstraints'] as $otherConstraint) {
                    $otherConstraintNode = $ciResourceConstraints->addChild('gmd:otherConstraints');
                    $otherConstraintNode->addChild('gco:CharacterString', $otherConstraint, 'http://www.isotc211.org/2005/gco');
                }
            }

            //useLimitation [array]
            if (isset($legalConstraints['useLimitation'])){
                foreach ((array)$legalConstraints['useLimitation'] as $useLimitation) {
                    $useLimitationNode = $ciResourceConstraints->addChild('gmd:useLimitation');
                    $useLimitationNode->addChild('gco:CharacterString', $useLimitation, 'http://www.isotc211.org/2005/gco');
                }
            }
            
        }
        
        //resourceSpecificUsage [array]

        //aggregationInfo

        //extent
        $extent=$metadata['extent'];

        $extentNode = $dataIdentification->addChild('gmd:extent');
        $geoExtent = $extentNode->addChild('gmd:EX_Extent');

        //geographicElement [array]
        if (!empty($extent['geographicElement'])) {            
            foreach ((array)$extent['geographicElement'] as $geographicElement_) {
                $geographicElement_ = new \Adbar\Dot($geographicElement_);
                $geographicElement = $geoExtent->addChild('gmd:geographicElement');                                
                $EX_GeographicBoundingBox = $geographicElement->addChild('gmd:EX_GeographicBoundingBox');
                $westBoundLongitude = $EX_GeographicBoundingBox->addChild('gmd:westBoundLongitude');
                $westBoundLongitude->addChild('gco:Decimal', $geographicElement_['geographicBoundingBox.westBoundLongitude'], 'http://www.isotc211.org/2005/gco');
                $eastBoundLongitude = $EX_GeographicBoundingBox->addChild('gmd:eastBoundLongitude');
                $eastBoundLongitude->addChild('gco:Decimal', $geographicElement_['geographicBoundingBox.eastBoundLongitude'], 'http://www.isotc211.org/2005/gco');
                $southBoundLatitude = $EX_GeographicBoundingBox->addChild('gmd:southBoundLatitude');
                $southBoundLatitude->addChild('gco:Decimal', $geographicElement_['geographicBoundingBox.southBoundLatitude'], 'http://www.isotc211.org/2005/gco');
                $northBoundLatitude = $EX_GeographicBoundingBox->addChild('gmd:northBoundLatitude');
                $northBoundLatitude->addChild('gco:Decimal', $geographicElement_['geographicBoundingBox.northBoundLatitude'], 'http://www.isotc211.org/2005/gco');
            }
        }

        //temporalElement [array]
        if (!empty($extent['temporalElementExtent'])) {
            foreach ((array)$extent['temporalElementExtent'] as $temporalEl) {
                $temporalEl=new \Adbar\Dot($temporalEl);

                $temporalElement = $geoExtent->addChild('gmd:temporalElement');
                $temporalElement = $temporalElement->addChild('gmd:EX_TemporalExtent');
                $temporalElement = $temporalElement->addChild('gmd:extent');
                $temporalElement = $temporalElement->addChild('gml:TimePeriod', null, 'http://www.opengis.net/gml/3.2');
                $beginPosition = $temporalElement->addChild('gml:beginPosition', $temporalEl['beginPosition']);
                $endPosition = $temporalElement->addChild('gml:endPosition',$temporalEl['endPosition']);
            }
        }

        
        //verticalElement [array]
        /*foreach ($metadata['extent.verticalElement'] as $verticalEl) {
            $verticalEl=new \Adbar\Dot($verticalEl);
            $verticalElement = $dataIdentification->addChild('gmd:EX_VerticalExtent');
            $verticalElement = $verticalElement->addChild('gmd:EX_VerticalExtent');
            $verticalElement = $verticalElement->addChild('gmd:extent');
            $verticalElement = $verticalElement->addChild('gml:VerticalExtent');
            $minimumValue = $verticalElement->addChild('gml:minimumValue');
            $minimumValue->addChild('gco:Double', $verticalEl['verticalExtent.minimumValue'], 'http://www.isotc211.org/2005/gco');
            $maximumValue = $verticalElement->addChild('gml:maximumValue');
            $maximumValue->addChild('gco:Double', $verticalEl['verticalExtent.maximumValue'], 'http://www.isotc211.org/2005/gco');
        }*/


        //spatialRepresentationType [array]
        foreach ((array)$metadata['spatialRepresentationType'] as $spatialRepresentationType) {
            $spatialRepresentationTypeNode = $dataIdentification->addChild('gmd:spatialRepresentationType');
            $spatialRepresentationTypeCode = $spatialRepresentationTypeNode->addChild('gmd:MD_SpatialRepresentationTypeCode', $spatialRepresentationType);
            $spatialRepresentationTypeCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_SpatialRepresentationTypeCode');
            $spatialRepresentationTypeCode->addAttribute('codeListValue', $spatialRepresentationType);
        }


        //spatialResolution [array]
        foreach ((array)$metadata['spatialResolution'] as $spatialResolution) {
            $spatialResolutionNode = $dataIdentification->addChild('gmd:spatialResolution');
            $mdResolution = $spatialResolutionNode->addChild('gmd:MD_Resolution');
            $gmdDistance = $mdResolution->addChild('gmd:distance');
            $gcoDistance=$gmdDistance->addChild('gco:Distance', $spatialResolution['value'], 'http://www.isotc211.org/2005/gco');
            $gcoDistance->addAttribute('uom', $spatialResolution['uom']);
        }

        //language [array]
        foreach((array)$metadata['language'] as $language) {
            $gmdLanguage = $dataIdentification->addChild('gmd:language');
            $languageGco=$gmdLanguage->addChild('gco:CharacterString', $language, 'http://www.isotc211.org/2005/gco');
        }
                
        
        //characterSet [array]
        foreach((array)$metadata['characterSet'] as $characterSet) {            
            $characterSetNode = $dataIdentification->addChild('gmd:characterSet');
            $characterSetCode = $characterSetNode->addChild('gmd:MD_CharacterSetCode', $characterSet['codeListValue']);
            $characterSetCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_CharacterSetCode');
            $characterSetCode->addAttribute('codeListValue', $characterSet['codeList']);
        }


        //topicCategory
        foreach ((array)$metadata['topicCategory'] as $topicCategory) {
            $topicCategoryNode = $dataIdentification->addChild('gmd:topicCategory');
            $topicCategoryCode = $topicCategoryNode->addChild('gmd:MD_TopicCategoryCode', $topicCategory);
            $topicCategoryCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_TopicCategoryCode');
            $topicCategoryCode->addAttribute('codeListValue', $topicCategory);
        }

        //supplementalInformation
        $supplementalInformation = $dataIdentification->addChild('gmd:supplementalInformation');
        $supplementalInformation->addChild('gco:CharacterString', $metadata['supplementalInformation'], 'http://www.isotc211.org/2005/gco');


        //serviceIdentification

    }


    //convert contact from JSON to ISO19139 XML contact
    public function createContacts($parentNode,$contacts)
    {
        if (empty($contacts)) {
            return;
        }

        foreach ($contacts as $contact) {
            $this->createContact($parentNode,$contact);
        }
    }    
    
    private function createContact($parentNode,$contact)
    {
        $contact=new \Adbar\Dot($contact);

        $ciResponsibleParty = $parentNode->addChild('gmd:CI_ResponsibleParty');

        //individualName
        $individualName = $ciResponsibleParty->addChild('gmd:individualName', $contact['individualName']);
        $individualName->addChild('gco:CharacterString', $contact['individualName'], 'http://www.isotc211.org/2005/gco');

        //organisationName
        $organisationName = $ciResponsibleParty->addChild('gmd:organisationName');
        $organisationName->addChild('gco:CharacterString', $contact['organisationName'], 'http://www.isotc211.org/2005/gco');
        
        //role
        $role = $ciResponsibleParty->addChild('gmd:role');
        $ciRoleCode = $role->addChild('gmd:CI_RoleCode', $contact['role']);
        $ciRoleCode->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/codeList.xml#CI_RoleCode');
        $ciRoleCode->addAttribute('codeListValue', $contact['role']);

        $contactInfo = $ciResponsibleParty->addChild('gmd:contactInfo');
        $ciContact = $contactInfo->addChild('gmd:CI_Contact');

        $address = $ciContact->addChild('gmd:address');
        $ciAddress = $address->addChild('gmd:CI_Address');
        
        $city=$ciAddress->addChild('gmd:city');
        $city->addChild('gco:CharacterString', $contact['contactInfo.address.city'], 'http://www.isotc211.org/2005/gco');

        $postalCode=$ciAddress->addChild('gmd:postalCode');
        $postalCode->addChild('gco:CharacterString', $contact['contactInfo.address.postalCode'], 'http://www.isotc211.org/2005/gco');

        $country=$ciAddress->addChild('gmd:country');
        $country->addChild('gco:CharacterString', $contact['contactInfo.address.country'], 'http://www.isotc211.org/2005/gco');
        
        $emailAddress=$ciAddress->addChild('gmd:electronicMailAddress');
        $emailAddress->addChild('gco:CharacterString', $contact['contactInfo.address.electronicMailAddress'], 'http://www.isotc211.org/2005/gco');

        //onlineResource
        $onlineResource = $ciContact->addChild('gmd:onlineResource');
        $ciOnlineResource = $onlineResource->addChild('gmd:CI_OnlineResource');

        //linkage
        $linkage = $ciOnlineResource->addChild('gmd:linkage');
        $linkage->addChild('gmd:URL', $contact['contactInfo.onlineResource.linkage']);

        //name
        $name=$ciOnlineResource->addChild('gmd:name');
        $name->addChild('gco:CharacterString', $contact['contactInfo.onlineResource.name'], 'http://www.isotc211.org/2005/gco');

        //description
        $description=$ciOnlineResource->addChild('gmd:description');
        $description->addChild('gco:CharacterString', $contact['contactInfo.onlineResource.description'], 'http://www.isotc211.org/2005/gco');
    }


    
    
}