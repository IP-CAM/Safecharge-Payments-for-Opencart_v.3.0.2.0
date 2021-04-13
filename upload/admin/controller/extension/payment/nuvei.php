<?php

if (!session_id()) {
    session_start();
}

require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'NUVEI_CLASS.php';
require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_version_resolver.php';

class ControllerExtensionPaymentNuvei extends Controller
{ 
	private $prefix                 = '';
	private $plugin_settings		= array();
	private $notify_url             = '';
	
    public function install()
    {
        $q =
            "CREATE TABLE IF NOT EXISTS `nuvei_transactions` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `orderId` int(10) unsigned NOT NULL,
                `data` text NOT NULL,
                
                PRIMARY KEY (`id`),
                KEY `orderId` (`orderId`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        
        $this->db->query($q);
        
        // change the default value for order_status_id in order table to 1
//        $q = "EXPLAIN " . DB_PREFIX . "order";
//        $resp = $this->db->query($q);
//        
//        if(isset($resp->rows) && !empty($resp->rows)) {
//            foreach($resp->rows as $field) {
//                if($field['Field'] == 'order_status_id') {
//                    if(intval($field['Default']) == 0) {
//                        $q = "ALTER TABLE `". DB_PREFIX ."order` CHANGE `order_status_id` "
//							. "`order_status_id` INT(11) NOT NULL DEFAULT '1';";
//                        $this->db->query($q);
//                    }
//                    
//                    break;
//                }
//            }
//        }
    }
    
    public function uninstall()
    {
        // change the default value for order_status_id in order table to 1
//        $q = "EXPLAIN " . DB_PREFIX . "order";
//        $resp = $this->db->query($q);
//        
//        if(isset($resp->rows) && !empty($resp->rows)) {
//            foreach($resp->rows as $field) {
//                if($field['Field'] == 'order_status_id') {
//                    if(intval($field['Default']) == 1) {
//                        $q = "ALTER TABLE `". DB_PREFIX ."order` CHANGE `order_status_id` `order_status_id` INT(11) NOT NULL DEFAULT '0';";
//                        $this->db->query($q);
//                    }
//                    
//                    break;
//                }
//            }
//        }
    }
    
	public function index()
    {
		$this->load->model('setting/setting');
        $this->load->model('sale/order');
		
		$this->prefix           = NuveiVersionResolver::get_settings_prefix();
		$this->plugin_settings  = $this->model_setting_setting->getSetting(trim($this->prefix, '_'));
        
        if(!empty($this->plugin_settings)) {
            $_SESSION['nuvei_create_logs']  = $this->plugin_settings[$this->prefix . 'create_logs'];
            $_SESSION['nuvei_test_mode']    = $this->plugin_settings[$this->prefix . 'test_mode'];
        }
		
        // detect ajax call
        if(
            isset($_SERVER['HTTP_X_REQUESTED_WITH'], $this->request->post['action'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
            && (isset($this->request->post['orderId']) || isset($this->request->post['refId']))
        ) {
            $this->ajax_call();
            exit;
        }
        
        $token_name		= NuveiVersionResolver::get_token_name();
        $ctr_file_path	= NuveiVersionResolver::get_ctr_file_path();
        $data           = $this->load->language($ctr_file_path); // add translation in the data
				
        // when save the settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            // Validate
            $save_post = true;
            
            if ($this->user->hasPermission('modify', $ctr_file_path)) {
                $data['error_permission'] = false;
            }
            else {
                $save_post = false;
            }

            if ($this->request->post[$this->prefix . 'merchantId']) {
            	$data['error_merchantId'] = false;
            }
            else {
                $save_post = false;
            }

            if ($this->request->post[$this->prefix . 'merchantSiteId']) {
            	$data['error_merchantSiteId'] = false;
            }
            else {
                $save_post = false;
            }

            if ($this->request->post[$this->prefix . 'secret']) {
            	$data['error_secret'] = false;
            }
            else {
                $save_post = false;
            }
            // Validate END
            
            // if all is ok - save settings
            if($save_post) {
                $resp = $this->model_setting_setting->editSetting(
                    trim($this->prefix, '_'),
                    $this->request->post
                );
                
                $this->session->data['success'] = $data['text_success'];
            }
        }
        // no post - no errors, set them to false
        else {
            $data['error_permission']       = false;
            $data['error_merchantId']       = false;
            $data['error_merchantId']       = false;
            $data['error_merchantSiteId']   = false;
            $data['error_secret']           = false;
        }
        
        // get settings
        $xtsettings = $this->model_setting_setting->getSetting(trim($this->prefix, '_'));
		
		$data['breadcrumbs'][] = array(
			'text' => $data['text_home'],
			'href' => $this->url->link(
                'common/dashboard',
                $token_name . '=' . $this->session->data[$token_name],
                true
            ),
            'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $data[NuveiVersionResolver::get_adm_ctr_text_extension_key()],
			'href' => $this->url->link(
                NuveiVersionResolver::get_adm_ctr_extensions_url(),
                $token_name . '=' . $this->session->data[$token_name] . '&type=payment',
                true
            ),
            'separator' => ' :: '
		);
        
        $data['breadcrumbs'][] = array(
            'text' => $data['heading_title'],
			'href' => $this->url->link(
                $this->request->get['route'],
                $token_name . '=' . $this->session->data[$token_name],
                true
            ),
            'separator' => ' :: '
   		);

		$data['action'] = $this->url->link(
            $this->request->get['route'],
            $token_name . '=' . $this->session->data[$token_name],
            true
        );
		
        $data['cancel'] = $this->url->link(
            NuveiVersionResolver::get_adm_ctr_extensions_url(),
            $token_name . '=' . $this->session->data[$token_name],
            true
        );

        # check for POST and set local variables by it
        $settings_fields = array(
            'merchantId',
            'merchantSiteId',
            'secret',
            'hash',
            'payment_action',
            'test_mode',
            'use_upos',
            'force_http',
            'create_logs',
            'total',
            'geo_zone_id',
            'status',
            'sort_order',
        );
        
        foreach($settings_fields as $field) {
            if (isset($this->request->post[$this->prefix . $field])) {
                $data[$this->prefix . $field] = $this->request->post[$this->prefix . $field];
            }
            else {
                $data[$this->prefix . $field] = $this->config->get($this->prefix . $field);
            }
        }
        # check for POST and set local variables by it END
        
        // set statuses manually
        $statuses = array(
            5   => 'order_status_id',
            1   => 'pending_status_id',
            7   => 'canceled_status_id',
            10  => 'failed_status_id',
            11  => 'refunded_status_id',
//            13  => 'chargeback_status_id',
        );
        
        foreach($statuses as $id => $name) {
            if (isset($this->request->post[$this->prefix . $name])) {
                $data[$this->prefix . $name] = $this->request->post[$this->prefix . $name];
            }
            elseif (isset($xtsettings[$this->prefix . $name])) {
                $data[$this->prefix . $name] = $this->config->get($this->prefix . $name); 
            }
            else {
                $data[$this->prefix . $name] = $id;
            }
        }
        // set statuses manually END
        
        // get all statuses
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        // get all geo-zones
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }
        elseif (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = $this->session->data['error_warning'];
            unset($this->session->data['error_warning']);
        }
        // check for POST and set local variables by it END
        
        // set output
        $data['header']			= $this->load->controller('common/header');
        $data['column_left']	= $this->load->controller('common/column_left');
        $data['footer']			= $this->load->controller('common/footer');
        
        // load common php template and then pass it to the real template
        // as single variable. The form is same for both versions
        ob_start();
        require DIR_TEMPLATE . $ctr_file_path . '.php';
        $sc_form['sc_form'] = ob_get_clean(); // the template of OC wants array
        
        $this->response->setOutput($this->load->view($ctr_file_path, $sc_form));
	}

    /**
     * Function ajax_call
     * Process Ajax calls here.
     */
    private function ajax_call()
    {
        try {
            $action             = $this->request->post['action'];
            $order_id           = (int) $this->request->post['orderId'];
            $this->notify_url   = $this->url->link(
                NuveiVersionResolver::get_ctr_file_path()
                . '/callback&nuvei_create_logs=' . $_SESSION['nuvei_create_logs']
                . '&action=' . $action . '&order_id=' . $order_id
            );
            
            $this->notify_url = str_replace('admin/', '', $this->notify_url);
        
            if($this->plugin_settings[$this->prefix . 'force_http'] == 'yes') {
                $this->notify_url = str_replace('https:', 'http:', $this->notify_url);
            }
        }
        catch (Exception $ex) {
            NUVEI_CLASS::create_log($ex->getMessage(), 'ajax_call() Exception');
            
			echo json_encode(array(
				'status'    => 0,
				'msg'		=> $ex->getMessage()
			));
            exit;
        }
        
        switch ($action) {
            case 'refund':
                $this->order_refund($order_id);
                exit;
                
            case 'refundManual':
                $this->order_refund($order_id, true);
                exit;
                
            case 'deleteManualRefund':
                $this->delete_refund(intval($this->request->post['refId']));
                exit;
                
            case 'void':
            case 'settle':
                $this->order_void_settle($order_id);
                exit;
                
            default:
                echo json_encode(array('status' => 0, 'msg' => 'Unknown order action: ' . $action));
                exit;
        }
    }
    
    private function order_refund($order_id, $is_manual = false)
    {
		$request_amount = round((float) $this->request->post['amount'], 2);
		
		NUVEI_CLASS::create_log(
			array(
				'order_id'	=> $order_id,
				'is_manual'	=> $is_manual,
			),
			'order_refund()'
		);
		
        if($request_amount <= 0) {
            echo json_encode(array(
                'status'    => 0,
                'msg'       => 'The Refund Amount must be greater than 0!')
            );
            exit;
        }
        
        $data                   = $this->model_sale_order->getOrder($order_id);
        $remaining_ref_amound   = $data['total'];
        $last_sale_tr           = array();
        
        NUVEI_CLASS::create_log($data['payment_custom_field'], 'refund payment_custom_field');
        
        // get the refunds
        foreach(array_reverse($data['payment_custom_field']) as $tr_data) {
            if(
                in_array($tr_data['transactionType'], array('Refund', 'Credit'))
                && 'approved' == $tr_data['status']
            ) {
                $remaining_ref_amound -= $tr_data['totalAmount'];
            }
            
            if(
                empty($last_salte_tr)
                && in_array($tr_data['transactionType'], array('Sale', 'Settle'))
                && 'approved' == $tr_data['status']
            ) {
                $last_sale_tr = $tr_data;
            }
        }
        
        if(round($remaining_ref_amound, 2) < $request_amount) {
            echo json_encode(array(
                'status'    => 0,
                'msg'       => 'Refunds sum exceeds Order Amount')
            );
            exit;
        }
        
        if($is_manual) {
            $order_status = $this->plugin_settings[$this->prefix . 'refunded_status_id']; // refunded
            
            $data['payment_custom_field'][] = array(
                'status'                => 'approved',
                'clientUniqueId'        => $order_id . '_' . $request_amount . '_' . date('YmdHis') . '_' . uniqid(),
                'transactionType'       => 'Refund',
                'transactionId'         => '',
                'relatedTransactionId'  => $last_sale_tr['transactionId'],
                'userPaymentOptionId'   => '',
                'authCode'              => '',
                'totalAmount'           => $request_amount,
                'currency'              => $last_sale_tr['currency'],
                'paymentMethod'         => $last_sale_tr['paymentMethod'],
                'responseTimeStamp'     => date('Y-m-d.H:i:s'),
            );
            
            $this->db->query(
                "UPDATE " . DB_PREFIX ."order "
                . "SET payment_custom_field = '". json_encode($data['payment_custom_field']) ."' "
                . "WHERE order_id = " . $order_id
            );
            
            $this->db->query(
                "UPDATE " . DB_PREFIX ."order "
                . "SET order_status_id = {$order_status} "
                . "WHERE order_id = {$order_id};"
            );
            
            echo json_encode(array('status' => 1));
            exit;
        }
        
        $clientUniqueId = uniqid();
		$time           = date('YmdHis');
		
        $ref_parameters = array(
			'merchantId'            => $this->plugin_settings[$this->prefix . 'merchantId'],
			'merchantSiteId'        => $this->plugin_settings[$this->prefix . 'merchantSiteId'],
			'clientUniqueId'        => $order_id . '_' . $request_amount . '_' . $time . '_' . $clientUniqueId,
			'amount'                => $this->request->post['amount'],
			'currency'              => $data['currency_code'],
			'relatedTransactionId'  => $last_sale_tr['transactionId'],
			'url'                   => $this->notify_url,
			'timeStamp'             => $time,
		);
		
		$checksum_str = implode('', $ref_parameters);
        
		$checksum = hash(
			$this->plugin_settings[$this->prefix . 'hash'],
			$checksum_str . $this->plugin_settings[$this->prefix . 'secret']
		);
		
        $ref_parameters['customData']           = $request_amount; // optional - pass the Refund Amount her
		$ref_parameters['checksum']             = $checksum;
		$ref_parameters['urlDetails']           = array('notificationUrl' => $this->notify_url);
		$ref_parameters['sourceApplication']    = NuveiVersionResolver::get_source_application();
		
		if(defined('VERSION')) {
            $ref_parameters['webMasterId'] = 'OpenCart ' . VERSION;
        }
		
		$resp = NUVEI_CLASS::call_rest_api('refundTransaction', $ref_parameters);	
			
        if(!$resp) {
            echo json_encode(array(
                'status'    => 0, 
                'msg'       => 'Empty response.')
            );
            exit;
        }
        
        // in case we have message but without status
        if(!isset($resp['status']) && isset($resp['msg'])) {
            // save response message in the History
//            $msg = 'Request Refund #' . $clientUniqueId . ' problem: ' . $resp['msg'];
            
//            $this->db->query(
//                "INSERT INTO `" . DB_PREFIX ."order_history` (`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) "
//                . "VALUES (" . $order_id . ", " . $data['order_status_id']
//                . ", 0, '" . $msg . "', '" . date('Y-m-d H:i:s', time()) . "');"
//            );
            
            echo json_encode(array(
                'status'    => 0,
                'msg'       => $resp['msg']
            ));
            exit;
        }
        
//        $cpanel_url = $this->plugin_settings[$this->prefix . 'test_mode'] == 'no' ? 'cpanel.safecharge.com' : 'sandbox.safecharge.com';
//
//        $msg = '';
//        $error_note = 'Request Refund #' . $clientUniqueId . ' fail, if you want login into <i>' . $cpanel_url
//            . '</i> and refund Transaction ID ' . $last_sale_tr['transactionId'];

        if($resp === false) {
//            $msg = 'The REST API retun false. ' . $error_note;
//
//            // save response message in the History
//            $this->db->query(
//                "INSERT INTO `" . DB_PREFIX ."order_history` (`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) "
//                . "VALUES (" . $order_id . ", " . $data['order_status_id']
//                . ", 0, '" . $msg . "', '" . date('Y-m-d H:i:s', time()) . "');"
//            );
            
            echo json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->load('The request faild.')
            ));
            exit;
        }
        
