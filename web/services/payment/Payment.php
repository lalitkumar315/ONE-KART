<?php
require_once(realpath(dirname(__FILE__) . "/../tools/rest.php"));

class Payment extends REST{

    private $mysqli = NULL;
    private $db = NULL;
    private $product_order_detail   = NULL;
    private $config 			    = NULL;
    private $payment_conf           = NULL;
    private $currency               = NULL;
    private $PAYPAL_URL_SANDBOX     = "https://api-m.sandbox.paypal.com";
    private $PAYPAL_URL_LIVE        = "https://api-m.paypal.com";

    public function __construct($db) {
        parent::__construct();
        $this->db = $db;
        $this->mysqli = $db->mysqli;
        $this->product_order_detail = new ProductOrderDetail($this->db);
        $this->config = new Config($this->db);

        $this->payment_conf = $this->config->findByGroupPlain('PAYMENT');
        $this->currency = $this->config->findByCodePlain('GENERAL')['currency'];
    }

    public function showPage(){
        if($this->get_request_method() != "GET") $this->response('',406);
        if(!isset($this->_request['id']) && !isset($this->_request['code']))$this->responseInvalidParam();
        $product_order_id = isset($this->_request['id']) ? (int)$this->_request['id'] : 0;
        $product_order_code = isset($this->_request['code']) ? $this->_request['code'] : '';
        if($product_order_id > 0){
            $product_order = $this->db->get_one("SELECT * FROM product_order po WHERE po.id=$product_order_id");
        } else {
            $product_order = $this->db->get_one("SELECT * FROM product_order po WHERE po.code='$product_order_code'");
            if(sizeof($product_order) != 0) $product_order_id = $product_order['id'];
        }

        $page_type = 'READY';
        $msg = '';

        if(sizeof($product_order) == 0){
            $page_type = 'NOT_FOUND';
            $msg = 'Order not found';
        } else if($product_order['payment_status'] == 'PAID'){
            $page_type = 'PAID';
            $msg = 'Order already paid';
        } else if($product_order['payment_status'] == 'REFUND'){
            $page_type = 'REFUND';
            $msg = 'Order has been refunded';
        } else {
            $product_order['currency'] = $this->currency;
            $order_detail = $this->product_order_detail->findAllByOrderIdPlain($product_order_id);
            $resp_stock = $this->product_order_detail->checkAvailableProductOrderDetail($order_detail);
            $product_order['amount'] = $product_order['total_fees'] . ' ' . $this->currency;
            if($resp_stock['status'] == 'failed'){
                $page_type = 'OUT_OF_STOCK';
                $msg = str_replace("\\n", '<br>', $resp_stock['msg']);
            }
            $product_order['amount'] = $product_order['total_fees'] . ' ' . $this->currency;
        }

        $payment_paypal     = json_decode($this->payment_conf['PAYMENT_PAYPAL'], true);
        $payment_razorpay   = json_decode($this->payment_conf['PAYMENT_RAZORPAY'], true);
        $payment_bank       = json_decode($this->payment_conf['PAYMENT_BANK'], true);

        if($page_type ==  'READY'){
            $template = file_get_contents(realpath(dirname(__FILE__) . "/template.html"));
        } else {
            $template = file_get_contents(realpath(dirname(__FILE__) . "/status.html"));
        }

        $product_order['paypal_active']     = $payment_paypal['active'];
        $product_order['paypal_client_id']  = $payment_paypal['client_id'];
        $product_order['razorpay_active']   = $payment_razorpay['active'];
        $product_order['razorpay_key_id']   = $payment_razorpay['key_id'];
        $product_order['bank_active']       = $payment_bank['active'];
        $product_order['bank_instruction']  = addslashes($payment_bank['instruction']);

        $product_order['page_type']         = $page_type;
        $product_order['msg']               = $msg;
        $product_order = array_reverse($product_order);
        foreach ($product_order as $key => $value) {
            $tagToReplace = "[@$key]";
            $template = str_replace($tagToReplace, $value, $template);
        }
        echo $template;
        exit;
    }

    public function validateCompletePayment($order){
        $payment = $order['payment'];
        if($payment == 'PAYPAL'){
            return $this->validatePaypal($order);
        } else if($payment == 'RAZORPAY'){
            return $this->validateRazorPay($order);
        } else {
            return false;
        }
    }

