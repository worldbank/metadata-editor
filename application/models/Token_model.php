<?php
class Token_model extends CI_Model {
 
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }
	  	
	function create_token(){
		
		//create new token
		$token=md5(uniqid());
		
		$data=array(
				'tokenid'=>$token,
				'dated'=>date("U")
				);
				
		//add to db
		$this->db->insert('tokens',$data);
		
		//clean expired tokens
		$this->clean();
		
		//return the new token
		return $token;
	}
	
	/**
	* checks if a URL exists
	*
	*/
	function token_exists($token)
	{
		$this->db->select('tokenid');		
		$this->db->from('tokens');		
		$this->db->where('tokenid',$token);		
        $result= $this->db->count_all_results();
		
		if ($result==0)
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	* Remove tokens older than the specified time
	*
	*/
	function clean()
	{
		$this->db->where('dated <=', date("U")-7200); 
		return $this->db->delete('tokens');
	}


	/**
	* Remove token
	*
	*/
	function remove_token($token)
	{
		$this->db->where('tokenid', $token); 
		return $this->db->delete('tokens');
	}

}
?>