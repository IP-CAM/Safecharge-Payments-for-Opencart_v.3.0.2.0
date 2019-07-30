<?php

if (!session_id()) {
    session_start();
}

require_once DIR_SYSTEM. 'config'. DIRECTORY_SEPARATOR. 'sc_config.php';
require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge'. DIRECTORY_SEPARATOR. 'sc_logger.php';
require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge'. DIRECTORY_SEPARATOR. 'sc_version_resolver.php';

class ControllerExtensionPaymentSafeCharge extends Controller
{
	public function index()
    {
        $this->load->model('checkout/order');
		$this->load->model('account/reward');
        
        $ctr_file_path = $ctr_url_path = SafeChargeVersionResolver::get_ctr_file_path();
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
		
        $this->language->load($ctr_file_path);
        $order_time = date('YmdHis', time());
        
		# get GW settings to call REST API later
		$settings['secret_key']         = $this->config->get($settigs_prefix . 'secret');
		$settings['merchant_id']        = $this->config->get($settigs_prefix . 'ppp_Merchant_ID');
		$settings['merchantsite_id']    = $this->config->get($settigs_prefix . 'ppp_Merchant_Site_ID');
		$settings['currencyCode']       = $this->session->data['currency'];
		$settings['languageCode']       = current(explode('-', $this->session->data['language']));
		$settings['sc_country']         = $this->session->data['payment_address']['iso_code_2'];
		$settings['payment_api']        = $this->config->get($settigs_prefix . 'payment_api');
		$settings['transaction_type']   = $this->config->get($settigs_prefix . 'transaction_type');
		$settings['test']               = $this->config->get($settigs_prefix . 'test_mode');
		$settings['hash_type']          = $this->config->get($settigs_prefix . 'hash_type');
		$settings['force_http']         = $this->config->get($settigs_prefix . 'force_http');
		$settings['create_logs']        = $this->session->data['create_logs']
                                        = $_SESSION['create_logs']
                                        = $this->config->get($settigs_prefix . 'create_logs');
        # get GW settings to call REST API later END
        
        $countriesWithStates = array('US', 'IN', 'CA');
        // get order data
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        // coupon
        $coupon = array();
        if (isset($this->session->data['coupon'])) {
            $coupon = $this->model_extension_total_coupon
                ->getCoupon($this->session->data['coupon']);
        }
        
        $prods = $this->cart->getProducts(); // get the products
        $tax_id = false;
        
        // array with data for the Order
        $params = array();
        
        $params['numberofitems']    = 1;
        $params['handling']         = '0.00';
        $params['total_tax']        = '0.00'; // taxes
        $params['merchant_id']      = $settings['merchant_id'];
		$params['merchant_site_id'] = $settings['merchantsite_id'];
		$params['time_stamp']       = $order_time;
		$params['encoding']         = 'utf-8';
		$params['version']          = '4.0.0';
        
        $params['success_url']      = $this->url->link($ctr_url_path . '/success');
		$params['pending_url']      = $this->url->link($ctr_url_path . '/success');
		$params['error_url']        = $this->url->link($ctr_url_path . '/fail');
		$params['back_url']         = $this->url->link('checkout/checkout', '', true);
		
        $params['notify_url']       = $this->url->link($ctr_url_path . '/callback&create_logs=' . $settings['create_logs']);
        if($settings['force_http'] == 'yes') {
            $params['notify_url'] = str_replace('https://', 'http://', $params['notify_url']);
        }
        
        $params['invoice_id']           = $this->session->data['order_id'].'_'.date('YmdHis', time());
		$params['merchant_unique_id']   = $this->session->data['order_id'];

        $params['first_name']   = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_firstname']));
        $params['last_name']    = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_lastname']));
        $params['address1']     = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_address_1']));
        $params['address2']     = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_address_2']));
        $params['zip']          = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_postcode']));
        $params['city']         = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_city']));
        
        $params['state']        = urlencode(preg_replace("/[[:punct:]]/", '', substr($order_info['payment_zone'], 0, 2)));
        if (in_array($order_info['payment_iso_code_2'], $countriesWithStates)) {
			$params['state']    = $order_info['payment_zone_code'];
		}
		
        $params['country']          = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['payment_iso_code_2']));
        $params['phone1']           = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['telephone']));
		$params['email']            = $order_info['email'];
        $params['user_token_id']    = $params['email'];
        
        $params['shippingFirstName']    = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_firstname']));
        $params['shippingLastName']     = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_lastname']));
        $params['shippingAddress']      = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_address_1']));
        $params['shippingCity']         = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_city']));
        $params['shippingCountry']      = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_iso_code_2']));
        $params['shippingZip']          = urlencode(preg_replace("/[[:punct:]]/", '', $order_info['shipping_postcode']));
        
        $params['user_token']       = 'auto';
        $params['payment_method']   = ''; // fill it for the REST API
        
        $total_amount = $this->currency->format(
            $order_info['total'],
            $order_info['currency_code'],
            $order_info['currency_value'],
            false
        );
        
        if($total_amount < 0) {
            $params['total_amount'] = number_format(0, 2, '.', '');
        }
        else {
            $params['total_amount'] = number_format($total_amount, 2, '.', '');
        }
        
        $params['currency']         = $order_info['currency_code'];
        $params['merchantLocale']   = $this->get_locale();
        $params['webMasterId']      = 'OpenCart ' . VERSION;
        
        # for the REST API
        if($settings['payment_api'] == 'rest') {
            require_once DIR_SYSTEM . 'library' .DIRECTORY_SEPARATOR .'safecharge' . DIRECTORY_SEPARATOR . 'SC_REST_API.php';
            
            $settings['merchantId'] = $settings['merchant_id'];
            $data['merchantSiteId'] = $settings['merchantSiteId'] = $settings['merchantsite_id'];
            
            // for the REST set one combined item only
            $params['items[0][name]']      = $this->session->data['order_id'];
            $params['items[0][price]']     = $params['total_amount'];
            $params['items[0][quantity]']  = 1;
            
            # get UPOs
            $upos = array();
            
            if((bool)$this->customer->isLogged()) {
                $time = date('YmdHis', time());
                
                $upos_data = SC_REST_API::get_user_upos(
                    array(
                        'merchantId'        => $settings['merchantId'],
                        'merchantSiteId'    => $settings['merchantSiteId'],
                        'userTokenId'       => $params['email'],
                        'clientRequestId'   => $time . '_' . uniqid(),
                        'timeStamp'         => $time,
                    ),
                    array(
                        'hash_type' => $settings['hash_type'],
                        'secret'    => $settings['secret_key'],
                        'test'      => $settings['test'],
                    )
                );

                if(isset($upos_data['paymentMethods']) && $upos_data['paymentMethods']) {
                    foreach($upos_data['paymentMethods'] as $upo_data) {
                        if(
                            $upo_data['upoStatus'] == 'enabled'
                            && strtotime(@$upo_data['expiryDate']) > strtotime(date('Ymd'))
                        ) {
                            $upos[] = $upo_data;
                        }
                    }
                }
            }
            
            # get APMs
            // client request id 1
            $time = date('YmdHis', time());
            $settings['cri1'] = $time. '_' .uniqid();

            // checksum 1 - checksum for session token
            $settings['cs1'] = hash(
                $settings['hash_type'],
                $settings['merchant_id'] . $settings['merchantsite_id']
                    . $settings['cri1'] . $time . $settings['secret_key']
            );

            // client request id 2
            $time = date('YmdHis', time());
            $settings['cri2'] = $time. '_' .uniqid();

            // checksum 2 - checksum for get apms
            $time = date('YmdHis', time());
            $settings['cs2'] = hash(
                $settings['hash_type'],
                $settings['merchant_id'] . $settings['merchantsite_id']
                    . $settings['cri2'] . $time . $settings['secret_key']
            );
            
            $res = SC_REST_API::get_rest_apms($settings);
            
            // set template data with the payment methods
            $data['payment_methods']  = array();
            if(is_array($res) && isset($res['paymentMethods']) && !empty($res['paymentMethods'])) {
                $data['payment_methods'] = $res['paymentMethods'];
            }
            else {
                SC_LOGGER::create_log($res, 'API response: ');
                
                echo
                    '<script type="text/javascript">location.href = "'
                        . $this->url->link($ctr_url_path . '/fail') . '";</script>';
                exit;
            }
            # get APMs END
            
            // add icons for the upos
            $data['icons']  = array();
            $data['upos']   = array();

            if($upos && $data['payment_methods']) {
                foreach($upos as $upo_key => $upo) {
                    if(
                        @$upo['upoStatus'] != 'enabled'
                        || (isset($upo['upoData']['ccCardNumber'])
                            && empty($upo['upoData']['ccCardNumber']))
                        || (isset($upo['expiryDate'])
                            && strtotime($upo['expiryDate']) < strtotime(date('Ymd')))
                    ) {
                        continue;
                    }

                    // search in payment methods
                    foreach($data['payment_methods'] as $pm) {
                        if(@$pm['paymentMethod'] == @$upo['paymentMethodName']) {
                            if(
                                in_array(@$upo['paymentMethodName'], array('cc_card', 'dc_card'))
                                && @$upo['upoData']['brand']
                            ) {
                                $data['icons'][@$upo['upoData']['brand']] = str_replace(
                                    'default_cc_card',
                                    $upo['upoData']['brand'],
                                    $pm['logoURL']
                                );
                            }
                            else {
                                $data['icons'][$pm['paymentMethod']] = $pm['logoURL'];
                            }
                            
                            $data['upos'][] = $upo;
                            break;
                        }
                    }
                }
            }
            
            // specific data for the REST payment
            $params['client_request_id']    = $time .'_'. uniqid();
            
            $params['checksum'] = hash(
                $settings['hash_type'],
                stripslashes(
                    $settings['merchant_id']
                    .$settings['merchantsite_id']
                    .$params['client_request_id']
                    .$params['total_amount']
                    .$params['currency']
                    .$order_time
                    .$settings['secret_key']
                )
            );
            
            // params for last get_session_token
            $time = date('YmdHis', time());
            $un_req_id = uniqid();
            $st_cs = hash(
                $settings['hash_type'],
                $settings['merchant_id'] . $settings['merchantsite_id']
                    . $un_req_id . $time . $settings['secret_key']
            );
            
            $resp = SC_REST_API::get_session_token(array(
                'merchantId'        => $settings['merchantId'],
                'merchantSiteId'    => $settings['merchantSiteId'],
                'cri1'              => $un_req_id,
                'cs1'               => $st_cs,
                'timeStamp'         => $time,
                'test'              => $settings['test'],
            ));
            
            if(!$resp || !isset($resp['sessionToken']) || !$resp['sessionToken']) {
                SC_LOGGER::create_log('Error when trying to generate Session Token for Fields! ');
//                echo '<script type="text/javascript">location.href = "'
//                    . $this->url->link($ctr_url_path . '/fail') . '";</script>';
//                exit;
            }
            
            unset($settings['secret_key']);
            $this->session->data['SC_Settings'] = $settings;

            $data['sessionToken']   = $resp['sessionToken'];
            $data['scLocale']       = substr($params['merchantLocale'], 0, 2);
            
            $data['action'] = $this->url->link($ctr_url_path . '/process_payment')
                . '&create_logs=' . ($settings['create_logs']);
            
            $data['payload_url'] = $this->url->link($ctr_url_path . '/sc_ajax_call')
                . '&create_logs=' . $settings['create_logs'];
            
            // fields for the template
            $data['html_inputs'] = $params;
        }
        # for the Cashier
        else {
            if(isset($this->session->data['shipping_method']['cost'])) {
                $params['handling'] = $this->tax->calculate(
                    $this->session->data['shipping_method']['cost'],
                    $this->session->data['shipping_method']['tax_class_id'],
                    $this->config->get('config_tax')
                );

                $tax_id = $this->session->data['shipping_method']['tax_class_id'];
            }
            
            // set the Items
            $i = $items_price = 0;

            foreach ($prods as $product) {
                $i++;
                
                if(!$tax_id && isset($product['tax_class_id'])) {
                    $tax_id = $product['tax_class_id'];
                }
                
//                $product_price_total = $this->tax->calculate(
//                    $product['total'],
//                    $tax_id,
//                    $this->config->get('config_tax')
//                );
                
                $product_price = $this->tax->calculate(
                    $product['price'],
                    $tax_id,
                    $this->config->get('config_tax')
                );
                
                $params['item_name_'.$i]      = urlencode($product['name']);
                $params['item_number_'.$i]    = $product['model'];
                $params['item_quantity_'.$i]  = $product['quantity'];
                $params['item_amount_'.$i]    = number_format($product_price, 2, '.', '');
                
                $items_price += $product_price * $product['quantity'];
            }
            
            $params['numberofitems'] = $i;
            
            # Discounts
            $discount = 0;
            
            // coupon
            if (isset($this->session->data['coupon'])) {
                $coupon = $this
                    ->model_extension_total_coupon
                    ->getCoupon($this->session->data['coupon']);
                
                if ($coupon['type'] != 'P') {
                    $discount = $coupon['discount'];
                }
                else {
                    $discount = $items_price * $coupon['discount'] / 100;
                }
                
                // when the coupon give free shipping
                if($coupon['shipping'] == 1) {
                    $discount += $params['handling'];
                }
            }
            
            // vauchers
            if (isset($_this->session->data['voucher'])) {
                $voucher = $this
                    ->model_extension_total_voucher
                    ->getVoucher($this->session->data['voucher']);
                
                if (isset($voucher['amount'])) {
                    $discount += $voucher['amount'];
                }
            }

            $discount_total = $this->tax->calculate(
				$discount,
				$tax_id,
				$this->config->get('config_tax')
			);
            # Discounts END
            
            $items_price            = round($items_price, 2);
            $params['handling']     = round($params['handling'], 2);
            $discount_total         = round($discount_total, 2);
            $params['total_amount'] = round($params['total_amount'], 2);
            
            // last check for correct calculations
            $test_diff = $items_price + $params['handling'] - $discount_total - $params['total_amount'];
            
            if($test_diff != 0) {
                SC_LOGGER::create_log($params['handling'], 'handling before $test_diff: ');
                SC_LOGGER::create_log($discount_total, 'discount_total before $test_diff: ');
                SC_LOGGER::create_log($test_diff, '$test_diff: ');
                
                if($test_diff > 0) {
                    if($params['handling'] - $test_diff >= 0) {
						$params['handling'] -= $test_diff; // will decrease
					}
                    else {
                        $discount_total += $test_diff; // will increase
                    }
					
				}
				else {
                    if($discount_total + $test_diff > 0) {
                        $discount_total += $test_diff; // will decrease
                    }
                    else {
                        $params['handling'] += abs($test_diff); // will increase
                    }
				}
            }
            
            $params['discount'] = number_format($discount_total, 2, '.', '');
            $params['handling'] = number_format($params['handling'], 2, '.', '');
            // set the Items END
            
            // the end point URL depends of the test mode and selected payment api
            $data['action'] = $settings['test'] == 'yes' ?
                SC_TEST_CASHIER_URL : SC_LIVE_CASHIER_URL;
            
            $for_checksum = $settings['secret_key'] . implode('', $params);
            
            $params['checksum'] = hash(
                $settings['hash_type'],
                stripslashes($for_checksum)
            );
            
            $data['html_inputs'] = $params;
        //    echo '<pre>'.print_r($params, true).'</pre>';
            SC_LOGGER::create_log($data['html_inputs'], 'Cashier inputs: ');
        }
		
        // data for the template
		$data['payment_api']        = $settings['payment_api'];
		$data['sc_test_env']        = $settings['test'];
        
        // texts
		$data['sc_attention']       = $this->language->get('Attention!');
		$data['sc_go_to_step_2_error'] =
            $this->language->get('You must confirm all steps starting from Step 2, to get correct APMs!');
		$data['button_confirm']     = $this->language->get('button_confirm');
		$data['sc_btn_loading']     = $this->language->get('Loading...');
        $data['sc_upos_title']      = $this->language->get('Choose from you prefered payment methods');
        $data['sc_pms_title']       = $this->language->get('Choose from the other payment methods');
        $data['choose_pm_error']    = $this->language->get('Please, choose payment method and fill all its fields!');
        $data['rest_no_apms_error'] = $this->language->get('rest_no_apms_error');
        $data['sc_token_error']     = $this->language->get('Error in Tokenization process.');
        $data['sc_token_error_2']   = $this->language->get('Error when try to proceed the payment. Please, check you fields!');
        
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
        SC_LOGGER::create_log('success page');
        
        $this->load->model('checkout/order');
    //    SC_LOGGER::create_log($this->session->data, 'success, session.');
    //    SC_LOGGER::create_log($_REQUEST, 'success, $_REQUEST.');
        
        # P3D case 1 - response form issuer/bank
        if(
            isset($this->session->data['SC_P3D_Params'], $_REQUEST['PaRes'])
            && is_array($this->session->data['SC_P3D_Params'])
            && !empty($_REQUEST['PaRes'])
        ) {
            $this->pay_with_d3d_p3d();
        }
        
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
        SC_LOGGER::create_log(@$_REQUEST, 'Order FAIL: ');
        
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
        SC_LOGGER::create_log(@$_REQUEST, 'DMN request: ');
        
        if(!$this->checkAdvancedCheckSum()) {
            SC_LOGGER::create_log('', 'DMN report: You receive DMN from not trusted source. The process ends here.');
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
            SC_LOGGER::create_log('', 'A sale/auth.');
            $order_id = 0;
            
            // Cashier
            if(!empty($_REQUEST['invoice_id'])) {
                SC_LOGGER::create_log('', 'Cashier sale.');
                
                try {
                    $arr = explode("_", $_REQUEST['invoice_id']);
                    $order_id  = intval($arr[0]);
                }
                catch (Exception $ex) {
                    SC_LOGGER::create_log($ex->getMessage(), 'Cashier DMN Exception when try to get Order ID: ');
                    echo 'DMN Exception: ' . $ex->getMessage();
                    exit;
                }
            }
            // REST
            else {
                SC_LOGGER::create_log('REST sale.');
                
                try {
                    $order_id = intval($_REQUEST['merchant_unique_id']);
                }
                catch (Exception $ex) {
                    SC_LOGGER::create_log($ex->getMessage(), 'REST DMN Exception when try to get Order ID: ');
                    echo 'DMN Exception: ' . $ex->getMessage();
                    exit;
                }
            }
            
            try {
                $order_info = $this->model_checkout_order->getOrder($order_id);
                
                $this->update_custom_payment_fields($order_id);

                // 5 => Complete
                $order_status_id = intval($order_info['order_status_id']);
                if($order_status_id != 5) {
                    $this->change_order_status($order_id, $req_status, $_REQUEST['transactionType']);
                }
            }
            catch (Exception $ex) {
                SC_LOGGER::create_log($ex->getMessage(), 'Sale DMN Exception: ');
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
            SC_LOGGER::create_log('OpenCart Refund DMN.');
            
            $order_info = $this->model_checkout_order->getOrder(@$_REQUEST['order_id']);
            
            if(!$order_info) {
                SC_LOGGER::create_log($order_info, 'There is no order info: ');
                    
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
            SC_LOGGER::create_log($_REQUEST['transactionType'], 'Void/Settle transactionType: ');
            
            try {
                $order_info = $this->model_checkout_order->getOrder($_REQUEST['order_id']);
                
                if($_REQUEST['transactionType'] == 'Settle') {
                    $this->update_custom_payment_fields($_REQUEST['order_id']);
                }
                
                $this->change_order_status(intval(@$_REQUEST['order_id']), $req_status, $_REQUEST['transactionType']);
            }
            catch (Exception $ex) {
                SC_LOGGER::create_log(
                    $ex->getMessage(),
                    'callback() Void/Settle Exception: '
                );
            }
        }
        
        SC_LOGGER::create_log('', 'Callback end. ');
        
        echo 'DMN received.';
        exit;
	}
    
    // we use it when tokenize a cart
    public function sc_ajax_call()
    {
        if(
            isset($_SERVER['HTTP_X_REQUESTED_WITH'], $_POST['needST'], $this->session->data['SC_Settings'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
            && $_POST['needST'] == 1
        ) {
            require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge' . DIRECTORY_SEPARATOR. 'SC_REST_API.php';
            
            SC_REST_API::get_session_token($this->session->data['SC_Settings'], true);
        }
        
        echo 'sc_ajax_call';
        exit;
    }
    
    /**
     * Function process_payment
     * We use this method with REST API.
     * Here we send the data from the form and prepare it before send it to the API.
     */
    public function process_payment()
    {
        SC_LOGGER::create_log('process_payment()');
        
        $post = $this->request->post;
        
        if(!@$post['payment_method_sc']) {
            SC_LOGGER::create_log('process_payment - payment_method_sc problem');
            
            $this->response->redirect(@$post['error_url']);
        }
        
        require_once DIR_SYSTEM. 'library' .DIRECTORY_SEPARATOR .'safecharge' . DIRECTORY_SEPARATOR. 'SC_REST_API.php';
        
        $ctr_file_path  = SafeChargeVersionResolver::get_ctr_file_path();
        $ctr_url_path   = SafeChargeVersionResolver::get_public_ctr_file_path();
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        
        $this->language->load($ctr_file_path);
        $data['process_payment'] = $this->language->get('Processing the payment. Please, wait!');
        
        $TimeStamp = date('YmdHis', time());
        
        // map here variables
        try {
            $test_mode  = $this->config->get($settigs_prefix . 'test_mode');
            $secret     = $this->config->get($settigs_prefix . 'secret');
            $hash       = $this->config->get($settigs_prefix . 'hash_type');
            
            $params = array(
                'merchantId'        => $post['merchant_id'],
                'merchantSiteId'    => $post['merchant_site_id'],
                'userTokenId'       => $post['user_token_id'],
                'clientUniqueId'    => $this->session->data['order_id'],
                'clientRequestId'   => $post['client_request_id'],
                'currency'          => $post['currency'],
                'amount'            => (string) $post['total_amount'],
                'amountDetails'     => array(
                    'totalShipping'     => '0.00',
                    'totalHandling'     => '0.00',
                    'totalDiscount'     => '0.00',
                    'totalTax'          => '0.00',
                ),
                'items'             => $post['items'],
                'userDetails'       => array(
                    'firstName'         => $post['first_name'],
                    'lastName'          => $post['last_name'],
                    'address'           => $post['address1'],
                    'phone'             => $post['phone1'],
                    'zip'               => $post['zip'],
                    'city'              => $post['city'],
                    'country'           => $post['country'],
                    'state'             => '',
                    'email'             => $post['email'],
                    'county'            => '',
                ),
                'shippingAddress'   => array(
                    'firstName'         => $post['shippingFirstName'],
                    'lastName'          => $post['shippingLastName'],
                    'address'           => $post['shippingAddress'],
                    'cell'              => '',
                    'phone'             => '',
                    'zip'               => $post['shippingZip'],
                    'city'              => $post['shippingCity'],
                    'country'           => $post['shippingCountry'],
                    'state'             => '',
                    'email'             => '',
                    'shippingCounty'    => '',
                ),
                'billingAddress'   => array(
                    'firstName'         => $post['first_name'],
                    'lastName'          => $post['last_name'],
                    'address'           => $post['address1'],
                    'cell'              => '',
                    'phone'             => $post['phone1'],
                    'zip'               => $post['zip'],
                    'city'              => $post['city'],
                    'country'           => $post['country'],
                    'state'             => $post['state'],
                    'email'             => $post['email'],
                    'county'            => '',
                ),
                'urlDetails'        => array(
                    'successUrl'        => $post['success_url'],
                    'failureUrl'        => $post['error_url'],
                    'pendingUrl'        => $post['pending_url'],
                    'notificationUrl'   => $post['notify_url'],
                ),
                'timeStamp'         => $TimeStamp,
                'webMasterID'       => @$post['webMasterId'],
                'sessionToken'      => @$post['lst'],
                'deviceDetails'     => SC_REST_API::get_device_details(),
            );
            
            $params['checksum'] = hash(
                $hash,
                $params['merchantId'] . $params['merchantSiteId'] . $params['clientRequestId']
                    . $params['amount'] . $params['currency'] . $TimeStamp . $secret
            );
            
            // in case of UPO
            if(is_numeric(@$post['payment_method_sc'])) {
                $params['userPaymentOption'] = array(
                    'userPaymentOptionId' => $post['payment_method_sc'],
                    'CVV' => $post['upo_cvv_field_' . $post['payment_method_sc']],
                );
                
                $params['isDynamic3D'] = 1;
                $endpoint_url = $test_mode == 'no' ? SC_LIVE_D3D_URL : SC_TEST_D3D_URL;
            }
            // in case of Card
            elseif(in_array(@$post['payment_method_sc'], array('cc_card', 'dc_card'))) {
                if(isset($post[$post['payment_method_sc']]['ccTempToken'])) {
                    $params['cardData']['ccTempToken'] = $post[$post['payment_method_sc']]['ccTempToken'];
                }
                
                if(isset($post[$post['payment_method_sc']]['CVV'])) {
                    $params['cardData']['CVV'] = $post[$post['payment_method_sc']]['CVV'];
                }
                
                if(isset($post[$post['payment_method_sc']]['cardHolderName'])) {
                    $params['cardData']['cardHolderName'] = $post[$post['payment_method_sc']]['cardHolderName'];
                }

                $params['isDynamic3D'] = 1;
                $endpoint_url = $test_mode == 'no' ? SC_LIVE_D3D_URL : SC_TEST_D3D_URL;
            }
            // in case of APM
            elseif(@$post['payment_method_sc']) {
                $endpoint_url = $test_mode == 'no' ? SC_LIVE_PAYMENT_URL : SC_TEST_PAYMENT_URL;
                $params['paymentMethod'] = $post['payment_method_sc'];
                
                if(isset($post[@$post['payment_method_sc']]) && is_array($post[$post['payment_method_sc']])) {
                    $params['userAccountDetails'] = $_POST[$_POST['payment_method_sc']];
                    
                }
            }
            
            $resp = SC_REST_API::call_rest_api($endpoint_url, $params);
            
            SC_LOGGER::create_log($resp, 'process_payment response:');
            
            if(!$resp || $this->get_request_status($resp) == 'ERROR') {
                $this->response->redirect($post['error_url']);
            }
            
            if($this->get_request_status($resp) == 'ERROR' || @$resp['transactionStatus'] == 'ERROR') {
                $this->change_order_status(
                    intval($this->session->data['order_id']), 
                    'ERROR', 
                    @$resp['transactionType']
                );
                
                $this->response->redirect($post['error_url']);
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
                # The case with D3D and P3D
                // isDynamic3D is hardcoded to be 1, see SC_REST_API line 509
                // for the three cases see: https://www.safecharge.com/docs/API/?json#dynamic3D,
                // Possible Scenarios for Dynamic 3D (isDynamic3D = 1)
                
                // clear the old session data
                if(isset($this->session->data['SC_P3D_Params'])) {
                    unset($this->session->data['SC_P3D_Params']);
                }
                
                // prepare the new session data
                if(isset($params['userPaymentOption']) || isset($params['cardData'])) {
                    $params_p3d = $params;
                    
                    $params_p3d['orderId']          = $resp['orderId'];
                    $params_p3d['transactionType']  = @$resp['transactionType'];
                    $params_p3d['paResponse']       = '';
                    
                    $this->session->data['SC_P3D_Params'] = $params_p3d;
                    
                    // case 1
                    if(
                        isset($resp['acsUrl'], $resp['threeDFlow'])
                        && !empty($resp['acsUrl'])
                        && intval($resp['threeDFlow']) == 1
                    ) {
                        SC_LOGGER::create_log('D3D case 1');
                        SC_LOGGER::create_log($resp['acsUrl'], 'acsUrl: ');
                    
                        // step 1 - go to acsUrl, it will return us to Pending page
                        $data['acsUrl']     = $resp['acsUrl'];
                        $data['paRequest']  = $resp['paRequest'];
                        // it is also pending page
                        $data['TermUrl']    = $post['success_url'] . '&create_logs=' . @$_REQUEST['create_logs'];
                        
                        SC_LOGGER::create_log($data, 'params for acsUrl: ');
                        
                        // step 2 - wait for the DMN
                    }
                    // case 2
                    elseif(isset($resp['threeDFlow']) && intval($resp['threeDFlow']) == 1) {
                        SC_LOGGER::create_log('process_payment() D3D case 2.');
                        $this->pay_with_d3d_p3d(@$params['webMasterId']); // we exit there
                    }
                    // case 3 do nothing
                }
                // The case with D3D and P3D END
                // in case we have redirectURL
                elseif(isset($resp['redirectURL']) && !empty($resp['redirectURL'])) {
                    $data['redirectURL'] = $resp['redirectURL'];
                    $data['pendingURL'] = $post['success_url'];
                }
            }
            
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($resp['orderId']);

            if($order_info['order_status_id'] == $this->config->get($settigs_prefix . 'pending_status_id')) {
                $this->model_checkout_order->addOrderHistory($resp['orderId'], 5, 'Order Completed.', false);
                $order_info['order_status_id'] = 5;
            }

            if(isset($resp['transactionId']) && $resp['transactionId'] != '') {
                $this->model_checkout_order->addOrderHistory(
                    $resp['orderId'],
                    $order_info['order_status_id'],
                    'Payment succsess for Transaction Id ' . $resp['transactionId'],
                    true
                );
            }
            else {
                $this->model_checkout_order->addOrderHistory(
                    $resp['orderId'],
                    $order_info['clientUniqueId'],
                    'Payment succsess.',
                    true
                );
            }
            
            $data['header']         = $this->load->controller('common/header');
            $data['footer']         = $this->load->controller('common/footer');
            $data['column_left']    = $this->load->controller('common/column_left');
            $data['success_url']    = $post['success_url'];

            // load common php template and then pass it to the real template
            // as single variable. The form is same for both versions
            $curr_tpl = str_replace('safecharge', 'process_payment', $ctr_file_path);

            ob_start();
            require DIR_TEMPLATE . 'default/template/' . $curr_tpl . '.php';
            $sc_form['sc_form'] = ob_get_clean(); // the template of OC wants array

            $this->response->setOutput($this->load->view(
                $curr_tpl . SafeChargeVersionResolver::get_tpl_extension(),
                $sc_form
            ));
        }
        catch (Exception $ex) {
            SC_LOGGER::create_log($ex->getMessage(), 'process_payment Exception: ');
            $this->response->redirect($post['error_url']);
        }
    }
    
    /**
     * Function pay_with_d3d_p3d
     * After we get the DMN form the issuer/bank call this method to continue the flow.
     */
    public function pay_with_d3d_p3d()
    {
        SC_LOGGER::create_log('pay_with_d3d_p3d');
        
        $settigs_prefix = SafeChargeVersionResolver::get_settings_prefix();
        $p3d_resp = false;
        
        if(isset($_REQUEST['PaRes'])) {
            $this->session->data['SC_P3D_Params']['paResponse'] = $_REQUEST['PaRes'];
        }
        
        try {
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['SC_P3D_Params']['clientUniqueId']);

            $sc_P3D_Params = $this->session->data['SC_P3D_Params'];
            $sc_P3D_Params['transactionType'] = $this->config->get($settigs_prefix . 'transaction_type');
//            $sc_P3D_Params['urlDetails']['notificationUrl'] =
//                $sc_P3D_Params['urlDetails']['notificationUrl']['notificationUrl'];
            
            // load help files
            require_once DIR_SYSTEM . 'library' .DIRECTORY_SEPARATOR .'safecharge' . DIRECTORY_SEPARATOR . 'SC_REST_API.php';

            $p3d_resp = SC_REST_API::call_rest_api(
                $this->config->get($settigs_prefix . 'test_mode') == 'yes' ? SC_TEST_P3D_URL : SC_LIVE_P3D_URL
                ,$sc_P3D_Params
                ,$sc_P3D_Params['checksum']
            );
        }
        catch (Exception $ex) {
            SC_LOGGER::create_log($ex->getMessage(), 'P3D fail Exception: ');
            $this->response->redirect($this->url->link('checkout/failure'));
        }
        
        SC_LOGGER::create_log($p3d_resp, 'D3D / P3D, REST API Call response: ');

        if(!$p3d_resp) {
            if($order_info['order_status_id'] == $this->config->get($settigs_prefix . 'pending_status_id')) {
                $this->change_order_status(
                    intval($this->session->data['SC_P3D_Params']['clientUniqueId']),
                    'FAIL',
                    $this->config->get($settigs_prefix . 'transaction_type')
                );
            }
            else {
                $this->model_checkout_order->addOrderHistory(
                    $this->session->data['SC_P3D_Params']['clientUniqueId'],
                    $order_info['order_status_id'],
                    'Payment 3D API response fails.',
                    true
                );
            }

            SC_LOGGER::create_log('Payment 3D API response fails.');
            $this->response->redirect($this->url->link('checkout/failure'));
        }

        $this->response->redirect($this->url->link('checkout/success'));
        exit;
        // now wait for the DMN
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
        
        SC_LOGGER::create_log(
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
                        . @$request['clientUniqueId'] . ' ' . $this->language->get('was canceld') . '.';

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
                        SC_LOGGER::create_log($e->getMessage(), 'Change order status Exception: ');
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
                SC_LOGGER::create_log($status, 'Unexisting status: ');
        }
        
        SC_LOGGER::create_log($order_id . ', ' . $status_id . ', ' . $message, '$order_id, $status_id, $message: ');
        
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
            SC_LOGGER::create_log($e->getMessage(), 'Exception in update_custom_payment_fields():');
        }
    }
    
}
