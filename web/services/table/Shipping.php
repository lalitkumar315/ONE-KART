<?php
require_once(realpath(dirname(__FILE__) . "/../tools/rest.php"));

class Shipping extends REST{
	
	private $mysqli = NULL;
	private $db = NULL; 
	
	public function __construct($db) {
		parent::__construct();
		$this->db = $db;
		$this->mysqli = $db->mysqli;
    }

    public function findOne(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $resp = $this->findOnePlain($id);
        $this->show_response($resp);
    }

    public function findOnePlain($id){
        $query="SELECT * FROM shipping sh WHERE sh.id=$id LIMIT 1";
        return $this->db->get_one($query);
    }

    public function allCount(){
        if($this->get_request_method() != "GET") $this->response('',406);
        $q = "";
        if(isset($this->_request['q'])) $q = $this->_request['q'];
        if($q != ""){
            $query=	"SELECT COUNT(DISTINCT sh.id) FROM shipping sh "
                ."WHERE sh.location REGEXP '$q' OR sh.location_id REGEXP '$q' ";
        } else{
            $query="SELECT COUNT(DISTINCT sh.id) FROM shipping sh";
        }
        $this->show_response_plain($this->db->get_count($query));
    }

    public function findAllByPage(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['limit']) || !isset($this->_request['page']))$this->responseInvalidParam();
        $limit = (int)$this->_request['limit'];
        $offset = ((int)$this->_request['page']) - 1;
        $q = "";
        if(isset($this->_request['q'])) $q = $this->_request['q'];
        if($q != ""){
            $query=	"SELECT sh.* FROM shipping sh "
                ."WHERE sh.location REGEXP '$q' OR sh.location_id REGEXP '$q' "
                ."ORDER BY sh.id DESC LIMIT $limit OFFSET $offset";
        } else {
            $query= "SELECT sh.* FROM shipping sh ORDER BY sh.id DESC LIMIT $limit OFFSET $offset";
        }
        $this->show_response($this->db->get_list($query));
    }

    public function findAllForClient($q, $limit, $offset){
        $query=	"SELECT sh.* FROM shipping sh "
            ."WHERE sh.active=1 AND (sh.location REGEXP '$q' OR sh.location_id REGEXP '$q') "
            ."LIMIT $limit OFFSET $offset";
        return $this->db->get_list($query);
    }

    public function insertOne(){
        if($this->get_request_method() != "POST") $this->response('', 406);
        $data = json_decode(file_get_contents("php://input"), true);
        if(!isset($data)) $this->responseInvalidParam();
        $column_names = array(
            'location', 'location_id', 'rate_economy', 'rate_regular', 'rate_express',
            'active', 'active_eco', 'active_reg', 'active_exp'
        );
        $table_name = 'shipping';
        $resp = $this->db->post_one($data, 'id', $column_names, $table_name);
        $this->show_response($resp);
    }

    public function updateOne(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['id'])) $this->responseInvalidParam();
        $id = (int)$data['id'];
        $column_names = array(
            'location', 'location_id', 'rate_economy', 'rate_regular', 'rate_express',
            'active', 'active_eco', 'active_reg', 'active_exp'
        );
        $table_name = 'shipping';
        $pk = 'id';
        $this->show_response($this->db->post_update($id, $data, $pk, $column_names, $table_name));
    }

    public function deleteOne(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $table_name = 'shipping';
        $pk = 'id';
        $this->show_response($this->db->delete_one($id, $pk, $table_name));
    }

    public function updateAll(){
        if ($this->get_request_method() != "POST") $this->response('', 406);
        $shipping = json_decode(file_get_contents("php://input"), true);
        if (!isset($shipping)) $this->responseInvalidParam();
        $column_names = array(
            'location', 'location_id', 'rate_economy', 'rate_regular', 'rate_express',
            'active', 'active_eco', 'active_reg', 'active_exp'
        );
        $table_name = 'shipping';
        $pk = 'id';
        $resp = $this->db->update_array_pk_str($pk, $shipping, $column_names, $table_name);
        $this->show_response($resp);
    }
	
}	
?>