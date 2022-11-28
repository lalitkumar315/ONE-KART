<?php
require_once(realpath(dirname(__FILE__) . "/../table/Config.php"));
require_once(realpath(dirname(__FILE__) . "/../table/ProductOrder.php"));
require_once(realpath(dirname(__FILE__) . "/../table/ProductOrderDetail.php"));
require_once(realpath(dirname(__FILE__) . "/../conf.php"));
require_once(realpath(dirname(__FILE__) . "/rest.php"));

require_once("mailer/PHPMailer.php");
require_once("mailer/Exception.php");
require_once("mailer/SMTP.php");
use PHPMailer\PHPMailer\PHPMailer;

class Mail extends REST {

    private $db = NULL;
    private $config = NULL;
    private $config_arr = NULL;
    private $product_order = NULL;
    private $product_order_detail = NULL;
    private $email_conf = NULL;

    public function __construct($db) {
        parent::__construct();
        $this->db = $db;
        $this->config = new Config($this->db);
        $this->product_order = new ProductOrder($this->db);
        $this->product_order_detail = new ProductOrderDetail($this->db);

        // init config
        $this->email_conf = $this->config->findByGroupPlain('EMAIL');
    }

    public function restEmail() {
        if ($this->get_request_method() != "GET") $this->response('', 406);
        if (!isset($this->_request['id'])) $this->responseInvalidParam();
        if (!isset($this->_request['type'])) $this->responseInvalidParam();
        $id = (int)$this->_request['id'];
        $type = $this->_request['type'];
        $debug = false;
        if (isset($this->_request['debug'])) {
            $d = (int)$this->_request['debug'];
            $debug = ($d == 1);
        }

        $email_order_conf = json_decode($this->email_conf['EMAIL_ORDER'], true);
        // filter with config
        if ($type == "NEW_ORDER") {
            if (!$email_order_conf['notif_order']) return;
        } else if ($type == "ORDER_PROCESS") {
            if (!$email_order_conf['notif_order_process']) return;
        } else if (!$type == "ORDER_UPDATE") {
            return;
        }

        $this->sendOrderEmail($id, $email_order_conf, $type, $debug);
    }

    private function sendOrderEmail($order_id, $email_order_conf, $type, $debug) {
        // find object order order_details
        $order = $this->product_order->findOnePlain($order_id);
        $order_details = $this->product_order_detail->findAllByOrderIdPlain($order_id);

        $email_smtp_conf = json_decode($this->email_conf['EMAIL_SMTP'], true);
        $email_text_conf = json_decode($this->email_conf['EMAIL_TEXT'], true);
        $email_receiver_arr = $email_order_conf['bcc_receiver'];
        //if (sizeof($email_receiver_arr) == 0) return;

        try {
            $mailer = new PHPMailer();
            $mailer->IsSMTP();
            $mailer->SMTPAuth = true;
            // SMTP connection will not close after each email sent, reduces SMTP overhead
            $mailer->SMTPKeepAlive = true;

            $mailer->Host       = $email_smtp_conf['host'];
            $mailer->Port       = $email_smtp_conf['port'];
            $mailer->Username   = $email_smtp_conf['email'];
            $mailer->Password   = $email_smtp_conf['password'];

            $subject_title = "";
            if ($type == "NEW_ORDER") {
                $subject_title = $email_text_conf['subject_email_new_order'];
                try {
                    if (sizeof($email_receiver_arr) != 0) {
                        foreach ($email_receiver_arr as $row) {
                            $mailer->addBCC($row, $row);
                        }
                    };
                } catch (Exception $e) {
                }
            } else if ($type == "ORDER_PROCESS") {
                $subject_title = $email_text_conf['subject_email_order_processed'];
            } else if ($type == "ORDER_UPDATE") {
                $subject_title = $email_text_conf['subject_email_order_updated'];
            }

            $subject = '[' . $order['code'] . '] ' . $subject_title;
            $mailer->addCustomHeader('X-Entity-Ref-ID', $order['code']);
            $mailer->Subject = $subject;

            $mailer->SetFrom($email_smtp_conf['email']);
            $mailer->addReplyTo($email_order_conf['reply_to']);
            $mailer->addAddress($order['email'], $order['email']);
            $template = $this->getEmailOrderTemplate($order, $order_details, $email_text_conf, $type);
            $mailer->msgHTML($template);

            $error = 'Message sent!';
            if (!$mailer->Send()) {
                $error = 'Mail error: ' . $mailer->ErrorInfo;
            }
            if ($debug) echo $error;

        } catch (Exception $e) {
        }
    }

