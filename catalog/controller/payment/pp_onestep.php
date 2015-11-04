<?php

class ControllerPaymentPPStandard2 extends Controller {


    public function index() {
        
        $this->language->load('payment/pp_standard2');

        $this->load->model('payment/pp_standard2');
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_id = intval($order_id);
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            $this->redirect($this->url->link('checkout/fail', '', 'SSL'));
        }

        $products = $this->model_checkout_order->getOrderProducts($order_id);
        $order_total = $this->model_checkout_order->getOrderTotal($order_id);
        $shipping = 2;
        $language = 'en';
        $data = array(
            'METHOD' => 'SetExpressCheckout',
            'RETURNURL' => $this->url->link('payment/pp_standard2/dopay', '', 'SSL'),
            'CANCELURL' => $this->url->link('checkout/cart', '', 'SSL'),
           
            'REQCONFIRMSHIPPING' => 0,
            'NOSHIPPING' => $shipping,
            'ALLOWNOTE' => $this->config->get('pp_standard2_allow_note'),
            'LOCALECODE' => $language,
            'LANDINGPAGE' => 'Login',
            //'HDRIMG' => $this->model_tool_image->resize($this->config->get('pp_standard2_logo'), 790, 90),
            'HDRBORDERCOLOR' => $this->config->get('pp_standard2_border_colour'),
            'HDRBACKCOLOR' => $this->config->get('pp_standard2_header_colour'),
            'PAYFLOWCOLOR' => $this->config->get('pp_standard2_page_colour'),
            'CHANNELTYPE' => 'Merchant',
            
        );

        
        $data = array_merge($data, $this->paymentRequestInfo($order_info, $products, $order_total));
        
        $merchant = $this->getMerchant($order_info);

        $data = array_merge($data, $merchant);
        
        $result = $this->model_payment_pp_standard2->call($data);

        
        /**
         * If a failed PayPal setup happens, handle it.
         */
        if (!isset($result['TOKEN'])) {
            $this->session->data['error'] = $result['L_LONGMESSAGE0'];
            /**
             * Unable to add error message to user as the session errors/success are not
             * used on the cart or checkout pages - need to be added?
             * If PayPal debug log is off then still log error to normal error log.
             */
            if ($this->config->get('pp_standard2_debug')) {
                $this->log->write(serialize($result));
            }

            $this->redirect($this->url->link('checkout/checkout', '', 'SSL'));
        }

        $this->session->data['paypal']['token'] = $result['TOKEN'];
        
