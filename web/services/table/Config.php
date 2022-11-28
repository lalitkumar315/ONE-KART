<?php
require_once(realpath(dirname(__FILE__) . "/../tools/rest.php"));

class Config extends REST{
	
	private $mysqli = NULL;
	private $db = NULL;
    private $conf = NULL;
	
	public function __construct($db) {
		parent::__construct();
		$this->db = $db;
		$this->mysqli = $db->mysqli;
        $this->conf = new CONF();
    }

	public function findAll(){
		if($this->get_request_method() != "GET") $this->response('',406);
		$this->show_response($this->findAllPlain());
	}

    public function findAllByGroup(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['group'])) $this->responseInvalidParam();
        $group = $this->_request['group'];
        $this->show_response($this->findByGroupPlain($group));
    }

    public function findByCode(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['code'])) $this->responseInvalidParam();
        $code = $this->_request['code'];
        $this->show_response($this->findByCodePlain($code));
    }

	public function findAllPlain(){
		$query="SELECT * FROM config cn";
		return $this->db->get_list($query);
	}

    public function findAllArr(){
        $query="SELECT * FROM config cn";
        return $this->db->get_list($query);
    }

    public function findByGroupPlain($group){
        $query="SELECT * FROM config cn WHERE grouping = '$group'";
        $list = $this->db->get_list($query);
        $resp = array();
        foreach($list as $l){
            $value = $l['value'];
            if($l['value'] == 'true'){
                $value = true;
            } else if($l['value'] == 'false'){
                $value = false;
            }
            $resp[$l['code']] = $value;
        }
        return $resp;
    }

    public function findByCodePlain($code){
        $query="SELECT * FROM config cn WHERE code = '$code'";
        $obj = $this->db->get_one($query);
        $resp = array();
        if(count($obj) > 0){
            $json = $obj['value'];
            $resp = json_decode($json, true);
        }
        return $resp;
    }
	
	public function updateAll(){
		if($this->get_request_method() != "POST") $this->response('',406);
		$config = json_decode(file_get_contents("php://input"),true);
		if(!isset($config))$this->responseInvalidParam();
        if($this->conf->DEMO_VERSION){
            $m = array("status" => "failed", "msg" => "Ops, this is demo version", "data" => null);
            $this->show_response($m);
            return;
        }
        $keys = array_keys($config);
        $obj_array = array();
        foreach($keys as $key){
            array_push($obj_array, array('code' => $key, 'value' => $config[$key]));
        }
		$column_names = array('code', 'value');
		$table_name = 'config';
		$pk = 'code';
		$resp = $this->db->update_array_pk_str($pk, $obj_array, $column_names, $table_name);
		$this->show_response($resp);
	}
	
}	
?>