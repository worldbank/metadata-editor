<?php
class ReaderFactory{
    
    static function getReader($readerType, $file,$metadata_key_mappings=NULL)
    {
        $reader= null;
        
        if ($readerType=='survey')
        {
            $reader =new DDIReader($file,$metadata_key_mappings);
        }
        else if ($readerType=='geospatial')
        {
            $reader = new GISReader($file,$metadata_key_mappings);
        }
        else
        {
            throw new Exception("READER TYPE NOT SUPPORTED: ".$readerType);
        }
        
        return $reader;
    }
}