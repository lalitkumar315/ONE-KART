<?php
require_once(realpath(dirname(__FILE__) . "/../tools/rest.php"));
require_once(realpath(dirname(__FILE__) . "/../tools/mail_handler.php"));

class ProductOrder extends REST{
	
	private $mysqli = NULL;
	private $db = NULL;
    private $product_order_detail = NULL;
    private $fcm = NULL;
    private $mail_handler = NULL;
    private $payment = NULL;

	public function __construct($db) {
		parent::__construct();
		$this->db = $db;
		$this->mysqli = $db->mysqli;
        $this->product_order_detail = new ProductOrderDetail($this->db);
        $this->fcm = new Fcm($this->db);
        $this->mail_handler = new MailHandler($this->db);
        $this->payment = new Payment($this->db);
    }
	
	public function findAll(){
		if($this->get_request_method() != "GET") $this->response('',406); 
		$query="SELECT * FROM product_order po ORDER BY po.id DESC";
		$this->show_response($this->db->get_list($query));
	}

    public function findOne(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $query="SELECT distinct * FROM product_order po WHERE po.id=$id";
        $this->show_response($this->db->get_one($query));
    }

    public function findOnePlain($id){
        $query="SELECT * FROM product_order po WHERE po.id=$id";
        return $this->db->get_one($query);
    }

    public function findOneByCodePlain($code){
        $query="SELECT * FROM product_order po WHERE po.code='$code'";
        return $this->db->get_one($query);
    }
	
	public function findAllByPage(){
		if($this->get_request_method() != "GET") $this->response('',406);
		if(!isset($this->_request['limit']) || !isset($this->_request['page']))$this->responseInvalidParam();
		$limit = (int)$this->_request['limit'];
		$offset = ((int)$this->_request['page']) - 1;
		$q = (isset($this->_request['q'])) ? ($this->_request['q']) : "";
        if($q != ""){
            $query=	"SELECT DISTINCT * FROM product_order po "
                    ."WHERE buyer REGEXP '$q' OR code REGEXP '$q' OR address REGEXP '$q' OR email REGEXP '$q' OR phone REGEXP '$q' OR comment REGEXP '$q' OR shipping REGEXP '$q' "
                    ."ORDER BY po.id DESC LIMIT $limit OFFSET $offset";
        } else {
		    $query="SELECT DISTINCT * FROM product_order po ORDER BY po.id DESC LIMIT $limit OFFSET $offset";
        }
		$this->show_response($this->db->get_list($query));
	}

    public function findAllByCodePlain($ids){
        $query="SELECT DISTINCT * FROM product_order po WHERE po.id IN (". $ids .")";
        return $this->db->get_list($query);
    }
	
	public function allCount(){
		if($this->get_request_method() != "GET") $this->response('',406);
		$query="SELECT COUNT(DISTINCT po.id) FROM product_order po";
		$this->show_response_plain($this->db->get_count($query));
	}

    public function insertOne(){
        if($this->get_request_method() != "POST") $this->response('', 406);
        $data = json_decode(file_get_contents("php://input"), true);
        if(!isset($data)) $this->responseInvalidParam();
        $resp = $this->insertOnePlain($data);
        $this->show_response($resp);
    }

    public function insertOnePlain($data){
        $column_names = array(
            'code', 'buyer', 'address', 'email', 'shipping', 'shipping_location', 'shipping_rate', 'date_ship',
            'phone', 'comment', 'status', 'total_fees', 'payment', 'payment_status', 'payment_data', 'tax', 'serial',
            'created_at', 'last_update'
        );
        $table_name = 'product_order';
        $pk = 'id';
        $data['code'] = $this->getRandomCode();
        $resp = $this->db->post_one($data, $pk, $column_names, $table_name);
        return $resp;
    }

