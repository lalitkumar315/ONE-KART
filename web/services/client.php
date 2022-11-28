<?php
require_once(realpath(dirname(__FILE__) . "/tools/rest.php"));
require_once(realpath(dirname(__FILE__) . "/tools/mail_handler.php"));

/*
 * This class handle all communication with Android Client
 */
class CLIENT extends REST{

    private $mysqli = NULL;
    private $db = NULL;
    private $product 				= NULL;
    private $product_category		= NULL;
    private $product_order			= NULL;
    private $product_order_detail	= NULL;
    private $product_image 			= NULL;
    private $category 				= NULL;
    private $user 					= NULL;
    private $fcm 					= NULL;
    private $news_info 				= NULL;
    private $currency 				= NULL;
    private $shipping 				= NULL;
    private $config 				= NULL;
    private $mail_handler           = NULL;
	public $conf                    = NULL;

    public function __construct($db) {
        parent::__construct();
        $this->db = $db;
        $this->mysqli = $db->mysqli;
        $this->user = new User($this->db);
        $this->product = new Product($this->db);
        $this->product_category = new ProductCategory($this->db);
        $this->product_order = new ProductOrder($this->db);
        $this->product_order_detail = new ProductOrderDetail($this->db);
        $this->product_image = new ProductImage($this->db);
        $this->category = new Category($this->db);
        $this->fcm = new Fcm($this->db);
        $this->news_info = new NewsInfo($this->db);
        $this->currency = new Currency($this->db);
        $this->shipping = new Shipping($this->db);
        $this->config = new Config($this->db);
        $this->app_version = new AppVersion($this->db);
        $this->mail_handler = new MailHandler($this->db);
		$this->conf = new CONF();
    }

