<?php

defined('BASEPATH') OR exit('No direct script access allowed');

use League\Event\Emitter;

/**
 * 
 * Example raising events
 * 
 * $this->events->emit('db.after.update', 'param value', 'param2');
 * 
 * 
 */

class Events extends Emitter {

    private $ci;

    function __construct()
    {
        $this->ci=&get_instance();
        $this->solr_listeners();
    }


    /**
     * 
     * 
     * Event listeners for SOLR indexing
     * 
     */
    function solr_listeners()
    {    
        $search_provider=$this->ci->config->item("search_provider");

        if($search_provider!='solr'){ 
            return false;
        }

        $this->ci->load->library("Solr_manager");

        /**
         * 
         * @object_type - table name 
         * @object_id - id or array of ids
         * @action - import, refresh, add, update, delete, etc
         * 
         */
        $this->addListener('db.after.update', function ($event, $object_type, $object_id, $action='atomic') {
            log_message('info',"event - ".print_r(array($object_type, $object_id, $action),TRUE));            

            try{                
                if(is_array($object_id)){
                    foreach($object_id as $single_id){
                        $this->ci->solr_manager->run_delta_update($table=$object_type, $delta_op=$action, $obj_id=$single_id);
                    }
                }else{
                    $this->ci->solr_manager->run_delta_update($table=$object_type, $delta_op=$action, $obj_id=$object_id);
                }
            } catch (Exception $e) {
                throw new exception($e->getMessage());
                die();
                log_message('error',"event-exception - ".$e->getMessage());  
            }            
        });

        //delete
        $this->addListener('db.after.delete', function ($event, $object_type, $object_id, $action='atomic') {
            log_message('info',"event - ".print_r(array($object_type, $object_id, $action),TRUE));            

            try{
                if(is_array($object_id)){
                    foreach($object_id as $single_id){
                        $this->ci->solr_manager->run_delta_update($table=$object_type, $delta_op=$action, $obj_id=$single_id);
                    }
                }else{
                    $this->ci->solr_manager->run_delta_update($table=$object_type, $delta_op=$action, $obj_id=$object_id);
                }
            } catch (Exception $e) {
                throw new exception($e->getMessage());
                die();
                log_message('error',"event-exception - ".$e->getMessage());  
            }            
        });
    }
    
}