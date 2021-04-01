<?php

try {
    require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'nuvei'
        . DIRECTORY_SEPARATOR . 'nuvei_version_resolver.php';

    // load Nuvei language file and use it
    $this->language->load(NuveiVersionResolver::get_ctr_file_path());

    if(!is_array($data)) {
        $data = array();
    }

    // add all translated strings
    $data = array_merge($data, $this->load->language(NuveiVersionResolver::get_ctr_file_path()));

    // then load again default language file
    $this->load->language('sale/order');
}
catch (Exception $e) {
    $data['error_warning'] = 'Nuvei modification exception: ' . $e->getMessage();
}