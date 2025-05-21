<?php
class JsonSerializer extends SimpleXmlElement implements JsonSerializable
{
    const ATTRIBUTE_INDEX = "@attr";
    const CONTENT_NAME = "_text";

    /**
     * SimpleXMLElement JSON serialization
     * @AUTHOR - https://stackoverflow.com/questions/31272929/xml-to-json-with-attributes-for-php-or-python
     * 
     * @return array
     *
     * @link http://php.net/JsonSerializable.jsonSerialize
     * @see JsonSerializable::jsonSerialize
     * @see https://stackoverflow.com/a/31276221/36175
     */
    function jsonSerialize()
    {
        $array = [];

        if ($this->count()) {
            //serialize children if there are children
            
            /**
             * @var string $tag
             * @var JsonSerializer $child
             */
            foreach ($this as $tag => $child) {
                $temp = $child->jsonSerialize();
                $attributes = [];

                foreach ($child->attributes() as $name => $value) {
                    $attributes["$name"] = (string) $value;
                }
                if(!empty($attributes)){
                    $array[$tag][] = array_merge($temp, [self::ATTRIBUTE_INDEX => $attributes]);
                }
                else{
                        $array[$tag][] = $temp;
                }
            }
        } else {
            // serialize attributes and text for a leaf-elements
            $temp = (string) $this;

            // if only contains empty string, it is actually an empty element
            if (trim($temp) !== "") {
                $array[self::CONTENT_NAME] = trim($temp);
            }
        }

        if ($this->xpath('/*') == array($this)) {
            // the root element needs to be named
            $array = [$this->getName() => $array];

            //root attributes
            foreach ($this->attributes() as $name => $value) {
                $array[$this->getName()]['@attr']["$name"] = (string) $value;
            }
        }

        return $array;
    }
}