        $to_url = '';
        if ($this->config->get('pp_standard2_test') == 1) {
             $to_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $result['TOKEN'];
        } else {
            $to_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $result['TOKEN'];
        }
        $data['button_continue_action'] = $to_url;
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/pp_standard2.tpl')) {
            $template = $this->config->get('config_template') . '/template/payment/pp_standard2.tpl';
        } else {
            $template = 'default/template/payment/pp_standard2.tpl';
        }

        $this->response->setOutput($this->load->view($template, $data));
    }

    public function dopay() {
        $this->language->load('payment/pp_standard2');
        $this->load->model('payment/pp_standard2');
        $this->load->model('checkout/order');

        if(!isset($this->session->data['paypal']) ||  !isset($this->session->data['paypal']['token'])){
            $this->redirect($this->url->link('checkout/cart', '', 'SSL'));
        }
        
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            $this->redirect($this->url->link('checkout/fail', '', 'SSL'));
        }

        // Validate minimum quantity requirments.
        $products = $this->model_checkout_order->getOrderProducts($order_id);
        $order_total = $this->model_checkout_order->getOrderTotal($order_id);
        
        $data = array(
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN' => $this->session->data['paypal']['token'],
        );
        $merchant = $this->getMerchant($order_info);
        $data = array_merge($data, $merchant);
        $result = $this->model_payment_pp_standard2->call($data);
        
        if(isset($result['PAYERID'])){
            $this->session->data['paypal']['payerid'] = $result['PAYERID'];
        }else if($this->request->request['PayerID']){
             $this->session->data['paypal']['payerid'] = $this->request->request['PayerID'];
        }
        
        
        
        $paypal_data = array(
            'TOKEN' => $this->session->data['paypal']['token'],
            'PAYERID' => $this->session->data['paypal']['payerid'],
            'METHOD' => 'DoExpressCheckoutPayment',
            'PAYMENTREQUEST_0_NOTIFYURL' => $this->url->link('payment/pp_standard2/ipn', '', 'SSL'),
            'RETURNFMFDETAILS' => 1,
        );

        $paypal_data = array_merge($paypal_data, $this->paymentRequestInfo($order_info,$products,$order_total));
        $merchant = $this->getMerchant($order_info);
        $paypal_data = array_merge($paypal_data, $merchant);
        
        //$ship_data = $this->paymentShipAddress($order_info);
        //$paypal_data = array_merge($paypal_data, $ship_data);
        
        $result = $this->model_payment_pp_standard2->call($paypal_data);
        unset($this->session->data['paypal']);
        unset($this->session->data['shipping_address']);
        unset($this->session->data['payment_method']);
        if (strtolower($result['ACK']) == 'success') {
            //handle order status
            switch ($result['PAYMENTINFO_0_PAYMENTSTATUS']) {
                case 'Canceled_Reversal':
                    $order_status_id = $this->config->get('pp_standard2_canceled_reversal_status_id');
                    break;
                case 'Completed':
                    $order_status_id = $this->config->get('pp_standard2_completed_status_id');
                    break;
                case 'Denied':
                    $order_status_id = $this->config->get('pp_standard2_denied_status_id');
                    break;
                case 'Expired':
                    $order_status_id = $this->config->get('pp_standard2_expired_status_id');
                    break;
                case 'Failed':
                    $order_status_id = $this->config->get('pp_standard2_failed_status_id');
                    break;
                case 'Pending':
                    $order_status_id = $this->config->get('pp_standard2_pending_status_id');
                    break;
                case 'Processed':
                    $order_status_id = $this->config->get('pp_standard2_processed_status_id');
                    break;
                case 'Refunded':
                    $order_status_id = $this->config->get('pp_standard2_refunded_status_id');
                    break;
                case 'Reversed':
                    $order_status_id = $this->config->get('pp_standard2_reversed_status_id');
                    break;
                case 'Voided':
                    $order_status_id = $this->config->get('pp_standard2_voided_status_id');
                    break;
            }

            $this->model_checkout_order->update($order_id, $order_status_id);
            if($order_info['parent_id '] == 0 && $order_info['is_parent'] == 1){
                $children = $this->model_checkout_order->getOrderChildren($order_id);
                foreach($children as $_item){
                    $this->model_checkout_order->update($_item['order_id'], $order_status_id);
                }
            }

            //add order to paypal table
            $paypal_order_data = array(
                'order_id' => $order_id,
                'capture_status' => ($this->config->get('pp_standard2_method') == 'Sale' ? 'Complete' : 'NotComplete'),
                'currency_code' => $result['PAYMENTINFO_0_CURRENCYCODE'],
                'authorization_id' => $result['PAYMENTINFO_0_TRANSACTIONID'],
                'total' => $result['PAYMENTINFO_0_AMT'],
            );
            $paypal_order_id = $this->model_payment_pp_standard2->addOrder($paypal_order_data);

            //add transaction to paypal transaction table
            $paypal_transaction_data = array(
                'paypal_order_id' => $paypal_order_id,
                'transaction_id' => $result['PAYMENTINFO_0_TRANSACTIONID'],
                'parent_transaction_id' => '',
                'note' => '',
                'msgsubid' => '',
                'receipt_id' => (isset($result['PAYMENTINFO_0_RECEIPTID']) ? $result['PAYMENTINFO_0_RECEIPTID'] : ''),
                'payment_type' => $result['PAYMENTINFO_0_PAYMENTTYPE'],
                'payment_status' => $result['PAYMENTINFO_0_PAYMENTSTATUS'],
                'pending_reason' => $result['PAYMENTINFO_0_PENDINGREASON'],
                'transaction_entity' => ($this->config->get('pp_standard2_method') == 'Sale' ? 'payment' : 'auth'),
                'amount' => $result['PAYMENTINFO_0_AMT'],
                'debug_data' => json_encode($result),
            );
            $this->model_payment_pp_standard2->addTransaction($paypal_transaction_data);

            $this->redirect($this->url->link('checkout/success','','SSL'));
        } else {
            $this->session->data['error'] = $result['L_LONGMESSAGE0'];
            $this->redirect($this->url->link('checkout/fail', '', 'SSL'));
        }
    }

    public function ipn() {
        $this->load->model('payment/pp_standard2');
        $this->load->model('account/recurring');
        $this->load->model('checkout/order');
        $request = 'cmd=_notify-validate';

        foreach ($_POST as $key => $value) {
            $request .= '&' . $key . '=' . urlencode(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
        }

        if ($this->config->get('pp_standard2_test') == 1) {
            $curl = curl_init('https://www.sandbox.paypal.com/cgi-bin/webscr');
        } else {
            $curl = curl_init('https://www.paypal.com/cgi-bin/webscr');
        }

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = trim(curl_exec($curl));

        if (!$response) {
            $this->model_payment_pp_standard2->log(array('error' => curl_error($curl), 'error_no' => curl_errno($curl)), 'Curl failed');
        }

        $this->model_payment_pp_standard2->log(array('request' => $request, 'response' => $response), 'IPN data');
        $response = strtoupper($response);
        if ($response == "VERIFIED") {

            $this->log->write((isset($this->request->post['transaction_entity']) ? $this->request->post['transaction_entity'] : ''));

            if (isset($this->request->post['txn_id'])) {
                $transaction = $this->model_payment_pp_standard2->getTransactionRow($this->request->post['txn_id']);
            } else {
                $transaction = false;
            }

            if (isset($this->request->post['parent_txn_id'])) {
                $parent_transaction = $this->model_payment_pp_standard2->getTransactionRow($this->request->post['parent_txn_id']);
            } else {
                $parent_transaction = false;
            }

            if ($transaction) {
                //transaction exists, check for cleared payment or updates etc
                $this->log->write('Transaction exists');

                //if the transaction is pending but the new status is completed
                if ($transaction['payment_status'] != $this->request->post['payment_status']) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "paypal_order_transaction` SET `payment_status` = '" . $this->request->post['payment_status'] . "' WHERE `transaction_id` = '" . $this->db->escape($transaction['transaction_id']) . "' LIMIT 1");
                } elseif ($transaction['payment_status'] == 'Pending' && ($transaction['pending_reason'] != $this->request->post['pending_reason'])) {
                    //payment is still pending but the pending reason has changed, update it.
                    $this->db->query("UPDATE `" . DB_PREFIX . "paypal_order_transaction` SET `pending_reason` = '" . $this->request->post['pending_reason'] . "' WHERE `transaction_id` = '" . $this->db->escape($transaction['transaction_id']) . "' LIMIT 1");
                }
                
                 //handle order status
                switch ($this->request->post['payment_status']) {
                    case 'Canceled_Reversal':
                        $order_status_id = $this->config->get('pp_standard2_canceled_reversal_status_id');
                        break;
                    case 'Completed':
                        $order_status_id = $this->config->get('pp_standard2_completed_status_id');
                        break;
                    case 'Denied':
                        $order_status_id = $this->config->get('pp_standard2_denied_status_id');
                        break;
                    case 'Expired':
                        $order_status_id = $this->config->get('pp_standard2_expired_status_id');
                        break;
                    case 'Failed':
                        $order_status_id = $this->config->get('pp_standard2_failed_status_id');
                        break;
                    case 'Pending':
                        $order_status_id = $this->config->get('pp_standard2_pending_status_id');
                        break;
                    case 'Processed':
                        $order_status_id = $this->config->get('pp_standard2_processed_status_id');
                        break;
                    case 'Refunded':
                        $order_status_id = $this->config->get('pp_standard2_refunded_status_id');
                        break;
                    case 'Reversed':
                        $order_status_id = $this->config->get('pp_standard2_reversed_status_id');
                        break;
                    case 'Voided':
                        $order_status_id = $this->config->get('pp_standard2_voided_status_id');
                        break;
                }
                $order_no = $this->request->post['invoice'];
                $order_info = $this->model_checkout_order->getOrderByNumber($order_no);
                if($order_info){
                    $order_id = $order_info['order_id'];
                    if(isset($this->request->post['payment_status']) && in_array($this->request->post['payment_status'],array('Pending','Processed','Completed'))){
                        if($order_status_id){
                            $this->model_checkout_order->update($order_id, $order_status_id);
                            if($order_info['parent_id '] == 0 && $order_info['is_parent'] == 1){
                                $children = $this->model_checkout_order->getOrderChildren($order_id);
                                foreach($children as $_item){
                                    $this->model_checkout_order->update($_item['order_id'], $order_status_id);
                                }
                            }
                        }
                    } else {
                        $refund_money = $this->request->post['mc_gross'];
                        if($refund_money<0){
                            $_refund_money = abs($refund_money);
                            $_refund_currency = $this->request->post['mc_currency'];
                            $_paypal_txn_id = $this->request->post['txn_id'];
                            $_notice = '';
                            //$this->model_checkout_order->refund($order_id, $_refund_money,$_refund_currency,$_paypal_txn_id,$_notice);
                        }
                    }
                }
                
            } else {
                $this->log->write('Transaction does not exist');
                if ($parent_transaction) {
                    $this->log->write('Parent transaction exists');
                    //parent transaction exists
                    //insert new related transaction
                    $transaction = array(
                        'paypal_order_id' => $parent_transaction['paypal_order_id'],
                        'transaction_id' => $this->request->post['txn_id'],
                        'parent_transaction_id' => $this->request->post['parent_txn_id'],
                        'note' => '',
                        'msgsubid' => '',
                        'receipt_id' => (isset($this->request->post['receipt_id']) ? $this->request->post['receipt_id'] : ''),
                        'payment_type' => (isset($this->request->post['payment_type']) ? $this->request->post['payment_type'] : ''),
                        'payment_status' => (isset($this->request->post['payment_status']) ? $this->request->post['payment_status'] : ''),
                        'pending_reason' => (isset($this->request->post['pending_reason']) ? $this->request->post['pending_reason'] : ''),
                        'amount' => $this->request->post['mc_gross'],
                        'debug_data' => json_encode($this->request->post),
                        'transaction_entity' => (isset($this->request->post['transaction_entity']) ? $this->request->post['transaction_entity'] : ''),
                    );

                    $this->model_payment_pp_standard2->addTransaction($transaction);

                    /**
                     * If there has been a refund, log this against the parent transaction.
                     */
                    if (isset($this->request->post['payment_status']) && $this->request->post['payment_status'] == 'Refunded') {
                        if (($this->request->post['mc_gross'] * -1) == $parent_transaction['amount']) {
                            $this->db->query("UPDATE `" . DB_PREFIX . "paypal_order_transaction` SET `payment_status` = 'Refunded' WHERE `transaction_id` = '" . $this->db->escape($parent_transaction['transaction_id']) . "' LIMIT 1");
                        } else {
                            $this->db->query("UPDATE `" . DB_PREFIX . "paypal_order_transaction` SET `payment_status` = 'Partially-Refunded' WHERE `transaction_id` = '" . $this->db->escape($parent_transaction['transaction_id']) . "' LIMIT 1");
                        }
                        $order_no = $this->request->post['invoice'];
                        $order_info = $this->model_checkout_order->getOrderByNumber($order_no);
                        if($order_info){
                            $order_id = $order_info['order_id'];
                            $refund_money = $this->request->post['mc_gross'];
                            if($refund_money<0){
                                $_refund_money = abs($refund_money);
                                $_refund_currency = $this->request->post['mc_currency'];
                                $_paypal_txn_id = $this->request->post['txn_id'];
                                $_notice = '';
                                //$this->model_checkout_order->refund($order_id, $_refund_money,$_refund_currency,$_paypal_txn_id,$_notice);
                            }
                        }
                    }

                    /**
                     * If the capture payment is now complete
                     */
                    if (isset($this->request->post['auth_status']) && $this->request->post['auth_status'] == 'Completed' && $parent_transaction['payment_status'] == 'Pending') {
                        $captured = round($this->model_payment_pp_standard2->totalCaptured($parent_transaction['paypal_order_id']), 2);
                        $refunded = round($this->model_payment_pp_standard2->totalRefundedOrder($parent_transaction['paypal_order_id']), 2);
                        $remaining = round($parent_transaction['amount'] - $captured + $refunded, 2);

                        $this->log->write('Captured: ' . $captured);
                        $this->log->write('Refunded: ' . $refunded);
                        $this->log->write('Remaining: ' . $remaining);

                        if ($remaining > 0.00) {
                            $transaction = array(
                                'paypal_order_id' => $parent_transaction['paypal_order_id'],
                                'transaction_id' => '',
                                'parent_transaction_id' => $this->request->post['parent_txn_id'],
                                'note' => '',
                                'msgsubid' => '',
                                'receipt_id' => '',
                                'payment_type' => '',
                                'payment_status' => 'Void',
                                'pending_reason' => '',
                                'amount' => '',
                                'debug_data' => 'Voided after capture',
                                'transaction_entity' => 'auth'
                            );

                            $this->model_payment_pp_standard2->addTransaction($transaction);
                        }

                        $this->model_payment_pp_standard2->updateOrder('Complete', $parent_transaction['order_id']);
                    }
                } else {
                    //parent transaction doesn't exists, need to investigate?
                    $this->log->write('Parent transaction not found');
                }
            }

            /*
             * Subscription payments
             *
             * profile ID should always exist if its a recurring payment transaction.
             *
             * also the reference will match a recurring payment ID
             */
            if (isset($this->request->post['txn_type'])) {
                //payment
                if ($this->request->post['txn_type'] == 'recurring_payment') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `amount` = '" . (float) $this->request->post['amount'] . "', `type` = '1'");

                        //as there was a payment the profile is active, ensure it is set to active (may be been suspended before)
                        if ($profile['status'] != 1) {
                            $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 2 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "'");
                        }
                    }
                }

                //suspend
                if ($this->request->post['txn_type'] == 'recurring_payment_suspended') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '6'");
                        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 3 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "' LIMIT 1");
                    }
                }

                //suspend due to max failed
                if ($this->request->post['txn_type'] == 'recurring_payment_suspended_due_to_max_failed_payment') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '7'");
                        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 3 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "' LIMIT 1");
                    }
                }

                //payment failed
                if ($this->request->post['txn_type'] == 'recurring_payment_failed') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '4'");
                    }
                }

                //outstanding payment failed
                if ($this->request->post['txn_type'] == 'recurring_payment_outstanding_payment_failed') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '8'");
                    }
                }

                //outstanding payment
                if ($this->request->post['txn_type'] == 'recurring_payment_outstanding_payment') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `amount` = '" . (float) $this->request->post['amount'] . "', `type` = '2'");

                        //as there was a payment the profile is active, ensure it is set to active (may be been suspended before)
                        if ($profile['status'] != 1) {
                            $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 2 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "'");
                        }
                    }
                }

                //created
                if ($this->request->post['txn_type'] == 'recurring_payment_profile_created') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '0'");

                        if ($profile['status'] != 1) {
                            $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 2 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "'");
                        }
                    }
                }

                //cancelled
                if ($this->request->post['txn_type'] == 'recurring_payment_profile_cancel') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false && $profile['status'] != 3) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '5'");
                        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 4 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "' LIMIT 1");
                    }
                }

                //skipped
                if ($this->request->post['txn_type'] == 'recurring_payment_skipped') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '3'");
                    }
                }

                //expired
                if ($this->request->post['txn_type'] == 'recurring_payment_expired') {
                    $profile = $this->model_account_recurring->getProfileByRef($this->request->post['recurring_payment_id']);

                    if ($profile != false) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "', `created` = NOW(), `type` = '9'");
                        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = 5 WHERE `order_recurring_id` = '" . (int) $profile['order_recurring_id'] . "' LIMIT 1");
                    }
                }
            }
        } elseif ($response == "INVALID") {
            $this->model_payment_pp_standard2->log(array('IPN was invalid'), 'IPN fail');
        } else {
            $this->log->write('string unknown ');
        }

        header("HTTP/1.1 200 Ok");
    }


    protected function paymentRequestInfo($order_info, $order_product, $order_total) {

        $data['PAYMENTREQUEST_0_SHIPPINGAMT'] = '';
        $data['PAYMENTREQUEST_0_CURRENCYCODE'] = $order_info['currency_code'];
        $data['PAYMENTREQUEST_0_PAYMENTACTION'] = $this->config->get('pp_standard2_method');

        $i = 0;
        $item_total = 0;

        foreach ($order_product as $item) {

            $item_price = $this->currency->format($item['price'], false, false, false);

            $data['L_PAYMENTREQUEST_0_NAME' . $i] = $item['name'];
            $data['L_PAYMENTREQUEST_0_NUMBER' . $i] = $item['model'];
            $data['L_PAYMENTREQUEST_0_AMT' . $i] = $item_price;
            $item_total += round($item_price * $item['quantity'], 2);
            $data['L_PAYMENTREQUEST_0_QTY' . $i] = $item['quantity'];

            $data['L_PAYMENTREQUEST_0_ITEMURL' . $i] = $this->url->link('product/product', 'product_id=' . $item['product_id']);

            $i++;
        }


        foreach ($order_total as $total_row) {
            if (!in_array($total_row['code'], array('total', 'sub_total'))) {
                if ($total_row['value'] != 0) {
                    $item_price = $this->currency->format($total_row['value'], false, false, false);
                    $data['L_PAYMENTREQUEST_0_NUMBER' . $i] = $total_row['code'];
                    $data['L_PAYMENTREQUEST_0_NAME' . $i] = $total_row['title'];
                    $data['L_PAYMENTREQUEST_0_AMT' . $i] = $this->currency->format($total_row['value'], false, false, false);
                    $data['L_PAYMENTREQUEST_0_QTY' . $i] = 1;
                    $item_total = round($item_total + $item_price, 2);
                    $i++;
                }
            }
        }

        $data['PAYMENTREQUEST_0_ITEMAMT'] = round($item_total, 2);
        $data['PAYMENTREQUEST_0_AMT'] = round($item_total, 2);
        $data['PAYMENTREQUEST_0_INVNUM'] = $order_info['order_number'];

        return $data;
    }

    protected function getMerchant() {
        $settings = array(
            'USER' => $this->config->get('pp_standard2_username'),
            'PWD' => $this->config->get('pp_standard2_password'),
            'SIGNATURE' => $this->config->get('pp_standard2_signature'),
            'VERSION' => '106.0',
            'BUTTONSOURCE' => 'my',
        );
        return $settings;
    }
    
    protected function paymentShipAddress($order_info){
        $ship_data = array(
            'PAYMENTREQUEST_0_SHIPTONAME' => $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'],
            'PAYMENTREQUEST_0_SHIPTOSTREET' => $order_info['shipping_address_1'],
            'PAYMENTREQUEST_0_SHIPTOSTREET2' => $order_info['shipping_address_2'],
            'PAYMENTREQUEST_0_SHIPTOCITY' => $order_info['shipping_city'],
            'PAYMENTREQUEST_0_SHIPTOSTATE' => $order_info['shipping_zone'],
            'PAYMENTREQUEST_0_SHIPTOZIP' => $order_info['shipping_postcode'],
            'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE' => $order_info['shipping_iso_code_2'],
            'PAYMENTREQUEST_0_SHIPTOPHONENUM' => $order_info['shipping_phone'],
        );
        
        return $ship_data;
    }



}

?>