    /* Cek status version and get some config data */
    public function info(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['version'])) $this->responseInvalidParam();
        $version = (int)$this->_request['version'];
        $query = "SELECT COUNT(DISTINCT a.id) FROM app_version a WHERE version_code = $version AND active = 1";
        $resp_ver = $this->db->get_count($query);
        $config = $this->config->findByCodePlain('GENERAL');
        $info = array(
            "active" => ($resp_ver > 0),
            "tax" => $config['tax'],
            "currency" => $config['currency'],
        );
        $response = array( "status" => "success", "info" => $info );
        $this->show_response($response);
    }

    /* Response featured News Info */
    public function findAllFeaturedNewsInfo(){
        if($this->get_request_method() != "GET") $this->response('',406);
        $featured_news = $this->news_info->findAllFeatured();
        $object_res = array();
        foreach ($featured_news as $r){
            unset($r['full_content']);
            array_push($object_res, $r);
        }
		$response = array(
            'status' => 'success', 'news_infos' => $object_res
        );
        $this->show_response($response);
    }

    /* Response All News Info */
    public function findAllNewsInfo(){
        if($this->get_request_method() != "GET") $this->response('',406);
        $limit = isset($this->_request['count']) ? ((int)$this->_request['count']) : 10;
        $page = isset($this->_request['page']) ? ((int)$this->_request['page']) : 1;
        $q = isset($this->_request['q']) && $this->_request['q'] != null ? ($this->_request['q']) : "";

        $offset = ($page * $limit) - $limit;
        $count_total = $this->news_info->allCountPlain($q, 1);
        $news_infos = $this->news_info->findAllByPagePlain($limit, $offset, $q, 1);

        $object_res = array();
        foreach ($news_infos as $r){
            unset($r['full_content']);
            array_push($object_res, $r);
        }
        $count = count($news_infos);
        $response = array(
            'status' => 'success', 'count' => $count, 'count_total' => $count_total, 'pages' => $page, 'news_infos' => $object_res
        );
        $this->show_response($response);
    }

    /* Response All Product */
    public function findAllProduct(){
        if($this->get_request_method() != "GET") $this->response('',406);
        $limit = isset($this->_request['count']) ? ((int)$this->_request['count']) : 10;
        $page = isset($this->_request['page']) ? ((int)$this->_request['page']) : 1;
        $q = isset($this->_request['q']) && $this->_request['q'] != null ? ($this->_request['q']) : "";
        $category_id = isset($this->_request['category_id']) && $this->_request['category_id'] != null ? ((int)$this->_request['category_id']) : -1;
        $column = isset($this->_request['col']) ? $this->_request['col'] : 'id';
        $order = isset($this->_request['ord']) ? $this->_request['ord'] : 'DESC';

        $offset = ($page * $limit) - $limit;
        $count_total = $this->product->allCountPlainForClient($q, $category_id);
        $products = $this->product->findAllByPagePlainForClient($limit, $offset, $q, $category_id, $column, $order);

        $object_res = array();
        foreach ($products as $r){
            unset($r['description']);
            array_push($object_res, $r);
        }
        $count = count($products);
        $response = array(
            'status' => 'success', 'count' => $count, 'count_total' => $count_total, 'pages' => $page, 'products' => $object_res
        );
        $this->show_response($response);
    }

    /* Response Details Product */
    public function findProductDetails(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $product = $this->product->findOnePlain($id);
		if(count($product) > 0){
			$categories = $this->category->getAllByProductIdPlain($id);
			$product_images = $this->product_image->findAllByProductIdPlain($id);
			$product['categories'] = $categories;
			$product['product_images'] = $product_images;	
			$response = array( 'status' => 'success', 'product' => $product );
		} else {
			$response = array( 'status' => 'failed', 'product' => null );
		}
        $this->show_response($response);
    }
	
    /* Response Details News Info */
    public function findNewsDetails(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $news_info = $this->news_info->findOnePlain($id);
		$response['status'] = 'success';
		$response['news_info'] = $news_info;
        $this->show_response($response);
    }	

    /* Response All Category */
    public function findAllCategory(){
        if($this->get_request_method() != "GET") $this->response('',406);
        $categories = $this->category->findAllForClient();
        $response = array(
            'status' => 'success', 'categories' => $categories
        );
        $this->show_response($response);
    }


    /* Response All Shipping */
    public function findAllActiveShipping(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['q']))$this->responseInvalidParam();
        $limit = 20;
        $offset = 0;
        $q = $this->_request['q'];
        $shipping = $this->shipping->findAllForClient($q, $limit, $offset);
        $response = array(
            'status' => 'success', 'shipping' => $shipping
        );
        $this->show_response($response);
    }

    /* Response Product Order By code */
    public function findProductOrder(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['code'])) $this->responseInvalidParam();
        $code = $this->_request['code'];
        $product_order = $this->product_order->findOneByCodePlain($code);
        if(empty($product_order)){
            $this->show_response(array('status' => 'failed', 'msg' => "Order not found"));
        } else {
            $response['status'] = 'success';
            $response['product_order'] = $this->getOrderMinimal($product_order);
            $this->show_response($response);
        }
    }

    /* Response All News POST method*/
    public function findAllProductOrderPOST() {
        if ($this->get_request_method() != "POST") $this->response('', 406);
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['ids'])) $this->responseInvalidParam();

        // checking security code
        if(!isset($this->_header['Security']) || $this->_header['Security'] != $this->conf->SECURITY_CODE){
            $m = array('status' => 'failed', 'msg' => 'Invalid security code', 'data' => null);
            $this->show_response($m);
        }

        $ids = $data['ids'];
        if(is_array($ids) && sizeof($ids) == 0){
            $this->show_response(array());
        }
        $ids_flat = join(', ', $ids);
        $orders = $this->product_order->findAllByCodePlain($ids_flat);
        $result = [];
        foreach ($orders as $od){
            array_push($result, $this->getOrderMinimal($od));
        }
        $this->show_response($result);
    }

    private function getOrderMinimal($order){
        $order_minimal = array();
        $order_minimal['id'] = $order['id'];
        $order_minimal['code'] = $order['code'];
        $order_minimal['buyer'] = $order['buyer'];
        $order_minimal['total_fees'] = $order['total_fees'];
        $order_minimal['status'] = $order['status'];
        $order_minimal['payment_status'] = $order['payment_status'];
        $order_minimal['created_at'] = $order['created_at'];
        $order_minimal['last_update'] = $order['last_update'];
        $order_minimal['cart_list'] = $this->product_order_detail->findAllByOrderIdPlain($order['id']);
        return $order_minimal;
    }

    /* Submit Product Order */
    public function submitProductOrder(){
        if($this->get_request_method() != "POST") $this->response('', 406);
        $data = json_decode(file_get_contents("php://input"), true);
        if(!isset($data) || !isset($data['product_order']) || !isset($data['product_order_detail'])) $this->responseInvalidParam();

        // checking security code
        if(!isset($this->_header['Security']) || $this->_header['Security'] != $this->conf->SECURITY_CODE){
            $m = array('status' => 'failed', 'msg' => 'Invalid security code', 'data' => null);
            $this->show_response($m);
            return;
        }

        // check stock product
        $order_detail = $data['product_order_detail'];
        $resp_stock = $this->product_order_detail->checkAvailableProductOrderDetail($order_detail);
        if($resp_stock['status'] == 'failed'){
            $this->show_response($resp_stock);
        }

        // submit order
        $resp_po = $this->product_order->insertOnePlain($data['product_order']);
        if($resp_po['status'] == "success"){
            $order_id = (int)$resp_po['data']['id'];
            $resp_pod = $this->product_order_detail->insertAllPlain($order_id, $order_detail);
            if($resp_pod['status'] == 'success'){
                $status = 'success';
                $msg = 'Success submit product order';
                // send email
                $this->mail_handler->curlEmailOrder($order_id);
            } else {
                $this->product_order->deleteOnePlain($order_id);
                $status = 'failed';
                $msg = 'Failed when submit order.';
            }
        } else {
            $status = 'failed';
            $msg = 'Failed when submit order';
        }
        $m = array('status' => $status, 'msg' => $msg, 'data' => $resp_po['data']);
        $this->show_response($m);
    }

    private function getValue($data, $code){
        foreach($data as $d){
            if($d['code'] == $code){
                return $d['value'];
            }
        }
    }
}
?>