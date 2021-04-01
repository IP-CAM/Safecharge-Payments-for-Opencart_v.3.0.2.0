<?php

try {
    foreach($results as $key => $order) {
        $refunds_sum = 0;

        $nuvei_query = $this->db->query('SELECT payment_custom_field FROM `' . DB_PREFIX 
            . 'order` WHERE order_id = ' . (int) $order['order_id']);

        if(!empty($nuvei_query->rows[0]["payment_custom_field"])) {
            $nuvei_data = json_decode($nuvei_query->rows[0]["payment_custom_field"], true);

            foreach($nuvei_data as $nuv_rec) {
                if(
                    !empty($nuv_rec['status'])
                    && 'approved' == $nuv_rec['status']
                    && !empty($nuv_rec['transactionType'])
                    && in_array($nuv_rec['transactionType'], array('Credit', 'Refund'))
                ) {
                    $refunds_sum += $nuv_rec['totalAmount'];
                }
            }

            if($refunds_sum > 0) {
                $formated_total = '<del>' . $this->currency->format(
                    $order['total'],
                    $order['currency_code'],
                    $order['currency_value']
                ) . '</del>';

                $formated_total .= '&nbsp;' . $this->currency->format(
                    ($order['total'] - $refunds_sum),
                    $order['currency_code'],
                    $order['currency_value']
                );

                $data['orders'][$key]['total'] = $formated_total;
            }
        }
    }
}
catch (Exception $e) {
    $data['error_warning'] = 'Nuvei modification exception: ' . $e->getMessage();
}