    private function getEmailOrderTemplate($order, $order_details, $email_text_conf, $type) {

        $currency = $this->config->findByCodePlain('GENERAL')['currency'];
        $order_item_row = "";

        // calculate total
        $price_total = 0;
        $amount_total = 0;
        $index = 1;

        foreach ($order_details as $od) {
            $price_total = 0;
            $item_row = file_get_contents(realpath(dirname(__FILE__) . "/template/order_item_row.html"));
            $price_total += $od['price_item'] * $od['amount'];
            $amount_total += $price_total;
            $od['index'] = $index;
            $od['price_total'] = number_format($price_total, 2, '.', '');
            foreach ($od as $key => $value) {
                $tagToReplace = "[@$key]";
                $item_row = str_replace($tagToReplace, $value, $item_row);
            }
            $order_item_row = $order_item_row . $item_row;
            $index++;
        }

        $price_tax = ($order['tax'] / 100) * $amount_total;
        $price_tax_formatted = number_format($price_tax, 2, '.', '');
        $price_sub_total_formatted = number_format($amount_total, 2, '.', '');
        $price_total = number_format(($amount_total + $price_tax), 2, '.', '');

        // binding data
        $order_template = file_get_contents(realpath(dirname(__FILE__) . "/template/order_template.html"));
        $order['date_ship'] = date("d M y", floatval($order['date_ship']) / 1000);
        $order['created_at'] = date("d M y", floatval($order['created_at']) / 1000);
        $order['last_update'] = date("d M y", floatval($order['last_update']) / 1000);
        foreach ($order as $key => $value) {
            $tagToReplace = "[@$key]";
            $order_template = str_replace($tagToReplace, $value, $order_template);
        }

        // put row view into $order_template
        $title = "";
        if ($type == "NEW_ORDER") {
            $title = $email_text_conf['title_report_new_order'];
        } else if ($type == "ORDER_PROCESS") {
            $title = $email_text_conf['title_report_order_processed'];
        } else if ($type == "ORDER_UPDATE") {
            $title = $email_text_conf['title_report_order_updated'];
        }
        $order_template = str_replace('[@report_title]', $title, $order_template);
        $order_template = str_replace('[@order_item_row]', $order_item_row, $order_template);

        $order_template = str_replace('[@conf_currency]', $currency, $order_template);
        $order_template = str_replace('[@price_tax_formatted]', $price_tax_formatted, $order_template);
        $order_template = str_replace('[@price_sub_total_formatted]', $price_sub_total_formatted, $order_template);
        $order_template = str_replace('[@price_total]', $price_total, $order_template);

        return $order_template;
    }

    private function getOrderProcessedTemplate($order, $config_arr, $type) {
        $order_template = "";
        return $order_template;
    }

    private function getValue($data, $code) {
        foreach ($data as $d) {
            if ($d['code'] == $code) {
                return $d['value'];
            }
        }
    }

    public function testEmailFunction() {
        if ($this->get_request_method() != "GET") $this->response('', 406);
        if (!isset($this->_request['email'])) $this->responseInvalidParam();
        $email = $this->_request['email'];
        $email_smtp_conf = json_decode($this->email_conf['EMAIL_SMTP'], true);

        try {
            $mailer = new PHPMailer();
            $mailer->IsSMTP();
            $mailer->SMTPAuth = true;
            // SMTP connection will not close after each email sent, reduces SMTP overhead
            $mailer->SMTPKeepAlive = true;

            $mailer->Host       = $email_smtp_conf['host'];
            $mailer->Port       = $email_smtp_conf['port'];
            $mailer->Username   = $email_smtp_conf['email'];
            $mailer->Password   = $email_smtp_conf['password'];

            $subject = '[TEST] Email OneKart';
            $mailer->addCustomHeader('X-Entity-Ref-ID', $subject);
            $mailer->Subject = $subject;

            $mailer->SetFrom($email_smtp_conf['email'], 'OneKart');
            $mailer->addReplyTo($email_smtp_conf['email']);
            $mailer->addAddress($email, '');
            $template = "This is test email content";
            $mailer->msgHTML($template);
            $error = 'Message sent to : '.$email;
            if (!$mailer->Send()) {
                $error = 'Mail error: ' . $mailer->ErrorInfo;
            }
            echo $error;

        } catch (Exception $e) {
        }
    }

}

?>