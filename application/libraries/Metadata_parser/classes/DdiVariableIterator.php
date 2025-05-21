<?php
/*
*
*
*
* Iterator for DDI variables
*
*
*/

class DdiVariableIterator implements Iterator
{
	private $xml_reader=NULL;
	private $position=0;
	private $is_valid=TRUE;

	public function __construct($xml_file)
    {
        require dirname(__FILE__).'/DdiVariable.php';

        $this->xml_reader= new XMLReader();

        //read the xml file
        if(!$this->xml_reader->open($xml_file,null,LIBXML_NOERROR | LIBXML_NOWARNING))
        {
            throw new Exception("DDIVARIABLEITERATOR::FAILED TO OPEN FILE:".$xml_file );
        }

        //read only the DDI docDscr and stdyDscr sections
        while ($this->xml_reader->read() )
        {
            if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var")
            {
                $this->position=0;
                break;
            }
        }
    }

    function rewind() {
        //return $this->xml_reader->readOuterXML();
    }

    function current() {

        if ($this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var")
        {
            //get variable xml
            $xml=$this->xml_reader->readOuterXML();

            //convert to xml object
            if (!$xml_obj=simplexml_load_string($xml))
            {
                throw new Exception("VARIABLE OUTPUT NOT VALID: ".$xml);
            }

            return new DdiVariable($xml_obj);
        }
    }



    function key() {}

    function next()
    {
        $this->is_valid=$this->xml_reader->next();

        if (!$this->is_valid)
        {
            return false;
        }

        if ($this->is_valid==TRUE && $this->xml_reader->nodeType == XMLReader::ELEMENT && $this->xml_reader->localName == "var")
        {
            $this->position++;
        }
        else
        {
            $this->next();
        }
    }

    function valid() {
        return $this->is_valid;
    }


}
