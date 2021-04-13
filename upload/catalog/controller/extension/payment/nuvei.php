<?php

if (!session_id()) {
    session_start();
}

require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'NUVEI_CLASS.php';
require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_version_resolver.php';

class ControllerExtensionPaymentNuvei extends Controller
{
	private $order_info;
	private $prefix	= '';
	
	public function index()
    {
		$ctr_file_path  = NuveiVersionResolver::get_ctr_file_path();
        $this->prefix   = NuveiVersionResolver::get_settings_prefix();
        
        $this->load->model('checkout/order');
		$this->load->model('account/reward');
        $this->language->load($ctr_file_path);
        
        $_SESSION['nuvei_test_mode']    = $this->config->get($this->prefix . 'test_mode');
        $_SESSION['nuvei_create_logs']  = $this->config->get($this->prefix . 'create_logs');
        $this->order_info               = $this->model_checkout_order
            ->getOrder($this->session->data['order_id']);
		
		// detect ajax call when we need new Open Order
        if(
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH']
			&& NUVEI_CLASS::get_param('route') == $ctr_file_path
		) {
            $this->ajax_call();
            exit;
        }
		
		// Open Order
        $order_data = $this->open_order();
		
		if(empty($order_data) || empty($order_data['sessionToken'])) {
			NUVEI_CLASS::create_log($order_data, 'Open Order problem with the response');
			
			if(!empty($order_data['message'])) {
				echo '<div class="alert alert-danger">'. $this->language->get($order_data['message']) .'</div>';
			}
			else {
				echo '<div class="alert alert-danger">'. $this->language->get('nuvei_order_error') .'</div>';
			}	
				
			exit;
		}
		
		# get APMs
        $time           = date('YmdHis');
		$apms_params    = array(
			'merchantId'        => $this->config->get($this->prefix . 'merchantId'),
			'merchantSiteId'    => $this->config->get($this->prefix . 'merchantSiteId'),
			'clientRequestId'   => $time . '_' . uniqid(),
			'timeStamp'         => $time,
			'sessionToken'      => $order_data['sessionToken'],
			'currencyCode'      => $order_data['currency'],
			'countryCode'       => $order_data['billingAddress']['country'],
			'languageCode'      => current(explode('-', $this->session->data['language'])),
		);
		
		$apms_params['checksum'] = hash(
			$this->config->get($this->prefix . 'hash'),
			$apms_params['merchantId']
                . $apms_params['merchantSiteId'] 
                . $apms_params['clientRequestId']
				. $time 
                . $this->config->get($this->prefix . 'secret')
		);
		
		$apms_res = NUVEI_CLASS::call_rest_api('getMerchantPaymentMethods', $apms_params);
		
		if(!is_array($apms_res) || empty($apms_res['paymentMethods'])) {
			NUVEI_CLASS::create_log($res, 'Get APMs problem with the response');

			echo '<div class="alert alert-danger">'. $this->language->get('pm_error') .'</div>';
			exit;
		}
		# get APMs END
        
        #get UPOs
        $upos = array();
        
        if(
            'yes' == $this->config->get($this->prefix . 'use_upos')
            && !empty($apms_res['paymentMethods']) 
            && is_array($apms_res['paymentMethods'])
            && !empty($this->customer->getEmail())
        ) {
            $time           = date('YmdHis');
            $upo_params     = array(
				'merchantId'        => $apms_params['merchantId'],
				'merchantSiteId'    => $apms_params['merchantSiteId'],
				'userTokenId'       => $this->customer->getEmail(),
				'clientRequestId'   => $time . '_' . uniqid(),
				'timeStamp'         => $time,
			);

			$upo_params['checksum'] = hash(
				$this->config->get($this->prefix . 'hash'),
				implode('', $upo_params) . $this->config->get($this->prefix . 'secret')
			);

			$upo_res = NUVEI_CLASS::call_rest_api('getUserUPOs', $upo_params);
            
            if (!empty($upo_res['paymentMethods']) && is_array($upo_res['paymentMethods'])) {
				foreach ($upo_res['paymentMethods'] as $data) {
					// chech if it is not expired
					if (!empty($data['expiryDate']) && gmdate('Ymd') > $data['expiryDate']) {
						continue;
					}

					if (empty($data['upoStatus']) || 'enabled' !== $data['upoStatus']) {
						continue;
					}

					// search for same method in APMs, use this UPO only if it is available there
					foreach ($apms_res['paymentMethods'] as $pm_data) {
						// found it
						if ($pm_data['paymentMethod'] === $data['paymentMethodName']) {
                            $data['name']    = @$pm_data['paymentMethodDisplayName'][0]['message'];
							$data['logoURL'] = 'cc_card' == $data['paymentMethodName'] 
                                ? 'catalog/view/theme/default/image/visa_mc_maestro.svg' : @$pm_data['logoURL'];
							
							$upos[] = $data;
							break;
						}
					}
				}
			}
        }
        #get UPOs END

        // set template data with the payment methods
        $data                       = $this->load->language($ctr_file_path);
        $data['ctr_path']           = $ctr_file_path;
		$data['payment_methods']    = $apms_res['paymentMethods'];
		$data['nuvei_upos']         = $upos;
		$data['scLocale']           = substr($this->get_locale(), 0, 2);
		$data['action']             = $this->url->link($ctr_file_path . '/process_payment')
			. '&create_logs=' . $_SESSION['nuvei_create_logs'] . '&order_id=' 
            . $this->session->data['order_id'];
		
        // data for the template
		$data['merchantId']             = $apms_params['merchantId'];
		$data['merchantSiteId']         = $apms_params['merchantSiteId'];
		$data['sessionToken']           = $order_data['sessionToken'];
		$data['sc_test_env']			= $_SESSION['nuvei_test_mode'];
		$data['webMasterId']			= $order_data['webMasterId'];
		$data['sourceApplication']		= NuveiVersionResolver::get_source_application();
		$data['isUserLogged']           = !empty($this->session->data['customer_id']) ? 1 : 0;
		$data['useUpos']                = $this->config->get($this->prefix . 'use_upos');
		$data['nuveiUserTokenId']       = !empty($this->customer->getEmail()) 
            ? $this->customer->getEmail() : $this->order_info['email'];
        
        // load common php template and then pass it to the real template
        // as single variable. The form is same for both versions
        $tpl_path = 'default/template/'  . $ctr_file_path;
        
        ob_start();
        require DIR_TEMPLATE . $tpl_path . '.php';
        return ob_get_clean(); // the template of OC wants array
	}
    
