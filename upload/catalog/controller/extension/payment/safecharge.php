<?php

if (!session_id()) {
    session_start();
}

require_once DIR_SYSTEM. 'config'. DIRECTORY_SEPARATOR. 'sc_config.php';
require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge'. DIRECTORY_SEPARATOR. 'SC_CLASS.php';
require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge'. DIRECTORY_SEPARATOR. 'sc_version_resolver.php';

class ControllerExtensionPaymentSafeCharge extends Controller
{
	private $order_info;
	
	public function index()
    {
        $this->load->model('checkout/order');
		$this->load->model('account/reward');
		
        $ctr_file_path = $ctr_url_path = SafeChargeVersionResolver::get_ctr_file_path();
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
		
        $this->language->load($ctr_file_path);
        
		# get GW settings to call REST API later
		$settings['secret_key']         = $this->config->get($settigs_prefix . 'secret');
		$settings['merchant_id']        = $data['merchantId']
										= $this->config->get($settigs_prefix . 'ppp_Merchant_ID');
		$settings['merchantsite_id']    = $data['merchantSiteId']
										= $this->config->get($settigs_prefix . 'ppp_Merchant_Site_ID');
		$settings['test']               = $data['sc_test_env']
										= $this->config->get($settigs_prefix . 'test_mode');
		$settings['hash_type']          = $this->config->get($settigs_prefix . 'hash_type');
		$settings['force_http']         = $this->config->get($settigs_prefix . 'force_http');
		$settings['create_logs']        = $this->session->data['create_logs']
                                        = $_SESSION['create_logs']
                                        = $this->config->get($settigs_prefix . 'create_logs');
        # get GW settings to call REST API later END
        
        // get order data
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
        $total_amount = $this->currency->format(
            $order_info['total'],
            $order_info['currency_code'],
            $order_info['currency_value'],
            false
        );
        
        if($total_amount < 0) {
            $total_amount = number_format(0, 2, '.', '');
        }
        else {
            $total_amount = number_format($total_amount, 2, '.', '');
        }
        
		$params['total_amount']		= number_format($total_amount, 2, '.', '');

		# Open Order
		$time = date('YmdHis');
		
		$oo_endpoint_url = 'yes' == $settings['test']
			? SC_TEST_OPEN_ORDER_URL : SC_LIVE_OPEN_ORDER_URL;

		$oo_params = array(
			'merchantId'        => $settings['merchant_id'],
			'merchantSiteId'    => $settings['merchantsite_id'],
			'clientRequestId'   => $time . '_' . uniqid(),
			'clientUniqueId'	=> $this->session->data['order_id'],
			'amount'            => $total_amount,
			'currency'          => $order_info['currency_code'],
			'timeStamp'         => $time,
			'urlDetails'        => array(
				'successUrl'        => $this->url->link($ctr_url_path . '/success'),
				'failureUrl'        => $this->url->link($ctr_url_path . '/fail'),
				'pendingUrl'        => $this->url->link($ctr_url_path . '/success'),
				'backUrl'			=> $this->url->link('checkout/checkout', '', true),
				'notificationUrl'   => $this->url->link($ctr_url_path . '/callback&create_logs=' . $settings['create_logs']),
			),
			'deviceDetails'     => SC_CLASS::get_device_details(),
			'userTokenId'       => $order_info['email'],
			'billingAddress'    => array(
				'country' => urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_iso_code_2'])),
			),
			'webMasterId'       => 'OpenCart ' . VERSION,
			'paymentOption'		=> ['card' => ['threeD' => ['isDynamic3D' => 1]]]
		);
		
		if($settings['force_http'] == 'yes') {
            $oo_params['urlDetails']['notificationUrl']
				= str_replace('https://', 'http://', $oo_params['urlDetails']['notificationUrl']);
        }

		$oo_params['checksum'] = hash(
			$settings['hash_type'],
			$settings['merchant_id'] . $settings['merchantsite_id'] . $oo_params['clientRequestId']
				. $total_amount . $oo_params['currency'] . $time . $settings['secret_key']
		);

		$resp = SC_CLASS::call_rest_api($oo_endpoint_url, $oo_params);
		
		if (
			empty($resp['status']) || empty($resp['sessionToken'])
			|| 'SUCCESS' != $resp['status']
		) {
			echo
				'<script type="text/javascript">alert("'
					. $this->language->get('pm_error') . '")</script>';
			exit;
		}
		
		$data['sessionToken'] = $resp['sessionToken'];
		# Open Order END
		
		# get APMs
		$apms_params = array(
			'merchantId'        => $oo_params['merchantId'],
			'merchantSiteId'    => $oo_params['merchantSiteId'],
			'clientRequestId'   => $time . '_' . uniqid(),
			'timeStamp'         => $time,
			'sessionToken'      => $resp['sessionToken'],
			'currencyCode'      => $oo_params['currency'],
			'countryCode'       => $oo_params['billingAddress']['country'],
			'languageCode'      => current(explode('-', $this->session->data['language'])),
		);
		
		$apms_params['checksum'] = hash(
			$settings['hash_type'],
			$oo_params['merchantId'] . $oo_params['merchantSiteId'] . $apms_params['clientRequestId']
				. $time . $settings['secret_key']
		);
		
		$endpoint_url = 'yes' == $settings['test']
			? SC_TEST_REST_PAYMENT_METHODS_URL : SC_LIVE_REST_PAYMENT_METHODS_URL;

		$res = SC_CLASS::call_rest_api($endpoint_url, $apms_params);
		
		if(!is_array($res) || empty($res['paymentMethods'])) {
			SC_CLASS::create_log($res, 'Get APMs problem with the response: ');

			echo
				'<script type="text/javascript">alert("'
					. $this->language->get('pm_error') . '")</script>';
			exit;
		}

		// set template data with the payment methods
		$data['payment_methods'] = $res['paymentMethods'];
		# get APMs END

		$data['scLocale']	= substr($this->get_locale(), 0, 2);
		$data['action']		= $this->url->link($ctr_url_path . '/process_payment') . '&create_logs=' . ($settings['create_logs']);
		$data['currency']	= $oo_params['currency'];
		$data['amount']		= $oo_params['amount'];

        // data for the template
		$data['sc_test_env']			= $settings['test'];
		$data['webMasterId']			= $oo_params['webMasterId'];
        
        // texts
		$data['sc_attention']           = $this->language->get('sc_attention');
		$data['sc_go_to_step_2_error']  = $this->language->get('sc_go_to_step_2_error');
		$data['button_confirm']         = $this->language->get('button_confirm');
		$data['sc_btn_loading']         = $this->language->get('Loading...');
        $data['sc_pms_title']           = $this->language->get('sc_pms_title');
        $data['choose_pm_error']        = $this->language->get('choose_pm_error');
        $data['rest_no_apms_error']     = $this->language->get('rest_no_apms_error');
        $data['sc_order_declined']		= $this->language->get('sc_order_declined');
        $data['sc_order_error']			= $this->language->get('sc_order_error');
        
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
        SC_CLASS::create_log('success page');
        
        $this->load->model('checkout/order');
        
        $arr = explode("_", @$_REQUEST['invoice_id']);
		$order_id  = $arr[0];
		$order_info = $this->model_checkout_order->getOrder($order_id);
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        
        if($order_info && $order_info['order_status_id'] == '0') {
            $message =
                'Payment process completed. Waiting for transaction status from safecharge. PPP_TransactionID = '
                . @$_REQUEST['PPP_TransactionID'].', GW_TransactionID = '
                . @$_REQUEST['TransactionID'];

                $this->model_checkout_order->addOrderHistory(
                    $order_id,
                    $this->config->get($settigs_prefix . 'pending_status_id'),
                    $message,
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
        SC_CLASS::create_log(@$_REQUEST, 'Order FAIL: ');
        
		$arr = explode("_", @$_REQUEST['invoice_id']);
		$order_id  = intval($arr[0]);
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if ($order_info) {
            $this->change_order_status($order_id, 'FAIL');
		}

		$this->session->data['error']= 'Payment Failed. Please try again. ';
        $this->response->redirect($this->url->link('checkout/cart'));
	}
    
    /**
     * Function callback
     * Receive DMNs here
     */
	public function callback()
    {
        SC_CLASS::create_log(@$_REQUEST, 'DMN request: ');
        
        if(!$this->checkAdvancedCheckSum()) {
            SC_CLASS::create_log('', 'DMN report: You receive DMN from not trusted source. The process ends here.');
            exit;
        }
        
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        
        // get the status from the request
        $req_status = $this->get_request_status();
        $this->load->model('checkout/order');
        
        # Sale and Auth
        if(
            isset($_REQUEST['transactionType'], $_REQUEST['invoice_id'])
            && in_array($_REQUEST['transactionType'], array('Sale', 'Auth'))
        ) {
            SC_CLASS::create_log('', 'A sale/auth.');
            $order_id = 0;
            
                SC_CLASS::create_log('REST sale.');
                
			try {
				$order_id = intval(@$_REQUEST['merchant_unique_id']);
                $order_info = $this->model_checkout_order->getOrder($order_id);
                
                $this->update_custom_payment_fields($order_id);

                // 5 => Complete
                $order_status_id = intval($order_info['order_status_id']);
                if($order_status_id != 5) {
                    $this->change_order_status($order_id, $req_status, $_REQUEST['transactionType']);
                }
            }
            catch (Exception $ex) {
                SC_CLASS::create_log($ex->getMessage(), 'Sale DMN Exception: ');
                echo 'DMN Exception: ' . $ex->getMessage();
                exit;
            }
            
            echo 'DMN received.';
            exit;
        }
        
        # Refund
        // see https://www.safecharge.com/docs/API/?json#refundTransaction -> Output Parameters
        // when we refund form CPanel we get transactionType = Credit and Status = 'APPROVED'
        if(
            (@$_REQUEST['action'] == 'refund'
                || in_array(@$_REQUEST['transactionType'], array('Credit', 'Refund')))
            && !empty($req_status)
        ) {
            SC_CLASS::create_log('OpenCart Refund DMN.');
            
            $order_info = $this->model_checkout_order->getOrder(@$_REQUEST['order_id']);
            
            if(!$order_info) {
                SC_CLASS::create_log($order_info, 'There is no order info: ');
                    
                $this->model_checkout_order->addOrderHistory(
                    @$_REQUEST['order_id'],
                    $this->config->get($settigs_prefix . 'order_status_id'),
                    'Missing Order info for this Order ID.',
                    false
                );

                echo 'DMN received, but there is no Order.';
                exit;
            }
            

            $this->change_order_status(intval(@$_REQUEST['order_id']), $req_status, 'Credit');
            
            echo 'DMN received.';
            exit;
        }
        
        # Void, Settle
        if(
            isset($_REQUEST['order_id'], $_REQUEST['transactionType'])
            && $_REQUEST['order_id'] != ''
            && in_array($_REQUEST['transactionType'], array('Void', 'Settle'))
        ) {
            SC_CLASS::create_log($_REQUEST['transactionType'], 'Void/Settle transactionType: ');
            
            try {
                $order_info = $this->model_checkout_order->getOrder($_REQUEST['order_id']);
                
                if($_REQUEST['transactionType'] == 'Settle') {
                    $this->update_custom_payment_fields($_REQUEST['order_id']);
                }
                
                $this->change_order_status(intval(@$_REQUEST['order_id']), $req_status, $_REQUEST['transactionType']);
            }
            catch (Exception $ex) {
                SC_CLASS::create_log(
                    $ex->getMessage(),
                    'callback() Void/Settle Exception: '
                );
            }
        }
        
        SC_CLASS::create_log('', 'Callback end. ');
        
        echo 'DMN received.';
        exit;
	}
    
    /**
     * Function process_payment
     * We use this method with REST API.
     * Here we send the data from the form and prepare it before send it to the API.
     */
    public function process_payment()
    {
        SC_CLASS::create_log('process_payment()');
        
        $post			= $this->request->post;
		$ctr_file_path	= SafeChargeVersionResolver::get_ctr_file_path();
		$settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
		
		$success_url    = $this->url->link($ctr_file_path . '/success');
		$pending_url	= $this->url->link($ctr_file_path . '/success');
		$error_url      = $this->url->link($ctr_file_path . '/fail');
		$back_url       = $this->url->link('checkout/checkout', '', true);
		$notify_url     = $this->url->link($ctr_file_path . '/callback&create_logs='
			. $this->session->data['create_logs']);
		
        if(empty($post['payment_method_sc'])) {
            SC_CLASS::create_log('process_payment - payment_method_sc problem');
            
            $this->response->redirect($error_url);
        }
		
		# WebSDK
		if(
			in_array($post['payment_method_sc'], array('cc_card', 'dc_card'))
			&& !empty($post['sc_transaction_id'])
		) {
			$this->finish_payment(
				$this->session->data['order_id'], 
				$post['sc_transaction_id'], 
				$success_url, $error_url
			);
		}
		
		# APMs
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
		
		$this->load->model('checkout/order');
		$this->order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
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
			'merchantId'        => $this->config->get($settigs_prefix . 'ppp_Merchant_ID'),
			'merchantSiteId'    => $this->config->get($settigs_prefix . 'ppp_Merchant_Site_ID'),
			'userTokenId'       => $this->order_info['email'],
			'clientUniqueId'    => $this->session->data['order_id'],
			'merchant_unique_id'=> $this->session->data['order_id'],
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
				'phone'             => '',
				'zip'               => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_postcode']),
				'city'              => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_city']),
				'country'           => preg_replace("/[[:punct:]]/", '', $this->order_info['shipping_iso_code_2']),
				'state'             => '',
				'email'             => '',
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
			'sessionToken'      => @$post['lst'],
			'deviceDetails'     => SC_CLASS::get_device_details(),
		);

		$params['billingAddress'] = $params['userDetails'];
		
		$params['items'][0] = array(
			'name'		=> $this->session->data['order_id'],
			'price'		=> $total_amount,
			'quantity'	=> 1,
		);

		$params['checksum'] = hash(
			$this->config->get($settigs_prefix . 'hash_type'),
			$params['merchantId'] . $params['merchantSiteId'] . $params['clientRequestId']
				. $params['amount'] . $params['currency'] . $TimeStamp
				. $this->config->get($settigs_prefix . 'secret')
		);

		$endpoint_url = $this->config->get($settigs_prefix . 'test_mode') == 'no'
			? SC_LIVE_PAYMENT_URL : SC_TEST_PAYMENT_URL;
		$params['paymentMethod'] = $post['payment_method_sc'];

		if(isset($post[@$post['payment_method_sc']]) && is_array($post[$post['payment_method_sc']])) {
			$params['userAccountDetails'] = $post[$post['payment_method_sc']];
		}
            
		$resp = SC_CLASS::call_rest_api($endpoint_url, $params);

		if(!$resp) {
			$this->response->redirect($post['error_url']);
		}

		if($this->get_request_status($resp) == 'ERROR' || @$resp['transactionStatus'] == 'ERROR') {
			$this->change_order_status(
				intval($this->session->data['order_id']), 
				'ERROR', 
				@$resp['transactionType']
			);

			$this->response->redirect($error_url);
		}

		if(@$resp['transactionStatus'] == 'DECLINED') {
			$this->change_order_status(
				intval($this->session->data['order_id']), 
				'DECLINED', 
				@$resp['transactionType']
			);

			$this->response->redirect($post['error_url']);
		}

		if($this->get_request_status($resp) == 'SUCCESS') {
			// in case we have redirectURL
			if(isset($resp['redirectURL']) && !empty($resp['redirectURL'])) {
				$this->response->redirect($data['redirectURL']);
			}
		}

		$this->finish_payment($resp['orderId'], $resp['transactionId'], $success_url, $error_url);
    }
	
	private function finish_payment($order_id, $trans_id, $success_url, $error_url)
	{
		try {
			$this->load->model('checkout/order');
			
			$settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
			
            if($this->order_info['order_status_id'] == $this->config->get($settigs_prefix . 'pending_status_id')) {
                $this->model_checkout_order->addOrderHistory($order_id, 5, 'Order Completed.', false);
                $this->order_info['order_status_id'] = 5;
            }

            if(!empty($trans_id)) {
                $this->model_checkout_order->addOrderHistory(
                    $order_id,
                    $this->order_info['order_status_id'],
                    'Payment succsess for Transaction Id ' . $trans_id,
                    true
                );
            }
            else {
                $this->model_checkout_order->addOrderHistory(
                    $order_id,
                    $this->order_info['clientUniqueId'],
                    'Payment succsess.',
                    true
                );
            }
			
			$this->response->redirect($success_url);
		}
		catch (Exception $ex) {
			SC_CLASS::create_log($ex->getMessage(), 'process_payment Exception: ');
            $this->response->redirect($error_url);
		}
	}
    
    /**
     * Function checkAdvancedCheckSum
     * Check if the DMN is not fake.
     * 
     * @return boolean
     */
    private function checkAdvancedCheckSum()
    {
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        
        $str = hash(
            $this->config->get($settigs_prefix . 'hash_type'),
            $this->config->get($settigs_prefix . 'secret') . @$_REQUEST['totalAmount']
                . @$_REQUEST['currency'] . @$_REQUEST['responseTimeStamp']
                . @$_REQUEST['PPP_TransactionID'] . $this->get_request_status()
                . @$_REQUEST['productId']
        );

        if ($str == @$_REQUEST['advanceResponseChecksum']) {
            return true;
        }
        
        return false;
	}
    
    /**
     * Function get_request_status
     * We need this stupid function because as response request variable
     * we get 'Status' or 'status'...
     * 
     * @return string
     */
    private function get_request_status($params = array())
    {
        if(empty($params)) {
            if(isset($_REQUEST['Status'])) {
                return $_REQUEST['Status'];
            }

            if(isset($_REQUEST['status'])) {
                return $_REQUEST['status'];
            }
        }
        else {
            if(isset($params['Status'])) {
                return $params['Status'];
            }

            if(isset($params['status'])) {
                return $params['status'];
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
     * @param array $res_args - we must use $res_args instead $_REQUEST, if not empty
     */
    private function change_order_status($order_id, $status, $transactionType = '', $res_args = array())
    {
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        
        SC_CLASS::create_log(
            'Order ' . $order_id .' has Status: ' . $status,
            'Change_order_status(): '
        );
        
        $request = @$_REQUEST;
        if(!empty($res_args)) {
            $request = $res_args;
        }
        
        $message = '';
        $send_message = true;
        
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        
        switch($status) {
            case 'CANCELED':
                $message = $this->language->get('Your request was Canceld') . '. '
                    . 'PPP_TransactionID = ' . @$request['PPP_TransactionID']
                    . ", Status = " . $status . ', GW_TransactionID = '
                    . @$request['TransactionID'];

                $status_id = $order_info['order_status_id'];
                break;

            case 'APPROVED':
                if($transactionType == 'Void') {
                    $message = $this->language->get('DMN message: Your Void request was success, Order #')
                        . @$request['order_id'] . ' ' . $this->language->get('was canceld') . '.';

                    $status_id = $this->config->get($settigs_prefix . 'canceled_status_id');
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
                    try {
                        // when we have Manual Refund there is no record into the DB
                    //    $update_or_insert = 'insert';
                        $curr_refund_amount = floatval(@$_REQUEST['totalAmount']);

                        // get all order Refunds
                        $query = $this->db->query('SELECT * FROM sc_refunds WHERE orderId = ' . $order_id);

                        $refs_sum = 0;
                        if(@$query->rows) {
                            foreach($query->rows as $row) {
                                $row_amount = round(floatval($row['amount']), 2);
                                
                                if($row['approved'] == 1) {
                                    $refs_sum += $row_amount;
                                }
                                // find the record for the current Refund
                                // and check the Amount, the amount in the base is correct one
                                elseif(
                                    $row['clientUniqueId'] == @$_REQUEST['clientUniqueId']
                                    && round($curr_refund_amount, 2) != $row_amount
                                ) {
                                    $curr_refund_amount = $row_amount;
                                //    $update_or_create = 'update';
                                }
                            }
                        }
                        
                        // to the sum of approved refund add current Refund amount
                        $refs_sum += $curr_refund_amount;

                        $send_message = false;
                        $status_id = $order_info['order_status_id'];

                        if(round($refs_sum, 2) == round($order_info['total'], 2)) {
                            $status_id = 11; // Refunded
                            $send_message = true;

                            $this->db->query("UPDATE " . DB_PREFIX
                                . "order SET order_status_id = 11 WHERE order_id = {$order_id};");
                        }
                        
                        $formated_refund = $this->currency->format(
                            $curr_refund_amount,
                            $order_info['currency_code'],
                            $order_info['currency_value']
                        );

                        $message = 'DMN message: Your Refund with Transaction ID #'
                            . @$_REQUEST['clientUniqueId'] .' and Refund Amount: -' . $formated_refund
                            . ' was APPROVED.';

                        # update or insert current Refund data into the DB
                    //    if($update_or_create == 'update') {
                            $q = "UPDATE sc_refunds SET "
                                . "transactionId = '{$this->db->escape(@$_REQUEST['TransactionID'])}', "
                                . "authCode = '{$this->db->escape(@$_REQUEST['AuthCode'])}', "
                                . "approved = 1 "
                            . "WHERE orderId = {$order_id} "
                                . "AND clientUniqueId = '{$this->db->escape(@$_REQUEST['clientUniqueId'])}'";
                    //    }
//                        else {
//                            $q = "INSERT INTO `sc_refunds` (orderId, clientUniqueId, amount, transactionId, authCode, approved) "
//                            . "VALUES ({$order_id}, '{$this->db->escape(@$_REQUEST['clientUniqueId'])}', '{$this->db->escape(@$_REQUEST['totalAmount'])}' ,'{$this->db->escape(@$_REQUEST['TransactionID'])}', '{$this->db->escape(@$_REQUEST['AuthCode'])}', 1)";
//                        }
                        
                        $this->db->query($q);
                    }
                    catch(Exception $e) {
                        SC_CLASS::create_log($e->getMessage(), 'Change order status Exception: ');
                    }
                    break;
                }
                
                $message = 'The amount has been authorized and captured by ' . SC_GATEWAY_TITLE . '. ';
                $status_id = 5; // Complete
                
                if($transactionType == 'Auth') {
                    $message = 'The amount has been authorized and wait to for Settle. ';
                    $status_id = $this->config->get($settigs_prefix . 'pending_status_id');
                }
                elseif($transactionType == 'Settle') {
                    $message = 'The amount has been captured by ' . SC_GATEWAY_TITLE . '. ';
                }
                // set the Order status to Complete
                elseif($transactionType == 'Sale') {
                    $this->db->query("UPDATE " . DB_PREFIX
                        . "order SET order_status_id = 5 WHERE order_id = {$order_id};");
                }
                
                $message .= 'PPP_TransactionID = ' . @$request['PPP_TransactionID']
                    . ", Status = ". $status;
                
                if($transactionType) {
                    $message .= ", TransactionType = ". $transactionType;
                }
                
                $message .= ', GW_TransactionID = '. @$request['TransactionID'];
                
                break;

            case 'ERROR':
            case 'DECLINED':
            case 'FAIL':
                $reason = ', Reason = ';
                if(isset($request['reason']) && $request['reason'] != '') {
                    $reason .= $request['reason'];
                }
                elseif(isset($request['Reason']) && $request['Reason'] != '') {
                    $reason .= $request['Reason'];
                }
                
                $message = 'Payment failed. PPP_TransactionID =  '. @$request['PPP_TransactionID']
                    . ", Status = " . $status . ", Error code = " . @$request['ErrCode']
                    . ", Message = " . @$request['message'] . $reason;
                
                if($transactionType) {
                    $message .= ", TransactionType = " . $transactionType;
                }

                $message .= ', GW_TransactionID = ' . @$request['TransactionID'];
                
                // Void, do not change status
                if($transactionType == 'Void') {
                    $message = $this->language->get('DMN message: Your Void request fail');
                    
                    if(@$_REQUEST['Reason']) {
                        $message .= ' ' . $this->language->get('with message')
                            . ' "' . $_REQUEST['Reason'] . '". ';
                    }
                    else {
                        $message .= '. ';
                    }
                    
                    $status_id = $order_info['order_status_id'];
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
                    $formated_refund = $this->currency->format(
                        @$_REQUEST['totalAmount'],
                        $order_info['currency_code'],
                        $order_info['currency_value']
                    );
                    
                    $message = 'DMN message: Your Refund with Transaction ID #'
                        . @$_REQUEST['clientUniqueId'] .' and Refund Amount: ' . @$formated_refund
                        . ' ' . @$_REQUEST['requestedCurrency'] . ' was fail.';
                    
                    if(@$_REQUEST['Reason']) {
                        $message .= ' Reason: ' . $_REQUEST['Reason'] . '.';
                    }
                    elseif(@$_REQUEST['paymentMethodErrorReason']) {
                        $message .= ' Reason: ' . $_REQUEST['paymentMethodErrorReason'] . '.';
                    }
                    elseif(@$_REQUEST['gwErrorReason']) {
                        $message .= ' Reason: ' . $_REQUEST['gwErrorReason'] . '.';
                    }
                    
                    $status_id = $order_info['order_status_id'];
                    $send_message = false;
                    break;
                }
                
                $status_id = $this->config->get($settigs_prefix . 'failed_status_id');
                break;

            case 'PENDING':
                $status_id = $this->config->get($settigs_prefix . 'pending_status_id');
                
                if ($order_info['order_status_id'] == '5' || $order_info['order_status_id'] == '15') {
                    $status_id = $order_info['order_status_id'];
                    break;
                }
                
                $message = 'Payment is still pending, PPP_TransactionID '
                    . @$request['PPP_TransactionID'] . ", Status = " . $status;

                if($transactionType) {
                    $message .= ", TransactionType = " . $transactionType;
                }

                $message .= ', GW_TransactionID = ' . @$request['TransactionID'];
                
                $this->model_checkout_order->addOrderHistory(
                    $order_id,
                    $status_id,
                    SC_GATEWAY_TITLE .' payment status is pending<br/>Unique Id: '
                        .@$request['PPP_TransactionID'],
                    true
                );
                
                break;
                
            default:
                SC_CLASS::create_log($status, 'Unexisting status: ');
        }
        
        SC_CLASS::create_log($order_id . ', ' . $status_id . ', ' . $message, '$order_id, $status_id, $message: ');
        
        $this->model_checkout_order->addOrderHistory($order_id, $status_id, $message, $send_message);
    }
    
    /**
     * Function update_custom_payment_fields
     * Update Order Custom Payment Fields
     * 
     * @param int $order_id
     * @param array $order_info
     * @param array $data - assocc array to save
     * @param bool $overwrite - overwrite the data or append it
     */
    private function update_custom_payment_fields($order_id, $data = array(), $overwrite = true)
    {
        try {
            // TODO pass the fields instead to get them. We got them at the plece where
            // we call this method.
            $query = $this->db->query(
                "SELECT `payment_custom_field` FROM `" . DB_PREFIX . "order` "
                . "WHERE order_id = " . intval($order_id));

            $payment_custom_fields = $query->row['payment_custom_field'];

            // get the fields as array
            if($payment_custom_fields && is_string($payment_custom_fields)) {
                $payment_custom_fields = json_decode($payment_custom_fields, true);
            }   

            if(empty($data)) {
                $data = array(
                    SC_AUTH_CODE_KEY => isset($_REQUEST['AuthCode']) ? $_REQUEST['AuthCode'] : '',
                    SC_GW_TRANS_ID_KEY => isset($_REQUEST['TransactionID']) ? $_REQUEST['TransactionID'] : '',
                    SC_GW_P3D_RESP_TR_TYPE => isset($_REQUEST['transactionType']) ? $_REQUEST['transactionType'] : '',
                );

                if(isset($_REQUEST['payment_method']) && $_REQUEST['payment_method']) {
                    $data['_paymentMethod'] = $_REQUEST['payment_method'];
                }
            }

            if($overwrite) {
                foreach($data as $key => $val) {
                    $payment_custom_fields[$key] = $val;
                }
            }
            // append data
            else {
                foreach($data as $key => $val) {
                    $payment_custom_fields[$key][] = $val;
                }
            }

            // update custom payment fields
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "order` SET `payment_custom_field` = '"
                . json_encode($payment_custom_fields) . "' WHERE `order_id` = " . $order_id
            );
        }
        catch (Exception $e) {
            SC_CLASS::create_log($e->getMessage(), 'Exception in update_custom_payment_fields():');
        }
    }
    
}
