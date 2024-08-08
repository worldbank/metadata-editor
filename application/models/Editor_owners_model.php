<?php


/**
 * 
 * Editor project access and sharing
 * 
 * 
 */
class Editor_owners_model extends ci_model {
 
    /*
    CREATE TABLE editor_project_owners(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sid int not null,
    user_id int not null,
    permissions varchar(100) not null,
    created int
);
*/

    private $permissions=array(
        'view'=>'View',
        'edit'=>'Edit',
        //'delete'=>'Delete',
        'admin'=>'Admin'
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }

    /**
	*
	* Return all owners of a project
	*
	*
	**/
	function select_all($sid)
	{        
		$this->db->select('editor_project_owners.id,editor_project_owners.user_id,users.username,users.email,editor_project_owners.permissions');
        $this->db->join('users','users.id=editor_project_owners.user_id');
		$this->db->where('sid',$sid);
		return $this->db->get('editor_project_owners')->result_array();
	}

    
    function list_users()
    {
        $this->db->select('users.id,username,email,meta.first_name,meta.last_name');
        $this->db->join('meta','users.id=meta.user_id');
		$this->db->order_by('username','ASC');
		return $this->db->get('users')->result_array();
    }

    function user_by_id($user_id)
    {
        $this->db->select('id,id as user_id,username,email');
        $this->db->where("id",$user_id);
		$this->db->order_by('username','ASC');
		return $this->db->get('users')->row_array();
    }


    function get_project_owner($sid)
	{
		$this->db->select('created_by');
		$this->db->where('id',$sid);
		$owner= $this->db->get('editor_projects')->row_array();

        if ($owner){
            return $this->user_by_id($owner['created_by']);
        }

        return $owner;
	}


    /**
     * 
     * Remove project member
     * 
     */
    function delete($sid,$user_id)
	{
		$this->db->where('sid',$sid);
        $this->db->where('user_id',$user_id);
		$result=$this->db->delete('editor_project_owners');
        $this->set_project_is_shared($sid);
        return $result;
	}


    function add($sid,$users, $permissions=null)
    {
        $result=array();
        foreach($users as $user_id){
            $result[]=$this->add_single_user($sid,$user_id,$permissions);
        }
        return $result;
    }


    function add_single_user($sid,$user_id, $permissions=null)
    {
        //check if user is the owner
        $owner=$this->get_project_owner($sid);

        if ($owner['id']==$user_id){
            return false;
        }

        //check if user is already a member
        $is_member=$this->is_project_member($sid,$user_id);

        if ($is_member){
            $this->delete($sid,$user_id);
        }

        //whitelist permissions
        if (!array_key_exists($permissions,$this->permissions)){
            $permissions='view';
        }

        $data=array(
            'sid'=>$sid,
            'user_id'=>$user_id,
            'permissions'=>$permissions,
            'created'=>time()
        );

        $this->db->insert('editor_project_owners',$data);
        $id=$this->db->insert_id();
        $this->set_project_is_shared($sid);
        return $id;
    }

    function is_project_member($sid,$user_id)
    {
        $this->db->where('sid',$sid);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('editor_project_owners')->row_array();
        return $result;
    }

    function set_project_is_shared($sid)
    {
        $is_shared=$this->is_project_shared($sid);
        $is_shared=$is_shared>0 ? 1 : 0;

        $this->db->where('id',$sid);
        $this->db->update('editor_projects',array('is_shared'=>$is_shared));
    }

    function is_project_shared($sid)
    {
        $this->db->where('sid',$sid);
        return $this->db->count_all_results('editor_project_owners');
    }


    function search_users($keywords)
    {
        $this->db->select('users.id,username,email,meta.first_name,meta.last_name');
        $this->db->join('meta','users.id=meta.user_id');

        $this->db->where("username like '".$keywords."%'");
        $this->db->or_where("email like '".$keywords."%'");
        $this->db->or_where("meta.first_name like '".$keywords."%'");
        $this->db->or_where("meta.last_name like '".$keywords."%'");

		$this->db->order_by('username','ASC');
		return $this->db->get('users')->result_array();
    }

}
