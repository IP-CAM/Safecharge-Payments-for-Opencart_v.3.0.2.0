<?php

/**
 * NUVEI_CLASS Class
 * 
 * A class for work with Nuvei REST API.
 * 
 * @author Nuvei
 */

define('NUVEI_PLUGIN_V',        '2.1');

define('NUVEI_LIVE_URL_BASE',   'https://secure.safecharge.com/ppp/api/v1/');
define('NUVEI_TEST_URL_BASE',   'https://ppp-test.safecharge.com/ppp/api/v1/');

define('NUVEI_AUTH_CODE',       '_authCode');
define('NUVEI_TRANS_ID',        '_transactionId');
define('NUVEI_TRANS_TYPE',      '_transactionType');

class NUVEI_CLASS
{
	// array details to validate request parameters
    private static $params_validation = array(
        // deviceDetails
        'deviceType' => array(
            'length' => 10,
            'flag'    => FILTER_SANITIZE_STRING
        ),
        'deviceName' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'deviceOS' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'browser' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
//        'ipAddress' => array(
//            'length' => 15,
//            'flag'    => FILTER_VALIDATE_IP
//        ),
        // deviceDetails END
        
        // userDetails, shippingAddress, billingAddress
        'firstName' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'lastName' => array(
            'length' => 40,
            'flag'    => FILTER_DEFAULT
        ),
        'address' => array(
            'length' => 60,
            'flag'    => FILTER_DEFAULT
        ),
        'cell' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'phone' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'zip' => array(
            'length' => 10,
            'flag'    => FILTER_DEFAULT
        ),
        'city' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'country' => array(
            'length' => 20,
            'flag'    => FILTER_SANITIZE_STRING
        ),
        'state' => array(
            'length' => 2,
            'flag'    => FILTER_SANITIZE_STRING
        ),
        'county' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        // userDetails, shippingAddress, billingAddress END
        
        // specific for shippingAddress
        'shippingCounty' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine2' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine3' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        // specific for shippingAddress END
        
        // urlDetails
        'successUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'failureUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'pendingUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'notificationUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        // urlDetails END
    );
	
	private static $params_validation_email = array(
		'length'	=> 79,
		'flag'		=> FILTER_VALIDATE_EMAIL
	);
	
    private static $devices = array('iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac');
    
    private static $browsers = array('ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident');
    
    private static $device_types = array('macintosh', 'tablet', 'mobile', 'tv', 'windows', 'linux', 'tv', 'smarttv', 'googletv', 'appletv', 'hbbtv', 'pov_tv', 'netcast.tv', 'bluray');
    
    /**
	 * Function call_rest_api
	 * 
	 * Call REST API with cURL post and get response.
	 * The URL depends from the case.
	 *
	 * @param string $url_method
	 * @param array $params - parameters
	 *
	 * @return mixed
	 */
    public static function call_rest_api($url_method, $params)
    {
		if (empty($url_method)) {
			self::create_log('REST API call, the URL is empty!');
			
			return false;
		}
		$url    = ('yes' == $_SESSION['nuvei_test_mode'] ? NUVEI_TEST_URL_BASE : NUVEI_LIVE_URL_BASE) . $url_method . '.do';
        $resp   = false;
		
        // get them only if we pass them empty
		if (empty($params['deviceDetails'])) {
			$params['deviceDetails'] = self::get_device_details();
		}
		
		# validate parameters
		// directly check the mails
		if(isset($params['billingAddress']['email'])) {
			if(!filter_var($params['billingAddress']['email'], self::$params_validation_email['flag'])) {
				self::create_log('call_rest_api() Error - Billing Address Email is not valid.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Billing Address Email is not valid.'
				);
			}
			
			if(strlen($params['billingAddress']['email']) > self::$params_validation_email['length']) {
				self::create_log('call_rest_api() Error - Billing Address Email is too long');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Billing Address Email is too long.'
				);
			}
		}
		
		if(isset($params['shippingAddress']['email'])) {
			if(!filter_var($params['shippingAddress']['email'], self::$params_validation_email['flag'])) {
				self::create_log('call_rest_api() Error - Shipping Address Email is not valid.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Shipping Address Email is not valid.'
				);
			}
			
			if(strlen($params['shippingAddress']['email']) > self::$params_validation_email['length']) {
				self::create_log('call_rest_api() Error - Shipping Address Email is too long.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Shipping Address Email is too long'
				);
			}
		}
		// directly check the mails END
		
		foreach ($params as $key1 => $val1) {
            if (!is_array($val1) && !empty($val1) && array_key_exists($key1, self::$params_validation)) {
                $new_val = $val1;
                
                if (mb_strlen($val1) > self::$params_validation[$key1]['length']) {
                    $new_val = mb_substr($val1, 0, self::$params_validation[$key1]['length']);
                    
                    self::create_log($key1, 'Limit');
                }
                
                $params[$key1] = filter_var($new_val, self::$params_validation[$key1]['flag']);
            }
			elseif (is_array($val1) && !empty($val1)) {
                foreach ($val1 as $key2 => $val2) {
                    if (!is_array($val2) && !empty($val2) && array_key_exists($key2, self::$params_validation)) {
                        $new_val = $val2;

                        if (mb_strlen($val2) > self::$params_validation[$key2]['length']) {
                            $new_val = mb_substr($val2, 0, self::$params_validation[$key2]['length']);
                            
                            self::create_log($key2, 'Limit');
                        }

                        $params[$key1][$key2] = filter_var($new_val, self::$params_validation[$key2]['flag']);
                    }
                }
            }
        }
		# validate parameters END
		
		self::create_log(
            array(
				'url' => $url,
				'params' => $params,
			)
            , 'call_rest_api() params after validation'
        );
        