    // on success add history note for the order
    public function success()
    {
        $ctr_file_path                  = NuveiVersionResolver::get_ctr_file_path();
        $this->prefix                   = NuveiVersionResolver::get_settings_prefix();
        $_SESSION['nuvei_test_mode']    = $this->config->get($this->prefix . 'test_mode');
        
        NUVEI_CLASS::create_log('success page');
        
        $this->load->model('checkout/order');
        $this->language->load($ctr_file_path);
        
		if(!empty($this->request->get['order_id'])) {
			$order_id = intval($this->request->get['order_id']);
		}
		elseif(NUVEI_CLASS::get_param('invoice_id')) {
			$arr		= explode("_", NUVEI_CLASS::get_param('invoice_id'));
			$order_id	= (int) $arr[0];
		}
		else {
			NUVEI_CLASS::create_log(
                isset($_REQUEST) ? $_REQUEST : '',
                'Success Error - can not recognize order ID.'
            );
			
            $this->response->redirect($this->url->link($ctr_file_path . '/fail'));
		}
		
		$this->order_info = $this->model_checkout_order->getOrder($order_id);
        
        if(
            isset($this->order_info['order_status_id'])
            && (int) $this->order_info['order_status_id'] == 0
        ) {
			$this->model_checkout_order->addOrderHistory(
				$order_id,
                $this->order_info['order_status_id'],
				$this->language->get('nuvei_payment_complete'),
				true
			);
        }
        
        $this->response->redirect($this->url->link('checkout/success'));
    }
    
    /*
     * Function fail()
     * When order fail came here.
     */
    public function fail()
	{
        NUVEI_CLASS::create_log(isset($_REQUEST) ? $_REQUEST : '', 'Order FAIL');
        
        $ctr_file_path                  = NuveiVersionResolver::get_ctr_file_path();
        $this->prefix                   = NuveiVersionResolver::get_settings_prefix();
        $_SESSION['nuvei_test_mode']    = $this->config->get($this->prefix . 'test_mode');
        
        $this->language->load($ctr_file_path);
        
		if(!empty($this->request->get['order_id'])) {
			$order_id = intval($this->request->get['order_id']);
		}
		elseif(NUVEI_CLASS::get_param('invoice_id')) {
			$arr		= explode("_", NUVEI_CLASS::get_param('invoice_id'));
			$order_id	= (int) $arr[0];
		}
		else {
			$this->session->data['error']= $this->language->get('nuvei_payment_faild');
			$this->response->redirect($this->url->link('checkout/cart'));
		}
		
		$this->load->model('checkout/order');
		$this->order_info = $this->model_checkout_order->getOrder($order_id);

		if ($this->order_info) {
            $this->change_order_status($order_id, 'FAIL');
		}

		$this->session->data['error']= $this->language->get('nuvei_payment_faild');
        
        $this->response->redirect($this->url->link('checkout/cart'));
	}
    
