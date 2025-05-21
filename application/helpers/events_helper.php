<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Events Helper
 * 
 * Events are only triggered if the events library is loaded
 * 
 * @package		CodeIgniter
 * @subpackage	Helpers
 */

// ------------------------------------------------------------------------

/**
 * Trigger an event
 *
 *
 * @access	public
 * @return	array
 */	
if ( ! function_exists('emit_event_db_update'))
{
    function emit_event_db_update($object_type,$object_id,$action='atomic')
    {
        $ci =& get_instance();
        if(!is_events_library_loaded()){
            return false;
        }        
        $ci->events->emit('db.after.update', $object_type, $object_id,$action);        
    }
}

if ( ! function_exists('emit_event_db_delete'))
{
    function emit_event_db_delete($object_type,$object_id,$action='atomic')
    {
        $ci =& get_instance();
        if(!is_events_library_loaded()){
            return false;
        }
        $ci->events->emit('db.after.delete', $object_type, $object_id,$action);        
    }
}

if ( ! function_exists('is_events_library_loaded'))
{
    function is_events_library_loaded()
    {
        $ci =& get_instance();

        if($ci->load->is_loaded('events')){
            return true;
        }
        else{
            return false;
        }
    }
}

/* End of file events_helper.php */
/* Location: ./system/helpers/events_helper.php */