        $json_post = json_encode($params);
        
        try {
            $header =  array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_post),
            );
            
            // create cURL post
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $resp = curl_exec($ch);
            curl_close ($ch);
			
			$resp_arr = json_decode($resp, true);
            
            self::create_log($resp_arr, 'REST API Response: ');
			
			return $resp_arr;
        }
        catch(Exception $e) {
            self::create_log($e->getMessage(), 'Call REST API Exception');
			return false;
        }
    }
    
    /**
     * Function get_device_details
	 * 
     * Get browser and device based on HTTP_USER_AGENT.
     * The method is based on D3D payment needs.
     * 
     * @return array $device_details
     */
    public static function get_device_details()
    {
        $device_details = array(
            'deviceType'    => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
            'deviceName'    => 'UNKNOWN',
			'deviceOS'      => 'UNKNOWN',
			'browser'       => 'UNKNOWN',
			'ipAddress'     => '0.0.0.0',
        );
        
        if(empty($_SERVER['HTTP_USER_AGENT'])) {
			$device_details['Warning'] = 'User Agent is empty.';
			
			self::create_log($device_details['Warning'], 'get_device_details() Error');
			return $device_details;
		}
		
		$user_agent = strtolower(filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));
		
		if (empty($user_agent)) {
			$device_details['Warning'] = 'Probably the merchant Server has problems with PHP filter_var function!';
			
			self::create_log($device_details['Warning'], 'get_device_details() Error');
			return $device_details;
		}
		
		$device_details['deviceName'] = $user_agent;
		
        foreach (self::$device_types as $d) {
            if (strstr($user_agent, $d) !== false) {
                if(in_array($d, array('linux', 'windows', 'macintosh'), true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } else if('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } else if('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        foreach (self::$devices as $d) {
            if (strstr($user_agent, $d) !== false) {
                $device_details['deviceOS'] = $d;
                break;
            }
        }

        foreach (self::$browsers as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

        // get ip
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
		}
		if (!empty($ip_address)) {
			$device_details['ipAddress'] = (string) $ip_address;
		}
            
        return $device_details;
    }
    
    /**
     * function get_param
     * 
     * Helper function to safety access request parameters
     * 
     * @param type $name
     * @param type $filter
     * 
     * @return mixed
     */
    public static function get_param($name, $filter = FILTER_DEFAULT) {
        $val = filter_input(INPUT_GET, $name, $filter);
        
        if(null === $val || false === $val) {
            $val = filter_input(INPUT_POST, $name, $filter);
        }
        
        if(null === $val || false === $val) {
            return false;
        }
        
        return $val;
    }
    
	/**
     * Function create_log
	 * 
     * @param mixed     $data
     * @param string    $title - title of the printed log
     */
    public static function create_log($data, $title = '')
	{
        if(
            'no' == @$_REQUEST['nuvei_create_logs']
			|| 'no' ==  @$_SESSION['nuvei_create_logs']
			|| !defined('DIR_LOGS') // it is defined in OC config.php file
			|| !is_dir(DIR_LOGS)
        ) {
			return;
		}
        
        if(!empty($_REQUEST['nuvei_create_logs'])) {
            $logs = $_REQUEST['nuvei_create_logs'];
        }
        elseif(!empty($_SESSION['nuvei_create_logs'])) {
            $logs = $_SESSION['nuvei_create_logs'];
        }
        else {
            $logs = 'daily';
        }
		
        $test_mode  = isset($_SESSION['nuvei_test_mode']) ? $_SESSION['nuvei_test_mode'] : 'no';
		$d          = $data;
        $string     = '[v.' . NUVEI_PLUGIN_V . '] | ';

		if(is_array($data)) {
			// do not log accounts if on prod
			if ('no' == $test_mode) {
				if (isset($data['userAccountDetails']) && is_array($data['userAccountDetails'])) {
					$data['userAccountDetails'] = 'account details';
				}
				if (isset($data['userPaymentOption']) && is_array($data['userPaymentOption'])) {
					$data['userPaymentOption'] = 'user payment options details';
				}
				if (isset($data['paymentOption']) && is_array($data['paymentOption'])) {
					$data['paymentOption'] = 'payment options details';
				}
			}
			// do not log accounts if on prod
			
			if(!empty($data['paymentMethods']) && is_array($data['paymentMethods'])) {
				$data['paymentMethods'] = json_encode($data['paymentMethods']);
			}
			
			$d = $test_mode ? print_r($data, true) : json_encode($data);
		}
		elseif(is_object($data)) {
			$d = $test_mode ? print_r($data, true) : json_encode($data);
		}
		elseif(is_bool($data)) {
			$d = $data ? 'true' : 'false';
		}
		
		if (!empty($title)) {
			if (is_string($title)) {
				$string .= $title;
			} else {
				$string .= "\r\n" . ( $test_mode 
                    ? json_encode($title, JSON_PRETTY_PRINT) : json_encode($title) );
			}
			
			$string .= "\r\n";
		}

		$string .= $d . "\r\n";
        
        $file_name = 'Nuvei-' . date('Y-m-d', time()) . '.txt';
		
        if($logs == 'single') {
            $file_name = 'Nuvei.txt';
        }
        elseif($logs == 'both') {
            file_put_contents(
                DIR_LOGS . $file_name,
                date('H:i:s', time()) . ': ' . $string,
                FILE_APPEND
            );
            
            $file_name = 'Nuvei.txt';
        }
        
		try {
			file_put_contents(
				DIR_LOGS . $file_name,
				date('H:i:s', time()) . ': ' . $string,
                FILE_APPEND
			);
		}
		catch (Exception $exc) {}
	}
}