    /**
     * Function callback
	 * 
     * Receive DMNs here
     */
	public function callback()
    {
        NUVEI_CLASS::create_log((isset($_REQUEST) ? $_REQUEST : ''), 'DMN request');
        
		$order_id                       = 0;
        $this->prefix                   = NuveiVersionResolver::get_settings_prefix();
        $_SESSION['nuvei_test_mode']    = $this->config->get($this->prefix . 'test_mode');
        
        ### Manual stop DMN is possible only in test mode
//        if('yes' == $_SESSION['nuvei_test_mode']) {
//            NUVEI_CLASS::create_log(http_build_query(@$_REQUEST), 'DMN request query');
//            die('manually stoped');
//        }
        ### Manual stop DMN END
        
        $trans_type             = NUVEI_CLASS::get_param('transactionType', FILTER_SANITIZE_STRING);
        $trans_id               = (int) NUVEI_CLASS::get_param('TransactionID');
        $relatedTransactionId   = (int) NUVEI_CLASS::get_param('relatedTransactionId');
        $merchant_unique_id     = NUVEI_CLASS::get_param('merchant_unique_id');
        $req_status             = $this->get_request_status();
		
		if(!$trans_type) {
            $this->return_message('DMN report: Transaction Type is empty');
		}
        
        if(empty($req_status)) {
            $this->return_message('DMN report: the Status parameter is empty.');
		}
		
		if('pending' == strtolower($req_status)) {
            $this->return_message('DMN status is Pending. Wait for another status.');
		}
		
        if(!$this->checkAdvancedCheckSum()) {
            $this->return_message('DMN report: You receive DMN from not trusted source. The process ends here.');
        }
        
        $this->load->model('checkout/order');
        
        // find Order ID
        if(!empty($_REQUEST['order_id'])) {
			$order_id = (int) $_REQUEST['order_id'];
		}
        elseif(!empty($merchant_unique_id) && false === strpos($merchant_unique_id, 'gwp_')) {
            if(is_numeric($merchant_unique_id)) {
                $order_id = (int) $merchant_unique_id;
            }
            // beacause of the modified merchant_unique_id - PayPal problem
            elseif(strpos($merchant_unique_id, '_') !== false) {
                $order_id_arr = explode('_', $merchant_unique_id);
                
                if(is_numeric($order_id_arr[0])) {
                    $order_id = (int) $order_id_arr[0];
                }
            }
        }
		else {
			$q = 'SELECT order_id FROM ' . DB_PREFIX . 'order '
                . 'WHERE custom_field = ' . $relatedTransactionId;
			
			$query      = $this->db->query($q);
            
            NUVEI_CLASS::create_log(@$query->row);
            $order_id   = (int) $query->row['order_id'];
		}
        
        if($order_id == 0 || !is_numeric($order_id)) {
            $this->return_message('DMN error - invalid Order ID.');
        }
        
        // get Order info
        try {
            $this->order_info = $this->model_checkout_order->getOrder($order_id);
            
            if(!$this->order_info || empty($this->order_info)) {
                $this->return_message('DMN error - there is no order info.');
            }
            
            // check for Nuvei Order
            if($this->order_info['payment_code'] != 'nuvei') {
                $this->return_message('DMN error - the Order does not belongs to the Nuvei.');
            }
        }
        catch (Exception $ex) {
            $this->return_message('DMN Exception', $ex->getMessage());
        }
        
        // do not override Order status
        if(
            $this->order_info['order_status_id'] > 0
            && $this->order_info['order_status_id'] != $this->config->get($this->prefix . 'pending_status_id')
            && 'pending' == strtolower($req_status)
        ) {
            $this->return_message('DMN Message - do not override current Order status with Pending');
        }
        
        # in Case of CPanel Refund DMN
        if(
            in_array($trans_type, array('Credit', 'Refund'))
            && strpos(NUVEI_CLASS::get_param('clientUniqueId'), 'gwp_') !== false
        ) {
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $this->order_info['order_status_id'],
                $this->language->get('CPanel Refund detected. Please, create a manual refund!'),
                false
            );

            $this->return_message('DMN received.');
        }
        # in Case of CPanel Refund DMN END
        
        # add new data into payment_custom_field
        $order_data = $this->order_info['payment_custom_field'];
        
        NUVEI_CLASS::create_log($order_data, 'callback() payment_custom_field');
        
        if(empty($order_data)) {
            $order_data = array();
        }
        
        // prevent dublicate data
        if(!empty($order_data)) {
            foreach($order_data as $trans) {
                if(
                    $trans['transactionId'] == $trans_id
                    && $trans['transactionType'] == $trans_type
                    && $trans['status'] == strtolower($req_status)
                ) {
                    $this->return_message('Dublicate DMN. We already have this information. Stop here.');
                }
            }
        }
        
        $order_data[] = array(
            'status'                => strtolower((string) $req_status),
            'clientUniqueId'        => NUVEI_CLASS::get_param('clientUniqueId', FILTER_SANITIZE_STRING),
            'transactionType'       => $trans_type,
            'transactionId'         => $trans_id,
            'relatedTransactionId'  => $relatedTransactionId,
            'userPaymentOptionId'   => (int) NUVEI_CLASS::get_param('userPaymentOptionId'),
            'authCode'              => (int) NUVEI_CLASS::get_param('AuthCode'),
            'totalAmount'           => round((float) NUVEI_CLASS::get_param('totalAmount'), 2),
            'currency'              => NUVEI_CLASS::get_param('currency', FILTER_SANITIZE_STRING),
            'paymentMethod'         => NUVEI_CLASS::get_param('payment_method', FILTER_SANITIZE_STRING),
            'responseTimeStamp'     => NUVEI_CLASS::get_param('responseTimeStamp', FILTER_SANITIZE_STRING),
        );
        