    public function validatePaypal($order){
        $paypal_conf = json_decode($this->payment_conf['PAYMENT_PAYPAL'], true);
        $url = $this->PAYPAL_URL_SANDBOX;
        if($paypal_conf['mode'] ==  'LIVE') {
            $url = $this->PAYPAL_URL_LIVE;
        }
        $token = $this->getPaypalToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'/v2/checkout/orders/'.$order['payment_data']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');


        $headers = array();
        $headers[] = 'Authorization: Bearer '.$token;
        $headers[] = 'Cache-Control: no-cache';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($result, true);
        if(!array_key_exists('status', $resp) || !array_key_exists('purchase_units', $resp)) false;
        $custom_id = $resp['purchase_units'][0]['custom_id'];
        if($order['code'] != $custom_id){
            return false;
        };
        return array_key_exists('status',$resp) && $resp['status'] == 'COMPLETED';
    }

    public function validateRazorPay($order){
        $razorpay_conf = json_decode($this->payment_conf['PAYMENT_RAZORPAY'], true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders/'.$order['payment_data']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_conf['key_id'] . ':' . $razorpay_conf['key_secret']);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($result, true);
        if(!array_key_exists('receipt', $resp) || !array_key_exists('status', $resp)) false;
        if($order['code'] != $resp['receipt']){
            return false;
        };
        return array_key_exists('status',$resp) && $resp['status'] == 'paid';
    }


    public function getPaypalToken(){
        $paypal_conf = json_decode($this->payment_conf['PAYMENT_PAYPAL'], true);
        $url = $this->PAYPAL_URL_SANDBOX;
        if($paypal_conf['mode'] ==  'LIVE') $url = $this->PAYPAL_URL_LIVE;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_USERPWD, $paypal_conf['client_id'] . ':' . $paypal_conf['secret']);

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Accept-Language: en_US';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($result, true);
        if(is_array($resp) && !array_key_exists("access_token",$resp)){
            return null;
        }
        return $resp['access_token'];
    }

    public function generatePaypalOrderId($order){
        $paypal_conf = json_decode($this->payment_conf['PAYMENT_PAYPAL'], true);
        $url = $this->PAYPAL_URL_SANDBOX;
        if($paypal_conf['mode'] ==  'LIVE') $url = $this->PAYPAL_URL_LIVE;

        $token = $this->getPaypalToken();
        $data = array(
            'intent' => 'CAPTURE', 'purchase_units' => array(
                array(
                    'description' => $order['code'], 'custom_id' => $order['code'],
                    'amount' => array('currency_code' => $this->currency, 'value' => $order['total_fees'])
                )
            ),
            'application_context' => array(
                'shipping_preference' => 'NO_SHIPPING', 'brand_name' => 'OneKart App'
            )
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url.'/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json($data));

        $headers = array();
        $headers[] = 'Authorization: Bearer '.$token;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $resp = json_decode($result, true);
        if (curl_errno($ch)) {
            return array('status' => 'failed', 'msg' => 'UNKNOWN ERROR');
        }
        curl_close($ch);
        if(array_key_exists("id", $resp)){
            return array('status' => 'success', 'msg' => '', 'data' => $resp['id']);
        } else if(array_key_exists("details", $resp) && sizeof($resp['details']) > 0){
            return array('status' => 'failed', 'msg' => $resp['details'][0]['description']);
        } else {
            return array('status' => 'failed', 'msg' => 'UNKNOWN ERROR');
        }
    }

    public function generateRazorPayOrderId($order){
        $razorpay_conf = json_decode($this->payment_conf['PAYMENT_RAZORPAY'], true);
        $amount = $order['total_fees'] * 100;
        $data = array('amount' => $amount, 'currency' => $this->currency, 'receipt' => $order['code']);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json($data));
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_conf['key_id'] . ':' . $razorpay_conf['key_secret']);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $resp = json_decode($result, true);
        if (curl_errno($ch)) {
            return array('status' => 'failed', 'msg' => 'UNKNOWN ERROR');
        }
        curl_close($ch);
        if(array_key_exists("id", $resp)){
            return array('status' => 'success', 'msg' => '', 'data' => $resp['id']);
        } else {
            return array('status' => 'failed', 'msg' => $resp['error']['description']);
        }
    }



}
?>