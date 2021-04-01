<?php

try {
    $this->load->model('setting/setting');

    require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR 
        . 'nuvei' . DIRECTORY_SEPARATOR . 'NUVEI_CLASS.php';
    require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR 
        . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_version_resolver.php';

    $nuvei_settings	= $this->model_setting_setting
        ->getSetting(trim(NuveiVersionResolver::get_settings_prefix(), '_'));

    $nuvei_last_trans			= array();
    $nuvei_param_token_name		= NuveiVersionResolver::get_token_name();
    $nuvei_ctr_path				= NuveiVersionResolver::get_ctr_file_path();
    $nuvei_remaining_total		= $order_info['total'];
    $nuvei_refunds              = array();
    $data['nuveiAjaxUrl']		= 'index.php?route=' . $nuvei_ctr_path . '&' . $nuvei_param_token_name . '=' 
        . NUVEI_CLASS::get_param($nuvei_param_token_name);

    $data['nuveiAllowRefundBtn'] = 0;
    $data['nuveiAllowVoidBtn']   = 0;
    $data['nuveiAllowSettleBtn'] = 0;

    if(!empty($order_info['payment_custom_field']) 
        && is_array($order_info['payment_custom_field'])
    ) {
        $nuvei_last_trans       = end($order_info['payment_custom_field']);
        $data['paymentMethod']  = $nuvei_last_trans['paymentMethod'];

        foreach($order_info['payment_custom_field'] as $trans_data) {
            if(in_array($trans_data['transactionType'], array('Refund', 'Credit'))
                && 'approved' == $trans_data['status']
            ) {
                $nuvei_remaining_total		-= $trans_data['totalAmount'];
                $ref_data					= $trans_data;
                $ref_data['amount_curr']	= '-' . $this->currency->format(
                    $trans_data['totalAmount'],
                    $order_info['currency_code'],
                    $order_info['currency_value']
                );

                $nuvei_refunds[] = $ref_data;
            }
        }
        
        $data['nuveiRefunds'] = json_encode($nuvei_refunds);

        // can we show Refund button
        if(in_array($nuvei_last_trans['transactionType'], array('Refund', 'Credit', 'Sale', 'Settle'))
            && 'approved' == $nuvei_last_trans['status']
            && in_array($nuvei_last_trans['paymentMethod'], array("cc_card", "apmgw_expresscheckout"))
            //&& round($data['remainingTotal'], 2) > 0
            && round($nuvei_remaining_total, 2) > 0
        ) {
            $data['nuveiAllowRefundBtn'] = 1;
        }

        // can we show Void button
        if(!in_array($nuvei_last_trans['transactionType'], array('Refund', 'Credit', 'Void'))
            && "cc_card" == $nuvei_last_trans['paymentMethod']
        ) {
            $data['nuveiAllowVoidBtn'] = 1;
        }

        // can we show Settle button
        if('Auth' == $nuvei_last_trans['transactionType']
            && 'approved' == $nuvei_last_trans['status']
        ) {
            $data['nuveiAllowSettleBtn'] = 1;
        }
        
        $data['remainingTotalCurr'] = $this->currency->format(
            $nuvei_remaining_total,
            $order_info['currency_code'],
            $order_info['currency_value']
        );
    }
}
catch (Exception $e) {
    $data['error_warning'] = 'Nuvei modification exception: ' . $e->getMessage();
}
