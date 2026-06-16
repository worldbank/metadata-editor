<?php

/**
 * 
 * Generate ISO 19110 XML from feature catalogue metadata
 * 
 * https://www.isotc211.org/2005/gfc
 *  
 * 
 */

class ISO19110Writer
{
    private $xml;
    private $metadata;

    public function __construct()
    {

    }

    /**
     * 
     * Generate ISO 19110 XML from feature catalogue metadata
     * 
     * @metadata - points to description.feature_catalogue
     */
    public function generate($metadata)
    {

        //metadata = description.feature_catalogue
        $metadata = new \Adbar\Dot($metadata);

        $this->xml = new SimpleXMLElement('<gfc:FC_FeatureCatalogue 
            xmlns:xlink="http://www.w3.org/1999/xlink" 
            xmlns:gmd="http://www.isotc211.org/2005/gmd" 
            xmlns:gco="http://www.isotc211.org/2005/gco" 
            xmlns:gml="http://www.opengis.net/gml/3.2" 
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
            xmlns:gmi="http://www.isotc211.org/2005/gmi" 
            xmlns:srv="http://www.isotc211.org/2005/srv" 
            xmlns:gmx="http://www.isotc211.org/2005/gmx" 
            xmlns:gfc="http://www.isotc211.org/2005/gfc" />');
        
        //gmx:name - is required
        if (!isset($metadata['name']) || empty($metadata['name'])) {
            throw new Exception('Feature catalogue [name] is required');
        }

        $name = $this->xml->addChild('gmx:name');
        $name->addChild('gco:CharacterString', $metadata['name'], 'http://www.isotc211.org/2005/gco');

        //gmx:scope [repeatable]
        if (isset($metadata['scope']) && !empty($metadata['scope'])) {
            foreach ((array)$metadata['scope'] as $scopeValue) {
                $scopeNode = $this->xml->addChild('gmx:scope');
                $scopeNode->addChild('gco:CharacterString', $scopeValue, 'http://www.isotc211.org/2005/gco');
            }
        }

        //gmx:versionNumber - is required
        if (!isset($metadata['versionNumber']) || empty($metadata['versionNumber'])) {
            throw new Exception('Feature catalogue [versionNumber] is required');
        }

        $versionNumber = $this->xml->addChild('gmx:versionNumber');
        $versionNumber->addChild('gco:CharacterString', $metadata['versionNumber'], 'http://www.isotc211.org/2005/gco');

        //gmx:versionDate
        if (isset($metadata['versionDate.date']) && !empty($metadata['versionDate.date'])) {
            $versionDate = $this->xml->addChild('gmx:versionDate');
            $versionDate->addChild('gco:Date', $metadata['versionDate.date'], 'http://www.isotc211.org/2005/gco');
        }

        //gmx:language
        if (isset($metadata['language']) && !empty($metadata['language'])) {
            $language = $this->xml->addChild('gmx:language');
            $language->addChild('gco:CharacterString', $metadata['language'], 'http://www.isotc211.org/2005/gco');
        }

        //gmx:characterSet [not-implemented]
        /*$characterSet = $this->xml->addChild('gmx:characterSet');
        $characterSetCode = $characterSet->addChild('gmd:MD_CharacterSetCode', $metadata['characterSet.codeListValue'], 'http://www.isotc211.org/2005/gco');
        $characterSet->addAttribute('codeList', 'http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_CharacterSetCode');
        $characterSet->addAttribute('codeListValue', $metadata['characterSet.codeList']);
        */

        /* [not-implemented]
        <gfc:producer>
            <gmd:CI_ResponsibleParty>
                <gmd:organisationName>
                    <gco:CharacterString>U.S. Department of Commerce, U.S. Census Bureau, Geography Division</gco:CharacterString>
                </gmd:organisationName>
                <gmd:contactInfo>
                    <gmd:CI_Contact>
                    <gmd:phone>
                        <gmd:CI_Telephone>
                            <gmd:voice>
                                <gco:CharacterString>301-763-1128</gco:CharacterString>
                            </gmd:voice>
                            <gmd:facsimile>
                                <gco:CharacterString>301-763-4710</gco:CharacterString>
                            </gmd:facsimile>
                        </gmd:CI_Telephone>
                    </gmd:phone>
                    <gmd:address>
                        <gmd:CI_Address>
                            <gmd:deliveryPoint>
                                <gco:CharacterString>4600 Silver Hill Road, Stop 7400</gco:CharacterString>
                            </gmd:deliveryPoint>
                            <gmd:city>
                                <gco:CharacterString>Washington</gco:CharacterString>
                            </gmd:city>
                            <gmd:administrativeArea>
                                <gco:CharacterString>DC</gco:CharacterString>
                            </gmd:administrativeArea>
                            <gmd:postalCode>
                                <gco:CharacterString>20233-7400</gco:CharacterString>
                            </gmd:postalCode>
                            <gmd:country>
                                <gco:CharacterString>United States</gco:CharacterString>
                            </gmd:country>
                            <gmd:electronicMailAddress>
                                <gco:CharacterString>geo.geography@census.gov</gco:CharacterString>
                            </gmd:electronicMailAddress>
                        </gmd:CI_Address>
                    </gmd:address>
                    </gmd:CI_Contact>
                </gmd:contactInfo>
                <gmd:role>
                    <gmd:CI_RoleCode codeList="http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#CI_RoleCode"
                                    codeListValue="pointOfContact">pointOfContact</gmd:CI_RoleCode>
                </gmd:role>
            </gmd:CI_ResponsibleParty>
        </gfc:producer>
        */

        /*producer - JSON

        "producer": {
            "individualName": "string",
            "organisationName": "string",
            "positionName": "string",
            "contactInfo": {
            "phone": {
            "voice": "string",
            "facsimile": "string"
            },
            "address": {
            "deliveryPoint": "string",
            "city": "string",
            "postalCode": "string",
            "country": "string",
            "electronicMailAddress": "string"
            },
            "onlineResource": {
            "linkage": "string",
            "name": "string",
            "description": "string",
            "protocol": "string",
            "function": "string"
            }
            },
            "role": "string"
            },

        */

        //producer
        foreach ((array)$metadata['producer'] as $producer) {
            $producer = new \Adbar\Dot($producer);
            $producerNode = $this->xml->addChild('gfc:producer');
            $ciResponsibleParty = $producerNode->addChild('gmd:CI_ResponsibleParty');
            $this->createContact($ciResponsibleParty, $producer);
        }

       //functionalLanguage - string
       $functionalLanguage = $this->xml->addChild('gfc:functionalLanguage');
       $functionalLanguage->addChild('gco:CharacterString', $metadata['functionalLanguage'], 'http://www.isotc211.org/2005/gco');

        
       //featureType [repeatable]
       foreach ((array)$metadata['featureType'] as $featureType) {
            $featureType = new \Adbar\Dot($featureType);
            $this->createFeatureType($this->xml, $featureType);
       }


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


    private function createFeatureType($xmlNode,$featureType)
    {
        //add gfc:featureType
        $featureTypeNode = $xmlNode->addChild('gfc:featureType');
        //add gfc:FC_FeatureType
        $fcFeatureType = $featureTypeNode->addChild('gfc:FC_FeatureType');
        //add gfc:typeName
        $typeName = $fcFeatureType->addChild('gfc:typeName');
        $typeName->addChild('gco:LocalName', $featureType['typeName'], 'http://www.isotc211.org/2005/gco');
        //add gfc:definition
        $definition = $fcFeatureType->addChild('gfc:definition');
        $definition->addChild('gco:CharacterString', $featureType['definition'], 'http://www.isotc211.org/2005/gco');

        //add gfc:code
        $code = $fcFeatureType->addChild('gfc:code');
        $code->addChild('gco:CharacterString', $featureType['code'], 'http://www.isotc211.org/2005/gco');
        //add gfc:isAbstract
        $isAbstract = $fcFeatureType->addChild('gfc:isAbstract');
        $isAbstract->addChild('gco:Boolean', $featureType['isAbstract'], 'http://www.isotc211.org/2005/gco');

        //add gfc:aliases [repeatable]
        if (isset($featureType['aliases']) && is_array($featureType['aliases']))
        {
            foreach ($featureType['aliases'] as $aliasValue) {
                $aliasNode = $fcFeatureType->addChild('gfc:alias');
                $aliasNode->addChild('gco:CharacterString', $aliasValue, 'http://www.isotc211.org/2005/gco');
            }
        }

        //carrierOfCharacteristics [repeatable]
        foreach ((array)$featureType['carrierOfCharacteristics'] as $carrierOfCharacteristics) {
            $this->createFeatureAttribute($fcFeatureType, $carrierOfCharacteristics);
        }
    }

    private function createFeatureAttribute($parentNode,$featureAttribute)
    {
        $featureAttribute = new \Adbar\Dot($featureAttribute);

        //add gfc:carrierOfCharacteristics
        $carrierOfCharacteristics = $parentNode->addChild('gfc:carrierOfCharacteristics');
        //add gfc:FC_FeatureAttribute
        $fcFeatureAttribute = $carrierOfCharacteristics->addChild('gfc:FC_FeatureAttribute');
        //add gfc:memberName
        $memberName = $fcFeatureAttribute->addChild('gfc:memberName');
        $memberName->addChild('gco:LocalName', $featureAttribute['memberName'], 'http://www.isotc211.org/2005/gco');
        //add gfc:definition
        $definition = $fcFeatureAttribute->addChild('gfc:definition');
        $definition->addChild('gco:CharacterString', $featureAttribute['definition'], 'http://www.isotc211.org/2005/gco');

        //cardinality
        if (isset($featureAttribute['cardinality']) && is_array($featureAttribute['cardinality'])) {
            $cardinality = $fcFeatureAttribute->addChild('gfc:cardinality');
            $multiplicity = $cardinality->addChild('gco:Multiplicity');
            $range = $multiplicity->addChild('gco:range');
            $multiplicityRange = $range->addChild('gco:MultiplicityRange');
            $lower = isset($featureAttribute['cardinality']['lower']) ? $featureAttribute['cardinality']['lower'] : 0;
            $upper = isset($featureAttribute['cardinality']['upper']) ? $featureAttribute['cardinality']['upper'] : 1;
            $multiplicityRange->addChild('gco:lower', $lower, 'http://www.isotc211.org/2005/gco');
            $multiplicityRange->addChild('gco:upper', $upper, 'http://www.isotc211.org/2005/gco');
        }
        
        //valueType
        $valueType = $fcFeatureAttribute->addChild('gfc:valueType');
        $typeName = $valueType->addChild('gco:TypeName');
        $aName = $typeName->addChild('gco:aName');
        $aName->addChild('gco:LocalName', $featureAttribute['valueType'], 'http://www.isotc211.org/2005/gco');

        //listedValue [repeatable]
        if (isset($featureAttribute['listedValue']) && is_array($featureAttribute['listedValue']) && !empty($featureAttribute['listedValue'])) {
            foreach ($featureAttribute['listedValue'] as $listedValue) {
                $listedValue = new \Adbar\Dot($listedValue);
                $listedValueNode = $fcFeatureAttribute->addChild('gfc:listedValue');
                $fcListedValue = $listedValueNode->addChild('gfc:FC_ListedValue');
                $this->createListedValue($fcListedValue, $listedValue);
            }
        }
    }


    private function createListedValue($parentNode,$listedValue)
    {
        $listedValue = new \Adbar\Dot($listedValue);
        
        $label = $parentNode->addChild('gfc:label');
        $label->addChild('gco:CharacterString', $listedValue['label'], 'http://www.isotc211.org/2005/gco');
        
        $code = $parentNode->addChild('gfc:code');
        $code->addChild('gco:CharacterString', $listedValue['code'], 'http://www.isotc211.org/2005/gco');
        
        $definition = $parentNode->addChild('gfc:definition');
        $definition->addChild('gco:CharacterString', $listedValue['definition'], 'http://www.isotc211.org/2005/gco');
    }
}