        if(!is_array($resp)) {
//            $msg = 'Invalid API response. ' . $error_note;
//
//            // save response message in the History
//            $this->db->query(
//                "INSERT INTO `" . DB_PREFIX ."order_history` (`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) "
//                . "VALUES (" . $order_id . ", " . $data['order_status_id']
//                . ", 0, '" . $msg . "', '" . date('Y-m-d H:i:s', time()) . "');"
//            );
            
            echo json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->load('Invalid request response.')
            ));
            exit;
        }
        
        // the status of the request is ERROR
        if(!empty($resp['status']) && $resp['status'] == 'ERROR') {
//            $msg = 'Request ERROR - "' . $resp['reason'] .'" '. $error_note;
//            
//            // save response message in the History
//            $this->db->query(
//                "INSERT INTO `" . DB_PREFIX ."order_history` (`order_id`, `order_status_id`, `notify`, `comment`, `date_added`) "
//                . "VALUES (" . $order_id . ", " . $data['order_status_id']
//                . ", 0, '" . $msg . "', '" . date('Y-m-d H:i:s', time()) . "');"
//            );

            echo json_encode(array(
                'status'    => 0, 
                'msg'       => $resp['reason']
            ));
            exit;
        }
        
        // if request is success, we will wait for DMN
//        $msg = 'Request Refund #' . $clientUniqueId . ', was sent. Please, wait for DMN!';
        
        $order_status = 1; // pending
        
        if($remaining_ref_amound == $request_amount) {
            $order_status = 11; // refunded
        }
        
        $this->db->query(
            "UPDATE " . DB_PREFIX ."order "
            . "SET order_status_id = {$order_status} "
            . "WHERE order_id = {$order_id};"
        );
        
        echo json_encode(array(
            'status' => 1
        ));
        exit;
    }
    
    private function delete_refund($order_id)
    {
        try {
//            $resp = $this->db->query("DELETE FROM nuvei_refunds WHERE id = " . $order_id . ";");
//            echo json_encode(array('success' => $resp));
        }
        catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'msg' => $e->getMessage()
            ));
        }
        
        exit;
    }


    /**
     * Function order_void_settle
     * 
     * We use one function for both because the only
     * difference is the endpoint, all parameters are same
     */
    private function order_void_settle($order_id)
    {
        $data               = $this->model_sale_order->getOrder($order_id);
        $time               = date('YmdHis', time());
        $last_allowed_trans = array();
        
        foreach(array_reverse($data['payment_custom_field']) as $tr_data) {
            if(
                'settle' == $this->request->post['action']
                && 'Auth' == $tr_data['transactionType']
            ) {
                $last_allowed_trans = $tr_data;
                break;
            }
            
            if(
                'void' == $this->request->post['action']
                && in_array($tr_data['transactionType'], array('Auth', 'Settle', 'Sale'))
            ) {
                $last_allowed_trans = $tr_data;
                break;
            }
        }
        
        $params = array(
            'merchantId'            => $this->plugin_settings[$this->prefix . 'merchantId'],
            'merchantSiteId'        => $this->plugin_settings[$this->prefix . 'merchantSiteId'],
            'clientRequestId'       => $time . '_' . $last_allowed_trans['transactionId'],
            'clientUniqueId'        => uniqid(),
            'amount'                => number_format($data['total'], 2, '.', ''),
            'currency'              => $data['currency_code'],
            'relatedTransactionId'  => $last_allowed_trans['transactionId'],
            'urlDetails'            => array('notificationUrl' => $this->notify_url),
            'timeStamp'             => $time,
            'sourceApplication'     => NuveiVersionResolver::get_source_application(),
        );
        
        if(defined('VERSION')) {
            $params['webMasterId'] = 'OpenCart ' . VERSION;
        }
        
        $params['checksum'] = hash(
            $this->plugin_settings[$this->prefix . 'hash'],
            $params['merchantId'] 
                . $params['merchantSiteId'] 
                . $params['clientRequestId'] 
                . $params['clientUniqueId'] 
                . $params['amount'] 
                . $params['currency'] 
                . $params['relatedTransactionId'] 
                . $this->notify_url 
                . $params['timeStamp'] 
                . $this->plugin_settings[$this->prefix . 'secret']
        );
        
        $resp = NUVEI_CLASS::call_rest_api(
            'settle' == $this->request->post['action'] ? 'settleTransaction' : 'voidTransaction', 
            $params, 
            $this->plugin_settings[$this->prefix . 'test_mode']
        );
		
		if(
            !$resp || !is_array($resp)
            || @$resp['status'] == 'ERROR'
            || @$resp['transactionStatus'] == 'ERROR'
        ) {
            echo json_encode(array('status' => 0));
			exit;
        }
		
		if(@$resp['transactionStatus'] == 'DECLINED') {
            echo json_encode(array(
				'status' => 0,
				'msg' => 'Your request was Declined.'
			));
			exit;
        }
		
		echo json_encode(array('status' => 1));
		exit;
    }
    
}