    public function updateOne(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['id'])) $this->responseInvalidParam();
        $id = (int)$data['id'];
        $column_names = array(
            'buyer', 'address', 'email', 'shipping', 'shipping_location', 'shipping_rate', 'date_ship',
            'phone', 'comment', 'status', 'total_fees', 'tax', 'serial',
            'created_at', 'last_update'
        );
        $table_name = 'product_order';
        $pk = 'id';
        $order = $this->findOnePlain($id);
        if($order['total_fees'] != $data[$table_name]['total_fees']){
            array_push($column_names, 'payment', 'payment_status', 'payment_data');
            $data[$table_name]['payment'] = '';
            $data[$table_name]['payment_status'] = '';
            $data[$table_name]['payment_data'] = '';
        }
        $this->show_response($this->db->post_update($id, $data, $pk, $column_names, $table_name));
    }

    public function updateStatusOrder(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['id'])) $this->responseInvalidParam();
        $id = (int)$data['id'];
        $column_names = array('status', 'last_update');
        $table_name = 'product_order';
        $pk = 'id';
        $order  = $this->findOnePlain($id);
        $resp = $this->db->post_update($id, $data, $pk, $column_names, $table_name);
        // send email
        if($resp['status'] == 'success'){
            if($order['status'] == 'WAITING' && $data[$table_name]['status'] == 'PROCESSED'){
                $this->mail_handler->curlEmailOrderProcess($id);
            } else {
                $this->mail_handler->curlEmailOrderUpdate($id);
            }
            $this->sendNotifProductOrder($resp['data']['product_order']);
        }
        $this->show_response($resp);
    }

    public function updatePayment(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['id'])) $this->responseInvalidParam();
        $id     = (int)$data['id'];
        $order  = $this->findOnePlain($id);
        if(empty($order)){
            $this->show_response(array('status' => 'failed', 'msg' => "Order not found"));
        }
        $order_detail = $this->product_order_detail->findAllByOrderIdPlain($order['id']);
        $column_names = array('payment_status');
        $table_name = 'product_order';
        $pk = 'id';

        // validate payment
        $is_payment_valid = $this->payment->validateCompletePayment($order);
        if(!$is_payment_valid){
            $this->show_response(array('status' => 'failed', 'msg' => "Payment not valid"));
        }

        // decrease product stock, send email and send notif
        $resp_process_order = $this->decreaseStockAndPaidPlain($order, $order_detail, false);
        if($resp_process_order['status'] == 'failed'){
            $this->show_response($resp_process_order);
        }
        $payment_status = 'PAID';
        $updated_obj = array("id" => $id, $table_name => array("payment_status" => $payment_status));
        $resp_db = $this->db->post_update($id, $updated_obj, $pk, $column_names, $table_name);

        // send email
        if($resp_process_order['status'] == 'success'){
            $this->mail_handler->curlEmailOrderUpdate($order['id']);
        }

        $this->show_response($resp_db);
    }

    public function deleteOne(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $table_name = 'product_order';
        $pk = 'id';
        $this->show_response($this->db->delete_one($id, $pk, $table_name));
    }

    public function deleteOnePlain($id){
        $table_name = 'product_order';
        $pk = 'id';
        return $this->db->delete_one($id, $pk, $table_name);
    }

    public function countByStatusPlain($status){
        $query = "SELECT COUNT(DISTINCT po.id) FROM product_order po WHERE po.status='$status' ";
        return $this->db->get_count($query);
    }

    public function decreaseStockAndPaid(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['product_order']) || !isset($data['product_order_detail'])) {
            $this->responseInvalidParam();
        }
        $order          = $data['product_order'];
        $order_detail   = $data['product_order_detail'];

        $resp = $this->decreaseStockAndPaidPlain($order, $order_detail, true);

        // send email
        if($resp['status'] == 'success'){
            $this->mail_handler->curlEmailOrderUpdate($order['id']);
        }

        $this->show_response($resp);
    }

    public function increaseStockAndRefund(){
        if($this->get_request_method() != "POST") $this->response('',406);
        $data = json_decode(file_get_contents("php://input"),true);
        if(!isset($data['id']) || !isset($data['returned'])) {
            $this->responseInvalidParam();
        }
        $id = (int)$data['id'];
        $returned = $data['returned'];

        $order = $this->findOnePlain($id);
        $order_detail = $this->product_order_detail->findAllByOrderIdPlain($id);

        $resp = $this->increaseStockAndRefundPlain($order, $order_detail, $returned);

        // send email
        if($resp['status'] == 'success'){
            $this->mail_handler->curlEmailOrderUpdate($order['id']);
        }

        $this->show_response($resp);
    }

    public function decreaseStockAndPaidPlain($order, $order_detail, $by_admin){
        $resp_od = $this->product_order_detail->checkAvailableProductOrderDetail($order_detail);
        if($resp_od['status'] == 'success'){
            // process product stock
            foreach($resp_od['data'] as $od){
                $val = (int)$od['stock'] - (int)$od['amount'];
                $product_id = $od['product_id'];
                if($val > 0){
                    $query = "UPDATE product SET stock=$val WHERE id=$product_id";
                } else {
                    $query = "UPDATE product SET stock=$val, status='OUT OF STOCK' WHERE id=$product_id";
                }
                $this->db->execute_query($query);
            }
            // update order status
            $order_id = $order['id'];
            $query_ = "UPDATE product_order SET payment_status='PAID' WHERE id=$order_id";
            if($by_admin){
                $query_ = "UPDATE product_order SET payment_status='PAID', payment='BY ADMIN' WHERE id=$order_id";
            }
            $this->db->execute_query($query_);

            // send notification
            $order['payment_status'] = 'PAID';
            $this->sendNotifProductOrderPaid($order);
        }
        return $resp_od;
    }

    public function increaseStockAndRefundPlain($order, $order_detail, $returned){
        if($returned){
            // process product stock
            foreach($order_detail as $od){
                $val = (int)$od['amount'];
                $product_id = $od['product_id'];
                $query = "UPDATE product SET stock = stock + $val WHERE id=$product_id";
                $this->db->execute_query($query);
            }
            // update order status
            $order_id = $order['id'];
            $query_ = "UPDATE product_order SET status='CANCEL', payment_status='REFUND' WHERE id=$order_id";
            $this->db->execute_query($query_);

            // send notification
            $order['payment_status'] = 'REFUND';
            $this->sendNotifProductOrderPaid($order);
        }
        return array('status' => 'success', 'msg' => '');
    }

    public function getPaymentOrderId(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id']) || !isset($this->_request['type'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $type = $this->_request['type'];

        // filter payment
        if($type != 'PAYPAL' && $type != 'RAZORPAY' &&$type != 'BANK') $this->responseInvalidParam();

        $order = $this->findOnePlain($id);
        $payment_data = $order['payment_data'];
        $payment = $order['payment'];
        $payment_status = $order['payment_status'];

        // filter payment status
        if($payment_status == 'PAID'){
            $this->show_response(array('status' => 'failed', 'msg' => "Payment already done"));
        }

        if($payment_data == null || $payment_data == '' || $payment != $type){
            $payment_data = '';
            $order_resp = array('status' => '');
            if($type == 'PAYPAL'){
                $order_resp = $this->payment->generatePaypalOrderId($order);
            } else if ($type == 'RAZORPAY'){
                $order_resp = $this->payment->generateRazorPayOrderId($order);
            } else if ($type == 'BANK'){
                $order_resp = array('status' => 'success', 'data' => 'BANK');
            }
            if($order_resp['status'] == 'success'){
                $payment_data = $order_resp['data'];
            } else if($order_resp['status'] == 'failed'){
                $this->show_response(array('status' => 'failed', 'msg' => $order_resp['msg']));
            }
            if($payment_data == ''){
                $this->show_response(array('status' => 'failed', 'msg' => "Failed create order"));
            }
            $query = "UPDATE product_order SET payment_data='$payment_data', payment='$type' WHERE id=$id";
            $this->db->execute_query($query);
        }
        $this->show_response(array('status' => 'success', 'data' => array('order_id' => $payment_data)));
    }

    private function sendNotifProductOrderPaid($order){
        if($order['serial'] != null){
            $regid = $this->fcm->findBySerial($order['serial']);
            $data = array(
                'title' => 'Order Payment Status Changed',
                'content' => 'Your order ' . $order['code'] .' Payment Status has been change to ' . $order['payment_status'],
                'type' => 'PROCESS_ORDER',
                'code' => $order['code'],
                'status' => $order['payment_status']
            );
            $this->fcm->sendPushNotification($regid['regid'], $data);
        }
    }

    private function sendNotifProductOrder($order){
        if($order['serial'] != null){
            $regid = $this->fcm->findBySerial($order['serial']);
            $data = array(
                'title' => 'Order Status Changed',
                'content' => 'Your order ' . $order['code'] .' status has been change to ' . $order['status'],
                'type' => 'PROCESS_ORDER',
                'code' => $order['code'],
                'status' => $order['status']
            );
            $this->fcm->sendPushNotification($regid['regid'], $data);
        }
    }

    // function to generate unique id
    private function getRandomCode() {
        $size = 10; // must > 6
        $alpha_key = '';
        $alpha_key2 = '';
        $keys = range('A', 'Z');
        for ($i = 0; $i < 2; $i++) {
            $alpha_key .= $keys[array_rand($keys)];
            $alpha_key2 .= $keys[array_rand($keys)];
        }
        $length = $size - 5;
        $key = '';
        $keys = range(0, 9);
        for ($i = 0; $i < $length; $i++) {
            $key .= $keys[array_rand($keys)];
        }
        $final_key = $alpha_key . $key . $alpha_key2;

        // make sure code is unique in database
        $query = "SELECT COUNT(DISTINCT po.id) FROM product_order po WHERE po.code='$final_key' ";
        $num_rows = $this->db->get_count($query);

        if($num_rows > 0) {
            return $this->getRandomCode();
        } else {
            return $final_key;
        }
    }
}	
?>