        // all data
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` "
            . "SET payment_custom_field = '" . json_encode($order_data) . "' "
            . "WHERE order_id = " . $order_id
        );
        
        // add only transaction ID if the transactions is Auth, Settle or Sale
        if(in_array($trans_type, array('Auth', 'Settle', 'Sale'))) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "order` "
                . "SET custom_field = '" . $trans_id . "' "
                . "WHERE order_id = " . $order_id
            );
        }
        # add new data into payment_custom_field END
		
        # Sale and Auth
        if(in_array($trans_type, array('Sale', 'Auth'))) {
            NUVEI_CLASS::create_log(
                array(
                    'order_status_id' => $this->order_info['order_status_id'],
                    'default complete status' => $this->config->get($this->prefix . 'order_status_id'),
                ),
                'DMN Sale/Auth compare order status and default complete status:'
            );
            
			// if is different than the default Complete status
			if($this->order_info['order_status_id'] 
                != $this->config->get($this->prefix . 'order_status_id')
            ) {
				$this->change_order_status($order_id, $req_status, $trans_type);
			}
            
            $this->return_message('DMN received.');
        }
        
        # Refund
        if(in_array($trans_type, array('Credit', 'Refund'))) {
            $this->change_order_status($order_id, $req_status, 'Credit');
            $this->return_message('DMN received.');
        }
        
        # Void, Settle
        if(in_array($trans_type, array('Void', 'Settle'))) {
            $this->change_order_status($order_id, $req_status, $trans_type);
			$this->return_message('DMN received.');
        }
        
        $this->return_message('DMN was not recognized!');
	}
    
    /**
     * Function process_payment
	 * 
     * We use this method with REST API.
     * Here we send the data from the form and prepare it before send it to the API.
     */
    public function process_payment()
    {
        $ctr_file_path                      = NuveiVersionResolver::get_ctr_file_path();
        $this->prefix                       = NuveiVersionResolver::get_settings_prefix();
        $_SESSION['nuvei_test_mode']        = $this->config->get($this->prefix . 'test_mode');
        $_SESSION['nuvei_last_oo_details']  = array();
        
        if('yes' == $_SESSION['nuvei_test_mode']) {
            NUVEI_CLASS::create_log(@$_POST, 'process_payment()');
        }
        
		$this->load->model('checkout/order');
		$this->order_info = $this->model_checkout_order->getOrder($this->request->get['order_id']);
		
		$success_url    = $this->url->link($ctr_file_path . '/success') 
            . '&order_id=' . $this->request->get['order_id'];
		$pending_url    = $success_url;
		$error_url      = $this->url->link($ctr_file_path . '/fail') 
            . '&order_id=' . $this->request->get['order_id'];
		$back_url       = $this->url->link('checkout/checkout', '', true);
		$notify_url     = $this->url->link($ctr_file_path . '/callback&create_logs=' 
            . $this->request->get['create_logs']);
		
        if(empty($this->request->post['payment_method_sc'])) {
            NUVEI_CLASS::create_log('process_payment() - payment_method_sc problem');
            
            $this->response->redirect($error_url);
        }
		
		# WebSDK
		if(
            !empty($this->request->post['sc_transaction_id'])
            && is_numeric($this->request->post['sc_transaction_id'])
            && ( 'cc_card' == $this->request->post['payment_method_sc'] 
                || is_numeric($this->request->post['payment_method_sc']) )
		) {
			$this->response->redirect($success_url);
		}
		
		# APMs
        $this->language->load($ctr_file_path);
        $data['process_payment'] = $this->language->get('Processing the payment. Please, wait!');
        
        $TimeStamp = date('YmdHis', time());
		
		$total_amount = $this->currency->format(
            $this->order_info['total'],
            $this->order_info['currency_code'],
            $this->order_info['currency_value'],
            false
        );
        
        if($total_amount < 0) {
            $total_amount = number_format(0, 2, '.', '');
        }
        else {
            $total_amount = number_format($total_amount, 2, '.', '');
        }
		
		$countriesWithStates = array('US', 'IN', 'CA');
		
		$state = preg_replace("/[[:punct:]]/", '', substr($this->order_info['payment_zone'], 0, 2));
		if (in_array($this->order_info['payment_iso_code_2'], $countriesWithStates)) {
			$state = $this->order_info['payment_zone_code'];
		}
        
		$params = array(
			'merchantId'        => $this->config->get($this->prefix . 'merchantId'),
			'merchantSiteId'    => $this->config->get($this->prefix . 'merchantSiteId'),
			'clientUniqueId'    => $this->request->get['order_id'] . '_' . uniqid(),
			'merchant_unique_id'=> $this->request->get['order_id'],
			'clientRequestId'   => $TimeStamp . '_' . uniqid(),
			'currency'          => $this->order_info['currency_code'],
			'amount'            => (string) $total_amount,
			'amountDetails'     => array(
				'totalShipping'     => '0.00',
				'totalHandling'     => '0.00',
				'totalDiscount'     => '0.00',
				'totalTax'          => '0.00',
			),
			'userDetails'       => array(
				'firstName'         => preg_replace("/[[:punct:]]/", '', $this->order_info['payment_firstname']),
				'lastName'          => preg_replace("/[[:punct:]]/", '', $this->order_info['payment_lastname']),
				'address'           => preg_replace("/[[:punct:]]/", '', $this->order_info['payment_address_1']),
				'phone'             => preg_replace("/[[:punct:]]/", '', $this->order_info['telephone']),
				'zip'               => preg_replace("/[[:punct:]]/", '', $this->order_info['payment_postcode']),
				'city'              => preg_replace("/[[:punct:]]/", '', $this->order_info['payment_city']),
				'country'           => $this->order_info['payment_iso_code_2'],
				'state'             => $state,
				'email'             => $this->order_info['email'],
				'county'            => '',
			),
			'shippingAddress'   => array(
				'firstName'         => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_firstname']),
				'lastName'          => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_lastname']),
				'address'           => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_address_1']),
				'cell'              => '',
				'phone'             => preg_replace("/[[:punct:]]/", '', $this->order_info['telephone']),
				'zip'               => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_postcode']),
				'city'              => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_city']),
				'country'           => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_iso_code_2']),
				'state'             => '',
				'email'             => $this->order_info['email'],
				'shippingCounty'    => '',
			),
			'urlDetails'        => array(
				'successUrl'        => $success_url,
				'failureUrl'        => $error_url,
				'pendingUrl'        => $pending_url,
				'backUrl'			=> $back_url,
				'notificationUrl'   => $notify_url,
			),
			'timeStamp'			=> $TimeStamp,
			'webMasterID'       => 'OpenCart ' . VERSION,
			'sessionToken'      => isset($this->request->post['lst']) ? $this->request->post['lst'] : '',
			'deviceDetails'     => NUVEI_CLASS::get_device_details(),
		);

		$params['billingAddress'] = $params['userDetails'];
		
		$params['items'][0] = array(
			'name'		=> $this->request->get['order_id'],
			'price'		=> $total_amount,
			'quantity'	=> 1,
		);
        
        if(!empty($this->request->post['nuvei_save_upo']) && 1 == $this->request->post['nuvei_save_upo']) {
            $params['userTokenId'] = $this->order_info['email'];
        }

		$params['checksum'] = hash(
			$this->config->get($this->prefix . 'hash'),
			$params['merchantId'] 
                . $params['merchantSiteId'] 
                . $params['clientRequestId']
				. $params['amount'] 
                . $params['currency'] 
                . $TimeStamp
				. $this->config->get($this->prefix . 'secret')
		);

        //$params['paymentMethod'] = $this->request->post['payment_method_sc'];
        $sc_payment_method = $this->request->post['payment_method_sc'];
		
		// UPO
		if (is_numeric($sc_payment_method)) {
			$endpoint_method                                = 'payment';
			$params['paymentOption']['userPaymentOptionId'] = $sc_payment_method;
			$params['userTokenId']							= $this->order_info['email'];
		}
        // APM
        else {
			$endpoint_method         = 'paymentAPM';
			$params['paymentMethod'] = $sc_payment_method;
			
			if (!empty($this->request->post[$sc_payment_method])) {
				$params['userAccountDetails'] = $this->request->post[$sc_payment_method];
			}
			
			if (
                isset($this->request->get['nuvei_save_upo']) == 1
                && $this->request->get['nuvei_save_upo'] == 1
            ) {
				$params['userTokenId'] = $this->order_info['email'];
			}
		}
        
//		if(
//			isset($this->request->post['payment_method_sc'], $this->request->post[$this->request->post['payment_method_sc']])
//			&& is_array($this->request->post[$this->request->post['payment_method_sc']])
//		) {
//			$params['userAccountDetails'] = $this->request->post[$this->request->post['payment_method_sc']];
//		}
            
//		$resp = NUVEI_CLASS::call_rest_api('paymentAPM', $params);
		$resp = NUVEI_CLASS::call_rest_api($endpoint_method, $params);

		if(!$resp) {
			$this->response->redirect($this->request->post['error_url']);
		}

		if($this->get_request_status($resp) == 'ERROR' || @$resp['transactionStatus'] == 'ERROR') {
			$this->change_order_status(
				(int) $this->request->get['order_id'], 
				'ERROR', 
				@$resp['transactionType']
			);

			$this->response->redirect($error_url);
		}

		if(@$resp['transactionStatus'] == 'DECLINED') {
			$this->change_order_status(
				(int) $this->request->get['order_id'], 
				'DECLINED', 
				@$resp['transactionType']
			);

            if(!empty($this->request->post['error_url'])) {
                $this->response->redirect($this->request->post['error_url']);
            }
            else {
                $this->response->redirect($error_url);
            }
		}

		if($this->get_request_status($resp) == 'SUCCESS') {
			// in case we have redirectURL
			if(!empty($resp['redirectURL'])) {
				$this->response->redirect($resp['redirectURL']);
			}
            elseif(!empty($resp['paymentOption']['redirectUrl'])) {
                $this->response->redirect($resp['paymentOption']['redirectUrl']);
            }
		}

		$this->response->redirect($success_url);
    }
	
    /**
     * Function checkAdvancedCheckSum
     * Check if the DMN is not fake.
     * 
     * @return boolean
     */
    private function checkAdvancedCheckSum()
    {
        $str = hash(
            $this->config->get($this->prefix . 'hash'),
            $this->config->get($this->prefix . 'secret')
                . NUVEI_CLASS::get_param('totalAmount')
                . NUVEI_CLASS::get_param('currency')
                . NUVEI_CLASS::get_param('responseTimeStamp')
                . NUVEI_CLASS::get_param('PPP_TransactionID')
                . $this->get_request_status()
                . NUVEI_CLASS::get_param('productId')
        );

        if (NUVEI_CLASS::get_param('advanceResponseChecksum') == $str) {
            return true;
        }
        
        return false;
	}
    
    /**
     * Function get_request_status
     * 
     * We need this stupid function because as response request variable
     * we get 'Status' or 'status'...
     * 
     * @return string
     */
    private function get_request_status($params = array())
    {
        if(empty($params)) {
            if(isset($_REQUEST['Status'])) {
                return filter_var($_REQUEST['Status'], FILTER_SANITIZE_STRING);
            }

            if(isset($_REQUEST['status'])) {
                return filter_var($_REQUEST['status'], FILTER_SANITIZE_STRING);
            }
        }
        else {
            if(isset($params['Status'])) {
                return filter_var($params['Status'], FILTER_SANITIZE_STRING);
            }

            if(isset($params['status'])) {
                return filter_var($params['status'], FILTER_SANITIZE_STRING);
            }
        }
        
        return '';
    }
    
    /**
     * Function get_locale
     * Extract locale code in format "en_GB"
     * 
     * @return string
     */
    private function get_locale()
    {
		$langs = $this->model_localisation_language->getLanguages();
        $langs = current($langs);
        
        if(isset($langs['locale']) && $langs['locale'] != '') {
            $locale_parts = explode(',', $langs['locale']);
            
            foreach($locale_parts as $part) {
                if(strlen($part) == 5 && strpos($part, '_') != false) {
                    return $part;
                }
            }
        }
        
        return '';
	}
    
    /**
     * Function change_order_status
     * Change the status of the order.
     * 
     * @param int $order_id - escaped
     * @param string $status
     * @param string $transactionType - not mandatory for the DMN
     */
    private function change_order_status($order_id, $status, $transactionType = '')
    {
        NUVEI_CLASS::create_log('change_order_status()');
        
        $message		= '';
        $send_message	= true;
        $trans_id       = (int) NUVEI_CLASS::get_param('TransactionID');
        $rel_tr_id      = (int) NUVEI_CLASS::get_param('relatedTransactionId');
        $payment_method = NUVEI_CLASS::get_param('payment_method', FILTER_SANITIZE_STRING);
        $total_amount   = (float) NUVEI_CLASS::get_param('totalAmount');
        $status_id      = $this->order_info['order_status_id'];
        $order_total    = round((float) $this->order_info['total'], 2);
        
        $comment_details = '<br/>' 
            . $this->language->get('Transaction ID: ') . $trans_id . '<br/>'
            . $this->language->get('Related Transaction ID: ') . $rel_tr_id . '<br/>'
            . $this->language->get('Status: ') . $status . '<br/>'
            . $this->language->get('Transaction Type: ') . $transactionType . '<br/>'
            . $this->language->get('Payment Method: ') . $payment_method . '<br/>';
        
        switch($status) {
            case 'CANCELED':
                $message = $this->language->get('Your request was Canceled.') . $comment_details;
                break;

            case 'APPROVED':
                if($transactionType == 'Void') {
                    $message    = $this->language->get('Your Order was Voided.') . $comment_details;
                    $status_id  = $this->config->get($this->prefix . 'canceled_status_id');
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
//					$curr_refund_amount = $total_amount;
					
//                        // get all order Refunds
//                        $query = $this->db->query('SELECT * FROM nuvei_refunds WHERE orderId = ' . $order_id);
//
//                        $refs_sum = 0;
//                        if(@$query->rows) {
//							NUVEI_CLASS::create_log($query->rows, 'Refunds:');
//							
//                            foreach($query->rows as $row) {
//                                $row_amount = round(floatval($row['amount']), 2);
//                                
//                                if($row['approved'] == 1) {
//                                    $refs_sum += $row_amount;
//                                }
//                                // find the record for the current Refund
//                                // and check the Amount, the amount in the base is correct one
//                                elseif(
//                                    $row['clientUniqueId'] == $cl_unique_id
//                                    && round($curr_refund_amount, 2) != $row_amount
//                                ) {
//                                    $curr_refund_amount = $row_amount;
//                                }
//                            }
//                        }
                        
                        // to the sum of approved refund add current Refund amount
						/** TODO because of bug, only cc_card provide correct Refund Amount */
//						if('cc_card' == $payment_method) {
//							$refs_sum += $curr_refund_amount;
//						}

                        $send_message   = false;
                        $status_id      = $this->config->get($this->prefix . 'refunded_status_id');

//                        if(round($refs_sum, 2) == round($this->order_info['total'], 2)) {
//                            $status_id = 11; // Refunded
//                            $send_message = true;
//
//                            $this->db->query("UPDATE " . DB_PREFIX
//                                . "order SET order_status_id = 11 WHERE order_id = {$order_id};");
//                        }
                        
                        $message = $this->language->get('Your Order was Refunded.') . $comment_details;
						
						//if($cl_unique_id) {
							$formated_refund = $this->currency->format(
//								$curr_refund_amount,
								$total_amount,
								$this->order_info['currency_code'],
								$this->order_info['currency_value']
							);
							
							$message .= $this->language->get('Refund Amount: ') . $formated_refund;
						//}
						
                        # update Refund data into the DB
//						$q = "UPDATE nuvei_refunds SET "
//							. "transactionId = '{$this->db->escape(@$_REQUEST['TransactionID'])}', "
//							. "authCode = '{$this->db->escape(@$_REQUEST['AuthCode'])}', "
//							. "approved = 1 "
//						. "WHERE orderId = {$order_id} "
//							. "AND clientUniqueId = '{$this->db->escape($cl_unique_id)}'";
//                        
//						NUVEI_CLASS::create_log($q, 'Refunds update query:');
//							
//                        $this->db->query($q);
                    break;
                }
                
                $status_id = $this->config->get($this->prefix . 'order_status_id'); // "completed"
                
                if($transactionType == 'Auth') {
                    $message    = $this->language->get('The amount has been authorized and wait for Settle.');
                    $status_id  = $this->config->get($this->prefix . 'pending_status_id');
                }
                elseif($transactionType == 'Settle') {
                    $message = $this->language->get('The amount has been captured by Nuvei.');
                }
                // set the Order status to Complete
                elseif($transactionType == 'Sale') {
                    $message = $this->language->get('The amount has been authorized and captured by Nuvei.');
                }
                
                // check for different Order Amount
                if(in_array($transactionType, array('Sale', 'Settle')) && $order_total != $total_amount) {
                    $msg = $this->language->get('Attention - the Order total is ') 
                        . $this->order_info['currency_code'] . ' ' . $order_total
                        . $this->language->get(', but the Captured amount is ')
                        . NUVEI_CLASS::get_param('currency', FILTER_SANITIZE_STRING)
                        . ' ' . $total_amount . '.';

                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $msg, false);
                }
                
				$message .= $comment_details;
                break;

            case 'ERROR':
            case 'DECLINED':
            case 'FAIL':
                $message = $this->language->get('Your request faild.') . $comment_details
                    . $this->language->get('Reason: ');
                
                if( ($reason = NUVEI_CLASS::get_param('reason', FILTER_SANITIZE_STRING)) ) {
                    $message .= $reason;
                }
                elseif( ($reason = NUVEI_CLASS::get_param('Reason', FILTER_SANITIZE_STRING)) ) {
                    $message .= $reason;
                }
                elseif( ($reason = NUVEI_CLASS::get_param('paymentMethodErrorReason', FILTER_SANITIZE_STRING)) ) {
                    $message .= $reason;
                }
                elseif( ($reason = NUVEI_CLASS::get_param('gwErrorReason', FILTER_SANITIZE_STRING)) ) {
                    $message .= $reason;
                }
                
                $message .= '<br/>';
                
                $message .= 
                    $this->language->get("Error code: ") 
                    . (int) NUVEI_CLASS::get_param('ErrCode') . '<br/>'
                    . $this->language->get("Message: ") 
                    . NUVEI_CLASS::get_param('message', FILTER_SANITIZE_STRING) . '<br/>';
                
                if(in_array($transactionType, array('Sale', 'Auth'))) {
                    $status_id = $this->config->get($this->prefix . 'failed_status_id');
                    break;
                }

                // Void, do not change status
                if($transactionType == 'Void') {
                    $status_id = $this->order_info['order_status_id'];
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
					//if($cl_unique_id) {
						$formated_refund = $this->currency->format(
                            $total_amount,
							$this->order_info['currency_code'],
							$this->order_info['currency_value']
						);
						
						$message .= $this->language->get('Refund Amount: ') . $formated_refund;
					//}
                    
                    $status_id = $this->order_info['order_status_id'];
                    $send_message = false;
                    break;
                }
                
                $status_id = $this->config->get($this->prefix . 'failed_status_id');
                break;

			/** TODO Remove it. We stop process in the beginning when status is Pending */
//            case 'PENDING':
//				NUVEI_CLASS::create_log($this->order_info['order_status_id'], 'Order status is:', $this->config->get($this->prefix . 'test_mode'));
//				
//                if ($this->order_info['order_status_id'] == '5' || $this->order_info['order_status_id'] == '15') {
//                    $status_id = $this->order_info['order_status_id'];
//                    break;
//                }
//				
//				$status_id = $this->config->get($this->prefix . 'pending_status_id');
//                
//                $message = 'Payment is still pending, PPP_TransactionID '
//                    . @$request['PPP_TransactionID'] . ", Status = " . $status;
//
//                if($transactionType) {
//                    $message .= ", TransactionType = " . $transactionType;
//                }
//
//                $message .= ', GW_TransactionID = ' . @$request['TransactionID'];
//                
//                $this->model_checkout_order->addOrderHistory(
//                    $order_id,
//                    $status_id,
//                    'Nuvei payment status is pending<br/>Unique Id: '
//                        .@$request['PPP_TransactionID'],
//                    true
//                );
//                
//                break;
                
            default:
                NUVEI_CLASS::create_log($status, 'Unexisting status:');
        }
        
        NUVEI_CLASS::create_log(
            array(
                'order_id'  => $order_id,
                'status_id' => $status_id,
            ),
            'addOrderHistory()'
        );
        
        $this->model_checkout_order->addOrderHistory($order_id, $status_id, $message, $send_message);
    }
    
	private function open_order()
    {
        $time               = date('YmdHis');
		$ctr_url_path       = NuveiVersionResolver::get_ctr_file_path();

        # try to update Order
		$resp = $this->update_order();
        
        if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			return $resp;
		}
        # try to update Order END
        
        $addresses = $this->get_order_addresses();
        
		$oo_params = array(
			'merchantId'        => $this->config->get($this->prefix . 'merchantId'),
			'merchantSiteId'    => $this->config->get($this->prefix . 'merchantSiteId'),
			'clientRequestId'   => $time . '_' . uniqid(),
			'clientUniqueId'	=> $this->session->data['order_id'] . '_' . uniqid(),
			'amount'            => round((float) $this->order_info['total'], 2),
			'currency'          => $this->order_info['currency_code'],
			'timeStamp'         => $time,
			
			'urlDetails'        => array(
				'successUrl'        => $this->url->link($ctr_url_path . '/success'),
				'failureUrl'        => $this->url->link($ctr_url_path . '/fail'),
				'pendingUrl'        => $this->url->link($ctr_url_path . '/success'),
				'backUrl'			=> $this->url->link('checkout/checkout', '', true),
				'notificationUrl'   => $this->url->link($ctr_url_path 
                    . '/callback&create_logs=' . $_SESSION['nuvei_create_logs']),
			),
			
			'deviceDetails'     => NUVEI_CLASS::get_device_details(),
			'billingAddress'	=> $addresses['billingAddress'],
            'shippingAddress'   => $addresses['shippingAddress'],
			
			'webMasterId'       => 'OpenCart ' . VERSION,
			'paymentOption'		=> array('card' => array('threeD' => array('isDynamic3D' => 1))),
			'transactionType'	=> $this->config->get($this->prefix . 'payment_action'),
            
//            'merchantDetails'   => array(),
		);
		
		if('yes' == $this->config->get($this->prefix . 'force_http')) {
            $oo_params['urlDetails']['notificationUrl']
				= str_replace('https://', 'http://', $oo_params['urlDetails']['notificationUrl']);
        }

		$oo_params['checksum'] = hash(
			$this->config->get($this->prefix . 'hash'),
			$this->config->get($this->prefix . 'merchantId') 
                . $this->config->get($this->prefix . 'merchantSiteId') 
                . $oo_params['clientRequestId']
				. $oo_params['amount'] 
                . $oo_params['currency'] 
                . $time 
                . $this->config->get($this->prefix . 'secret')
		);

		$resp = NUVEI_CLASS::call_rest_api('openOrder', $oo_params);
		
		if (empty($resp['status']) || empty($resp['sessionToken']) || 'SUCCESS' != $resp['status']) {
			if(!empty($resp['message'])) {
				return $resp;
			}
			
			return array();
		}
        
        // set them to session for the check before submit the data to the webSDK
		$_SESSION['nuvei_last_oo_details'] = array(
			'amount'			=> $oo_params['amount'],
//			'merchantDetails'	=> $oo_params['merchantDetails'],
			'sessionToken'		=> $resp['sessionToken'],
			'clientRequestId'	=> $oo_params['clientRequestId'],
			'orderId'			=> $resp['orderId'],
			'billingAddress'	=> $oo_params['billingAddress'],
		);
        
        $oo_params['sessionToken'] = $resp['sessionToken'];
		
		return $oo_params;
	}
    
    private function update_order()
    {
        NUVEI_CLASS::create_log('Try to update order.');
        
        if (empty($_SESSION['nuvei_last_oo_details'])) {
            NUVEI_CLASS::create_log('update_order() - Missing last Order session data.');
			
			return array('status' => 'ERROR');
		}
        
        $time           = date('YmdHis');
        $addresses      = $this->get_order_addresses();
        $cart_items     = array();
        
        // create Order upgrade
		$params = array(
			'sessionToken'		=> $_SESSION['nuvei_last_oo_details']['sessionToken'],
			'orderId'			=> $_SESSION['nuvei_last_oo_details']['orderId'],
			'merchantId'		=> $this->config->get($this->prefix . 'merchantId'),
			'merchantSiteId'    => $this->config->get($this->prefix . 'merchantSiteId'),
            'clientRequestId'   => $time . '_' . uniqid(),
			'clientUniqueId'	=> $this->session->data['order_id'] . '_' . uniqid(),
            'currency'          => $this->order_info['currency_code'],
            'amount'            => round((float) $this->order_info['total'], 2),
            'timeStamp'			=> $time,
            'billingAddress'	=> $addresses['billingAddress'],
            'shippingAddress'   => $addresses['shippingAddress'],
            'webMasterId'       => 'OpenCart ' . VERSION,
            
            'items'				=> array(
				array(
					'name'		=> 'oc_order',
					'price'		=> round((float) $this->order_info['total'], 2),
					'quantity'	=> 1
				)
			),
//            'merchantDetails'   => array(),
		);
        
        $params['userDetails']  = $params['billingAddress'];
		$params['checksum']     = hash(
			$this->config->get($this->prefix . 'hash'),
			$this->config->get($this->prefix . 'merchantId') 
                . $this->config->get($this->prefix . 'merchantSiteId') 
                . $params['clientRequestId']
				. $params['amount'] 
                . $params['currency'] 
                . $time 
                . $this->config->get($this->prefix . 'secret')
		);
		
		$resp = NUVEI_CLASS::call_rest_api('updateOrder', $params);
        
        # Success
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			$_SESSION['nuvei_last_oo_details']['amount']					= $params['amount'];
//			$_SESSION['nuvei_last_oo_details']['merchantDetails']			= $params['merchantDetails'];
			$_SESSION['nuvei_last_oo_details']['billingAddress']['country']	= $params['billingAddress']['country'];
			
			return array_merge($resp, $params);
		}
		
		NUVEI_CLASS::create_log('update_order() - Order update was not successful.');

		return array('status' => 'ERROR');
    }
    
    private function get_order_addresses()
    {
        return array(
            'billingAddress'	=> array(
				"firstName"	=> $this->order_info['payment_firstname'],
				"lastName"	=> $this->order_info['payment_lastname'],
				"address"   => $this->order_info['payment_address_1'],
				"phone"     => $this->order_info['telephone'],
				"zip"       => $this->order_info['payment_postcode'],
				"city"      => $this->order_info['payment_city'],
				'country'	=> $this->order_info['payment_iso_code_2'],
				'email'		=> $this->order_info['email'],
			),
            
            'shippingAddress'    => [
				"firstName"	=> $this->order_info['shipping_firstname'],
				"lastName"	=> $this->order_info['shipping_lastname'],
				"address"   => $this->order_info['shipping_address_1'],
				"phone"     => $this->order_info['telephone'],
				"zip"       => $this->order_info['shipping_postcode'],
				"city"      => $this->order_info['shipping_city'],
				'country'	=> $this->order_info['shipping_iso_code_2'],
				'email'		=> $this->order_info['email'],
			],
        );
    }

	private function ajax_call()
    {
		NUVEI_CLASS::create_log('ajax_call()');
        
        // remove UPO
        if(
            !empty($this->request->post['action'])
            && 'remove_upo' == $this->request->post['action']
            && !empty($this->request->post['upoId'])
            && is_numeric($this->request->post['upoId'])
        ) {
            $this->remove_upo();
        }
		
        $oo_data = $this->open_order();
		
		if(empty($oo_data)) {
			echo json_encode(array('status' => 'error'));
			exit;
		}
		
		echo json_encode(array(
			'status'		=> 'success',
			'sessionToken'	=> $oo_data['sessionToken']
		));
		exit;
	}
	
    private function remove_upo()
    {
        if(empty($this->customer->getEmail())) {
            echo json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->get('nuvei_error_logged_user')
            ));
            exit;
        }
        
        $timeStamp = gmdate('YmdHis', time());
			
		$params = array(
			'merchantId'            => $this->config->get($this->prefix . 'merchantId'),
			'merchantSiteId'        => $this->config->get($this->prefix . 'merchantSiteId'),
			'userTokenId'           => $this->customer->getEmail(),
			'clientRequestId'       => $timeStamp . '_' . uniqid(),
			'userPaymentOptionId'   => (int) $this->request->post['upoId'],
			'timeStamp'             => $timeStamp,
		);
		
		$params['checksum'] = hash(
			$this->config->get($this->prefix . 'hash'),
			implode('', $params) 
                . $this->config->get($this->prefix . 'secret')
		);
		
		$resp = NUVEI_CLASS::call_rest_api('deleteUPO', $params);
        
        if (empty($resp['status']) || 'SUCCESS' != $resp['status']) {
			$msg = !empty($resp['reason']) ? $resp['reason'] : '';
			
			echo json_encode(array(
				'status'    => 0,
				'msg'       => $msg
            ));
			exit;
		}
		
		echo json_encode(array('status' => 1));
		exit;
    }

    /**
     * Function return_message
     * 
     * @param string    $msg
     * @param mixed     $data
     */
    private function return_message($msg, $data = '') {
        if(!is_string($msg)) {
            $msg = json_encode($msg);
        }
        
        if(!empty($data)) {
            NUVEI_CLASS::create_log($data, $msg);
        }
        else {
            NUVEI_CLASS::create_log($msg);
        }
        
        echo $msg;
        exit;
    }
}
